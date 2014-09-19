<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

class PassthroughSerializer implements Serializer {
    private $backendType;

    function __construct($backendType = null) {
        $this->backendType = $backendType;
    }

    function getBackendType() {
        return $this->backendType;
    }

    function extern($obj) {
        return $obj;
    }

    function intern($id) {
        return $id;
    }
}

?>
