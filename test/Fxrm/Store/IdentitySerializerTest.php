<?php

namespace Fxrm\Store;

class IdentitySerializerTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        // backend reference is not used in most calls
        $this->s = new IdentitySerializer('Fxrm\\Store\\TESTIDENTITY', null);
    }

    public function testExternNull() {
        $this->assertSame(null, $this->s->extern(null));
    }

    public function testExternClassMismatch() {
        $this->setExpectedException('Exception');
        $this->s->extern((object)array());
    }

    public function testExternNewEntity() {
        // use a test instance with mocked backend
        $backend = $this->getMock('backend', array('create'));
        $this->s = new IdentitySerializer('Fxrm\\Store\\TESTIDENTITY', $backend);

        $id = new TESTIDENTITY();

        $backend->expects($this->once())->method('create')->with($this->equalTo('Fxrm\\Store\\TESTIDENTITY'))->will($this->returnValue('TESTID'));
        $this->assertSame('TESTID', $this->s->extern($id));
        $this->assertSame('TESTID', $this->s->extern($id)); // run a second time to check

        $this->assertSame($id, $this->s->intern('TESTID'));
    }

    public function testInternHasCorrectClass() {
        $this->assertInstanceOf('Fxrm\\Store\\TESTIDENTITY', $this->s->intern('TESTEXISTINGID'));
    }

    public function testInternMaintainsUniqueReference() {
        $this->assertSame($this->s->intern('TESTEXISTINGID'), $this->s->intern('TESTEXISTINGID'));
    }

    public function testInternIsReversible() {
        $this->assertSame('TESTEXISTINGID', $this->s->extern($this->s->intern('TESTEXISTINGID')));
    }
}

class TESTIDENTITY {
}
