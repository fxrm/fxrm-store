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
    private $primitiveSerializer;
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

        // used to pass-through primitives
        $this->primitiveSerializer = new PassthroughSerializer();

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
        $id = $this->getAnySerializer($idClass)->extern($idObj); // @todo use identity serializer explicitly
        $propertySerializer = $this->getAnySerializer($propertyClass);

        $value = $this->backendMap->$backendName->get($implName, $idClass, $id, $propertySerializer->getBackendType(), $propertyName);

        return $propertySerializer->intern($value);
    }

    function set($backendName, $implName, $idClass, $idObj, $properties) {
        $id = $this->getAnySerializer($idClass)->extern($idObj); // @todo use identity serializer explicitly

        $values = array();
        $valueTypeMap = array();

        foreach ($properties as $propertyName => $qualifiedValue) {
            list($propertyClass, $value) = $qualifiedValue;
            $propertySerializer = $this->getAnySerializer($propertyClass);

            $values[$propertyName] = $propertySerializer->extern($value);
            $valueTypeMap[$propertyName] = $propertySerializer->getBackendType();
        }

        $this->backendMap->$backendName->set($implName, $idClass, $id, $valueTypeMap, $values);
    }

    function find($backendName, $implName, $returnClass, $fieldClassMap, $properties, $returnArray) {
        $values = array();
        $valueTypeMap = array();

        foreach ($properties as $propertyName => $qualifiedValue) {
            list($propertyClass, $value) = $qualifiedValue;
            $propertySerializer = $this->getAnySerializer($propertyClass);

            $values[$propertyName] = $propertySerializer->extern($value);
            $valueTypeMap[$propertyName] = $propertySerializer->getBackendType();
        }

        $idClass = property_exists($this->idSerializerMap, $returnClass) ? $returnClass : null;

        $returnElementSerializer = $fieldClassMap !== null
            ? $this->getDataRowSerializer($returnClass, $fieldClassMap)
            : $this->getAnySerializer($returnClass);

        $data = $this->backendMap->$backendName->find($implName, $idClass, $valueTypeMap, $values, $returnElementSerializer->getBackendType(), $returnArray);

        $returnSerializer = $returnArray
            ? new ArraySerializer($returnElementSerializer)
            : $returnElementSerializer;

        return $returnSerializer->intern($data);
    }

    function retrieve($backendName, $querySpecMap, $paramMap, $returnTypeMap) {
        $paramValueMap = array();
        $paramTypeMap = array();

        foreach ($paramMap as $paramName => $paramValue) {
            // @todo find a way to declare param class?
            $paramClass = is_object($paramValue) ? get_class($paramValue) : null;
            $paramSerializer = $this->getAnySerializer($paramClass);

            $paramValueMap[$paramName] = $paramSerializer->extern($paramValue);
            $paramTypeMap[$paramName] = $paramSerializer->getBackendType();
        }

        $returnElementSerializer = $this->getDataRowSerializer('stdClass', $returnTypeMap);

        $data = $this->backendMap->$backendName->retrieve($querySpecMap, $paramTypeMap, $paramValueMap, $returnElementSerializer->getBackendType());

        $returnSerializer = new ArraySerializer($returnElementSerializer);
        return $returnSerializer->intern($data);
    }

    function isSerializableClass($class) {
        return property_exists($this->classSerializerCacheMap, $class);
    }

    private function getDataRowSerializer($className, $fieldClassMap) {
        $fieldSerializerMap = array();

        // copying strictly only the defined properties
        foreach ($fieldClassMap as $k => $class) {
            $fieldSerializerMap[$k] = $this->getAnySerializer($class);
        }

        return new DataRowSerializer($className, $fieldSerializerMap);
    }

    private function getAnySerializer($class) {
        return $class === null ? $this->primitiveSerializer : $this->createClassSerializer($class);
    }
}

?>
