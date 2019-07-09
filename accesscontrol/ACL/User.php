<?php

include_once __DIR__ . '/../../FlexAPI.php';
include_once __DIR__ . '/../../datamodel/DataEntity.php';

class User extends DataEntity {

    public function __construct() {
        parent::__construct('user');
        $this->addFields([
            ['name' => 'name'      , 'type' => 'varchar', 'length' => FlexAPI::get('maxUserNameLength'), 'primary' => true],
            ['name' => 'password'  , 'type' => 'varchar', 'length' => 256],
            ['name' => 'isVerified', 'type' => 'boolean', 'default' => false]
        ]);
    }
}
