<?php

namespace Fxrm\Store;

class EnvironmentStoreTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->backend = $this->getMockBuilder('Fxrm\\Store\\Backend')->getMock();
    }

    public function testBadBackend() {
        $this->setExpectedException('Exception');
        new EnvironmentStore(array('TEST_BACKEND' => new \DateTime()), array(), array(), array());
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

    public function testFind() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array('Fxrm\\Store\\TEST_CLASS_VALUE'),
            array()
        );

        $obj = $s->intern('Fxrm\\Store\\TEST_CLASS_ID', 'TEST_ID_STRING');

        $this->backend->expects($this->once())
            ->method('find')->with(
                'TEST_NS\\TEST_CLASS\\TEST_METHOD',
                'Fxrm\\Store\\TEST_CLASS_ID',
                array('TEST_PROPERTY' => array('a' => null, 'b' => null)),
                array('TEST_PROPERTY' => (object)array('a' => null, 'b' => 'bee')),
                null,
                true
            )
            ->will($this->returnValue(array('TEST_ID_STRING 1', 'TEST_ID_STRING 2')));

        $result = $s->find(
            'TEST_BACKEND',
            'TEST_NS\\TEST_CLASS\\TEST_METHOD',
            'Fxrm\\Store\\TEST_CLASS_ID',
            array(
                'TEST_PROPERTY' => array('Fxrm\\Store\\TEST_CLASS_VALUE', new TEST_CLASS_VALUE())
            ),
            true
        );

        $this->assertCount(2, $result);
        $this->assertSame('TEST_ID_STRING 1', $s->extern($result[0]));
        $this->assertSame('TEST_ID_STRING 2', $s->extern($result[1]));
    }

    public function testFindRows() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array('Fxrm\\Store\\TEST_CLASS_VALUE'),
            array()
        );

        $obj = $s->intern('Fxrm\\Store\\TEST_CLASS_ID', 'TEST_ID_STRING');

        $this->backend->expects($this->once())
            ->method('find')->with(
                'TEST_NS\\TEST_CLASS\\TEST_METHOD',
                null,
                array('TEST_PROPERTY' => array('a' => null, 'b' => null)),
                array('TEST_PROPERTY' => (object)array('a' => null, 'b' => 'bee')),
                array(
                    'c' => null
                ),
                true
            )
            ->will($this->returnValue(array(
                (object)array('c' => 'TEST_ID_STRING 1'),
                (object)array('c' => 'TEST_ID_STRING 2')
            )));

        $result = $s->find(
            'TEST_BACKEND',
            'TEST_NS\\TEST_CLASS\\TEST_METHOD',
            'Fxrm\\Store\\TEST_CLASS_ROW',
            array(
                'TEST_PROPERTY' => array('Fxrm\\Store\\TEST_CLASS_VALUE', new TEST_CLASS_VALUE())
            ),
            true
        );

        $this->assertCount(2, $result);
        $this->assertSame('Fxrm\\Store\\TEST_CLASS_ROW', get_class($result[0]));
        $this->assertSame('TEST_ID_STRING 1', $s->extern($result[0]->c));
        $this->assertSame('Fxrm\\Store\\TEST_CLASS_ROW', get_class($result[1]));
        $this->assertSame('TEST_ID_STRING 2', $s->extern($result[1]->c));
    }

    public function testFindSingle() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array('Fxrm\\Store\\TEST_CLASS_VALUE'),
            array()
        );

        $obj = $s->intern('Fxrm\\Store\\TEST_CLASS_ID', 'TEST_ID_STRING');

        $this->backend->expects($this->once())
            ->method('find')->with(
                'TEST_NS\\TEST_CLASS\\TEST_METHOD',
                'Fxrm\\Store\\TEST_CLASS_ID',
                array('TEST_PROPERTY' => array('a' => null, 'b' => null)),
                array('TEST_PROPERTY' => (object)array('a' => null, 'b' => 'bee')),
                null,
                false
            )
            ->will($this->returnValue('TEST_ID_STRING single'));

        $result = $s->find(
            'TEST_BACKEND',
            'TEST_NS\\TEST_CLASS\\TEST_METHOD',
            'Fxrm\\Store\\TEST_CLASS_ID',
            array(
                'TEST_PROPERTY' => array('Fxrm\\Store\\TEST_CLASS_VALUE', new TEST_CLASS_VALUE())
            ),
            false
        );

        $this->assertSame('TEST_ID_STRING single', $s->extern($result));
    }

    public function testFindRowStatic() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array('Fxrm\\Store\\TEST_CLASS_VALUE'),
            array()
        );

        $this->backend->expects($this->once())
            ->method('find')->with(
                'TEST_NS\\TEST_CLASS\\TEST_METHOD',
                null,
                array('TEST_PROPERTY' => array('a' => null, 'b' => null)),
                array('TEST_PROPERTY' => (object)array('a' => null, 'b' => 'bee')),
                array(
                    'x' => null
                ),
                true
            )
            ->will($this->returnValue(array(
                (object)array('x' => 'TEST_ID_STRING 1'),
                (object)array('x' => 'TEST_ID_STRING 2')
            )));

        $result = $s->find(
            'TEST_BACKEND',
            'TEST_NS\\TEST_CLASS\\TEST_METHOD',
            'Fxrm\\Store\\TEST_CLASS_ROW_STATIC',
            array(
                'TEST_PROPERTY' => array('Fxrm\\Store\\TEST_CLASS_VALUE', new TEST_CLASS_VALUE())
            ),
            true
        );

        $this->assertCount(2, $result);
        $this->assertSame('Fxrm\\Store\\TEST_CLASS_ROW_STATIC', get_class($result[0]));
        $this->assertSame('TEST_ID_STRING 1', $result[0]->x);
        $this->assertSame('Fxrm\\Store\\TEST_CLASS_ROW_STATIC', get_class($result[1]));
        $this->assertSame('TEST_ID_STRING 2', $result[1]->x);
    }

    public function testFindRowPrivate() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array('Fxrm\\Store\\TEST_CLASS_VALUE'),
            array()
        );

        // @todo proper error class
        $this->setExpectedException('Exception');
        $s->find(
            'TEST_BACKEND',
            'TEST_NS\\TEST_CLASS\\TEST_METHOD',
            'Fxrm\\Store\\TEST_CLASS_ROW_PRIVATE',
            array(
                'TEST_PROPERTY' => array('Fxrm\\Store\\TEST_CLASS_VALUE', new TEST_CLASS_VALUE())
            ),
            true
        );
    }

    public function testFindRowArray() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array('Fxrm\\Store\\TEST_CLASS_VALUE'),
            array()
        );

        // @todo proper error class
        $this->setExpectedException('Exception');
        $s->find(
            'TEST_BACKEND',
            'TEST_NS\\TEST_CLASS\\TEST_METHOD',
            'Fxrm\\Store\\TEST_CLASS_ROW_ARRAY',
            array(
                'TEST_PROPERTY' => array('Fxrm\\Store\\TEST_CLASS_VALUE', new TEST_CLASS_VALUE())
            ),
            true
        );
    }

    public function testRetrieve() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array('Fxrm\\Store\\TEST_CLASS_VALUE'),
            array()
        );

        $this->backend->expects($this->once())
            ->method('retrieve')->with(
                'TEST_QUERY_SPEC_MAP',
                array('TEST_PARAM' => array('a' => null, 'b' => null)),
                array('TEST_PARAM' => (object)array('a' => null, 'b' => 'bee')),
                array('TEST_RESULT_PROP' => array('a' => null, 'b' => null))
            )
            ->will($this->returnValue(array(
                (object)array('TEST_RESULT_PROP' => (object)array('a' => 'aaa', 'b' => null))
            )));

        $result = $s->retrieve(
            'TEST_BACKEND',
            'TEST_QUERY_SPEC_MAP',
            array(
                'TEST_PARAM' => new TEST_CLASS_VALUE()
            ),
            array(
                'TEST_RESULT_PROP' => 'Fxrm\\Store\\TEST_CLASS_VALUE'
            )
        );

        $this->assertCount(1, $result);

        $this->assertSame('stdClass', get_class($result[0]));
        $this->assertObjectHasAttribute('TEST_RESULT_PROP', $result[0]);

        $expectedObject = new TEST_CLASS_VALUE();
        $expectedObject->a = 'aaa';
        $expectedObject->b = null;
        $this->assertEquals($expectedObject, $result[0]->TEST_RESULT_PROP);
    }

    public function testRetrieveUndeclaredProperties() {
        $s = new EnvironmentStore(
            array('TEST_BACKEND' => $this->backend),
            array('Fxrm\\Store\\TEST_CLASS_ID' => 'TEST_BACKEND'),
            array('Fxrm\\Store\\TEST_CLASS_VALUE'),
            array()
        );

        $this->backend->expects($this->once())
            ->method('retrieve')->with(
                'TEST_QUERY_SPEC_MAP',
                array('TEST_PARAM' => array('a' => null, 'b' => null)),
                array('TEST_PARAM' => (object)array('a' => null, 'b' => 'bee')),
                array()
            )
            ->will($this->returnValue(array(
                (object)array(
                    'TEST_UNDECLARED_PROP' => 12345
                )
            )));

        $result = $s->retrieve(
            'TEST_BACKEND',
            'TEST_QUERY_SPEC_MAP',
            array(
                'TEST_PARAM' => new TEST_CLASS_VALUE()
            ),
            array(
                // no declared properties
            )
        );

        $this->assertCount(1, $result);

        // ensure undeclared property keys are returned as well
        $this->assertObjectHasAttribute('TEST_UNDECLARED_PROP', $result[0]);
        $this->assertSame(12345, $result[0]->TEST_UNDECLARED_PROP);
    }
}

class TEST_CLASS_OTHER {}

class TEST_CLASS_ID {}

class TEST_CLASS_VALUE {
    public $a, $b = 'bee';
}

class TEST_CLASS_ROW {
    /** @var TEST_CLASS_ID */ public $c;
}

class TEST_CLASS_ROW_STATIC {
    public static $stat;
    /** @var int */ public $x;
}

class TEST_CLASS_ROW_PRIVATE {
    /** @var int */ private $x;
}

class TEST_CLASS_ROW_ARRAY {
    /** @var string[] */ public $x;
}
