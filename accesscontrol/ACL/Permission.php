<?php

include_once __DIR__ . '/../../datamodel/DataEntity.php';

class Permission extends DataEntity {

    protected $guard = null;
    protected $protectedDataModel = null;

    public function __construct() {
        parent::__construct('permission');
        $this->addFields([
            ['name' => 'key', 'type' => 'varchar', 'notNoll' => true, 'length' => 128, 'primary' => true],
            ['name' => 'user', 'type' => 'varchar', 'notNoll' => true, 'length' => 32],
            ['name' => 'methods', 'type' => 'varchar', 'length' => 4],
            ['name' => 'entityName', 'type' => 'varchar', 'notNoll' => true, 'length' => 32],
            ['name' => 'entityId', 'type' => 'varchar', 'notNoll' => true, 'length' => 64]
        ]);
    }

    public function setGuard($guard) {
        $this->guard = $guard;
    }

    public function setProtectedDataModel($dataModel) {
        $this->protectedDataModel = $dataModel;
    }

    public static function toPermissionKey($user, $entityName, $entityId) {
        return sprintf("//%s/%s#%s", $user, $entityName, $entityId);
    }

    public function observationUpdate($event) {
        if ($event['context'] === 'onInsert') {
            $entityName = $event['subjectName'];
            if (!$this->guard->permissionsNeeded($entityName)) {
                return;
            }
            $methods = '';
            if ($this->guard->userMay('read', $entityName)) {
                $methods .= 'R';
            }
            if ($this->guard->userMay('update', $entityName)) {
                $methods .= 'U';
            }
            if ($this->guard->userMay('delete', $entityName)) {
                $methods .= 'D';
            }
            $key = Permission::toPermissionKey($event['user'], $event['subjectName'], $event['insertId']);
            if ($key === null) {
                throw('A permission can only be issued for entities with one primary key.');
            }
            $this->dataModel->insert('permission', [
                'key' => $key,
                'user' => $event['user'],
                'methods' => $methods,
                'entityName' => $event['subjectName'],
                'entityId' => $event['insertId']
            ]);
        } elseif ($event['context'] === 'onDelete') {
            $entityName = $event['subjectName'];
            $keyName = $event['subjectEntity']->uniqueKey();
            foreach ($event['keys'] as $key) {
                $this->dataModel->delete('permission', [
                    'entityName' => $entityName,
                    'entityId' => $key[$keyName]
                ]);
            }
        }
    }
}
