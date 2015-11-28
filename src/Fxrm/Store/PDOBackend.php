<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

abstract class PDOBackend extends Backend {
    private $pdo;
    private $idGeneratorMap;

    function __construct($dsn, $user = null, $password = null, $options = array()) {
        $this->idGeneratorMap = array_key_exists('idGeneratorMap', $options)
            ? (array)$options['idGeneratorMap']
            : array();

        $this->tuplePropertyMap = array_key_exists('tuplePropertyList', $options)
            ? array_fill_keys($options['tuplePropertyList'], null)
            : array();

        $this->pdo = new \PDO($dsn, $user, $password);

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->initPDO($this->pdo);
    }

    final function find($method, $entity, $valueTypeMap, $valueMap, $returnType, $multiple) {
        $stmt = $this->pdo->prepare($this->getCustomQuery($method) ?: $this->generateFindQuery(
            $this->getEntityName($entity),
            $this->getFlattenedFieldList($entity, array_keys($valueMap), $valueTypeMap),
            $multiple
        ));

        foreach ($valueMap as $field => $value) {
            $valueType = $valueTypeMap[$field];

            if ($this->getIsTupleProperty($entity, $field)) {
                // null $value is not tolerated by design (@todo add explicit null check throw?)
                foreach ($valueType as $k => $kType) {
                    $this->bindStatementValue($stmt, ':' . $field . '__' . $k, $this->fromValue($kType, $value->$k));
                }
            } else {
                $this->bindStatementValue($stmt, ':' . $field, $this->fromValue($valueType, $value));
            }
        }

        $stmt->execute();

        // if specific entity requested, only return the first column, assumed to contain the ID
        $rows = is_array($returnType) ? $stmt->fetchAll(\PDO::FETCH_OBJ) : $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);

        $stmt->closeCursor();

        // process all rows in-place
        if (is_array($returnType)) {
            foreach ($rows as &$row) {
                $row = $this->toRow($returnType, $row);
            }
        } else {
            foreach ($rows as &$row) {
                $row = $this->toValue($returnType, $row);
            }
        }

