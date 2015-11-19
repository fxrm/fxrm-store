<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2014, Nick Matantsev
 */

namespace Fxrm\Store;

class DataRowSerializer implements Serializer {
    private $className;
    private $fieldSerializerMap;

    function __construct($className, $fieldSerializerMap) {
        $this->className = $className;
        $this->fieldSerializerMap = $fieldSerializerMap;
    }

    function getBackendType() {
        throw new \Exception('not externalizable');
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
}
