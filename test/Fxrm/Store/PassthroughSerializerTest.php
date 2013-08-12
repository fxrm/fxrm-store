<?php

namespace Fxrm\Store;

class PassthroughSerializerTest extends \PHPUnit_Framework_TestCase {
    public function testInternExtern() {
        $s = new PassthroughSerializer();
        $value = (object)array();

        $this->assertSame($value, $s->intern($value));
        $this->assertSame($value, $s->extern($value));
    }
}
