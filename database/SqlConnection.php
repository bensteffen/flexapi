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

    protected function executeQuery($sqlQuery) {
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

    protected function lastInsertId() {
        return $this->dbConnection->insert_id;
    }

    protected function checkConnection() {
        if ($this->dbConnection === null) {
            throw(new Exception('SqlConnection.checkConnection(): No connection established', 500));
        }
    }
}
