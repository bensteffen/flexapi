<?php

include_once __DIR__ . "/IfDatabseConnection.php";
include_once __DIR__ . "/AbstractSqlConnection.php";
include_once __DIR__ . "/SqlQueryFactory.php";
include_once __DIR__ . '/../../../bensteffen/bs-php-utils/utils.php';

class SqlConnection extends AbstractSqlConnection implements IfDatabseConnection {
    private $dbConnection = null;
    private $dbDatabase = null;

    public function __construct($credentials) {
        if ($credentials) {
            $this->establish($credentials);
        }
    }

    public function establish($credentials) {
        if (!$this->dbConnection) {
            $this->dbDatabase = $credentials['database'];

            $this->dbConnection = new mysqli(
                $credentials['host'],
                $credentials['username'],
                $credentials['password'],
                $credentials['database']
            );
            $this->checkConnection();
        }
    }

    protected function executeQuery($sqlQuery) {
        $result = $this->dbConnection->query($sqlQuery);
        if (!$result) {
            throw(new Exception('Could not execute query --> '.$sqlQuery.' <--": '.$this->dbConnection->error, 500));
        }
        return $result;
    }

    protected function fetchData($result) {
        $output = [];
        if ($result) {
            while($row = $result->fetch_assoc()) {
                array_push($output,$row);
            }
        }
        return $output;
    }

    protected function lastInsertId() {
        return $this->dbConnection->insert_id;
    }

    protected function checkConnection() {
        if ($this->dbConnection === null) {
            throw(new Exception('SqlConnection.checkConnection(): No connection established', 500));
        }

        if ($this->dbConnection->connect_errno) {
            throw(new Exception('SqlConnection.checkConnection(): Failed to connect', 500));
        }
    }
}
