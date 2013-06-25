<?php

namespace Fxrm\Store;

interface Serializer {
    function extern($obj);

    function intern($id);
}

?>