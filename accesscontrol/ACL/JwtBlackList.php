<?php

include_once __DIR__ . '/../../datamodel/DataEntity.php';

class JwtBlackList extends DataEntity {

    public function __construct() {
        parent::__construct('jwtblacklist');
        $this->addField(
            ['name' => 'id', 'type' => 'int', 'notNoll' => true, 'length' => 11, 'primary' => true, 'autoIncrement' => true]
        );
        $this->addField(
            ['name' => 'jwt', 'type' => 'varchar', 'length' => 512, 'notNoll' => true]
        );
        $this->addField(
            ['name' => 'expire', 'type' => 'timestamp', 'notNoll' => true]
        );
    }
}

