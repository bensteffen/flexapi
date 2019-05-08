<?php

include_once __DIR__ . '/../../datamodel/DataEntity.php';

class CrudPermission extends DataEntity {

    public function __construct() {
        parent::__construct('crudpermission');
        $this->addFields([
            ['name' => 'role', 'type' => 'varchar', 'length' => 32, 'primaryKey' => true],
            ['name' => 'entity', 'type' => 'varchar', 'length' => 32, 'primaryKey' => true],
            ['name' => 'create', 'type' => 'boolean', 'default' => false],
            ['name' => 'read', 'type' => 'boolean', 'default' => false],
            ['name' => 'update', 'type' => 'boolean', 'default' => false],
            ['name' => 'delete', 'type' => 'boolean', 'default' => false],
            ['name' => 'permissionNeeded', 'type' => 'boolean', 'default' => true]
        ]);
    }

}
