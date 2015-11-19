<?php

namespace Fxrm\Store;

class EnvironmentTest extends \PHPUnit_Framework_TestCase {
    /** @var Environment */ private $env;
    /** @var EnvironmentStore */ private $store;

    public function setUp() {
        $this->store = $this->getMockBuilder('Fxrm\\Store\\EnvironmentStore')->disableOriginalConstructor()->getMock();

        // create with dummy empty config and then inject
        // our own mock before running the rest of the tests
        // @todo this could be done using a subclass with protected getter
        $configJson = json_encode(array(
            'idClasses' => (object)array(),
            'valueClasses' => array(),
            'methods' => (object)array()
        ));

        $this->env = new Environment('data://text/plain,' . $configJson, array());

        $envClassInfo = new \ReflectionClass($this->env);
        $envStorePropInfo = $envClassInfo->getProperty('store');

        if ($envStorePropInfo->isStatic() || !$envStorePropInfo->isPrivate()) {
            throw new \Exception('expecting private member field');
        }

        $envStorePropInfo->setAccessible(true);
        $envStorePropInfo->setValue($this->env, $this->store);
        $envStorePropInfo->setAccessible(false);
    }

    public function testImplementNoConstructor() {
        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_EMPTY');

        $this->assertInstanceOf('Fxrm\\Store\\TEST_ENV_EMPTY', $impl);
    }

    public function testImplementNoConstructorMismatchingArgs() {
        $this->setExpectedException('Exception');
        $this->env->implement(
            'Fxrm\\Store\\TEST_ENV_EMPTY',
            'TEST_ARG'
        );
    }

    public function testImplementMismatchingArgs() {
        $this->setExpectedException('Exception');
        $this->env->implement('Fxrm\\Store\\TEST_ENV_EMPTY_WITH_ARGS');
    }

    public function testImplementWithArgs() {
        $impl = $this->env->implement(
            'Fxrm\\Store\\TEST_ENV_EMPTY_WITH_ARGS',
            'TEST_ARG_A',
            'TEST_ARG_B'
        );

        $this->assertInstanceOf('Fxrm\\Store\\TEST_ENV_EMPTY_WITH_ARGS', $impl);
        $this->assertSame('TEST_ARG_A', $impl->a);
        $this->assertSame('TEST_ARG_B', $impl->b);
    }

    public function testImplementTwice() {
        $impl = $this->env->implement(
            'Fxrm\\Store\\TEST_ENV_EMPTY_WITH_ARGS',
            'TEST_ARG_A',
            'TEST_ARG_B'
        );

        $implClass = new \ReflectionClass($impl);

        $impl2 = $this->env->implement(
            'Fxrm\\Store\\TEST_ENV_EMPTY_WITH_ARGS',
            'TEST_ARG_C',
            'TEST_ARG_D'
        );

        $this->assertNotSame($impl, $impl2);
        $this->assertTrue($implClass->isInstance($impl2));
    }

    public function testPreserveNonAbstract() {
        $impl = $this->env->implement(
            'Fxrm\\Store\\TEST_ENV_NON_ABSTRACT'
        );

        $implClass = new \ReflectionClass($impl);
        $declClass = $implClass->getMethod('nonAbstractMethod')->getDeclaringClass();

        $this->assertSame('Fxrm\\Store\\TEST_ENV_NON_ABSTRACT', $declClass->getName());
    }

    public function testImplementParentMethods() {
        $this->store->expects($this->any())
            ->method('isSerializableClass')
            ->will($this->returnValue(true));

        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_DERIVED_OF_GETTER');
        $implClass = new \ReflectionClass($impl);
        $implMethodInfo = $implClass->getMethod('getTEST_ENV_TestProperty');
        $protoMethodInfo = $implMethodInfo->getPrototype();

        $this->assertSame('Fxrm\\Store\\TEST_ENV_DERIVED_OF_GETTER', $implClass->getParentClass()->getName());

        $this->assertSame($implClass->getName(), $implMethodInfo->getDeclaringClass()->getName());
        $this->assertSame('Fxrm\\Store\\TEST_ENV_GETTER', $protoMethodInfo->getDeclaringClass()->getName());
    }

    public function testExport() {
        $this->store->expects($this->any())
            ->method('extern')->with('TEST_OBJ')
            ->will($this->returnValue('TEST_STRING'));

        $this->assertSame('TEST_STRING', $this->env->export('TEST_OBJ'));
    }

    public function testImport() {
        $this->store->expects($this->any())
            ->method('intern')->with('TEST_CLASS', 'TEST_STRING')
            ->will($this->returnValue('TEST_OBJ'));

        $this->assertSame('TEST_OBJ', $this->env->import('TEST_CLASS', 'TEST_STRING'));
    }

