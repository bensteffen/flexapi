<?php

include_once __DIR__ . "/IfDatabseConnection.php";
include_once __DIR__ . "/AbstractSqlConnection.php";
include_once __DIR__ . "/SqlQueryFactory.php";
include_once __DIR__ . '/../../bs-php-utils/utils.php';

class PdoPreparedConnection extends AbstractSqlConnection implements IfDatabseConnection {
    private $pdo = null;

    public function __construct($credentials) {
        if ($credentials) {
            $this->establish($credentials);
        }
    }

    public function establish($credentials) {
        if (!$this->pdo) {
            $dsn = $credentials['driver'].':host='.$credentials['host'];
            $dsn .= ';dbname='.$credentials['database'];

            $options = [
                PDO::ATTR_EMULATE_PREPARES   => false, // turn off emulation mode for "real" prepared statements
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
            ];

            $this->pdo = new PDO($dsn, $credentials['username'], $credentials['password'], $options);
            $this->checkConnection();
        }
    }

    protected function executeQuery($query) {
        $statement = null;
        try {
            $statement = $this->pdo->prepare($query);
            $statement->execute();
        } catch (PDOException $exc) {
            throw(new Exception("Error executing query --> $query <--: ".$exc->getMessage(), 500));
        }
        return $statement;
    }

    protected function fetchData($result) {
        return $result->fetchAll();
    }

    protected function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    protected function checkConnection() {
        if ($this->pdo === null) {
            throw(new Exception('SqlConnection.checkConnection(): No connection established', 500));
        }
    }
}
