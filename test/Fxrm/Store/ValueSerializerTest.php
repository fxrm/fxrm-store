<?php

namespace Fxrm\Store;

class ValueSerializerTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->s = new ValueSerializer('Fxrm\\Store\\TESTVALUE');
    }

    public function testExternNull() {
        $this->assertSame(null, $this->s->extern(null));
    }

    public function testExternClassMismatch() {
        $this->setExpectedException('Exception');
        $this->s->extern((object)array());
    }

    public function testExtern() {
        $this->assertSame('TESTVAL', $this->s->extern(new TESTVALUE()));
    }

    public function testIntern() {
        $this->assertSame('TEST2', $this->s->intern('TEST2')->testObtainX());
    }

    public function testInternHasCorrectClass() {
        $this->assertInstanceOf('Fxrm\\Store\\TESTVALUE', $this->s->intern('TEST3'));
    }

    public function testInternIsReversible() {
        $this->assertSame('TEST4', $this->s->extern($this->s->intern('TEST4')));
    }
}

class TESTVALUE {
    private $x = 'TESTVAL';

    public function testObtainX() {
        return $this->x;
    }
}