        // model may expect object to not exist: null value is the "pure" way to communicate that
        return $multiple ? $rows : (count($rows) === 0 ? null : $rows[0]);
    }

    final function get($method, $entity, $id, $fieldType, $field) {
        $isTuple = $this->getIsTupleProperty($entity, $field);

        // @todo use the original model parameter name as custom query param
        $sql = $this->getCustomQuery($method) ?: $this->generateGetQuery(
            $this->getEntityName($entity),
            $isTuple ? array_map(
                function ($k) use ($field) { return $field . '$' . $k; },
                array_keys($fieldType)
            ) : array($field)
        );

        $stmt = $this->pdo->prepare($sql);
        $this->bindStatementValue($stmt, ':id', $id);
        $stmt->execute();

        // expecting only a single value per row
        $rows = $isTuple
            ? $stmt->fetchAll(\PDO::FETCH_OBJ)
            : $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);

        $stmt->closeCursor();

        // object is expected to exist; this exception is not caught by model logic
        if (count($rows) !== 1) {
            throw new \Exception('object not found');
        }

        if ($isTuple) {
            $data = $rows[0];
            $result = (object)null;

            foreach ($fieldType as $k => $type) {
                $result->$k = $this->toValue($type, $data->{$field . '$' . $k});
            }

            return $result;
        } else {
            return $this->toValue($fieldType, $rows[0]);
        }
    }

    private function getFlattenedFieldList($entity, $fieldList, &$valueTypeMap) {
        $result = array();

        foreach ($fieldList as $field) {
            $valueType = $valueTypeMap[$field];

            if ($this->getIsTupleProperty($entity, $field)) {
                foreach ($valueType as $k => $kType) {
                    $result[] = $field . '$' . $k;
                }
            } else {
                $result[] = $field;
            }
        }

        return $result;
    }

    final function set($method, $entity, $id, $valueTypeMap, $valueMap) {
        // @todo use the original model parameter name as query param
        $stmt = $this->pdo->prepare($this->getCustomQuery($method) ?: $this->generateSetQuery(
            $this->getEntityName($entity),
            $this->getFlattenedFieldList($entity, array_keys($valueMap), $valueTypeMap)
        ));
        $this->bindStatementValue($stmt, ':id', $id);

        $valueCount = 0;
        foreach ($valueMap as $field => $value) {
            $valueType = $valueTypeMap[$field];

            if ($this->getIsTupleProperty($entity, $field)) {
                // null $value is not tolerated by design (@todo add explicit null check throw?)
                foreach ($valueType as $k => $kType) {
                    $this->bindStatementValue($stmt, ':' . $field . '__' . $k, $this->fromValue($kType, $value->$k));
                }
            } else {
                $this->bindStatementValue($stmt, ':' . $field, $this->fromValue($valueType, $value));
            }

            $valueCount += 1;
        }

        $stmt->execute();

        // not checking updated row count in case the custom query updates multiple rows
        $stmt->closeCursor();
    }

    final function create($entity) {
        $predefinedId = null;

        if (array_key_exists($entity, $this->idGeneratorMap)) {
            $generator = $this->idGeneratorMap[$entity];
            $predefinedId = $generator($this->pdo);
        }

        $stmt = $this->pdo->prepare($this->generateCreateQuery($this->getEntityName($entity), ($predefinedId !== null)));

        if ($predefinedId !== null) {
            $this->bindStatementValue($stmt, ':id', $predefinedId);
        }

        $stmt->execute();
        $id = $predefinedId === null ? $this->pdo->lastInsertId() : $predefinedId;

        $stmt->closeCursor();

        return $id;
    }

    final function retrieve($querySpecMap, $valueTypeMap, $paramMap, $returnTypeMap) {
        $ns = $this->getImplementationNamespace();

        if (!array_key_exists($ns, $querySpecMap)) {
            throw new \Exception('no query spec for namespace "' . $ns . '"');
        }

        $spec = $querySpecMap[$ns];

        $stmt = $this->pdo->prepare($spec);
        foreach ($paramMap as $field => $value) {
            $this->bindStatementValue($stmt, is_int($field) ? $field + 1 : ':' . $field, $this->fromValue($valueTypeMap[$field], $value));
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
        $stmt->closeCursor();

        // process all rows in-place
        foreach ($rows as &$row) {
            $row = $this->toRow($returnTypeMap, $row);
        }

        return $rows;
    }

    private function toRow($fieldTypeMap, $data) {
        $result = (object)null;

        // permissively copying all fields, including those with unknown types
        foreach ($data as $k => $v) {
            $result->$k = isset($fieldTypeMap[$k]) ? $this->toValue($fieldTypeMap[$k], $v) : $v;
        }

        return $result;
    }

    private function toValue($type, $data) {
        if ($data === null) {
            return null;
        }

        if (is_array($type)) {
            return $this->toJSONValue($type, json_decode($data, false)); // prefer anonymous objects instead of associative arrays
        } else {
            return $this->toSimpleValue($type, $data);
        }

        return $data;
    }

    private function toSimpleValue($type, $data) {
        if ($type === Backend::DATE_TIME_TYPE) {
            return $this->internDateTime($data);
        }

        return $data;
    }

    private function toJSONValue($fieldType, $data) {
        if ($data === null) {
            return null;
        }

        if (!is_array($fieldType)) {
            return $this->toSimpleValue($fieldType, $data);
        } elseif (array_key_exists(0, $fieldType)) {
            return $this->toJSONArray($fieldType, $data);
        } else {
            return $this->toJSONMap($fieldType, $data);
        }
    }

    private function toJSONArray($fieldTypeMap, $data) {
        $type = $fieldTypeMap[0];
        $result = array();

        foreach ($data as $v) {
            $result[] = $this->toJSONValue($type, $v);
        }

        return $result;
    }

    private function toJSONMap($fieldTypeMap, $data) {
        $result = (object)null;

        foreach ($fieldTypeMap as $k => $type) {
            $result->$k = $this->toJSONValue($type, $data->$k);
        }

        return $result;
    }

    private function fromValue($type, $value) {
        if ($value === null) {
            return null;
        }

        if (is_array($type)) {
            return json_encode($this->fromJSONValue($type, $value));
        } else {
            return $this->fromSimpleValue($type, $value);
        }
    }

    private function fromSimpleValue($type, $value) {
        if ($type === Backend::DATE_TIME_TYPE) {
            return $this->externDateTime($value);
        }

        return $value;
    }

    private function fromJSONValue($fieldType, $data) {
        if ($data === null) {
            return null;
        }

        if (!is_array($fieldType)) {
            return $this->fromSimpleValue($fieldType, $data);
        } elseif (array_key_exists(0, $fieldType)) {
            return $this->fromJSONArray($fieldType, $data);
        } else {
            return $this->fromJSONMap($fieldType, $data);
        }
    }

    private function fromJSONArray($fieldTypeMap, $data) {
        $type = $fieldTypeMap[0];
        $result = array();

        foreach ($data as $v) {
            $result[] = $this->fromJSONValue($type, $v);
        }

        return $result;
    }

    private function fromJSONMap($fieldTypeMap, $data) {
        $result = (object)null;

        foreach ($fieldTypeMap as $k => $type) {
            $result->$k = $this->fromJSONValue($type, $data->$k);
        }

        return $result;
    }

    private function getIsTupleProperty($entity, $field) {
        return array_key_exists($entity . '.' . $field, $this->tuplePropertyMap);
    }

    private function getEntityName($idClass) {
        if ( ! preg_match('/([^\\\\]+)Id$/', $idClass, $idMatch)) {
            throw new \Exception('entity class must be an identity: ' . $idClass);
        }

        return $idMatch[1];
    }

    private function getCustomQuery($fullName) {
        $segments = explode('\\', $fullName);

        $memberName = array_pop($segments);

        $classShortName = array_pop($segments);
        $segments[] = $this->getImplementationNamespace();
        $segments[] = $classShortName . 'SQL';
        $className = join('\\', $segments);

        $sqlConstantName = "$className::$memberName";
        return defined($sqlConstantName) ? constant($sqlConstantName) : null;
    }

    private function bindStatementValue($stmt, $param, $value) {
        $stmt->bindValue($param, $value, (
            is_int($value)
                ? \PDO::PARAM_INT
                : (
                    is_bool($value)
                        ? \PDO::PARAM_BOOL
                        : \PDO::PARAM_STR
                )
            )
        );
    }

    protected function initPDO(\PDO $pdo) {
        // implementations may override this to run extra initialization commands on the PDO instance
    }

    abstract protected function getImplementationNamespace();

    abstract protected function externDateTime(\DateTime $value);

    abstract protected function internDateTime($data);

    abstract protected function generateFindQuery($entity, $fieldList, $multiple);

    abstract protected function generateGetQuery($entity, $fieldList);

    abstract protected function generateSetQuery($entity, $fieldList);

    abstract protected function generateCreateQuery($entity, $isPredefinedId);
}

?>
