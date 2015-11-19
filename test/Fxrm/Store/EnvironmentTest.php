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

    public function testImplementMismatchingArgs() {
        $this->setExpectedException('Exception');
        $this->env->implement('Fxrm\\Store\\TEST_ENV_EMPTY');
    }

    public function testImplementWithArgs() {
        $impl = $this->env->implement(
            'Fxrm\\Store\\TEST_ENV_EMPTY',
            'TEST_ARG_A',
            'TEST_ARG_B'
        );

        $this->assertInstanceOf('Fxrm\\Store\\TEST_ENV_EMPTY', $impl);
        $this->assertSame('TEST_ARG_A', $impl->a);
        $this->assertSame('TEST_ARG_B', $impl->b);
    }
}

abstract class TEST_ENV_EMPTY {
    public $a, $b;

    function __construct($a, $b) {
        $this->a = $a;
        $this->b = $b;
    }
}
