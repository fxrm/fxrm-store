<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2014, Nick Matantsev
 */

namespace Fxrm\Store;

class DataRowSerializer implements Serializer {
    private $className;
    private $fieldSerializerMap;

    function __construct($className, EnvironmentStore $store) {
        $this->className = $className;
        $this->fieldSerializerMap = self::getRowFieldSerializerMap($className, $store);
    }

    function getBackendType() {
        return array_map(
            function (Serializer $v) { return $v->getBackendType(); },
            $this->fieldSerializerMap
        );
    }

    function extern($obj) {
        throw new \Exception('not externalizable');
    }

    function intern($rawMap) {
        if ($rawMap === null) {
            return null;
        }

        $className = $this->className;
        $result = new $className(); // @todo not call constructor due to the intern convention

        // copying strictly only the defined properties
        foreach ($this->fieldSerializerMap as $k => $ser) {
            $result->$k = $ser->intern($rawMap->$k);
        }

        return $result;
    }

    private static function getRowFieldSerializerMap($className, EnvironmentStore $store) {
        $targetClassInfo = new \ReflectionClass($className);
        $fieldMap = array();

        foreach ($targetClassInfo->getProperties() as $prop) {
            if ($prop->isStatic()) {
                continue;
            }

            if ( ! $prop->isPublic()) {
                throw new \Exception('row object must only contain public properties');
            }

            $propTypeInfo = TypeInfo::createForProperty($prop);
            $fieldMap[$prop->getName()] = $store->getSerializerForType($propTypeInfo);
        }

        return $fieldMap;
    }
}
