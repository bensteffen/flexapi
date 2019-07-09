<?php

include_once __DIR__ . "/IfDatabseConnection.php";
include_once __DIR__ . "/AbstractSqlConnection.php";
include_once __DIR__ . "/SqlQueryFactory.php";
include_once __DIR__ . '/../../bs-php-utils/utils.php';

class SqlConnection extends AbstractSqlConnection implements IfDatabseConnection {
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
        return $this->dbConnection->insert_id;
    }

    public function readFromDatabase($entity, $filter, $fieldSelection, $entityReferences = [], $distinct = true, $order = [], $pagination = []) {
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

    private function executeQuery($sqlQuery) {
        $result = $this->dbConnection->query($sqlQuery);
        if (!$result) {
            throw(new Exception('Could not execute query --> '.$sqlQuery.' <--": '.$this->dbConnection->error, 500));
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
}
