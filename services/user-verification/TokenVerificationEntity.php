<?php

include_once __DIR__ . '/../../datamodel/IdEntity.php';

class UserVerificationToken extends IdEntity {
    public function __construct() {
        parent::__construct('userverificationtoken');
        $this->addFields([
            ['name' => 'user', 'type' => 'varchar', 'length' => 256],
            ['name' => 'expires', 'type' => 'int'],
            ['name' => 'token'  , 'type' => 'varchar', 'length' => 32],
        ]);
    }
}
