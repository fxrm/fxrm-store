<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

/**
 * Internal storage backend multiplexer.
 */
class EnvironmentStore {
    private $backendMap;
    private $classSerializerCacheMap;
    private $idSerializerMap;
    private $idClassMap;
    private $methodMap;

    function __construct($backends, $idClasses, $valueClasses, $methods) {
        // set up backends
        $this->backendMap = (object)array();

        foreach ($backends as $backendName => $backendInstance) {
            if ( ! ($backendInstance instanceof Backend)) {
                throw new \Exception('unrecognized backend class');
            }

            $this->backendMap->$backendName = $backendInstance;
        }

        // set up identity serializers
        $this->classSerializerCacheMap = (object)array();
        $this->idSerializerMap = (object)array();
        $this->idClassMap = (object)array();

        foreach ($idClasses as $idClass => $backendName) {
            $this->idClassMap->$idClass = $backendName;
            $ser = new IdentitySerializer($idClass, $this->backendMap->$backendName);
            $this->idSerializerMap->$idClass = $ser;

            $this->classSerializerCacheMap->$idClass = null;
        }

        // mark value classes as serializable
        foreach ($valueClasses as $valueClass) {
            $this->classSerializerCacheMap->$valueClass = null;
        }

        // DateTime is always serializable
        $this->classSerializerCacheMap->DateTime = null;

        // copy over the method backend names
        // @todo verify names
        $this->methodMap = (object)array();

        foreach ($methods as $method => $backendName) {
            $this->methodMap->$method = $backendName;
        }
    }

    // @todo eliminate this in favour of getIdentitySerializer or something
    function createClassSerializer($className) {
        if (!property_exists($this->classSerializerCacheMap, $className)) {
            throw new \Exception('not a serializable class');
        }

        // cache the created serializers
        if ($this->classSerializerCacheMap->$className === null) {
            $this->classSerializerCacheMap->$className = (
                property_exists($this->idSerializerMap, $className)
                    ? $this->idSerializerMap->$className // have to return same instance as everywhere else
                    : ($className === 'DateTime'
                        ? new PassthroughSerializer(Backend::DATE_TIME_TYPE)
                        : new ValueSerializer($className, $this)
                    )
            );
        }

        return $this->classSerializerCacheMap->$className;
    }

    function extern($obj) {
        $className = get_class($obj);

        // explicitly deal with identities only - values are not a concern
        if ( ! property_exists($this->idSerializerMap, $className)) {
            throw new \Exception('only identities can be externalized'); // developer error
        }

        // this will always auto-create data store entries (rows, etc) as necessary
        return $this->idSerializerMap->$className->extern($obj);
    }

    function intern($className, $id) {
        // explicitly deal with identities only - values are not a concern
        if ( ! property_exists($this->idSerializerMap, $className)) {
            throw new \Exception('only identities can be externalized'); // developer error
        }

        // explicitly deal with identities only - values are not a concern
        return $this->idSerializerMap->$className->intern($id);
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
        $id = $this->externAny($idClass, $idObj); // @todo use identity serializer explicitly

        $value = $this->backendMap->$backendName->get($implName, $idClass, $id, $this->getBackendType($propertyClass), $propertyName);

        return $this->internAny($propertyClass, $value);
    }

    function set($backendName, $implName, $idClass, $idObj, $properties) {
        $id = $this->externAny($idClass, $idObj); // @todo use identity serializer explicitly

        $values = array();
        $valueTypeMap = array();

        foreach ($properties as $propertyName => $qualifiedValue) {
            list($propertyClass, $value) = $qualifiedValue;

            $values[$propertyName] = $this->externAny($propertyClass, $value);
            $valueTypeMap[$propertyName] = $this->getBackendType($propertyClass);
        }

        $this->backendMap->$backendName->set($implName, $idClass, $id, $valueTypeMap, $values);
    }

    function find($backendName, $implName, $returnClass, $fieldClassMap, $properties, $returnArray) {
        $values = array();
        $valueTypeMap = array();

        foreach ($properties as $propertyName => $qualifiedValue) {
            list($propertyClass, $value) = $qualifiedValue;

            $values[$propertyName] = $this->externAny($propertyClass, $value);
            $valueTypeMap[$propertyName] = $this->getBackendType($propertyClass);
        }

        $idClass = property_exists($this->idSerializerMap, $returnClass) ? $returnClass : null;

        $data = $this->backendMap->$backendName->find($implName, $idClass, $valueTypeMap, $values, $fieldClassMap ? $this->getBackendTypeMap($fieldClassMap) : $this->getBackendType($returnClass), $returnArray);

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
                $data = $data === null ? null : $this->internRow($returnClass, $fieldClassMap, $data);
            } else {
                $data = $this->internAny($returnClass, $data);
            }
        }

        return $data;
    }

    function retrieve($backendName, $querySpecMap, $paramMap, $returnTypeMap) {
        $paramValueMap = array();
        $paramTypeMap = array();

        foreach ($paramMap as $paramName => $paramValue) {
            // @todo find a way to declare param class?
            $paramClass = is_object($paramValue) ? get_class($paramValue) : null;
            $paramValueMap[$paramName] = $this->externAny($paramClass, $paramValue);
            $paramTypeMap[$paramName] = $this->getBackendType($paramClass);
        }

        $data = $this->backendMap->$backendName->retrieve($querySpecMap, $paramTypeMap, $paramValueMap, $this->getBackendTypeMap($returnTypeMap));

        foreach ($data as &$value) {
            foreach ($returnTypeMap as $k => $class) {
                $value->$k = $this->internAny($class, $value->$k);
            }
        }

        return $data;
    }

    function isSerializableClass($class) {
        return property_exists($this->classSerializerCacheMap, $class);
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
        return $class === null ? $value : $this->createClassSerializer($class)->intern($value);
    }

    private function externAny($class, $value) {
        return $class === null ? $value : $this->createClassSerializer($class)->extern($value);
    }

    private function getBackendType($class) {
        return $class === null ? null : $this->createClassSerializer($class)->getBackendType();
    }

    private function getBackendTypeMap($classMap) {
        return array_map(array($this, 'getBackendType'), $classMap);
    }
}

?>
