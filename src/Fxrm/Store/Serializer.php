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

        $class = "\\$class"; // fully qualified class

        // @todo reify using deserialization tricks to avoid triggering validation
        return new $class($v);
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
