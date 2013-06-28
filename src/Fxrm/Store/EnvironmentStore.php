<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

class EnvironmentStore {
    private $backendMap;
    private $serializerMap;
    private $idClassMap;
    private $methodMap;

    public function __construct($configPath) {
        $config = json_decode(file_get_contents($configPath));

        // set up backends
        $this->backendMap = (object)array();

        foreach ($config->backends as $backendName => $backendArgs) {
            $backendClass = new \ReflectionClass(array_shift($backendArgs));
            $this->backendMap->$backendName = $backendClass->newInstanceArgs($backendArgs);
        }

        // set up serializers
        // @todo verify names
        $this->serializerMap = (object)array();
        $this->idClassMap = (object)array();

        foreach ($config->idClasses as $idClass => $backendName) {
            $this->idClassMap->$idClass = $backendName;
            $this->serializerMap->$idClass = new IdentitySerializer($idClass, $this->backendMap->$backendName);
        }

        foreach ($config->valueClasses as $valueClass) {
            $this->serializerMap->$valueClass = new ValueSerializer($valueClass);
        }

        $this->serializerMap->DateTime = new PassthroughSerializer();

        // copy over the method backend names
        // @todo verify names
        $this->methodMap = (object)array();

        foreach ($config->methods as $method => $backendName) {
            $this->methodMap->$method = $backendName;
        }
    }

    function extern($obj) {
        $className = get_class($obj);
        $serializer = $this->serializerMap->$className;

        // explicitly deal with identities only - values are not a concern
        if (!($serializer instanceof IdentitySerializer)) {
            throw new \Exception('only identities can be externalized'); // developer error
        }

        // this will always auto-create data store entries (rows, etc) as necessary
        return $serializer->extern($obj);
    }

    function intern($className, $id) {
        // explicitly deal with identities only - values are not a concern
        if ( ! $this->isIdentityClass($className)) {
            throw new \Exception('only identities can be externalized'); // developer error
        }

        // explicitly deal with identities only - values are not a concern
        return $this->serializerMap->$className->intern($id);
    }

    function getBackendName($implName, $idClass, $firstPropertyName) {
        // see if explicit backend designation is set
        if (property_exists($this->methodMap, $implName)) {
            return $this->methodMap->$implName;
        }

        // otherwise, use the identity class
        // @todo also consider property name
        if (property_exists($this->idClassMap, $idClass)) {
            return $this->idClassMap->$idClass;
        }

        throw new \Exception('cannot find backend for ' . $implName . ' using ' . $idClass);
    }

    function get($backendName, $implName, $idClass, $idObj, $propertyClass, $propertyName) {
        $id = $this->externAny($idClass, $idObj);

        $value = $this->backendMap->$backendName->get($implName, $idClass, $id, $this->getBackendType($propertyClass), $propertyName);

        return $this->internAny($propertyClass, $value);
    }

    function set($backendName, $implName, $idClass, $idObj, $properties) {
        $id = $this->externAny($idClass, $idObj);

        $values = array();

        foreach ($properties as $propertyName => $qualifiedValue) {
            list($propertyClass, $value) = $qualifiedValue;

            $values[$propertyName] = $this->externAny($propertyClass, $value);
        }

        $this->backendMap->$backendName->set($implName, $idClass, $id, $values);
    }

    function find($backendName, $implName, $returnClass, $fieldClassMap, $properties, $returnArray) {
        $values = array();

        foreach ($properties as $propertyName => $qualifiedValue) {
            list($propertyClass, $value) = $qualifiedValue;

            $values[$propertyName] = $this->externAny($propertyClass, $value);
        }

        $idClass = $this->isIdentityClass($returnClass) ? $returnClass : null;

        $data = $this->backendMap->$backendName->find($implName, $idClass, $values, $fieldClassMap ? $this->getBackendTypeMap($fieldClassMap) : $this->getBackendType($returnClass), $returnArray);

        if ($returnArray) {
            if ($fieldClassMap) {
                foreach ($data as &$value) {
                    $value = $this->internRow($returnClass, $fieldClassMap, $value);
                }
            } else {
                foreach ($data as &$value) {
                    $value = $this->internAny($returnClass, $value);
                }
            }
        } else {
            if ($fieldClassMap) {
                $data = $this->internRow($returnClass, $fieldClassMap, $data);
            } else {
                $data = $this->internAny($returnClass, $data);
            }
        }

        return $data;
    }

    function isSerializableClass($class) {
        return property_exists($this->serializerMap, $class);
    }

    private function isIdentityClass($class) {
        return property_exists($this->serializerMap, $class) && $this->serializerMap->$class instanceof IdentitySerializer;
    }

    private function internRow($className, $fieldClassMap, $value) {
        $result = new $className();

        // copying strictly only the defined properties
        foreach ($fieldClassMap as $k => $class) {
            $result->$k = $this->internAny($class, $value->$k);
        }

        return $result;
    }

    private function internAny($class, $value) {
        return $class === null ? $value : $this->serializerMap->$class->intern($value);
    }

    private function externAny($class, $value) {
        return $class === null ? $value : $this->serializerMap->$class->extern($value);
    }

    private function getBackendType($class) {
        return $class === 'DateTime' ? Backend::DATE_TIME_TYPE : null;
    }

    private function getBackendTypeMap($classMap) {
        return array_map(array($this, 'getBackendType'), $classMap);
    }
}

?>
