<?php

namespace Fxrm\Store;

class ValueSerializer {
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

        $bin = serialize($obj);
        $internalName = $this->getValuePropertyInternalName($class);

        $binPrefix = 'O:' . strlen($class) . ':"' . $class . '":1:{s:' . strlen($internalName) . ':"' . $internalName . '";';
        $binSuffix = '}';

        if (substr($bin, 0, strlen($binPrefix)) !== $binPrefix || substr($bin, -strlen($binSuffix)) !== $binSuffix) {
            throw new \Exception('could not serialize value');
        }

        $valueBin = substr($bin, strlen($binPrefix), -strlen($binSuffix));

        return unserialize($valueBin);
    }

    function intern($v) {
        // passthrough null
        if ($v === null) {
            return null;
        }

        // reify using deserialization trick to avoid triggering validation
        $internalName = $this->getValuePropertyInternalName($this->class);

        $bin = 'O:' . strlen($this->class) . ':"' . $this->class . '":1:{s:' . strlen($internalName) . ':"' . $internalName . '";s:' . strlen($v) . ':"' . $v . '";}';
        $obj = unserialize($bin);

        if ($obj === false) {
            throw new \Exception('error reifying value object');
        }

        return $obj;
    }

    private function getValuePropertyInternalName($class) {
        $classInfo = new \ReflectionClass($class);
        $properties = $classInfo->getProperties();

        if (count($properties) !== 1) {
            throw new \Exception('value class must have one property');
        }

        return "\x00" . $class . "\x00" . $properties[0]->getName();
    }
}

?>
