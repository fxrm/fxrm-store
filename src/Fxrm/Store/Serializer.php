<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

interface Serializer {
    function extern($obj);

    function intern($id);

    function getBackendType();
}

?>
