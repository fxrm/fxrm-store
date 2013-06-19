<?php

namespace Fxrm\Store;

// @todo if an identity class is abstract, store the real class in a field + "Class" column
class Storable {
    private $backend, $serializer;

    function __construct($backend) {
        $this->backend = $backend;
        $this->serializer = new Serializer($backend);
    }

    function implement($className) {
        $constructArguments = array_slice(func_get_args(), 1);

        $classInfo = new \ReflectionClass($className);

        $implementationName = 'FxrmStore_' . md5($className);
        $implementationSource = array();

        $implementationSource[] = 'class ' . $implementationName;
        $implementationSource[] = ' extends \\' . $classInfo->getName();
        $implementationSource[] = '{ private $s;';

        // implement constructor
        $constructorInfo = $classInfo->getConstructor();

        $implementationSource[] = 'function __construct($s, $args) {';
        $implementationSource[] = '$this->s = $s;'; // this must be set before calling parent

        if ($constructorInfo) {
            $implementationSource[] = 'parent::__construct(';

            $count = count($constructorInfo->getParameters());

            if (count($constructArguments) !== $count) {
                throw new \Exception('expecting ' . $count . ' constructor argument(s)');
            }

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
                $implementationSource[] = self::defineGetter($methodInfo);
            } elseif (substr($name, 0, 4) === 'find') {
                $implementationSource[] = self::defineFinder($methodInfo);
            } else {
                $implementationSource[] = self::defineSetter($methodInfo);
            }
        }

        $implementationSource[] = 'public static function _getStorable($instance) { return $instance->s; }';
        $implementationSource[] = '}';

        //echo(join('', $implementationSource));
        eval(join('', $implementationSource));

        return new $implementationName($this, $constructArguments);
    }

    public static function extern($instance, $obj) {
        // explicitly deal with identities only - values are not a concern
        $instance::_getStorable()->fromIdentity($obj);
    }

    public static function intern($instance, $class, $id) {
        // explicitly deal with identities only - values are not a concern
        $instance::_getStorable()->toIdentity($class, $id);
    }

    private function internAny($class, $value) {
        return $class === null ? $value : (
            substr($class, -2) === 'Id' ?
                $this->serializer->toIdentity($class, $value) :
                $this->serializer->toValue($class, $value)
            );
    }

    private function externAny($class, $value) {
        return $class === null ? $value : (
            substr($class, -2) === 'Id' ?
                $this->serializer->fromIdentity($value, true) :
                $this->serializer->fromValue($value)
            );
    }

    function get($implName, $idClass, $idObj, $propertyClass, $propertyName) {
        $id = $this->serializer->fromIdentity($idObj, true);

        $value = $this->backend->get($implName, $idClass, $id, $propertyClass, $propertyName);

        return $this->internAny($propertyClass, $value);
    }

    function set($implName, $idClass, $idObj, $properties) {
        $id = $this->serializer->fromIdentity($idObj, true);

        $values = array();

        foreach ($properties as $propertyName => $qualifiedValue) {
            list($propertyClass, $value) = $qualifiedValue;

            $values[$propertyName] = $this->externAny($propertyClass, $value);
        }

        $this->backend->set($implName, $idClass, $id, $values);
    }

    function find($implName, $idClass, $properties, $returnArray) {
        $values = array();

        foreach ($properties as $propertyName => $qualifiedValue) {
            list($propertyClass, $value) = $qualifiedValue;

            $values[$propertyName] = $this->externAny($propertyClass, $value);
        }

        $data = $this->backend->find($implName, $idClass, $values, $returnArray);

        if ($returnArray) {
            foreach ($data as &$value) {
                $value = $this->serializer->toIdentity($idClass, $value);
            }
        } else {
            $data = $this->serializer->toIdentity($idClass, $data);
        }

        return $data;
    }

    private static function defineGetter(\ReflectionMethod $info) {
        $signature = self::getSignature($info);

        if (count((array)$signature->parameters) != 1) {
            throw new \Exception('getters must have one parameter');
        }

        if ( ! preg_match('/([^\\\\]+)Id$/', $signature->firstParameterClass, $idMatch)) {
            throw new \Exception('target class must be an identity');
        }

        $fullPrefix = 'get' . ucfirst($idMatch[1]);

        if (strpos($info->getName(), $fullPrefix) !== 0) {
            throw new \Exception('getter must include target class name: ' . $fullPrefix);
        }

        $source[] = $signature->preamble . ' {';
        $source[] = 'return $this->s->get(';
        $source[] = var_export($signature->fullName, true) . ', ';
        $source[] = var_export($signature->firstParameterClass, true) . ', ';
        $source[] = '$a0, ';
        $source[] = var_export($signature->returnClass, true) . ', ';
        $source[] = var_export(lcfirst(substr($info->getName(), strlen($fullPrefix))), true);
        $source[] = ');';
        $source[] = '}';

        return join('', $source);
    }

    private static function defineSetter(\ReflectionMethod $info) {
        $signature = self::getSignature($info);

        if (count((array)$signature->parameters) < 2) {
            throw new \Exception('setters must have an id parameter and at least one value parameter');
        }

        $source[] = $signature->preamble . ' {';
        $source[] = '$this->s->set(';
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

    private static function defineFinder(\ReflectionMethod $info) {
        $signature = self::getSignature($info);

        $isArray = property_exists($signature, 'returnArrayClass');

        $source[] = $signature->preamble . ' {';
        $source[] = 'return $this->s->find(';
        $source[] = var_export($signature->fullName, true) . ', ';
        $source[] = var_export($isArray ? $signature->returnArrayClass : $signature->returnClass, true) . ', ';
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
        $source[] = $isArray ? 'true' : 'false';
        $source[] = ');';
        $source[] = '}';

        return join('', $source);
    }

    private static function getSignature(\ReflectionMethod $info) {
        $signature = (object)array();

        $signature->name = $info->getName();

        $signature->fullName = $info->getDeclaringClass()->getName() . '\\' . $signature->name;

        $comment = $info->getDocComment();
        if (preg_match('/@return\\s+(\\S+)/', $comment, $commentMatch)) {
            // @todo ignore standard names like "string" and others
            $targetIdClassHint = $commentMatch[1];

            $isArray = substr($targetIdClassHint, -2) === '[]';

            if ($isArray) {
                $targetIdClassHint = substr($targetIdClassHint, 0, -2);
            }

            $elementClass = null;

            if ($targetIdClassHint !== 'object') {
                $targetClassInfo = new \ReflectionClass($targetIdClassHint[0] === '\\' ?
                        $targetIdClassHint :
                        $info->getDeclaringClass()->getNamespaceName() . '\\' . $targetIdClassHint);

                $elementClass = $targetClassInfo->getName();
            }

            if ($isArray) {
                $signature->returnArrayClass = $elementClass;
            } else {
                $signature->returnClass = $elementClass;
            }
        } else {
            $signature->returnClass = null;
        }

        $signature->parameters = (object)array();
        foreach ($info->getParameters() as $param) {
            $class = $param->getClass();
            $signature->parameters->{$param->getName()} = ($class ? $class->getName() : null);
        }

        $signature->preamble = 'function ' . $signature->name . '(';

        $count = 0;
        foreach ($signature->parameters as $param => $class) {
            if ($count === 0) {
                $signature->firstParameterClass = $class;
            }

            $signature->preamble .= ($count > 0 ? ',' : '') . ($class ? "\\$class" : '') . ' $a' . $count;
            $count += 1;
        }

        $signature->preamble .= ')';

        return $signature;
    }
}

?>
