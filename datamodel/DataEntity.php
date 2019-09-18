<?php

include_once __DIR__ . '/../../../bensteffen/bs-php-utils/utils.php';


class DataEntity {
    protected $name;
    protected $fieldSet;
    protected $dataModel = null;
    protected $queryFactories = [];

    public function __construct($name) {
        $this->name = $name;
        $this->fieldSet = [];
    }

    public function addField($field) {
        array_push($this->fieldSet, $field);
    }

    public function addFields($fields) {
        foreach($fields as $field) {
            $this->addField($field);
        }
    }

    public function getField($fieldName) {
        $field = array_filter($this->fieldSet, function($f) use($fieldName) {
            return $f['name'] === $fieldName;
        });
        return array_shift($field);
    }

    public function setDataModel($dataModel) {
        if ($this->dataModel === null) {
            $this->dataModel = $dataModel;
        }
    }

    public function getName() {
        return $this->name;
    }

    public function getFieldSet() {
        return $this->fieldSet;
    }

    public function fieldNames() {
        return array_map(function($f) { return $f['name']; }, $this->fieldSet);
    }

    public function uniqueKey() {
        $pKeys = $this->primaryKeys();
        if (count($pKeys) === 1) {
            return $pKeys[0];
        }
        return null;
    }

    public function uniqueFilter($keyValue) {
        $keyName = $this->uniqueKey();
        return [$keyName => $keyValue];
    }

    public function primaryKeys() {
        $primaryFields = array_filter($this->fieldSet, function($f) {
            return array_key_exists('primary', $f) && $f['primary'];
        });
        return array_column($primaryFields, 'name');
    }

    public function secondaryKeys() {
        $secondaryFields = array_filter($this->fieldSet, function($f) {
            return !array_key_exists('primary', $f) || !$f['primary'];
        });
        return array_column($secondaryFields, 'name');
    }

    public function primaryKeyData($data) {
        $pKeys = $this->primaryKeys();
        $filter = extractArray($pKeys, $data);
        if (count($filter) === count($pKeys)) { // filter is comlplete
            return $filter;
        }
        return null;
    }

    public function update($updateEvent) {

    }

    public function registerQueryFactory($language, $method, $factory) {
        $factory->setEntity($this);
        $this->queryFactories[$language] = [$method => $factory];
    }

    public function getQueryFactory($language, $method) {
        if (array_key_exists($language, $this->queryFactories)) {
            if (array_key_exists($method, $this->queryFactories[$language])) {
                return $this->queryFactories[$language][$method];
            }
        }
        return null;
    }

    // public function fromJson($fileName) {

    // }
}

