<?php

include_once __DIR__ . '/DataEntity.php';

class IdEntity extends DataEntity {

    public function __construct($entityName) {
        parent::__construct($entityName);

        $this->addField(['name' => 'id', 'type' => 'int', 'notNoll' => true,
            'primary' => true, 'autoIncrement' => true]
        );
    }
}

