<?php

include_once __DIR__ . '/../../bs-php-utils/utils.php';
include_once __DIR__ . '/query.php';


class SqlCreator implements QueryCreator {
    public $useTics = true;

    public function makeValue($queryValue) {
        if ($queryValue->plain) {
            $str = $queryValue->value;
        } else {
            $str = jsenc($queryValue->value);
            if (is_array($queryValue->value)) {
                $str = jsenc($str);
            }
        }
        return $str;  
    }

    public function makeColumn($column) {
        $n = $this->addTics($column->name);
        $t = $this->addTics($column->table);
        $d = $this->addTics($column->database);
        $refs = $column->references;
        if (count($refs) > 0) {
            foreach($refs as $refId => $ref) {
                $fieldChain = array_map(function($r) { return $r['referenceField']; }, $ref);
                $t = implode('_', array_merge([$refId], $fieldChain));
            }
            $t = $this->addTics($t);
        }

        $str = $n;
        if ($t) {
            $str = "$t.$str";
            if ($d) {
                $str = "$d.$str";
            }
        }
        return $str;
    }

    public function makeCondition($cond) {
        $op = strtolower($cond->operator);
        if ($op === 'not') {
            return sprintf("NOT %s = %s", $cond->itemA->toQuery(), $cond->itemB->toQuery());
        } elseif ($op === 'con') {
            $cond->itemB->plain = true;
            return sprintf('%s LIKE "%%%s%%"', $cond->itemA->toQuery(), $cond->itemB->toQuery());
        } else {
            $operatorMap = ['eq' => '=', 'gr' => '>', 'ls' => '<', 'ge' => '>=', 'le' => '<='];
            return sprintf("%s %s %s", $cond->itemA->toQuery(), $operatorMap[$op], $cond->itemB->toQuery());
        }
    }

    public function makeAssignment($a) {
        return sprintf("%s = %s", $a->column->toQuery(), $a->value->toQuery());
    }

    public function makeSequence($seq) {
        return implode(', ', array_map(function($item) { return $item->toQuery(); } , $seq->items));
    }

    public function makeAnd($and) {
        return sprintf("%s AND %s", $and->itemA->toQuery(), $and->itemB->toQuery());
    }

    public function makeOr($or) {
        return sprintf("%s OR %s", $or->itemA->toQuery(), $or->itemB->toQuery());
    }

    public function makeNot($not) {
        return sprintf("NOT %s", $not->item->toQuery());
    }

    public function makeGroup($group) {
        return sprintf("(%s)", $group->item->toQuery());
    }

    public function makeVoid($void) {
        return sprintf("1");
    }

    // public function makeConditionSequence($seq) {
    //     $operatorMap = ['and' => ' AND ', 'or' => ' OR '];
    //     $queries = array_map(function($item) { return $item->toQuery(); }, $seq->items);
    //     return implode($operatorMap[$seq->operator], $queries);
    // }

    protected function addTics($s) {
        if ($s && $this->useTics && $s !== '*') {
            $s = sprintf("`%s`", $s);
        };
        return $s;
    }
}

class Sql {
    protected static $sqlCreator = null;

    public static function Value($value) {
        return Sql::attachCreator(new QueryValue($value));
    }

    public static function Column($name = "*", $table = null, $database = null) {
        return Sql::attachCreator(new QueryColumn($name, $table, $database));
    }

    public static function Assignment($column, $value) {
        return Sql::attachCreator(new QueryAssignment($column, $value));
    }

    public static function Sequence($items, $converter = null) {
        if ($converter) {
            $items = array_map($converter, $items);
        }
        return Sql::attachCreator(new QuerySequence($items));
    }

    public static function Condition($itemA, $operator, $itemB) {
        return Sql::attachCreator(new QueryCondition($itemA, $itemB, $operator));
    }

    public static function AndOperation($itemA, $itemB) {
        return Sql::attachCreator(new QueryAnd($itemA, $itemB));
    }

    public static function OrOperation($itemA, $itemB) {
        return Sql::attachCreator(new QueryOr($itemA, $itemB));
    }

    public static function NotOperation($item) {
        return Sql::attachCreator(new QueryNot($item));
    }

    public static function Group($item) {
        return Sql::attachCreator(new QueryGroup($item));
    }

    // public static function ConditionSequence($items, $converter = null) {
    //     if ($converter) {
    //         $items = array_map($converter, $items);
    //     }
    //     return Sql::attachCreator(new QueryConditionSequence($items));
    // }

    public static function attachCreator($queryElement) {
        if (Sql::$sqlCreator === null) {
            Sql::$sqlCreator = new SqlCreator();
        }
        $queryElement->setCreator(Sql::$sqlCreator);
        return $queryElement;
    }
}

