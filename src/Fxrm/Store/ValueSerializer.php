<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

class ValueSerializer implements Serializer {
    private $class;

    function __construct($class) {
        $this->class = $class;
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

        $property = $this->getValueProperty($class);
        return $property->getValue($obj);
    }

    function intern($v) {
        // passthrough null
        if ($v === null) {
            return null;
        }

        // reify using deserialization trick to avoid triggering validation
        $property = $this->getValueProperty($this->class);

        $bin = 'O:' . strlen($this->class) . ':"' . $this->class . '":0:{}';
        $obj = unserialize($bin);

        $property->setValue($obj, $v);

        if ($obj === false) {
            throw new \Exception('error reifying value object');
        }

        return $obj;
    }

    private function getValueProperty($class) {
        $classInfo = new \ReflectionClass($class);
        $properties = $classInfo->getProperties();

        if (count($properties) === 0 && $classInfo->getParentClass()) {
            return $this->getValueProperty($classInfo->getParentClass()->getName()); // @todo pass class ref itself for speed
        }

        if (count($properties) !== 1) {
            throw new \Exception('value class must have one property');
        }

        $properties[0]->setAccessible(true);

        return $properties[0];
    }
}

?>