    public function testExportUsing() {
        $this->store->expects($this->any())
            ->method('extern')->with('TEST_OBJ')
            ->will($this->returnValue('TEST_STRING'));

        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_EMPTY');

        $this->assertSame('TEST_STRING', $this->env->exportUsing($impl, 'TEST_OBJ'));
    }

    public function testImportUsing() {
        $this->store->expects($this->any())
            ->method('intern')->with('TEST_CLASS', 'TEST_STRING')
            ->will($this->returnValue('TEST_OBJ'));

        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_EMPTY');

        $this->assertSame('TEST_OBJ', $this->env->importUsing($impl, 'TEST_CLASS', 'TEST_STRING'));
    }

    public function testRetrieveUsing() {
        $this->store->expects($this->any())
            ->method('retrieve')->with(
                'TEST_BACKEND',
                'TEST_SPEC_MAP',
                'TEST_PARAM_MAP',
                'TEST_COLUMN_MAP'
            )
            ->will($this->returnValue('TEST_DATA'));

        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_EMPTY');

        $this->assertSame('TEST_DATA', $this->env->retrieveUsing(
            $impl,
            'TEST_BACKEND',
            'TEST_SPEC_MAP',
            'TEST_PARAM_MAP',
            'TEST_COLUMN_MAP'
        ));
    }

    public function testGetter() {
        $this->store->expects($this->any())
            ->method('getBackendName')->with(
                'Fxrm\\Store\\TEST_ENV_GETTER\\getTEST_ENV_TestProperty',
                'Fxrm\\Store\\TEST_ENV_Id',
                'testProperty'
            )
            ->will($this->returnValue('TEST_BACKEND'));

        $this->store->expects($this->any())
            ->method('isSerializableClass')
            ->will($this->returnValue(true));

        $id = new TEST_ENV_Id();
        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_GETTER');

        $this->store->expects($this->once())
            ->method('get')->with(
                'TEST_BACKEND',
                'Fxrm\\Store\\TEST_ENV_GETTER\\getTEST_ENV_TestProperty',
                'Fxrm\\Store\\TEST_ENV_Id',
                $id,
                'Fxrm\\Store\\TEST_ENV_VALUE',
                'testProperty'
            )
            ->will($this->returnValue('TEST_VALUE'));

        $val = $impl->getTEST_ENV_TestProperty($id);

        $this->assertSame('TEST_VALUE', $val);
    }

    public function testGetterUndecorated() {
        $this->store->expects($this->any())
            ->method('getBackendName')->with(
                'Fxrm\\Store\\TEST_ENV_GETTER_UNDECORATED\\getTEST_ENV_TestProperty',
                'Fxrm\\Store\\TEST_ENV_Id',
                'testProperty'
            )
            ->will($this->returnValue('TEST_BACKEND'));

        $this->store->expects($this->any())
            ->method('isSerializableClass')
            ->will($this->returnValue(true));

        $id = new TEST_ENV_Id();
        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_GETTER_UNDECORATED');

        $this->store->expects($this->once())
            ->method('get')->with(
                'TEST_BACKEND',
                'Fxrm\\Store\\TEST_ENV_GETTER_UNDECORATED\\getTEST_ENV_TestProperty',
                'Fxrm\\Store\\TEST_ENV_Id',
                $id,
                null,
                'testProperty'
            );

        $impl->getTEST_ENV_TestProperty($id);
    }

    public function testGetterInt() {
        $this->store->expects($this->any())
            ->method('getBackendName')->with(
                'Fxrm\\Store\\TEST_ENV_GETTER_INT\\getTEST_ENV_TestProperty',
                'Fxrm\\Store\\TEST_ENV_Id',
                'testProperty'
            )
            ->will($this->returnValue('TEST_BACKEND'));

        $this->store->expects($this->any())
            ->method('isSerializableClass')
            ->will($this->returnValue(true));

        $id = new TEST_ENV_Id();
        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_GETTER_INT');

        $this->store->expects($this->once())
            ->method('get')->with(
                'TEST_BACKEND',
                'Fxrm\\Store\\TEST_ENV_GETTER_INT\\getTEST_ENV_TestProperty',
                'Fxrm\\Store\\TEST_ENV_Id',
                $id,
                null,
                'testProperty'
            );

        $impl->getTEST_ENV_TestProperty($id);
    }

