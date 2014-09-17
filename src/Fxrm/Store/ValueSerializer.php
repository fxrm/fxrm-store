<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

class ValueSerializer implements Serializer {
    private $class;
    private $property;

    function __construct($class) {
        $classInfo = new \ReflectionClass($class);

        $this->class = $classInfo->getName();
        $this->property = $this->getValueProperty($classInfo);
    }

    function extern($obj) {
        // passthrough null
        if ($obj === null) {
            return null;
        }

        // use serialization trick to extract value
        $class = get_class($obj);

        if($this->class !== get_class($obj)) {
            throw new \Exception('class mismatch'); // developer error
        }

        return $this->property->getValue($obj);
    }

    function intern($v) {
        // passthrough null
        if ($v === null) {
            return null;
        }

        // reify using deserialization trick to avoid triggering validation
        $obj = unserialize('O:' . strlen($this->class) . ':"' . $this->class . '":0:{}');

        $this->property->setValue($obj, $v);

        return $obj;
    }

    private function getValueProperty(\ReflectionClass $classInfo) {
        $properties = $classInfo->getProperties();

        if (count($properties) === 0 && $classInfo->getParentClass()) {
            return $this->getValueProperty($classInfo->getParentClass());
        }

        if (count($properties) !== 1) {
            throw new \Exception('value class must have one property');
        }

        $properties[0]->setAccessible(true);

        return $properties[0];
    }
}

?>
