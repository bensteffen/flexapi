<?php

include_once __DIR__ . '/IfEscapeService.php';

class PdoEscapeService implements IfEscapeService {
    private $pdo;

    public function __construct($pdo) {
        $this->$pdo = $pdo;
    }

    public function escape($string) {
        return $this->pdo->quote($string);
    }
}
