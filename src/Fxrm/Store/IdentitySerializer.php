<?php

namespace Fxrm\Store;

class IdentitySerializer implements Serializer {
    private $className;
    private $toString, $fromString;
    private $backend;

    function __construct($className, $backend) {
        $this->className = $className;
        $this->backend = $backend;
        $this->toString = new \SplObjectStorage();
        $this->fromString = (object)array();
    }

    function extern($obj, $noCreate = false) {
        // passthrough null
        if ($obj === null) {
            return null;
        }

        if ( ! isset($this->toString[$obj])) {
            if ($noCreate) {
                throw new \Exception('unknown object'); // developer error
            }

            if($this->className !== get_class($obj)) {
                throw new \Exception('class mismatch'); // developer error
            }

            $id = $this->backend->create($this->className);

            $this->fromString->$id = $obj;
            $this->toString[$obj] = $id;
        }

        return $this->toString[$obj];
    }

    function externWithoutCreating($obj) {
        return $this->extern($obj, true);
    }

    function intern($id) {
        // passthrough null
        if ($id === null) {
            return null;
        }

        if ( ! isset($this->fromString->$id)) {
            $class = '\\' . $this->className; // fully qualified class
            $obj = new $class();

            $this->fromString->$id = $obj;
            $this->toString[$obj] = $id;
        }

        return $this->fromString->$id;
    }
}

?>
