<?php

namespace Fxrm\Store;

class EnvironmentStoreTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->backend = $this->getMockBuilder('Fxrm\\Store\\Backend')->getMock();
    }

    public function testExternNonIdentity() {
        $s = new EnvironmentStore(array(), array(), array('Fxrm\\Store\\TEST_CLASS_VALUE'), array());

        $this->setExpectedException('Exception');
        $s->extern(new TEST_CLASS_VALUE());
    }

    public function testExternDateTime() {
        $s = new EnvironmentStore(array(), array(), array(), array());

        $this->setExpectedException('Exception');
        $s->extern(new \DateTime());
    }

    public function testExternIdentity() {
        $this->backend->expects($this->any())
            ->method('create')->with('Fxrm\\Store\\TEST_CLASS_ID')
            ->will($this->returnValue('TEST_ID_STRING'));

        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array(),
            array()
        );

        $this->assertSame('TEST_ID_STRING', $s->extern(new TEST_CLASS_ID()));
    }

    public function testInternNonIdentity() {
        $s = new EnvironmentStore(array(), array(), array('Fxrm\\Store\\TEST_CLASS_VALUE'), array());

        $this->setExpectedException('Exception');
        $s->intern('Fxrm\\Store\\TEST_CLASS_VALUE', null);
    }

    public function testInternDateTime() {
        $s = new EnvironmentStore(array(), array(), array(), array());

        $this->setExpectedException('Exception');
        $s->intern('DateTime', null);
    }

    public function testInternIdentity() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array(),
            array()
        );

        $this->assertInstanceOf('Fxrm\\Store\\TEST_CLASS_ID', $s->intern('Fxrm\\Store\\TEST_CLASS_ID', 'TEST_ID_STRING'));
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
