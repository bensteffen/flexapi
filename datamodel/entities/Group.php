<?php

include_once __DIR__ . '/../DataEntity.php';

class Group extends DataEntity {

    public function __construct() {
        parent::__construct('group');

        $this->addField(
            ['name' => 'id', 'type' => 'int', 'notNoll' => true, 'length' => 11, 'primary' => true, 'autoIncrement' => true]
        );
        $this->addField(
            ['name' => 'name', 'type' => 'varchar', 'notNoll' => true, 'length' => 32]
        );
    }
}

?>
