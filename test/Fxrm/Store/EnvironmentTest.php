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

    public function testGetter() {
        $this->store->expects($this->any())
            ->method('getBackendName')->with(
                'Fxrm\\Store\\TEST_ENV_GETTER\\getTEST_ENV_TestProperty',
                'Fxrm\\Store\\TEST_ENV_Id',
                'testProperty'
            )
            ->will($this->returnValue('TEST_BACKEND'));

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
}

class TEST_ENV_Id {}
class TEST_ENV_VALUE {}

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

abstract class TEST_ENV_DERIVED_OF_GETTER implements TEST_ENV_GETTER {
}
