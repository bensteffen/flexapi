<?php

include_once __DIR__ . '/../../utils/utils.php';
include_once __DIR__ . '/query.php';


class SqlCreator implements QueryCreator {
    public $useTics = true;

    public function makeValue($queryValue) {
        $str = jsenc($queryValue->value);
        if (is_array($queryValue->value)) {
            $str = jsenc($str);
        }
        return $str;  
    }

    public function makeColumn($column) {
        $n = $this->addTics($column->name);
        $t = $this->addTics($column->table);
        $d = $this->addTics($column->database);

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
        } else {
            $operatorMap = ['eq' => '=', 'gr' => '>', 'sm' => '<', 'greq' => '>=', 'smeq' => '<='];
            return sprintf("%s %s %s", $cond->itemA->toQuery(), $operatorMap[$op], $cond->itemB->toQuery());
        }
    }

    public function makeAssignment($a) {
        return sprintf("%s = %s", $a->column->toQuery(), $a->value->toQuery());
    }

    public function makeSequence($seq) {
        return implode(', ', array_map(function($item) { return $item->toQuery(); } , $seq->items));
    }

    public function makeConditionSequence($seq) {
        $str = "";
        foreach ($seq->conditions as $i => $item) {
            if ($i === 0) {
                $str .= $item['condition']->toQuery();
            } else {
                $str .= sprintf(" %s %s", $item['concatOperator'], $item['condition']->toQuery());
            }
        }
        return $str;
    }

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
        return Sql::attachSqlCreator(new QueryValue($value));
    }

    public static function Column($name = "*", $table = null, $database = null) {
        return Sql::attachSqlCreator(new QueryColumn($name, $table, $database));
    }

    public static function Assignment($column, $value) {
        return Sql::attachSqlCreator(new QueryAssignment($column, $value));
    }

    public static function Sequence($items, $converter = null) {
        if ($converter) {
            $items = array_map($converter, $items);
        }
        return Sql::attachSqlCreator(new QuerySequence($items));
    }

    public static function Condition($itemA, $operator, $itemB) {
        return Sql::attachSqlCreator(new QueryCondition($itemA, $operator, $itemB));
    }

    public static function ConditionSequence($items, $converter = null) {
        if ($converter) {
            $items = array_map($converter, $items);
        }
        return Sql::attachSqlCreator(new QueryConditionSequence($items));
    }

    protected static function attachSqlCreator($queryElement) {
        if (Sql::$sqlCreator === null) {
            Sql::$sqlCreator = new SqlCreator();
        }
        $queryElement->setCreator(Sql::$sqlCreator);
        return $queryElement;
    }
}

?>
