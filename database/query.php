<?php

include_once __DIR__ . '/../../bs-php-utils/utils.php';

abstract class QueryElement {
    protected $creator;

    public function setCreator($creator) {
        $this->creator = $creator;
    }

    public abstract function toQuery();
}

interface QueryCreator {
    function makeValue($valueElement);
    function makeColumn($columnElement);
    function makeCondition($conditionElement);
    function makeAssignment($assignmentElement);
    function makeSequence($sequenceElement);
    function makeConditionSequence($conditionSequenceElement);
}

class QueryValue extends QueryElement {
    public $value;

    public function __construct($value) {
        $this->value = $value;
    }

    public function toQuery() {
        return $this->creator->makeValue($this);
    }
}

class QueryColumn extends QueryElement {
    public $name;
    public $table;
    public $database;

    public function __construct($name, $table = null, $database = null) {
        $this->name = $name;
        $this->table = $table;
        $this->database = $database;
    }

    public function toQuery() {
        return $this->creator->makeColumn($this);
    }
}

class QueryCondition extends QueryElement {
    public $itemA;
    public $operator = 'eq';
    public $itemB;

    public function __construct($itemA, $operator, $itemB) {
        if ($itemA && $operator && $itemB) {
            $this->itemA = $itemA;
            $this->operator = $operator;
            $this->itemB = $itemB;
        }
    }

    public function toQuery() {
        $this->itemA->setCreator($this->creator);
        $this->itemB->setCreator($this->creator);
        return $this->creator->makeCondition($this);
    }
}

class QueryConditionSequence extends QueryElement {
    public $operator;
    public $items;

    public function __construct($operator, $items = []) {
        $this->items = $items;
    }

    public function addItem($item) {
        array_push($this->items, $item);
    }

    public function toQuery() {
        foreach ($this->items as $item) {
            $item->setCreator($this->creator);
        }
        return $this->creator->makeConditionSequence($this);
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

?>