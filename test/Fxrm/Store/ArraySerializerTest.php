<?php

namespace Fxrm\Store;

class ArraySerializerTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->es = $this->getMockBuilder('Fxrm\\Store\\Serializer')->getMock();

        $this->s = new ArraySerializer($this->es);
    }

    public function testBackendType() {
        $this->es->expects($this->any())
            ->method('getBackendType')
            ->will($this->returnValue('TEST_BACKEND_TYPE'));

        $this->assertSame(array('TEST_BACKEND_TYPE'), $this->s->getBackendType());
    }

    public function testExternNull() {
        $this->setExpectedException('Exception');
        $this->s->extern(null);
    }

    public function testExtern() {
        $this->es->expects($this->once())
            ->method('extern')->with('TEST_INPUT_ELEMENT')
            ->will($this->returnValue('TEST_OUTPUT_ELEMENT'));

        $result = $this->s->extern(array('TEST_INPUT_ELEMENT'));
        $this->assertEquals(array('TEST_OUTPUT_ELEMENT'), $result);
    }

    public function testIntern() {
        $this->es->expects($this->once())
            ->method('intern')->with('TEST_INPUT_ELEMENT')
            ->will($this->returnValue('TEST_OUTPUT_ELEMENT'));

        $result = $this->s->intern(array('TEST_INPUT_ELEMENT'));
        $this->assertEquals(array('TEST_OUTPUT_ELEMENT'), $result);
    }
}
