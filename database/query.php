<?php

include_once __DIR__ . '/../../../bensteffen/bs-php-utils/utils.php';

abstract class QueryElement {
    protected $creator;

    public function setCreator($creator) {
        $this->creator = $creator;
        return $this;
    }

    public abstract function toQuery();
}

interface QueryCreator {
    function makeValue($valueElement);
    function makeColumn($columnElement);
    function makeCondition($conditionElement);
    function makeAssignment($assignmentElement);
    function makeSequence($sequenceElement);
    function makeAnd($andElement);
    function makeOr($orElement);
    function makeNot($notElement);
    function makeGroup($groupElement);
    function makeOrderDirection($orderDirectionElement);
    function makeOrderItem($orderItemElement);
    function makeOrder($orderElement);
    function makePagination($paginationElement);
    function makeVoid($voidElement);
}

class QueryValue extends QueryElement {
    public $value;
    public $type;
    public $plain = false;
    public function __construct($value, $type='') {
        $this->value = $value;
        if ($type == '') {
            if (is_string($value)) {
                $type = 'string';
            } elseif (is_int($value)) {
                $type = 'int';
            }
        }
        if ($value !== null && $type == '') {
            trigger_error("Value ohne type", E_USER_WARNING);
        }
        $this->type = $type;
    }

    public function toQuery() {
        return $this->creator->makeValue($this);
    }
}

class QueryColumn extends QueryElement {
    public $name;
    public $table;
    public $database;
    public $references;
    public $type;
    public $alias;

    public function __construct($name, $table = null, $database = null, $references = []) {
        $this->name = $name;
        $this->table = $table;
        $this->database = $database;
        $this->references = $references;
    }

    public function toQuery() {
        return $this->creator->makeColumn($this);
    }
}

class QueryCondition extends QueryElement {
    public $itemA;
    public $operator;
    public $itemB;

    public function __construct($itemA, $itemB, $operator = 'eq') {
        $this->itemA = $itemA;
        $this->operator = $operator;
        $this->itemB = $itemB;
    }

    public function toQuery() {
        $this->itemA->setCreator($this->creator);
        $this->itemB->setCreator($this->creator);
        return $this->creator->makeCondition($this);
    }
}

class QueryAnd extends QueryElement {
    public $itemA;
    public $itemB;

    public function __construct($itemA, $itemB) {
        $this->itemA = $itemA;
        $this->itemB = $itemB;
    }

    public function toQuery() {
        $this->itemA->setCreator($this->creator);
        $this->itemB->setCreator($this->creator);
        return $this->creator->makeAnd($this);
    }
}

class QueryOr extends QueryElement {
    public $itemA;
    public $itemB;

    public function __construct($itemA, $itemB) {
        $this->itemA = $itemA;
        $this->itemB = $itemB;
    }

    public function toQuery() {
        $this->itemA->setCreator($this->creator);
        $this->itemB->setCreator($this->creator);
        return $this->creator->makeOr($this);
    }
}

class QueryNot extends QueryElement {
    public $item;

    public function __construct($item) {
        $this->item = $item;
    }

    public function toQuery() {
        $this->item->setCreator($this->creator);
        return $this->creator->makeNot($this);
    }
}

class QueryGroup extends QueryElement {
    public $item;

    public function __construct($item) {
        $this->item = $item;
    }

    public function toQuery() {
        $this->item->setCreator($this->creator);
        return $this->creator->makeGroup($this);
    }
}

class QueryVoid extends QueryElement {
    public function toQuery() {
        return $this->creator->makeVoid($this);
    }
}

class QueryAssignment extends QueryElement {
    public $column;
    public $value;

    public function __construct($column, $value) {
        $this->column = $column;
        $this->value = $value;
    }

    public function toQuery() {
        $this->column->setCreator($this->creator);
        $this->value->setCreator($this->creator);
        return $this->creator->makeAssignment($this);
    }
}

class QuerySequence extends QueryElement {
    public $items = [];

    public function __construct($items) {
        if ($items) {
            $this->items = $items;
        }
    }

    public function addItem($item) {
        array_push($this->items, $item);
    }

    public function toQuery() {
        foreach ($this->items as $item) {
            $item->setCreator($this->creator);
        }
        return $this->creator->makeSequence($this);
    }
}

class QueryOrderDirection extends QueryElement {
    public $direction;

    public function __construct($direction) {
        if ($direction) {
            $this->direction = $direction;
        }
    }

    public function toQuery() {
        $this->setCreator($this->creator);
        return $this->creator->makeOrderDirection($this);
    }
}

class QueryOrderItem extends QueryElement {
    public $column;
    public $direction;

    public function __construct($column, $direction) {
        $this->column = $column;
        $this->direction = $direction;
    }

    public function toQuery() {
        $this->column->setCreator($this->creator);
        $this->direction->setCreator($this->creator);
        return $this->creator->makeOrderItem($this);
    }
}

class QueryOrder extends QueryElement {
    public $items;

    public function __construct($items) {
        if ($items) {
            $this->items = $items;
        }
    }

    public function toQuery() {
        $this->items->setCreator($this->creator);
        return $this->creator->makeOrder($this);
    }
}

class QueryPagination extends QueryElement {
    public $size;
    public $offset;

    public function __construct($size, $offset) {
        $this->size = $size;
        $this->offset = $offset;
    }

    public function toQuery() {
        return $this->creator->makePagination($this);
    }
}
