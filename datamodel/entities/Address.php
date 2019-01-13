<?php

include_once __DIR__ . '/../DataEntity.php';

class Address extends DataEntity {

    public function __construct() {
        parent::__construct('address');
        $this->addField(
            ['name' => 'id', 'type' => 'int', 'notNoll' => true, 'length' => 11, 'primary' => true, 'autoIncrement' => true]
        );
        $this->addField(
            ['name' => 'city', 'type' => 'varchar', 'notNoll' => true, 'length' => 32]
        );
        $this->addField(
            ['name' => 'street', 'type' => 'varchar', 'notNoll' => true, 'length' => 32]
        );
        $this->addField(
            ['name' => 'zip', 'type' => 'int', 'notNoll' => true]
        );
    }
}

?>
