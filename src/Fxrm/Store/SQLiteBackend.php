<?php

namespace Fxrm\Store;

class SQLiteBackend extends PDOBackend {
    protected function getImplementationNamespace() {
        return 'sqlite';
    }

    protected function externDateTime(\DateTime $value) {
        // Unix timestamp
        return $value->getTimestamp();
    }

    protected function internDateTime($data) {
        // Unix timestamp
        return new \DateTime('@' . (int)$data);
    }

    protected function generateFindQuery($entity, $fieldList, $multiple) {
        $sql[] = 'SELECT ROWID AS id FROM "' . $entity . '" WHERE ';

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

    protected function generateGetQuery($entity, $field) {
        return 'SELECT "' . $field . '" AS v FROM "' . $entity . '" WHERE ROWID = :id LIMIT 1';
    }

    protected function generateSetQuery($entity, $fieldList) {
        $sql[] = 'UPDATE "' . $entity . '" SET ';

        foreach ($fieldList as $i => $field) {
            if ($i > 0) {
                $sql[] = ', ';
            }

            $sql[] = '"' . $field . '" = :' . $field; // @todo check for unsafe chars (not important here)
        }

        $sql[] = ' WHERE ROWID = :id';

        return join('', $sql);
    }

    protected function generateCreateQuery($entity) {
        return 'INSERT INTO "' . $entity . '" (ROWID) VALUES (NULL)';
    }
}

?>
