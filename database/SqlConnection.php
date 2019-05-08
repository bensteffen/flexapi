<?php

include_once __DIR__ . "/IfDatabseConnection.php";
include_once __DIR__ . "/SqlQueryFactory.php";
include_once __DIR__ . '/../../bs-php-utils/utils.php';

class SqlConnection implements IfDatabseConnection {
    private $dbConnection = null;

    public function __construct($credentials) {
        if ($credentials) {
            $this->establish($credentials);
        }
    }

    public function establish($credentials) {
        if (!$this->dbConnection) {
            $this->dbConnection = new mysqli(
                $credentials['host'],
                $credentials['username'],
                $credentials['password'],
                $credentials['database']
            );
            $this->checkConnection();
        }
    }

    public function createEntity($entity) {
        $this->createTable($entity);
    }

    public function insertIntoDatabase($entity, $data){
        $this->checkConnection();
        $insertQuery = SqlQueryFactory::makeInsertQuery($entity, $this->prepareData($entity, $data));
        // echo "<br>insert query: $insertQuery<br>";
        $this->executeQuery($insertQuery);
        return $this->dbConnection->insert_id;
    }

    public function readFromDatabase($entity, $filter, $fieldSelection, $entityReferences = [], $distinct = true) {
        $this->checkConnection();
        $selectQuery = SqlQueryFactory::makeSelectQuery($entity, $filter, $fieldSelection, $distinct);
        // echo "<br>select query: $selectQuery<br>";
        return $this->finishData($entity, $this->fetchData($this->executeQuery($selectQuery)));
    }

    public function updateDatabase($entity, $data){
        $this->checkConnection();
        $updateQuery = SqlQueryFactory::makeUpdateQuery($entity, $this->prepareData($entity, $data));
        // echo "<br>update query: $updateQuery<br>";
        $this->executeQuery($updateQuery);
    }

    protected function createTable($entity) {
        $this->checkConnection();
        $createQuery = SqlQueryFactory::makeCreateQuery($entity);
        // echo "<br>create query: $createQuery<br>";
        if (!$this->executeQuery($createQuery)) {
            throw(new Exception(sprintf('SqlConnection.createTable(): Could not create table for entity "%s"', $entity->getName()), 500));
        }
    }

    public function deleteFromDatabase($entity, $filter){
        $this->checkConnection();
        $deleteQuery = SqlQueryFactory::makeDeleteQuery($entity, $filter);
        // echo "<br>delete query: $deleteQuery<br>";
        $this->executeQuery($deleteQuery);
    }

    public function joinTables($type, $entity1, $entity2, $condition, $selection, $filter) {
        $this->checkConnection();
        $joinQuery = SqlQueryFactory::makeJoinQuery($type, $entity1, $entity2, $condition, $selection, $filter);
        // echo "<br>join query: $joinQuery<br>";
        $data = $this->fetchData($this->executeQuery($joinQuery));
        $data = $this->finishData($entity1, $data); 
        $data = $this->finishData($entity2, $data); 
        return $data;
    }

    function prepareData($entity, $data) {
        $objectKeys = extractByKey('name', array_filter($entity->getFieldSet(), function($f) {
            return $f['type'] === 'object';
        }));
        foreach(array_intersect($objectKeys, array_keys($data)) as $key) {
            $data[$key] = jsenc($data[$key]);
        }
        return $data;
    }

    function finishData($entity, $data) {
        $objectKeys = extractByKey('name', array_filter($entity->getFieldSet(), function($f) {
            return $f['type'] === 'object';
        }));
        foreach($data as $i => $d) {
            foreach(array_intersect($objectKeys, array_keys($d)) as $key) {
                $data[$i][$key] = (array) json_decode($d[$key]);
            }
        }
        $objectKeys = extractByKey('name', array_filter($entity->getFieldSet(), function($f) {
            return $f['type'] === 'bool' || $f['type'] === 'boolean';
        }));
        foreach($data as $i => $d) {
            foreach(array_intersect($objectKeys, array_keys($d)) as $key) {
                $v = false;
                if ($data[$i][$key]) { $v = true; }
                $data[$i][$key] = $v;
            }
        }
        return $data;
    }

    private function executeQuery($sqlQuery) {
        $result = $this->dbConnection->query($sqlQuery);
        if (!$result) {
            throw(new Exception($this->dbConnection->error, 404));
        }
        return $result;
    }

    protected function fetchData($result) {
        if ($result && $result->num_rows > 0) {
            $output = [];
            while($row = $result->fetch_assoc()) {
                array_push($output,$row);
            }
        } else {
            $output = [];
        }
        return $output;
    }

    protected function checkConnection() {
        if ($this->dbConnection === null) {
            throw(new Exception('SqlConnection.checkConnection(): No connection established', 500));
        }
    }

    protected function transformByType($entity, $data, $typeName, $transformation) {
        $objectKeys = extractByKey('name', array_filter($entity->getFieldSet(), function($f) {
            return $f['type'] === $typeName;
        }));
        foreach($data as $i => $d) {
            foreach(array_intersect($objectKeys, array_keys($d)) as $key) {
                $data[$i][$key] = $transformation($data[$i][$key]);
            }
        }
        return $data;
    }
}

