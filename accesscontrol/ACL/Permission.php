<?php

include_once __DIR__ . '/../../datamodel/DataEntity.php';

class Permission extends DataEntity {

    public function __construct() {
        parent::__construct('permission');
        $this->addField(
            ['name' => 'id', 'type' => 'int', 'notNoll' => true, 'length' => 11, 'primary' => true, 'autoIncrement' => true]
        );
        $this->addField(
            ['name' => 'user', 'type' => 'varchar', 'notNoll' => true, 'length' => 32]
        );
        $this->addField(
            ['name' => 'accessLevel', 'type' => 'int', 'notNoll' => true, 'length' => 4]
        );
        $this->addField(
            ['name' => 'entityName', 'type' => 'varchar', 'notNoll' => true, 'length' => 32]
        );
        $this->addField(
            ['name' => 'entityId', 'type' => 'varchar', 'notNoll' => true, 'length' => 16]
        );
        // $this->addField(
        //     ['name' => 'primaryKeyData', 'type' => 'object', 'notNoll' => true]
        // );
    }

    public function observationUpdate($event) {
        $key = $this->dataModel->getEntity($event['subjectName'])->uniqueKey();
        if ($event['subjectName'] !== 'permission') {
            if ($event['context'] === 'onInsert') {
                if ($key === null) {
                    throw('A permission can only be issued for entities with one primary key.');
                }
                $this->dataModel->insert('permission', [
                    'user' => $event['user'],
                    'accessLevel' => 4,
                    'entityName' => $event['subjectName'],
                    'entityId' => $event['insertId']
                ]);
            } elseif ($event['context'] === 'onDelete') {
                $this->dataModel->delete('permission', [
                    'filter' => [
                        'user' => $event['user'],
                        'entityName' => $event['subjectName'],
                        'entityId' => $event['insertId']
                    ]
                ]);
            }
        }
    }
}

?>
