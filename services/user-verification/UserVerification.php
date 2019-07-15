<?php

include_once __DIR__ . '/../../datamodel/IdEntity.php';

class UserVerification extends IdEntity {
    public function __construct() {
        parent::__construct('userverification');
        $this->addFields([
            ['name' => 'expires', 'type' => 'int'],
            ['name' => 'token'  , 'type' => 'varchar', 'length' => 1024]
        ]);
    }
}
