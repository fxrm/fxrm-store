<?php

namespace Fxrm\Store;

class Serializer {
    private $toString, $fromString;

    function __construct() {
        $this->toString = new \SplObjectStorage();
        $this->fromString = (object)array();
    }

    function fromValue($obj) {
        // passthrough null
        if ($obj === null) {
            return null;
        }

        // @todo allow customizing this
        return (string)$obj;
    }

    function toValue($class, $v) {
        // passthrough null
        if ($v === null) {
            return null;
        }

        // reify using deserialization trick to avoid triggering validation
        if ($class[0] === '\\') {
            $class = substr($class, 1);
        }

        $classInfo = new \ReflectionClass($class);
        $properties = $classInfo->getProperties();

        if (count($properties) !== 1) {
            throw new \Exception('value class must have one property');
        }

        $internalName = "\x00" . $class . "\x00" . $properties[0]->getName();

        $bin = 'O:' . strlen($class) . ':"' . $class . '":1:{s:' . strlen($internalName) . ':"' . $internalName . '";s:' . strlen($v) . ':"' . $v . '";}';
        $obj = unserialize($bin);

        if ($obj === false) {
            throw new \Exception('error reifying value object');
        }

        return $obj;
    }

    function fromIdentity($obj) {
        // passthrough null
        if ($obj === null) {
            return null;
        }

        if ( ! isset($this->toString[$obj])) {
            throw new \Exception('unknown object');
        }

        return $this->toString[$obj];
    }

    function toIdentity($class, $v) {
        // passthrough null
        if ($v === null) {
            return null;
        }

        $class = "\\$class"; // fully qualified class
        $key = $class . '$' . $v; // using special separator character

        if ( ! isset($this->fromString->$key)) {
            $obj = new $class();
            $this->fromString->$key = $obj;
            $this->toString[$obj] = $v;
        }

        return $this->fromString->$key;
    }
}

?>
