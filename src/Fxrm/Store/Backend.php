<?php

namespace Fxrm\Store;

abstract class Backend {
    abstract function find($method, $entity, $valueMap, $returnType, $multiple);

    abstract function get($method, $entity, $id, $fieldType, $field);

    abstract function set($method, $entity, $id, $valueMap);

    abstract function create($entity);
}

?>
