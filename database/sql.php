<?php

include_once __DIR__ . '/../../../bensteffen/bs-php-utils/utils.php';
include_once __DIR__ . '/query.php';
include_once __DIR__ . '/../services/escape/MySqlEscapeService.php';

class SqlCreator implements QueryCreator {
    public $useTics = true;
    private $escapeService = null;

    public function __construct($escapeService = null) {
        if (!$escapeService) {
            $escapeService = new MySqlEscapeService();
        }
        $this->escapeService = $escapeService;
    }

    public function makeValue($queryValue) {
        $value = $queryValue->value;
        if ($queryValue->type == 'point') {
          $x = $this->escapeService->escape($value[0]);
          $y = $this->escapeService->escape($value[1]);
          return sprintf('ST_GeomFromText("POINT(%f %f)")', $x, $y);
        }
        if ($queryValue->type == 'object') {
            $str = json_encode($value,JSON_UNESCAPED_UNICODE);
        }
        if ($queryValue->plain) {
            $str = $queryValue->value;
        } else {
            $str = $this->escapeService->escape($value);
        }
        return $str;
    }

    public function makeColumn($column) {
        $this->throwExceptionOnInvalidName($column);

        $n = $this->addTics($column->name);
        $t = $this->addTics($column->table);
        $d = $this->addTics($column->database);

        $a = $column->alias;
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
        if ($column->type=='point') {
          return sprintf('ST_X(%s) AS  %s, ST_Y(%s) AS  %s', $str, $this->addTics($column->name.'_lon'), $str, $this->addTics($column->name.'_lat'));
        } else {
          if ($a) {
              $str .= ' AS '.$a;
          }
          return $str;
        }
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

    function makeOrderDirection($orderDirectionElement) {
        $map = ['ascending' => 'ASC', 'descending' => 'DESC'];
        return $map[$orderDirectionElement->direction];
    }

    function makeOrderItem($orderItemElement) {
        return sprintf('%s %s', $orderItemElement->column->toQuery(), $orderItemElement->direction->toQuery());
    }

    public function makeOrder($order) {
        return sprintf('ORDER BY %s', $order->items->toQuery());
    }

    public function makePagination($pagination) {
        return sprintf('LIMIT %s OFFSET %s', $pagination->size, $pagination->offset);
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

    protected static function throwExceptionOnInvalidName($column) {
        if (!SqlCreator::isValidName($column->name)) {
            throw(new Exception(sprintf('Invalid field name "%s"', $column->name), 500));
        }
        if (!SqlCreator::isValidName($column->table)) {
            throw(new Exception(sprintf('Invalid table name "%s"', $column->table), 500));
        }
        if (!SqlCreator::isValidName($column->database)) {
            throw(new Exception(sprintf('Invalid database name "%s"', $column->database), 500));
        }
    }

    protected static function isValidName($name) {
        /**
         * Filtert aus $name gültigen String heraus, d.h. nur Buchstaben,
         * Unterstriche und Ziffern, kürzer 256 Zeichen, keine Ziffer am 
         * Anfang. Falls nach dem Filtern noch Zeichen verbleiben ( strlen > 0 )
         * enthält der Name ungültige Zeichen oder ist zu lang.
         */
        return strlen(preg_filter('/[_a-zA-Z][_a-zA-Z0-9]{0,255}/', '', $name)) === 0;
    }
}

class Sql {
    protected static $sqlCreator = null;

    public static function Value($value, $type) {
        return Sql::attachCreator(new QueryValue($value, $type));
    }

    public static function Column($name = "*", $table = null, $database = null, $type =null, $alias = null) {
        $qc=new QueryColumn($name, $table, $database);
        if ($type) {
          $qc->type=$type;
        }
        if ($alias) {
          $qc->alias=$alias;
        }
        return Sql::attachCreator($qc);
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

    public static function Order($items) {
        $items = array_map(function($item) {
            $column = new QueryColumn($item['by']);
            $direction = new QueryOrderDirection($item['direction']);
            return new QueryOrderItem($column, $direction);
        }, $items);
        return Sql::attachCreator(new QueryOrder(new QuerySequence($items)));
    }

    public static function Pagination($pagination) {
        return Sql::attachCreator(new QueryPagination($pagination['size'], $pagination['offset']));
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
