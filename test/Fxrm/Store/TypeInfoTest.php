<?php

namespace Fxrm\Store;

class TypeInfoTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->classInfo = new \ReflectionClass('Fxrm\\Store\\TESTPROPS');
    }

    public function testBlank() {
        $t = TypeInfo::createForProperty($this->classInfo->getProperty('blank'));
        $this->assertFalse($t->getIsArray());
        $this->assertNull($t->getElementClass());
    }

    public function testInt() {
        $t = TypeInfo::createForProperty($this->classInfo->getProperty('int'));
        $this->assertFalse($t->getIsArray());
        $this->assertNull($t->getElementClass());
    }

    public function testIntArray() {
        $t = TypeInfo::createForProperty($this->classInfo->getProperty('intArray'));
        $this->assertTrue($t->getIsArray());
        $this->assertNull($t->getElementClass());
    }

    public function testArray() {
        $t = TypeInfo::createForProperty($this->classInfo->getProperty('array'));
        $this->assertTrue($t->getIsArray());
        $this->assertNull($t->getElementClass());
    }

    public function testObjectArray() {
        $t = TypeInfo::createForProperty($this->classInfo->getProperty('objectArray'));
        $this->assertTrue($t->getIsArray());
        $this->assertSame('stdClass', $t->getElementClass()->getName());
        $this->assertSame('', $t->getElementClass()->getNamespaceName());
    }

    public function testAbsoluteNameArray() {
        $t = TypeInfo::createForProperty($this->classInfo->getProperty('absoluteNameArray'));
        $this->assertTrue($t->getIsArray());
        $this->assertSame('Fxrm\\Store\\TESTPROPS', $t->getElementClass()->getName());
        $this->assertSame('Fxrm\\Store', $t->getElementClass()->getNamespaceName());
    }

    public function testRelativeName() {
        $t = TypeInfo::createForProperty($this->classInfo->getProperty('relativeName'));
        $this->assertFalse($t->getIsArray());
        $this->assertSame('Fxrm\\Store\\TESTPROPS', $t->getElementClass()->getName());
        $this->assertSame('Fxrm\\Store', $t->getElementClass()->getNamespaceName());
    }
}

class TESTPROPS {
    private $blank;
    /** @var int */ private $int;
    /** @var int[] */ private $intArray;
    /** @var array */ private $array;
    /** @var object[] */ private $objectArray;
    /** @var \Fxrm\Store\TESTPROPS[] */ private $absoluteNameArray;
    /** @var TESTPROPS */ private $relativeName;
}
