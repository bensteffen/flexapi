<?php

include_once __DIR__ . '/../../datamodel/DataEntity.php';

class Permission extends DataEntity {

    public function __construct() {
        parent::__construct('permission');
        $this->addFields([
            ['name' => 'key', 'type' => 'varchar', 'notNoll' => true, 'length' => 128, 'primary' => true],
            ['name' => 'user', 'type' => 'varchar', 'notNoll' => true, 'length' => 32],
            ['name' => 'accessLevel', 'type' => 'int', 'notNoll' => true, 'length' => 4],
            ['name' => 'entityName', 'type' => 'varchar', 'notNoll' => true, 'length' => 32],
            ['name' => 'entityId', 'type' => 'varchar', 'notNoll' => true, 'length' => 16]
        ]);
    }

    public static function toPermissionKey($user, $entityName, $entityId) {
        return sprintf("//%s/%s#%s", $user, $entityName, $entityId);
    }

    public function observationUpdate($event) {
        $key = $this->dataModel->getEntity($event['subjectName'])->uniqueKey();
        if ($event['subjectName'] !== 'permission') {
            $key = Permission::toPermissionKey($event['user'], $event['subjectName'], $event['insertId']);
            if ($event['context'] === 'onInsert') {
                if ($key === null) {
                    throw('A permission can only be issued for entities with one primary key.');
                }
                $this->dataModel->insert('permission', [
                    'key' => $key,
                    'user' => $event['user'],
                    'accessLevel' => 4,
                    'entityName' => $event['subjectName'],
                    'entityId' => $event['insertId']
                ]);
            } elseif ($event['context'] === 'onDelete') {
                $this->dataModel->delete('permission', [ 'filter' => [ 'key' => $key ] ]);
            }
        }
    }
}

?>