    public function testGetterObject() {
        $this->store->expects($this->any())
            ->method('getBackendName')->with(
                'Fxrm\\Store\\TEST_ENV_GETTER_OBJECT\\getTEST_ENV_TestProperty',
                'Fxrm\\Store\\TEST_ENV_Id',
                'testProperty'
            )
            ->will($this->returnValue('TEST_BACKEND'));

        $this->store->expects($this->any())
            ->method('isSerializableClass')
            ->will($this->returnValue(true));

        $id = new TEST_ENV_Id();
        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_GETTER_OBJECT');

        $this->store->expects($this->once())
            ->method('get')->with(
                'TEST_BACKEND',
                'Fxrm\\Store\\TEST_ENV_GETTER_OBJECT\\getTEST_ENV_TestProperty',
                'Fxrm\\Store\\TEST_ENV_Id',
                $id,
                'stdClass',
                'testProperty'
            );

        $impl->getTEST_ENV_TestProperty($id);
    }

    public function testGetterExtraArgs() {
        $this->setExpectedException('Exception');
        $this->env->implement('Fxrm\\Store\\TEST_ENV_GETTER_EXTRA_ARGS');
    }

    public function testGetterArray() {
        $this->setExpectedException('Exception');
        $this->env->implement('Fxrm\\Store\\TEST_ENV_GETTER_ARRAY');
    }

    public function testGetterNonId() {
        // @todo a more specific error class
        $this->setExpectedException('Exception');
        $this->env->implement('Fxrm\\Store\\TEST_ENV_GETTER_NON_ID');
    }

    public function testGetterMisnamed() {
        // @todo a more specific error class
        $this->setExpectedException('Exception');
        $this->env->implement('Fxrm\\Store\\TEST_ENV_GETTER_MISNAMED');
    }

    public function testSetter() {
        $this->store->expects($this->any())
            ->method('getBackendName')->with(
                'Fxrm\\Store\\TEST_ENV_SETTER\\testSetterMethod',
                'Fxrm\\Store\\TEST_ENV_Id',
                'testId'
            )
            ->will($this->returnValue('TEST_BACKEND'));

        $this->store->expects($this->any())
            ->method('isSerializableClass')
            ->will($this->returnValue(true));

        $id = new TEST_ENV_Id();
        $val = new TEST_ENV_VALUE();
        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_SETTER');

        $this->store->expects($this->once())
            ->method('set')->with(
                'TEST_BACKEND',
                'Fxrm\\Store\\TEST_ENV_SETTER\\testSetterMethod',
                'Fxrm\\Store\\TEST_ENV_Id',
                $id,
                array(
                    'testProperty' => array('Fxrm\\Store\\TEST_ENV_VALUE', $val)
                )
            )
            ->will($this->returnValue('TEST_VALUE'));

        $impl->testSetterMethod($id, $val);
    }

    public function testSetterFewArgs() {
        // @todo a more specific error class
        $this->setExpectedException('Exception');
        $this->env->implement('Fxrm\\Store\\TEST_ENV_SETTER_FEW_ARGS');
    }

    public function testFinder() {
        $this->store->expects($this->any())
            ->method('getBackendName')->with(
                'Fxrm\\Store\\TEST_ENV_FINDER\\testFinderMethod',
                'Fxrm\\Store\\TEST_ENV_Id',
                null
            )
            ->will($this->returnValue('TEST_BACKEND'));

        $this->store->expects($this->any())
            ->method('isSerializableClass')
            ->with('Fxrm\\Store\\TEST_ENV_Id')
            ->will($this->returnValue(true));

        $val = new TEST_ENV_VALUE();
        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_FINDER');

        $this->store->expects($this->once())
            ->method('find')->with(
                'TEST_BACKEND',
                'Fxrm\\Store\\TEST_ENV_FINDER\\testFinderMethod',
                'Fxrm\\Store\\TEST_ENV_Id',
                array(
                    'testProperty' => array('Fxrm\\Store\\TEST_ENV_VALUE', $val)
                ),
                false
            )
            ->will($this->returnValue('TEST_VALUE'));

        $this->assertSame('TEST_VALUE', $impl->testFinderMethod($val));
    }

    public function testFinderMultiRow() {
        $this->store->expects($this->any())
            ->method('getBackendName')->with(
                'Fxrm\\Store\\TEST_ENV_FINDER_MULTI_ROW\\testFinderMethod',
                'Fxrm\\Store\\TEST_ENV_ROW_DECORATED',
                null
            )
            ->will($this->returnValue('TEST_BACKEND'));

        $this->store->expects($this->any())
            ->method('isSerializableClass')
            ->with('Fxrm\\Store\\TEST_ENV_Id')
            ->will($this->returnValue(true));

        $val = new TEST_ENV_VALUE();
        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_FINDER_MULTI_ROW');

        $this->store->expects($this->once())
            ->method('find')->with(
                'TEST_BACKEND',
                'Fxrm\\Store\\TEST_ENV_FINDER_MULTI_ROW\\testFinderMethod',
                'Fxrm\\Store\\TEST_ENV_ROW_DECORATED',
                array(
                    'testProperty' => array('Fxrm\\Store\\TEST_ENV_VALUE', $val)
                ),
                true
            )
            ->will($this->returnValue('TEST_VALUE'));

        $this->assertSame('TEST_VALUE', $impl->testFinderMethod($val));
    }

