<?php

namespace Fxrm\Store;

class Serializer {
    private $toString, $fromString;
    private $backend;

    function __construct($backend) {
        $this->backend = $backend;
        $this->toString = new \SplObjectStorage();
        $this->fromString = (object)array();
    }

    // @todo these belong inside the backend implementation itself?
    function fromValue($obj) {
        // passthrough null
        if ($obj === null) {
            return null;
        }

        if ($obj instanceof \DateTime) {
            return $obj;
        }

        // use serialization trick to extract value
        $bin = serialize($obj);
        $class = get_class($obj);
        $internalName = $this->getValuePropertyInternalName($class);

        $binPrefix = 'O:' . strlen($class) . ':"' . $class . '":1:{s:' . strlen($internalName) . ':"' . $internalName . '";';
        $binSuffix = '}';

        if (substr($bin, 0, strlen($binPrefix)) !== $binPrefix || substr($bin, -strlen($binSuffix)) !== $binSuffix) {
            throw new \Exception('could not serialize value');
        }

        $valueBin = substr($bin, strlen($binPrefix), -strlen($binSuffix));

        return unserialize($valueBin);
    }

    function toValue($class, $v) {
        // passthrough null
        if ($v === null) {
            return null;
        }

        if ($class === 'DateTime') {
            return $v;
        }

        // reify using deserialization trick to avoid triggering validation
        if ($class[0] === '\\') {
            $class = substr($class, 1);
        }

        $internalName = $this->getValuePropertyInternalName($class);

        $bin = 'O:' . strlen($class) . ':"' . $class . '":1:{s:' . strlen($internalName) . ':"' . $internalName . '";s:' . strlen($v) . ':"' . $v . '";}';
        $obj = unserialize($bin);

        if ($obj === false) {
            throw new \Exception('error reifying value object');
        }

        return $obj;
    }

    function fromIdentity($obj, $autoCreate = false) {
        // passthrough null
        if ($obj === null) {
            return null;
        }

        if ( ! isset($this->toString[$obj])) {
            if ( ! $autoCreate) {
                throw new \Exception('unknown object');
            }

            $class = get_class($obj);
            $id = $this->backend->create($class);

            $class = "\\$class"; // fully qualified class
            $key = $class . '$' . $id; // using special separator character

            $this->fromString->$key = $obj;
            $this->toString[$obj] = $id;
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
