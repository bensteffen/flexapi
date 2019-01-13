<?php

include_once __DIR__ . '/../../datamodel/DataEntity.php';

class User extends DataEntity {

    public function __construct() {
        parent::__construct('user');
        $this->addFields([
            ['name' => 'name'       , 'type' => 'varchar', 'length' => 32, 'primary' => true],
            ['name' => 'password'   , 'type' => 'varchar', 'length' => 256],
            ['name' => 'accessLevel', 'type' => 'int'    , 'length' => 4]
        ]);
    }
}

?>
