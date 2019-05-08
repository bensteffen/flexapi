<?php

include_once __DIR__ . '/../../datamodel/DataEntity.php';

class Role extends DataEntity {

    public function __construct() {
        parent::__construct('role');
        $this->addFields([
            ['name' => 'name'       , 'type' => 'varchar', 'length' => 32, 'primary' => true],
            ['name' => 'needsPermission', 'type' => 'varchar', 'length' => 256]
        ]);
    }
}

