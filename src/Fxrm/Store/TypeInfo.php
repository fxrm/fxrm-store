<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2014, Nick Matantsev
 */

namespace Fxrm\Store;

/**
 * Class name parsing helper.
 */
class TypeInfo {
    private static $PRIMITIVE_TYPES = array('string', 'integer', 'int', 'boolean', 'bool', 'float'); // @todo add more

    private $classInfo;
    private $isArray = false;

    private function __construct(\ReflectionClass $declaringClass, $classHint) {
        if (substr($classHint, -2) === '[]') {
            $this->isArray = true;
            $classHint = substr($classHint, 0, -2);
        } elseif ($classHint === 'array') {
            $this->isArray = true;
            $classHint = null;
        }

        if ($classHint === null || array_search($classHint, self::$PRIMITIVE_TYPES) !== FALSE) {
            $this->classInfo = null;
        } elseif ($classHint === 'object') {
            // special object shorthand
            $this->classInfo = new \ReflectionClass('stdClass');
        } else {
            $this->classInfo = new \ReflectionClass($classHint[0] === '\\' ?
                substr($classHint, 1) :
                $declaringClass->getNamespaceName() . '\\' . $classHint);
        }
    }

    /**
     * @return Serializer
     */
    public function createSerializer(EnvironmentStore $store) {
        // @todo primitive types
        $elementSerializer = $this->classInfo === null
            ? new PassthroughSerializer()
            : $store->createClassSerializer($this->classInfo->getName());

        return $this->isArray ?
            new ArraySerializer($elementSerializer) :
            $elementSerializer;
    }

    public function getIsArray() {
        return $this->isArray;
    }

    public function getElementClass() {
        return $this->classInfo;
    }

    public static function createForProperty(\ReflectionProperty $prop) {
        $hint = preg_match('/@var\\s+(\\S+)/', $prop->getDocComment(), $commentMatch) ?
            $commentMatch[1] :
            null;

        return new self($prop->getDeclaringClass(), $hint);
    }

    public static function createForMethodReturn(\ReflectionMethod $info) {
        $hint = preg_match('/@return\\s+(\\S+)/', $info->getDocComment(), $commentMatch) ?
            $commentMatch[1] :
            null;

        return new self($info->getDeclaringClass(), $hint);
    }
}
