<?php

namespace Fxrm\Store;

class SQLiteBackend {
    function __construct($dsn) {
        $this->pdo = new \PDO($dsn);

        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    function find($method, $entity, $valueMap, $multiple) {
        $stmt = $this->pdo->prepare($this->getCustomQuery($method) ?: $this->generateFindQuery($entity, array_keys($valueMap), $multiple));

        foreach ($valueMap as $field => $value) {
            $stmt->bindValue(':' . $field, $value);
        }

        $stmt->execute();

        // if specific entity requested, only return the first column, assumed to contain the ID
        $rows = $entity === null ? $stmt->fetchAll(\PDO::FETCH_OBJ) : $stmt->fetchAll(\PDO::FETCH_COLUMN, 0);

        $stmt->closeCursor();

        // model may expect object to not exist: null value is the "pure" way to communicate that
        return $multiple ? $rows : (count($rows) === 0 ? null : (string)$rows[0]);
    }

    function get($method, $entity, $id, $field) {
        // @todo use the original model parameter name as query param
        $sql = $this->getCustomQuery($method) ?: $this->generateGetQuery($entity, $field);

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

        return $rows[0];
    }

    function set($method, $entity, $id, $valueMap) {
        // @todo use the original model parameter name as query param
        $stmt = $this->pdo->prepare($this->getCustomQuery($method) ?: $this->generateSetQuery($entity, array_keys($valueMap)));
        $stmt->bindValue(':id', (int)$id);

        $valueCount = 0;
        foreach ($valueMap as $field => $value) {
            $stmt->bindValue(':' . $field, $value);
            $valueCount += 1;
        }

        $stmt->execute();

        if ($stmt->rowCount() !== 1) {
            throw new \Exception('did not update exactly one row');
        }

        $stmt->closeCursor();
    }

    function create($method, $entity, $valueMap) {
        $stmt = $this->pdo->prepare($this->getCustomQuery($method) ?: $this->generateCreateQuery($entity, array_keys($valueMap)));

        foreach ($valueMap as $field => $value) {
            $stmt->bindValue(':' . $field, $value);
        }

        $stmt->execute();
        $id = $this->pdo->lastInsertId();

        $stmt->closeCursor();

        return $id;
    }

    private function generateFindQuery($entity, $fieldList, $multiple) {
        $sql[] = 'SELECT ROWID AS id FROM "' . $this->getTableName($entity) . '" WHERE ';

        foreach ($fieldList as $i => $field) {
            if ($i > 0) {
                $sql[] = ' AND ';
            }

            $sql[] = '"' . $field . '" = :' . $field; // @todo check for unsafe chars (not important here)
        }

        if (count($fieldList) === 0) {
            $sql[] = '1';
        }

        if ( ! $multiple) {
            $sql[] = ' LIMIT 1';
        }

        return join('', $sql);
    }

    private function generateGetQuery($entity, $field) {
        return 'SELECT "' . $field . '" AS v FROM "' . $this->getTableName($entity) . '" WHERE ROWID = :id LIMIT 1';
    }

    private function generateSetQuery($entity, $fieldList) {
        $sql[] = 'UPDATE "' . $this->getTableName($entity) . '" SET ';

        foreach ($fieldList as $i => $field) {
            if ($i > 0) {
                $sql[] = ', ';
            }

            $sql[] = '"' . $field . '" = :' . $field; // @todo check for unsafe chars (not important here)
        }

        $sql[] = ' WHERE ROWID = :id';

        return join('', $sql);
    }

    private function generateCreateQuery($entity, $fieldList) {
        $sql[] = 'INSERT INTO "' . $this->getTableName($entity) . '" (';

        foreach ($fieldList as $i => $field) {
            if ($i > 0) {
                $sql[] = ',';
            }

            $sql[] = '"' . $field . '"';
        }

        $sql[] = ') VALUES (';

        foreach ($fieldList as $i => $field) {
            if ($i > 0) {
                $sql[] = ',';
            }

            $sql[] = ':' . $field;
        }

        $sql[] = ')';

        return join('', $sql);
    }

    private function getTableName($idClass) {
        if ( ! preg_match('/([^\\\\]+)Id$/', $idClass, $idMatch)) {
            throw new \Exception('entity class must be an identity: ' . $idClass);
        }

        return $idMatch[1];
    }

    private function getCustomQuery($fullName) {
        $segments = explode('\\', $fullName);

        $memberName = array_pop($segments);

        $classShortName = array_pop($segments);
        $segments[] = 'sqlite';
        $segments[] = $classShortName . 'SQL';
        $className = join('\\', $segments);

        $sqlConstantName = "$className::$memberName";
        return defined($sqlConstantName) ? constant($sqlConstantName) : null;
    }
}

?>
