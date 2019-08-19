<?php

include_once __DIR__ . '/sql.php';
include_once __DIR__ . '/../../../bensteffen/bs-php-utils/utils.php';

class SqlQueryFactory {
    public static function makeCreateQuery($entity) {
        $pkQuery = "";
        $primaryKeys = $entity->primaryKeys();
        if (count($primaryKeys) > 0) {
            $pkQuery = sprintf(', PRIMARY KEY (%s)', Sql::Sequence($primaryKeys, function($k) { return Sql::Column($k); })->toQuery());
        }
        // $sqlFormat = "CREATE TABLE IF NOT EXISTS `%s` (%s%s) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
        $sqlFormat = "CREATE TABLE IF NOT EXISTS `%s` (%s%s) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";
        return sprintf(
            $sqlFormat,
            $entity->getName(),
            SqlQueryFactory::fieldSetToCreateString($entity->getFieldSet()),
            $pkQuery
        );
    }

    public static function makeDropQuery($entity) {
        return sprintf("DROP TABLE IF EXISTS %s;", $entity->getName());
    }

    public static function makeSelectQuery($entity, $filter = [], $fieldSelection = [], $distinct = false, $order = [], $pagination = []) {
        if (count($fieldSelection) === 0) {
            $fieldSelection = $entity->fieldNames();
        }
        $select = 'SELECT';
        if ($distinct) {
            $select .= ' DISTINCT';
        }
        $orderQuery = '';
        if (count($order) > 0) {
            $orderQuery = ' '.Sql::Order($order)->toQuery();
        }
        $paginationQuery = '';
        if (count($pagination) > 0) {
            $paginationQuery = ' '.Sql::Pagination($pagination)->toQuery();
        }
        return sprintf("%s %s FROM `%s` %s WHERE %s%s%s",
            $select,
            Sql::Sequence($fieldSelection, function($f) use($entity) {
                return Sql::Column($f, $entity->getName());
            })->toQuery(),
            $entity->getName(),
            SqlQueryFactory::makeReferencedTablesJoinQuery(array_merge($filter['references'])),
            Sql::attachCreator($filter['tree'])->toQuery(),
            $orderQuery,
            $paginationQuery
        );
    }

    public static function makeInsertQuery($entity, $data) {
        return sprintf("INSERT INTO `%s` (%s) VALUES (%s)",
            $entity->getName(),
            Sql::Sequence(array_keys($data)  , function($f) { return Sql::Column($f); })->toQuery(),
            Sql::Sequence(array_values($data), function($v) { return Sql::Value($v);  })->toQuery()
        );
    }

    public static function makeUpdateQuery($entity, $data) {
        $extractor = function($k) use ($data) {
            return Sql::Assignment(Sql::Column($k), Sql::Value($data[$k]));
        };
        $updateKeys = array_keys(extractArray($entity->secondaryKeys(), $data));
        return sprintf("UPDATE `%s` SET %s WHERE %s",
            $entity->getName(),
            Sql::Sequence($updateKeys, $extractor)->toQuery(),
            Sql::Sequence($entity->primaryKeys(), $extractor)->toQuery()
        );
    }

    public static function makeDeleteQuery($entity, $filter) {
        return sprintf("DELETE `%s` FROM `%s` %s WHERE %s",
            $entity->getName(),
            $entity->getName(),
            SqlQueryFactory::makeReferencedTablesJoinQuery(array_merge($filter['references'])),
            Sql::attachCreator($filter['tree'])->toQuery()
        );
    }

    public static function makeJoinQuery($type, $baseEntity, $joinedEntity, $joinConditions, $selection, $filter) {
        $baseTable = $baseEntity->getName();
        $joinedTable = $joinedEntity->getName();

        $selectionSequence = Sql::Sequence(array_merge(
            array_map(function($col) use($baseTable) { return Sql::Column($col, $baseTable); }, $selection[0]),
            array_map(function($col) use($joinedTable) { return Sql::Column($col, $joinedTable); }, $selection[1])
        ));

        return sprintf("SELECT %s FROM `%s` %s JOIN `%s` ON %s WHERE %s",
            $selectionSequence->toQuery(),
            $baseEntity->getName(),
            $type,
            $joinedEntity->getName(),
            Sql::attachCreator($joinConditions)->toQuery(),
            Sql::attachCreator($filter)->toQuery()
        );
    }

    protected static function fieldSetToCreateString($fieldSet) {
        $createStrings = [];
        foreach($fieldSet as $field) {
            $type = $field['type'];
            if ($type === 'object') {
                $type = "longtext";
            }
            if (!array_key_exists('notNull', $field)) {
                $field['notNull'] = true;
            }

            $createString = sprintf("`%s` %s", $field['name'], $type);
            if (array_key_exists('length', $field)) {
                $createString = sprintf("%s(%s)", $createString, $field['length']."");
            }
            if (array_key_exists('autoIncrement', $field) && $field['autoIncrement'] === true) {
                $createString = sprintf("%s %s", $createString, "AUTO_INCREMENT");
            } else {
                if ($field['notNull'] === true) {
                    $createString = sprintf("%s %s", $createString, "NOT NULL");
                }
                if ($field['notNull'] === true || array_key_exists('default', $field)) {
                    $default = SqlQueryFactory::getDefaultValue($field);
                    $createString = sprintf("%s %s %s", $createString, "DEFAULT", jsenc($default));
                }
            }
            array_push($createStrings, $createString);
        }
        return implode(", ",$createStrings);
    }

    protected static function getDefaultValue($field) {
        if (array_key_exists('default', $field)) {
            return $field['default'];
        }
        switch ($field['type']) {
            case 'varchar':
            case 'text':
                return '';
            case 'int':
            case 'smallint':
            case 'decimal':
            case 'float':
            case 'double':
            case 'timestamp':
                return 0;
            case 'boolean':
            case 'bool':
                return false;
            case 'date':
                return '1970-01-01';
            case 'point':
            case 'polygon':
                return '';
        }
    }

    protected static function makeReferencedTablesJoinQuery($references) {
        $joinQueries = [];
        foreach ($references as $refNo => $refChain) {
            $referencingEntity = $refChain[0]['referencingEntity'];
            $fieldChain = [];
            $fieldChain = [$refNo];
            foreach($refChain as $ref) {
                array_push($fieldChain, $ref['referenceField']);
                $as = implode('_', $fieldChain);
                if (array_key_exists('referenceCondition', $ref)) {

                    $on = Sql::attachCreator($ref['referenceCondition']);
                } else {
                    $on = Sql::Condition(
                        Sql::Column($ref['referenceField'], $referencingEntity),
                        'eq', Sql::Column($ref['referenceKeyName'], $as)
                    );
                }

                $query = sprintf("JOIN `%s` AS `%s` ON %s", $ref['referencedEntity'], $as, $on->toQuery());
                array_push($joinQueries, $query);
                $referencingEntity = $as;
            }
        }
        return implode(' ', $joinQueries);
    }
}
