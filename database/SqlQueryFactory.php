<?php

include_once __DIR__ . '/sql.php';
include_once __DIR__ . '/../../utils/utils.php';

class SqlQueryFactory {
    public static function makeCreateQuery($entity) {
        $sqlFormat = "CREATE TABLE IF NOT EXISTS `%s` (%s, PRIMARY KEY (%s) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
        return sprintf(
            $sqlFormat, 
            $entity->getName(),
            SqlQueryFactory::fieldSetToCreateString($entity->getFieldSet()),
            Sql::Sequence($entity->primaryKeys(), function($k) { return Sql::Column($k); })->toQuery()
        );
    }

    public static function makeSelectQuery($entity, $filter = [], $fieldSelection = []) {
        if (count($fieldSelection) === 0) {
            $fieldSelection = ['*'];
        }
        return sprintf("SELECT %s FROM `%s` WHERE %s",
            Sql::Sequence($fieldSelection, function($f) { return Sql::Column($f); })->toQuery(),
            $entity->getName(),
            SqlQueryFactory::filter2conditionString($filter)
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
        return sprintf("DELETE FROM `%s` WHERE %s",
            $entity->getName(),
            SqlQueryFactory::filter2conditionString($filter)
        );
    }

    public static function makeJoinQuery($type, $baseEntity, $joinedEntity, $joinConditions, $selection, $filter) {
        $baseTable = $baseEntity->getName();
        $joinedTable = $joinedEntity->getName();

        $selectionSequence = Sql::Sequence(array_merge(
            array_map(function($col) use($baseTable) { return Sql::Column($col, $baseTable); }, $selection[0]),
            array_map(function($col) use($joinedTable) { return Sql::Column($col, $joinedTable); }, $selection[1])
        ));

        $joinConditions->setCreator(new SqlCreator());

        return sprintf("SELECT %s FROM `%s` %s JOIN `%s` ON %s WHERE %s",
            $selectionSequence->toQuery(),
            $baseEntity->getName(),
            $type,
            $joinedEntity->getName(),
            $joinConditions->toQuery(),
            SqlQueryFactory::filter2conditionString($filter)
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
            if ($field['notNull'] === true) {
                $createString = sprintf("%s %s", $createString, "NOT NULL");
            }
            if (array_key_exists('autoIncrement', $field) && $field['autoIncrement'] === true) {
                $createString = sprintf("%s %s", $createString, "AUTO_INCREMENT");
            }
            array_push($createStrings, $createString);
        }
        return implode(", ",$createStrings);
    }

    protected static function filter2conditionString($filters) {
        if (count($filters) === 0) {
            return "1";
        }
        return Sql::ConditionSequence($filters, function($f) {
            return [
                'concatOperator' => $f['concatOperator'],
                'condition' => Sql::Condition(Sql::Column($f['field'], $f['entityName']), $f['operator'], Sql::Value($f['value']))
            ];
        })->toQuery();
    }
}

?>
