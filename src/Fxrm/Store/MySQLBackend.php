<?php

/**
 * @author Nick Matantsev <nick.matantsev@gmail.com>
 * @copyright Copyright (c) 2013, Nick Matantsev
 */

namespace Fxrm\Store;

class MySQLBackend extends PDOBackend {
    protected function initPDO(\PDO $pdo) {
        $pdo->exec('SET NAMES utf8');
    }

    protected function getImplementationNamespace() {
        return 'mysql';
    }

    protected function externDateTime(\DateTime $value) {
        // Unix timestamp
        return $value->getTimestamp();
    }

    protected function internDateTime($data) {
        // Unix timestamp
        return new \DateTime('@' . (int)$data, new \DateTimeZone('UTC')); // @todo optimize
    }

    protected function generateFindQuery($entity, $fieldList, $multiple) {
        $sql[] = 'SELECT id FROM `' . $entity . '` WHERE ';

        foreach ($fieldList as $i => $field) {
            if ($i > 0) {
                $sql[] = ' AND ';
            }

            $sql[] = '`' . $field . '` = :' . $field; // @todo check for unsafe chars (not important here)
        }

        if (count($fieldList) === 0) {
            $sql[] = '1';
        }

        if ( ! $multiple) {
            $sql[] = ' LIMIT 1';
        }

        return join('', $sql);
    }

    protected function generateGetQuery($entity, $fieldList) {
        return 'SELECT `' . implode('`, `', $fieldList) . '` FROM `' . $entity . '` WHERE id = :id LIMIT 1';
    }

    protected function generateSetQuery($entity, $fieldList) {
        $sql[] = 'UPDATE `' . $entity . '` SET ';

        foreach ($fieldList as $i => $field) {
            if ($i > 0) {
                $sql[] = ', ';
            }

            $sql[] = '`' . $field . '` = :' . $field; // @todo check for unsafe chars (not important here)
        }

        $sql[] = ' WHERE id = :id';

        return join('', $sql);
    }

    protected function generateCreateQuery($entity, $isPredefinedId) {
        return 'INSERT INTO `' . $entity . '` (id) VALUES (' . ($isPredefinedId ? ':id' : 'NULL') . ')';
    }
}

?>
