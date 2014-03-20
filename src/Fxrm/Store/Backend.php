<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

abstract class Backend {
    const DATE_TIME_TYPE = '03e38550-dd54-11e2-a28f-0800200c9a66';

    abstract function find($method, $entity, $valueMap, $returnType, $multiple);

    abstract function get($method, $entity, $id, $fieldType, $field);

    abstract function set($method, $entity, $id, $valueMap);

    abstract function create($entity);

    abstract function retrieve($querySpecMap, $paramMap, $returnTypeMap);
}

?>
