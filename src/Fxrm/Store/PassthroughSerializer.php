<?php

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
