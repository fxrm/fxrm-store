<?php

namespace Fxrm\Store;

class ValueSerializerTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->store = $this->getMock('Fxrm\\Store\\EnvironmentStore', array(), array(), '', false);
        $this->s = new ValueSerializer('Fxrm\\Store\\TESTVALUE', $this->store);
        $this->sub = new ValueSerializer('Fxrm\\Store\\TESTSUBVALUE', $this->store);

        $this->store->expects($this->any())->method('createClassSerializer')->will($this->returnValueMap(array(
            array('Fxrm\\Store\\TESTVALUE', $this->s)
        )));

        $this->s2 = new ValueSerializer('Fxrm\\Store\\TESTCOMPLEXVALUE', $this->store);
    }

    public function testBackendTypes() {
        $this->assertSame(null, $this->s->getBackendType());
        $this->assertSame(null, $this->sub->getBackendType());
        $this->assertSame(array('x' => null, 'a' => null, 'b' => array(null)), $this->s2->getBackendType());
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

    public function testSubExtern() {
        $this->assertSame('TESTVAL', $this->sub->extern(new TESTSUBVALUE()));
    }

    public function testSubIntern() {
        $this->assertSame('TEST2', $this->sub->intern('TEST2')->testObtainSubX());
    }

    public function testInternHasCorrectClass() {
        $this->assertInstanceOf('Fxrm\\Store\\TESTVALUE', $this->s->intern('TEST3'));
    }

    public function testInternIsReversible() {
        $this->assertSame('TEST4', $this->s->extern($this->s->intern('TEST4')));
    }

    public function testExternComplex() {
        $this->assertEquals((object)array('a' => 'A', 'b' => array('B'), 'x' => 'TESTVAL'), $this->s2->extern(new TESTCOMPLEXVALUE()));
    }

    public function testInternComplex() {
        $obj = $this->s2->intern((object)array('a' => 'A1', 'b' => array('B1'), 'x' => 'TESTVAL'));
        $this->assertSame('A1', $obj->testObtainA());
        $this->assertEquals(array(new TESTVALUE('B1')), $obj->testObtainB());
    }
}

class TESTVALUE {
    private $x;

    public function __construct($val = 'TESTVAL') {
        $this->x = $val;
    }

    public function testObtainX() {
        return $this->x;
    }
}

class TESTSUBVALUE extends TESTVALUE {
    public function testObtainSubX() {
        return $this->testObtainX();
    }
}

class TESTCOMPLEXVALUE extends TESTVALUE {
    private $a = 'A';
    /** @var TESTVALUE[] */ private $b = array();

    public function __construct() {
        parent::__construct();

        $this->b[] = new TESTVALUE('B');
    }

    public function testObtainA() {
        return $this->a;
    }

    public function testObtainB() {
        return $this->b;
    }
}
