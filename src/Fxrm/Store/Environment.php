<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

// @todo if an identity class is abstract, store the real class in a field + "Class" column

/**
 * Storage environment context. Instantiate this with configuration settings and use
 * as a factory.
 */
class Environment {
    /** @var EnvironmentStore */ private $store;

    /**
     * Initialize the storage environment context with implementation hints.
     * Implementation hints are a JSON object with the following keys:
     *
     * - idClasses: identity-class -> backend-label map
     * - valueClasses: array of value-class names
     * - methods: method-name -> backend-label map, where method-name is of the form "classNamespace\className\methodName"
     *
     * @param string $configPath location of the storage hint JSON file
     * @param array $backendMap keys are backend labels and values are backend implementations
     */
    public function __construct($configPath, $backendMap) {
        $config = json_decode(file_get_contents($configPath));

        $this->store = new EnvironmentStore(
            (object)$backendMap,
            (object)$config->idClasses,
            (array)$config->valueClasses,
            (object)$config->methods
        );
    }

    /**
     * Create an instance of given class backed by this storage context. Extra constructor arguments can be specified following the class name.
     *
     * @param string $className fully-qualified name of the storable class to implement
     * @param mixed ... class constructor arguments
     */
    public function implement($className) {
        $constructArguments = array_slice(func_get_args(), 1);

        return $this->implementArgs($className, $constructArguments);
    }

    /**
     * Create an instance of given class backed by this storage context. Extra constructor arguments are given as an explicit array.
     *
     * @param string $className fully-qualified name of the storable class to implement
     * @param array $constructArguments class constructor arguments
     */
    public function implementArgs($className, $constructArguments) {
        $classInfo = new \ReflectionClass($className);

        $implementationName = 'FxrmStore_' . md5($className);

        if (class_exists($implementationName)) {
            return new $implementationName($this->store, $constructArguments);
        }

        $implementationSource = array();

        $implementationSource[] = 'class ' . $implementationName;
        $implementationSource[] = ' ' . ($classInfo->isInterface() ? 'implements' : 'extends') . ' \\' . $classInfo->getName();
        $implementationSource[] = '{ private $s;';

        // implement constructor
        // @todo properly get grandparent constructor if parent is empty
        $constructorInfo = $classInfo->getConstructor();
        $count = $constructorInfo ? count($constructorInfo->getParameters()) : 0;

        $implementationSource[] = 'function __construct($s, $args) {';
        $implementationSource[] = '$this->s = $s;'; // this must be set before calling parent

        $implementationSource[] = 'if (count($args) !== ' . $count . ') { throw new \\Exception(\'wrong constructor arg count\'); }';

        if ($constructorInfo) {
            $implementationSource[] = 'parent::__construct(';

            if ($count > 0) {
                foreach (range(0, $count - 1) as $i) {
                    $implementationSource[] = ($i === 0 ? '' : ',') . '$args[' . $i . ']';
                }
            }

            $implementationSource[] = ');';
        }

        $implementationSource[] = '}';

        // implement all abstract methods
        foreach ($classInfo->getMethods(\ReflectionMethod::IS_ABSTRACT) as $methodInfo) {
            $name = $methodInfo->getName();

            if (substr($name, 0, 3) === 'get') {
                $implementationSource[] = $this->defineGetter($methodInfo);
            } else {
                $signature = $this->getSignature($methodInfo);

                if ($signature->hasDeclaredReturnType) {
                    $implementationSource[] = $this->defineFinder($signature);
                } else {
                    $implementationSource[] = $this->defineSetter($signature);
                }
            }
        }

        $implementationSource[] = 'public static function _getFxrmStore($instance) { return $instance->s; }';
        $implementationSource[] = '}';

        eval(join('', $implementationSource));

        return new $implementationName($this->store, $constructArguments);
    }

    /**
     * Convert identity object to external ID string, as managed by the appropriate backend.
     * This may result in the data entry being auto-created.
     *
     * @param mixed $obj ideninty object to export
     * @return string representation of the identity object
     */
    public function export($obj) {
        return $this->store->extern($obj);
    }

    /**
     * Convert identity object to external ID string, as managed by the appropriate backend.
     * This may result in the data entry being auto-created.
     *
     * @param mixed $impl implementation of a storage interface
     * @param mixed $obj identity object to export
     * @return string representation of the identity object
     */
    public static function exportUsing($impl, $obj) {
        $implClass = get_class($impl);

        return $implClass::_getFxrmStore($impl)->extern($obj);
    }

    /**
     * Convert external ID string to identity object, as managed by the appropriate backend.
     * Calling twice with the same class and ID will return the same object instance.
     *
     * @param string $className identity object class
     * @param string $id external identifier string
     * @return mixed identity object instance
     */
    public function import($className, $id) {
        return $this->store->intern($className, $id);
    }

    /**
     * Convert external ID string to identity object, as managed by the appropriate backend.
     * Calling twice with the same class and ID will return the same object instance.
     *
     * @param mixed $impl implementation of a storage interface
     * @param string $className identity object class
     * @param string $id external identifier string
     * @return mixed identity object instance
     */
    public static function importUsing($impl, $className, $id) {
        $implClass = get_class($impl);

        return $implClass::_getFxrmStore($impl)->intern($className, $id);
    }

