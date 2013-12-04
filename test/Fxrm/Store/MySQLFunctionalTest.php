<?php

namespace Fxrm\Store;

class MySQLFunctionalTest extends \PHPUnit_Framework_TestCase {
    public function setUp() {
        $dsn = getenv('TEST_MYSQL_DSN') ?: 'mysql:host=127.0.0.1;port=8889;dbname=fxrm_test';
        $user = getenv('TEST_MYSQL_USER') !== false ? getenv('TEST_MYSQL_USER') : 'root';
        $password = getenv('TEST_MYSQL_PASSWORD') !== false ? getenv('TEST_MYSQL_PASSWORD') : 'root';

        $this->pdo = new \PDO($dsn, $user, $password);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('
            create table MyTest (id INT PRIMARY KEY AUTO_INCREMENT, testProp VARCHAR(255) NULL, testDate INT NULL);
            insert into MyTest (testProp, testDate) VALUES (\'v1\', 1), (\'v2\', 2);
        ');

        $this->backend = new \Fxrm\Store\MySQLBackend($dsn, $user, $password);
    }

    public function tearDown() {
        if ($this->pdo) {
            $this->pdo->exec('
                drop table MyTest;
            ');
        }
    }

    public function testGet() {
        $this->assertEquals('v1', $this->backend->get('\\', 'Foo\\MyTestId', 1, null, 'testProp'));
    }

    public function testGetDate() {
        $d = $this->backend->get('\\', 'Foo\\MyTestId', 2, Backend::DATE_TIME_TYPE, 'testDate');
        $this->assertInstanceof('DateTime', $d);
        $this->assertEquals(2, $d->getTimestamp());
    }

    public function testSet() {
        $this->backend->set('\\', 'Foo\\MyTestId', 1, array('testProp' => 'CHANGED'));
        $this->assertEquals(1, $this->backend->find('\\', 'Foo\\MyTestId', array('testProp' => 'CHANGED'), null, false));
    }

    public function testMultiFind() {
        $result = $this->backend->find('\\', 'Foo\\MyTestId', array('testProp' => 'v2'), null, true);
        $this->assertEquals(array(2), $result);
    }

    public function testMultiFindByDate() {
        $result = $this->backend->find('\\', 'Foo\\MyTestId', array('testDate' => new \DateTime('1970-01-01 00:00:01 UTC')), null, true);
        $this->assertEquals(array(1), $result);
    }

    public function testMultiFindEmpty() {
        $result = $this->backend->find('\\', 'Foo\\MyTestId', array('testProp' => 'NONEXISTENT'), null, true);
        $this->assertEquals(array(), $result);
    }

    public function testMultiFindAll() {
        $result = $this->backend->find('\\', 'Foo\\MyTestId', array(), null, true);
        $this->assertEquals(array(1, 2), $result);
    }

    public function testSingleFind() {
        $result = $this->backend->find('\\', 'Foo\\MyTestId', array('testProp' => 'v1'), null, false);
        $this->assertEquals(1, $result);
    }

    public function testSingleFindEmpty() {
        $result = $this->backend->find('\\', 'Foo\\MyTestId', array('testProp' => 'NONEXISTENT'), null, false);
        $this->assertNull($result);
    }

    public function testCreate() {
        $this->assertEquals(3, $this->backend->create('Foo\\MyTestId'));
    }
}
