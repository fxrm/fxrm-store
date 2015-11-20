<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

class ValueSerializer implements Serializer {
    private $class;
    private $propertyMap;
    private $propertyClassMap;

    function __construct($class, EnvironmentStore $store) {
        $classInfo = new \ReflectionClass($class);

        $this->class = $classInfo->getName();
        $this->propertyMap = array();
        $this->propertyClassMap = array();

        $this->collectValueProperties($classInfo, $store);

        if (count($this->propertyMap) < 1) {
            throw new \Exception('value class must have at least one property');
        }
    }

    function getBackendType() {
        if (count($this->propertyMap) === 1) {
            foreach ($this->propertyMap as $n => $prop) {
                return $this->propertyClassMap[$n]->getBackendType();
            }
        } else {
            $typeMap = array();

            foreach ($this->propertyMap as $n => $prop) {
                $typeMap[$n] = $this->propertyClassMap[$n]->getBackendType();
            }

            return $typeMap;
        }
    }

    function extern($obj) {
        // passthrough null
        if ($obj === null) {
            return null;
        }

        if(!is_object($obj) || $this->class !== get_class($obj)) {
            throw new \Exception('class mismatch'); // developer error
        }

        if (count($this->propertyMap) === 1) {
            foreach ($this->propertyMap as $n => $prop) {
                $pv = $prop->getValue($obj);
                return $this->propertyClassMap[$n]->extern($pv);
            }
        } else {
            $v = (object)array();

            foreach ($this->propertyMap as $n => $prop) {
                $pv = $prop->getValue($obj);
                $v->$n = $this->propertyClassMap[$n]->extern($pv);
            }

            return $v;
        }
    }

    function intern($v) {
        // passthrough null
        if ($v === null) {
            return null;
        }

        // reify using deserialization trick to avoid triggering validation
        $obj = unserialize('O:' . strlen($this->class) . ':"' . $this->class . '":0:{}');

        if (count($this->propertyMap) === 1) {
            foreach ($this->propertyMap as $n => $prop) {
                $pv = $this->propertyClassMap[$n]->intern($v);
                $prop->setValue($obj, $pv);
            }
        } else {
            foreach ($this->propertyMap as $n => $prop) {
                if (!property_exists($v, $n)) {
                    throw new \Exception('missing raw property'); // @todo custom exception
                }

                $pv = $this->propertyClassMap[$n]->intern($v->$n);
                $prop->setValue($obj, $pv);
            }
        }

        return $obj;
    }

    private function collectValueProperties(\ReflectionClass $classInfo, EnvironmentStore $store) {
        if ($classInfo->getParentClass()) {
            $this->collectValueProperties($classInfo->getParentClass(), $store);
        }

        foreach ($classInfo->getProperties() as $property) {
            $type = TypeInfo::createForProperty($property);

            $property->setAccessible(true);

            $this->propertyMap[$property->getName()] = $property;
            $this->propertyClassMap[$property->getName()] = $store->getSerializerForType($type);
        }
    }
}

?>
