<?php

include_once __DIR__ . '/../../datamodel/IdEntity.php';

class PasswordChange extends IdEntity {

    public function __construct() {
        parent::__construct('passwordchange');
        $this->addFields([
            ['name' => 'jwt', 'type' => 'varchar', 'length' => 512],
            ['name' => 'expires', 'type' => 'int']
        ]);
    }
}
