<?php

include_once __DIR__ . '/../DataEntity.php';

class Employee extends DataEntity {

    public function __construct() {
        parent::__construct('employee');

        $this->addField(
            ['name' => 'id', 'type' => 'int', 'notNoll' => true, 'length' => 11, 'primary' => true, 'autoIncrement' => true]
        );
        $this->addField(
            ['name' => 'name', 'type' => 'varchar', 'notNoll' => true, 'length' => 32]
        );
        $this->addField(
            ['name' => 'age', 'type' => 'int', 'notNoll' => true]
        );
        $this->addField(
            ['name' => 'homeAddress', 'type' => 'int', 'notNoll' => true]
        );
        $this->addField(
            ['name' => 'groupId', 'type' => 'int', 'notNoll' => true]
        );
    }
}

?>
