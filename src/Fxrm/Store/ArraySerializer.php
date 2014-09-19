<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2014, Nick Matantsev
 */

namespace Fxrm\Store;

class ArraySerializer implements Serializer {
    private $elementSerializer;

    function __construct(Serializer $elementSerializer) {
        $this->elementSerializer = $elementSerializer;
    }

    function extern($objArray) {
        $ser = $this->elementSerializer;

        return array_map(function ($v) use($ser) {
            return $ser->extern($v);
        }, $objArray);
    }

    function intern($rawArray) {
        $ser = $this->elementSerializer;

        return array_map(function ($v) use($ser) {
            return $ser->intern($v);
        }, $rawArray);
    }
}
