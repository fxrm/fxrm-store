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
    private $serializerMap;
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

        // set up serializers
        // @todo verify names
        $this->serializerMap = (object)array();
        $this->idClassMap = (object)array();

        foreach ($idClasses as $idClass => $backendName) {
            $this->idClassMap->$idClass = $backendName;
            $this->serializerMap->$idClass = new IdentitySerializer($idClass, $this->backendMap->$backendName);
        }

        foreach ($valueClasses as $valueClass) {
            $this->serializerMap->$valueClass = new ValueSerializer($valueClass);
        }

        $this->serializerMap->DateTime = new PassthroughSerializer();

        // copy over the method backend names
        // @todo verify names
        $this->methodMap = (object)array();

        foreach ($methods as $method => $backendName) {
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

    function find($backendName, $implName, $returnClass, $properties, $returnArray) {
        if (!$this->isIdentityClass($returnClass)) {
            throw new \Exception('cannot find non-identity class');
        }

        $values = array();

        foreach ($properties as $propertyName => $qualifiedValue) {
            list($propertyClass, $value) = $qualifiedValue;

            $values[$propertyName] = $this->externAny($propertyClass, $value);
        }

        // always getting backend type for ID class because we can't assume it is string/integer
        $data = $this->backendMap->$backendName->find($implName, $returnClass, $values, $this->getBackendType($returnClass), $returnArray);

        if ($returnArray) {
            foreach ($data as &$value) {
                $value = $this->internAny($returnClass, $value);
            }
        } else {
            $data = $this->internAny($returnClass, $data);
        }

        return $data;
    }

    function isSerializableClass($class) {
        return property_exists($this->serializerMap, $class);
    }

    private function isIdentityClass($class) {
        return property_exists($this->serializerMap, $class) && $this->serializerMap->$class instanceof IdentitySerializer;
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
