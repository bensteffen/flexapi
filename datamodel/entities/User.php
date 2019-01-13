<?php

include_once __DIR__ . '/../DataEntity.php';

class User extends DataEntity {

    public function __construct() {
        parent::__construct('user');
        $this->addField(
            ['name' => 'name', 'type' => 'varchar', 'notNoll' => true, 'length' => 32, 'primary' => true]
        );
        $this->addField(
            ['name' => 'password', 'type' => 'varchar', 'notNoll' => true, 'length' => 16]
        );
    }
}

?>
