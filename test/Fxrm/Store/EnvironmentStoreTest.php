<?php

namespace Fxrm\Store;

class EnvironmentStoreTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->backend = $this->getMockBuilder('Fxrm\\Store\\Backend')->getMock();
    }

    public function testSerializerDateTime() {
        $s = new EnvironmentStore(array(), array(), array(), array());

        $ser = $s->createClassSerializer('DateTime');

        $this->assertInstanceOf('Fxrm\\Store\\PassthroughSerializer', $ser);
        $this->assertSame(Backend::DATE_TIME_TYPE, $ser->getBackendType());
    }

    public function testSerializerIdentityIsSame() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array(),
            array()
        );

        $ser = $s->createClassSerializer('Fxrm\\Store\\TEST_CLASS_ID');
        $ser2 = $s->createClassSerializer('Fxrm\\Store\\TEST_CLASS_ID');

        $this->assertInstanceOf('Fxrm\\Store\\IdentitySerializer', $ser);
        $this->assertSame($ser, $ser2);
    }

    public function testSerializerValue() {
        $s = new EnvironmentStore(array(), array(), array('Fxrm\\Store\\TEST_CLASS_VALUE'), array());

        $ser = $s->createClassSerializer('Fxrm\\Store\\TEST_CLASS_VALUE');
        $this->assertInstanceOf('Fxrm\\Store\\ValueSerializer', $ser);
        $this->assertEquals((object)array('a' => null, 'b' => 'bee'), $ser->extern(new TEST_CLASS_VALUE()));
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

    public function testBackendNameForUnknown() {
        $s = new EnvironmentStore(array(), array(), array(), array());

        $this->setExpectedException('Exception');
        $s->getBackendName('TEST_NS\\TEST_CLASS\\TEST_METHOD', 'Fxrm\\Store\\TEST_CLASS_ID', null);
    }

    public function testBackendNameByMethodName() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array(),
            array(),
            array('TEST_NS\\TEST_CLASS\\TEST_METHOD' => 'TEST_BACKEND')
        );

        $this->assertSame('TEST_BACKEND', $s->getBackendName('TEST_NS\\TEST_CLASS\\TEST_METHOD', 'Fxrm\\Store\\TEST_CLASS_ID', null));
    }

    public function testBackendNameByIdClass() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array(),
            array()
        );

        $this->assertSame('TEST_BACKEND', $s->getBackendName('TEST_NS\\TEST_CLASS\\TEST_METHOD', 'Fxrm\\Store\\TEST_CLASS_ID', null));
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

    public function testGet() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array(),
            array()
        );

        $obj = $s->intern('Fxrm\\Store\\TEST_CLASS_ID', 'TEST_ID_STRING');

        $this->backend->expects($this->once())
            ->method('get')->with(
                'TEST_NS\\TEST_CLASS\\TEST_METHOD',
                'Fxrm\\Store\\TEST_CLASS_ID',
                'TEST_ID_STRING',
                null,
                'TEST_PROPERTY'
            )
            ->will($this->returnValue('TEST_PROP_VALUE'));

        $val = $s->get(
            'TEST_BACKEND',
            'TEST_NS\\TEST_CLASS\\TEST_METHOD',
            'Fxrm\\Store\\TEST_CLASS_ID',
            $obj,
            null,
            'TEST_PROPERTY'
        );

        $this->assertSame('TEST_PROP_VALUE', $val);
    }

    public function testSet() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array('Fxrm\\Store\\TEST_CLASS_VALUE'),
            array()
        );

        $obj = $s->intern('Fxrm\\Store\\TEST_CLASS_ID', 'TEST_ID_STRING');

        $this->backend->expects($this->once())
            ->method('set')->with(
                'TEST_NS\\TEST_CLASS\\TEST_METHOD',
                'Fxrm\\Store\\TEST_CLASS_ID',
                'TEST_ID_STRING',
                array('TEST_PROPERTY' => array('a' => null, 'b' => null)),
                array('TEST_PROPERTY' => (object)array('a' => null, 'b' => 'bee'))
            );

        $s->set(
            'TEST_BACKEND',
            'TEST_NS\\TEST_CLASS\\TEST_METHOD',
            'Fxrm\\Store\\TEST_CLASS_ID',
            $obj,
            array(
                'TEST_PROPERTY' => array('Fxrm\\Store\\TEST_CLASS_VALUE', new TEST_CLASS_VALUE())
            )
        );
    }
}

class TEST_CLASS_OTHER {}
class TEST_CLASS_ID {}
class TEST_CLASS_VALUE {
    private $a, $b = 'bee';
}
