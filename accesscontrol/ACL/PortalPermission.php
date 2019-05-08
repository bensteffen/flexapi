<?php

include_once __DIR__ . '/../../datamodel/DataEntity.php';

class PortalPermission extends DataEntity {

    public function __construct() {
        parent::__construct('portalpermission');
        $this->addFields([
            ['name' => 'role', 'type' => 'varchar', 'length' => 32, 'primaryKey' => true],
            ['name' => 'register', 'type' => 'boolean', 'default' => false],
            ['name' => 'unregister', 'type' => 'boolean', 'default' => false],
            ['name' => 'unregisterOthers', 'type' => 'boolean', 'default' => false],
            ['name' => 'publish', 'type' => 'boolean', 'default' => false]
        ]);
    }

}
