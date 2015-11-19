<?php

namespace Fxrm\Store;

class EnvironmentStoreTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->backend = $this->getMockBuilder('Fxrm\\Store\\Backend')->getMock();
    }

    public function testDateTimeIsSerializable() {
        $s = new EnvironmentStore(array(), array(), array(), array());
        $this->assertTrue($s->isSerializableClass('DateTime'));
    }

    public function testCustomClassesAreSerializable() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array('Fxrm\\Store\\TEST_CLASS_VALUE'),
            array()
        );
        $this->assertFalse($s->isSerializableClass('Fxrm\\Store\\TEST_CLASS_OTHER'));
        $this->assertTrue($s->isSerializableClass('Fxrm\\Store\\TEST_CLASS_ID'));
        $this->assertTrue($s->isSerializableClass('Fxrm\\Store\\TEST_CLASS_VALUE'));
    }
}

class TEST_CLASS_OTHER {}
class TEST_CLASS_ID {}
class TEST_CLASS_VALUE {
    private $a;
}
