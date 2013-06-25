<?php

namespace Fxrm\Store;

class PassthroughSerializer {
    function extern($obj) {
        return $obj;
    }

    function intern($id) {
        return $id;
    }
}

?>
