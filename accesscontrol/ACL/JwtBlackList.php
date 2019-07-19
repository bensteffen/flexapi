<?php

include_once __DIR__ . '/../../datamodel/IdEntity.php';

class JwtBlackList extends DataEntity {

    public function __construct() {
        parent::__construct('jwtblacklist');
        $this->addFields([
            ['name' => 'jwt', 'type' => 'varchar', 'length' => 512],
            ['name' => 'expire', 'type' => 'int']
        ]);
    }
}

