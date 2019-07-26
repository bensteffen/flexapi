<?php

abstract class AbstractSqlConnection {

    public abstract function establish($credentials);
    protected abstract function executeQuery($sqlQuery);
    protected abstract function fetchData($queryResult);
    protected abstract function lastInsertId();

    public function createEntity($entity) {
        $this->checkConnection();
        $createQuery = SqlQueryFactory::makeCreateQuery($entity);
        // echo "<br>create query: $createQuery<br>";
        $this->executeQuery($createQuery);
        // if (!$this->executeQuery($createQuery)) {
        //     throw(new Exception(sprintf('SqlConnection.createTable(): Could not create table for entity "%s"', $entity->getName()), 500));
        // }
    }

    public function clearEntity($entity) {
        $clearQuery = SqlQueryFactory::makeDropQuery($entity);
        // echo "<br>clear query: $clearQuery<br>";
        $this->executeQuery($clearQuery);
    }

    public function insertIntoDatabase($entity, $data){
        $this->checkConnection();
        $insertQuery = SqlQueryFactory::makeInsertQuery($entity, $this->prepareData($entity, $data));
        // echo "<br>insert query: $insertQuery<br>";
        $this->executeQuery($insertQuery);
        return $this->lastInsertId();
    }

    public function readFromDatabase($entity, $filter, $fieldSelection, $distinct = true, $order = [], $pagination = []) {
        $this->checkConnection();
        $selectQuery = SqlQueryFactory::makeSelectQuery($entity, $filter, $fieldSelection, $distinct, $order, $pagination);
        // echo "<br>select query: $selectQuery<br>";
        return $this->finishData($entity, $this->fetchData($this->executeQuery($selectQuery)));
    }

    public function updateDatabase($entity, $data){
        $this->checkConnection();
        $updateQuery = SqlQueryFactory::makeUpdateQuery($entity, $this->prepareData($entity, $data));
        // echo "<br>update query: $updateQuery<br>";
        $this->executeQuery($updateQuery);
    }

    public function deleteFromDatabase($entity, $filter){
        $this->checkConnection();
        $deleteQuery = SqlQueryFactory::makeDeleteQuery($entity, $filter);
        // echo "<br>delete query: $deleteQuery<br>";
        $this->executeQuery($deleteQuery);
    }

    public function prepareData($entity, $data) {
        $objectKeys = extractByKey('name', array_filter($entity->getFieldSet(), function($f) {
            return $f['type'] === 'object';
        }));
        foreach(array_intersect($objectKeys, array_keys($data)) as $key) {
            $data[$key] = jsenc($data[$key]);
        }
        return $data;
    }
    
    public function finishData($entity, $data) {
        $objectKeys = extractByKey('name', array_filter($entity->getFieldSet(), function($f) {
            return $f['type'] === 'object';
        }));
        foreach($data as $i => $d) {
            foreach(array_intersect($objectKeys, array_keys($d)) as $key) {
                $data[$i][$key] = (array) json_decode($d[$key]);
            }
        }
        $boolKeys = extractByKey('name', array_filter($entity->getFieldSet(), function($f) {
            return $f['type'] === 'bool' || $f['type'] === 'boolean';
        }));
        foreach($data as $i => $d) {
            foreach(array_intersect($boolKeys, array_keys($d)) as $key) {
                $v = false;
                if ($data[$i][$key]) { $v = true; }
                $data[$i][$key] = $v;
            }
        }
        $numKeys = extractByKey('name', array_filter($entity->getFieldSet(), function($f) {
            return in_array($f['type'], ['int', 'smallint', 'decimal', 'float', 'double']);
        }));
        foreach($data as $i => $d) {
            foreach(array_intersect($numKeys, array_keys($d)) as $key) {
                $data[$i][$key] = json_decode($data[$i][$key], JSON_NUMERIC_CHECK);
            }
        }
        return $data;
    }
}