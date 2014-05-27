<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

abstract class PDOBackend extends Backend {
    private $pdo;

    function __construct($dsn, $user = null, $password = null) {
        $this->pdo = new \PDO($dsn, $user, $password);

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->initPDO($this->pdo);
    }

    final function find($method, $entity, $valueMap, $returnType, $multiple) {
        $stmt = $this->pdo->prepare($this->getCustomQuery($method) ?: $this->generateFindQuery($this->getEntityName($entity), array_keys($valueMap), $multiple));

        foreach ($valueMap as $field => $value) {
            $stmt->bindValue(':' . $field, $this->fromValue($value));
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
        // @todo use the original model parameter name as query param
        $sql = $this->getCustomQuery($method) ?: $this->generateGetQuery($this->getEntityName($entity), $field);

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', (int)$id);
        $stmt->execute();

        // expecting only a single value per row
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);

        $stmt->closeCursor();

        // object is expected to exist; this exception is not caught by model logic
        if (count($rows) !== 1) {
            throw new \Exception('object not found');
        }

        return $this->toValue($fieldType, $rows[0]);
    }

    final function set($method, $entity, $id, $valueMap) {
        // @todo use the original model parameter name as query param
        $stmt = $this->pdo->prepare($this->getCustomQuery($method) ?: $this->generateSetQuery($this->getEntityName($entity), array_keys($valueMap)));
        $stmt->bindValue(':id', (int)$id);

        $valueCount = 0;
        foreach ($valueMap as $field => $value) {
            $stmt->bindValue(':' . $field, $this->fromValue($value));
            $valueCount += 1;
        }

        $stmt->execute();

        // not checking updated row count in case the custom query updates multiple rows
        $stmt->closeCursor();
    }

    final function create($entity) {
        $stmt = $this->pdo->prepare($this->generateCreateQuery($this->getEntityName($entity)));
        $stmt->execute();
        $id = $this->pdo->lastInsertId();

        $stmt->closeCursor();

        return $id;
    }

    final function retrieve($querySpecMap, $paramMap, $returnTypeMap) {
        $ns = $this->getImplementationNamespace();

        if (!array_key_exists($ns, $querySpecMap)) {
            throw new \Exception('no query spec for namespace "' . $ns . '"');
        }

        $spec = $querySpecMap[$ns];

        $stmt = $this->pdo->prepare($spec);
        foreach ($paramMap as $field => $value) {
            $stmt->bindValue(is_int($field) ? $field + 1 : ':' . $field, $this->fromValue($value));
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

        if ($type === Backend::DATE_TIME_TYPE) {
            return $this->internDateTime($data);
        }

        return $data;
    }

    private function fromValue($value) {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTime) {
            return $this->externDateTime($value);
        }

        return $value;
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

    protected function initPDO(\PDO $pdo) {
        // implementations may override this to run extra initialization commands on the PDO instance
    }

    abstract protected function getImplementationNamespace();

    abstract protected function externDateTime(\DateTime $value);

    abstract protected function internDateTime($data);

    abstract protected function generateFindQuery($entity, $fieldList, $multiple);

    abstract protected function generateGetQuery($entity, $field);

    abstract protected function generateSetQuery($entity, $fieldList);

    abstract protected function generateCreateQuery($entity);
}

?>
