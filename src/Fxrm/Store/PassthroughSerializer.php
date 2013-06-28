<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

class PassthroughSerializer implements Serializer {
    function extern($obj) {
        return $obj;
    }

    function intern($id) {
        return $id;
    }
}

?>