    public function testFinderRowDecorated() {
        $this->store->expects($this->any())
            ->method('getBackendName')->with(
                'Fxrm\\Store\\TEST_ENV_FINDER_ROW_DECORATED\\testFinderMethod',
                'Fxrm\\Store\\TEST_ENV_ROW_DECORATED',
                null
            )
            ->will($this->returnValue('TEST_BACKEND'));

        $this->store->expects($this->any())
            ->method('isSerializableClass')
            ->will($this->returnValue(false));

        $val = new TEST_ENV_VALUE();
        $impl = $this->env->implement('Fxrm\\Store\\TEST_ENV_FINDER_ROW_DECORATED');

        $this->store->expects($this->once())
            ->method('find')->with(
                'TEST_BACKEND',
                'Fxrm\\Store\\TEST_ENV_FINDER_ROW_DECORATED\\testFinderMethod',
                'Fxrm\\Store\\TEST_ENV_ROW_DECORATED',
                array(
                    'testProperty' => array('Fxrm\\Store\\TEST_ENV_VALUE', $val)
                ),
                false
            )
            ->will($this->returnValue('TEST_VALUE'));

        $this->assertSame('TEST_VALUE', $impl->testFinderMethod($val));
    }
}

// using random field value to help find mismatch during assertions
class TEST_ENV_Id { function __construct() { $this->v = rand(); } }
class TEST_ENV_VALUE { function __construct() { $this->v = rand(); } }
class TEST_ENV_ROW_DECORATED { /** @var TEST_ENV_VALUE */ public $a; }

abstract class TEST_ENV_EMPTY {
}

abstract class TEST_ENV_EMPTY_WITH_ARGS {
    public $a, $b;

    function __construct($a, $b) {
        $this->a = $a;
        $this->b = $b;
    }
}

abstract class TEST_ENV_NON_ABSTRACT {
    function nonAbstractMethod() {}
}

interface TEST_ENV_GETTER {
    /** @return TEST_ENV_VALUE */ function getTEST_ENV_TestProperty(TEST_ENV_Id $id);
}

interface TEST_ENV_GETTER_EXTRA_ARGS {
    /** @return TEST_ENV_VALUE */ function getTEST_ENV_TestProperty(TEST_ENV_Id $id, TEST_ENV_VALUE $extraArg);
}

interface TEST_ENV_GETTER_ARRAY {
    /** @return TEST_ENV_VALUE[] */ function getTEST_ENV_TestProperty(TEST_ENV_Id $id);
}

interface TEST_ENV_GETTER_NON_ID {
    /** @return TEST_ENV_VALUE */ function getTEST_ENV_TestProperty(TEST_ENV_VALUE $val);
}

interface TEST_ENV_GETTER_MISNAMED {
    /** @return TEST_ENV_VALUE */ function getEXTRA_TEST_ENV_TestProperty(TEST_ENV_Id $id);
}

interface TEST_ENV_GETTER_UNDECORATED {
    function getTEST_ENV_TestProperty(TEST_ENV_Id $id);
}

interface TEST_ENV_GETTER_INT {
    /** @return int */ function getTEST_ENV_TestProperty(TEST_ENV_Id $id);
}

interface TEST_ENV_GETTER_OBJECT {
    /** @return object */ function getTEST_ENV_TestProperty(TEST_ENV_Id $id);
}

abstract class TEST_ENV_DERIVED_OF_GETTER implements TEST_ENV_GETTER {
}

interface TEST_ENV_SETTER {
    function testSetterMethod(TEST_ENV_Id $testId, TEST_ENV_VALUE $testProperty);
}

interface TEST_ENV_SETTER_FEW_ARGS {
    function testSetterMethod(TEST_ENV_Id $testId);
}

interface TEST_ENV_FINDER {
    /** @return TEST_ENV_Id */ function testFinderMethod(TEST_ENV_VALUE $testProperty);
}

interface TEST_ENV_FINDER_MULTI_ROW {
    /** @return TEST_ENV_ROW_DECORATED[] */ function testFinderMethod(TEST_ENV_VALUE $testProperty);
}

interface TEST_ENV_FINDER_ROW_DECORATED {
    /** @return TEST_ENV_ROW_DECORATED */ function testFinderMethod(TEST_ENV_VALUE $testProperty);
}