    /**
     * Run native query on the backend adapter. Strongly encouraged to use only for reading data, not updating.
     *
     * @param mixed $impl implementation of a storage interface
     * @param array $backendName name of the backend to invoke
     * @param array $querySpecMap map of native dialect names and corresponding query specs (e.g. 'mysql' => 'select ...')
     * @param array $paramMap map of query parameters
     * @param array $columnTypeMap map of column names to class names
     * @return array rows of internalized data as value/id objects
     */
    public static function retrieveUsing($impl, $backendName, $querySpecMap, $paramMap, $columnTypeMap) {
        $implClass = get_class($impl);

        return $implClass::_getFxrmStore($impl)->retrieve($backendName, $querySpecMap, $paramMap, $columnTypeMap);
    }

    private function defineGetter(\ReflectionMethod $info) {
        $signature = $this->getSignature($info);

        if (count((array)$signature->parameters) != 1) {
            throw new \Exception('getters must have one parameter');
        }

        // @todo check if serializable
        if ($signature->returnTypeInfo->getIsArray()) {
            throw new \Exception('getters cannot return arrays');
        }

        if ( ! preg_match('/([^\\\\]+)Id$/', $signature->firstParameterClass, $idMatch)) {
            throw new \Exception('target class must be an identity');
        }

        $fullPrefix = 'get' . ucfirst($idMatch[1]);

        if (strpos($info->getName(), $fullPrefix) !== 0) {
            throw new \Exception('getter must include target class name: ' . $fullPrefix);
        }

        $propertyName = lcfirst(substr($info->getName(), strlen($fullPrefix)));
        $backendName = $this->store->getBackendName($signature->fullName, $signature->firstParameterClass, $propertyName);

        $source[] = $signature->preamble . ' {';
        $source[] = 'return $this->s->get(';
        $source[] = var_export($backendName, true) . ', ';
        $source[] = var_export($signature->fullName, true) . ', ';
        $source[] = var_export($signature->firstParameterClass, true) . ', ';
        $source[] = '$a0, ';
        $source[] = var_export($signature->returnTypeInfo->getElementClassName(), true) . ', ';
        $source[] = var_export($propertyName, true);
        $source[] = ');';
        $source[] = '}';

        return join('', $source);
    }

    private function defineSetter($signature) {
        if (count((array)$signature->parameters) < 2) {
            throw new \Exception('setters must have an id parameter and at least one value parameter');
        }

        $backendName = $this->store->getBackendName($signature->fullName, $signature->firstParameterClass, $signature->firstParameterName);

        $source[] = $signature->preamble . ' {';
        $source[] = '$this->s->set(';
        $source[] = var_export($backendName, true) . ', ';
        $source[] = var_export($signature->fullName, true) . ', ';
        $source[] = var_export($signature->firstParameterClass, true) . ', ';
        $source[] = '$a0, ';
        $source[] = 'array(';

        $count = 0;
        foreach ($signature->parameters as $param => $class) {
            // skip the id parameter
            if ($count > 0) {
                $source[] = ($count === 1 ? '' : ',');
                $source[] = var_export($param, true);
                $source[] = ' => array(';
                $source[] = var_export($class, true) . ', ' . '$a' . $count;
                $source[] = ')';
            }

            $count += 1;
        }

        $source[] = '));';
        $source[] = '}';

        return join('', $source);
    }

    private function defineFinder($signature) {
        $backendName = $this->store->getBackendName($signature->fullName, $signature->returnTypeInfo->getElementClassName(), null);

        $source[] = $signature->preamble . ' {';
        $source[] = 'return $this->s->find(';
        $source[] = var_export($backendName, true) . ', ';
        $source[] = var_export($signature->fullName, true) . ', ';
        $source[] = var_export($signature->returnTypeInfo->getElementClassName(), true) . ', ';
        $source[] = 'array(';

        $count = 0;
        foreach ($signature->parameters as $param => $class) {
            $source[] = ($count === 0 ? '' : ',');
            $source[] = var_export($param, true);
            $source[] = ' => array(';
            $source[] = var_export($class, true) . ', ' . '$a' . $count;
            $source[] = ')';

            $count += 1;
        }

        $source[] = '),';
        $source[] = $signature->returnTypeInfo->getIsArray() ? 'true' : 'false';
        $source[] = ');';
        $source[] = '}';

        return join('', $source);
    }

    private function getSignature(\ReflectionMethod $info) {
        $signature = (object)array();

        $signature->name = $info->getName();

        $signature->fullName = $info->getDeclaringClass()->getName() . '\\' . $signature->name;

        $signature->hasDeclaredReturnType = !!preg_match('/@return\\s+\\S+/', $info->getDocComment());

        $typeInfo = TypeInfo::createForMethodReturn($info);
        $signature->returnTypeInfo = $typeInfo;

        $signature->parameters = (object)array();
        foreach ($info->getParameters() as $param) {
            $class = $param->getClass();
            $signature->parameters->{$param->getName()} = ($class ? $class->getName() : null);
        }

        // @todo copy original public/protected modifier!
        $signature->preamble = 'function ' . $signature->name . '(';

        $count = 0;
        foreach ($signature->parameters as $param => $class) {
            if ($count === 0) {
                $signature->firstParameterName = $param;
                $signature->firstParameterClass = $class;
            }

            // @todo read the allowsNull property of the parameter!
            $signature->preamble .= ($count > 0 ? ',' : '') . ($class ? "\\$class" : '') . ' $a' . $count . ' = null';
            $count += 1;
        }

        $signature->preamble .= ')';

        return $signature;
    }
}

?>
