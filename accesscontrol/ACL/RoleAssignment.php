<?php

include_once __DIR__ . '/../../datamodel/DataEntity.php';

class RoleAssignment extends DataEntity {

    public function __construct() {
        parent::__construct('roleassignment');
        $this->addFields([
            ['name' => 'role', 'type' => 'varchar', 'length' => 32, 'primary' => true],
            ['name' => 'user', 'type' => 'varchar', 'length' => 32, 'primary' => true],
        ]);
    }
    
}