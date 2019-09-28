<?php

include_once __DIR__ . '/IfEscapeService.php';

class MySqlEscapeService implements IfEscapeService {
    public function escape($string) {
        return sprintf("'%s'", mysql_real_escape_string($string));
    }
}
