<?php

include_once __DIR__ . '/datamodel/DataModel.php';
include_once __DIR__ . '/datamodel/DataEntity.php';
include_once __DIR__ . '/datamodel/IdEntity.php';

class EntityMonitor {
    protected $monitoredModel;
    protected $changeDataModel;
    protected $oldStates = [];

    public function __construct($modelToWatch, $entitiesToWatch, $changeNotification = null) {
        $change= new EntityChange($this, $changeNotification);
        $this->changeDataModel = new DataModel();
        $this->changeDataModel->addEntities([
            $change,
            new ChangeMetaData(),
            new FieldChange(),
            new ChangePath()
        ]);
        $this->changeDataModel->setConnection($modelToWatch->getConnection());
    
        foreach($entitiesToWatch as $entityName) {
            // $modelToWatch->addEntity($modelToWatch->getEntity($entityName)); // ???
            $modelToWatch->addObservation([
                'observer' => $change,
                'subjectName' => $entityName,
                'context' => ['onInsert', 'beforeUpdate', 'onUpdate', 'onDelete']
            ]);
        }

        $this->changeDataModel->addReference('entitychange.meta -> changemetadata');

        $this->changeDataModel->addObservation([
            'observerName' => 'changemetadata',
            'subjectName' => 'entitychange',
            'context' => ['onInsert', 'onDelete']
        ]);

        $this->monitoredModel = $modelToWatch;
    }

    public function getMonitoredModel() {
        return $this->monitoredModel;
    }

    public function storeOldState($entity, $entityId) {
        $oldState = $this->monitoredModel->read($entity->getName(), [
            'filter' => $entity->uniqueFilter($entityId),
            'flatten' => 'singleResult'
        ]);
        $this->oldStates[$entity->getName()][$entityId] = $oldState;
    }

    public function getOldState($entity, $entityId) {
        return $this->oldStates[$entity->getName()][$entityId];
    }

    public function history($entityName, $entityId) {
        $entity = $this->monitoredModel->getEntity($entityName);

        $state = $this->getCurrentState($entity, $entityId);
        $change = $this->getLatestChange($entity, $entityId);
        
        if (!$change) {
            $result = ['newState' => $state];
        }

        $history = [];
        while($change) {
            array_push($history, [
                "change" => ['id' => $change['id'], 'meta' => $change['meta']],
                "state" => $state
            ]);
            $result = $this->applyChange('undo', $state, $change);
            $state = $result['newState'];
            $change = $result['nextChange'];
        }
        array_push($history, [
            "change" => null,
            "state" => $result['newState']
        ]);
        return $history;
    }

    public function rewindTo($entity, $entityId, $targetChangeId) {
        $changeIds = $this->changeDataModel->read('entitychange', [
            'filter' => ['entityName' => $entity->getName()],
            'select' => 'id',
            'flatten' => 'singleField'
        ]);
        if (!in_array($targetChangeId, $changeIds)) {
            throw(new Exception("Change id $targetChangeId not existing for entity ".$entity->getName().".", 400));
        }
        $state = $this->getCurrentState($entity, $entityId);
        $change = $this->getLatestChange($entity, $entityId);
        $headId = $change['id'];
        while($change['previous'] != $targetChangeId && $change['previous'] !== null) {
            $state = $this->applyChange('undo', $state, $change);
            $change = $this->getChangeById($entity, $entityId, $change['previous']);
        }
        $this->setAsHead($change, $headId);
    }

    public function reset() {
        $this->changeDataModel->reset();
    }

    protected function getChangesByPath($entity, $entityId, $pathId) {
        
    }

    public function getChangeById($entity, $entityId, $changeId) {
        return $this->changeDataModel->read('entitychange', [
            'filter' => [
                'id' => $changeId,
                'entityName' => $entity->getName(),
                'entityId' => $entityId
            ],
            'references' => ['format' => 'data'],
            'flatten' => 'singleResult'
        ]);
    }

    public function getLatestChange($entity, $entityId) {
        return $this->changeDataModel->read('entitychange', [
            'filter' => [
                'entityName' => $entity->getName(),
                'entityId' => $entityId,
                'isHead' => true
            ],
            'references' => ['format' => 'data'],
            'flatten' => 'singleResult'
        ]);
    }

    protected function getCurrentState($entity, $entityId) {
        return $this->monitoredModel->read($entity->getName(), [
            'filter' => $entity->uniqueFilter($entityId),
            'flatten' => 'singleResult'
        ]);
    }

    protected function applyChange($direction, $state, $change) {
        $fieldChanges = $this->changeDataModel->read('fieldchange', [
            'filter' => [ 'changeId' => $change['id'] ]
        ]);
        $map = ['undo' => 'oldValue', 'do' => 'newValue' ];
        $dMap = ['undo' => 'previous', 'do' => 'next' ];
        foreach ($fieldChanges as $fieldChange) {
            $state[$fieldChange['fieldName']] = $fieldChange[$map[$direction]];
        }
        $nextChange = $this->changeDataModel->read('entitychange', [
            'filter' => [ 'id' => $change[$dMap[$direction]] ],
            'references' => ['format' => 'data'],
            'flatten' => 'singleResult'
        ]);
        return [
            'newState' => $state,
            'nextChange' => $nextChange
        ];
    }

    protected function setAsHead($change, $actualHeadId = null) {
        if ($actualHeadId === null) {
            $latestChange = $this->getLatestChange($change['entityName'], $change['entityId']);
            $actualHeadId = $latestChange['id'];
        }
        $this->update('entitychange', [ 'id' => $actualHeadId, 'isHead' => false ]);
        $this->update('entitychange', [ 'id' => $change['id'], 'isHead' => true ]);
    }

    public function createInitialState($entity) {
        $state = [];
        foreach ($entity->getFieldSet() as $field) {
            $state[$field['name']] = null;
        }
        return $state;
    }
}

class EntityChange extends IdEntity {
    protected $entityMonitor;
    protected $notification = null;
    protected $oldStates = [];

    public function __construct($entityMonitor, $notification) {
        $this->entityMonitor = $entityMonitor;
        $this->notification = $notification;
        parent::__construct('entitychange');
        $this->addFields([
            ['name' => 'entityName', 'type' => 'varchar', 'length' => 64],
            ['name' => 'entityId'  , 'type' => 'varchar', 'length' => 64],
            ['name' => 'meta'      , 'type' => 'int'],
            ['name' => 'next'      , 'type' => 'int', 'notNull' => false],
            ['name' => 'previous'  , 'type' => 'int', 'notNull' => false],
            ['name' => 'isHead'    , 'type' => 'boolean'],
            ['name' => 'path'      , 'type' => 'int']
        ]);
    }

    public function observationUpdate($event) {
        $entity = $event['subjectEntity'];
        $keyName = $entity->uniqueKey();
        if ($event['context'] === 'onInsert') {
            // $changeId = $this->dataModel->insert('entitychange', [
            //     'entityName' => $entity->getName(),
            //     'entityId' => $event['insertId'],
            //     'next' => null,
            //     'previous' => null,
            //     'isHead' => true,
            //     'path' => 0
            // ]);
            // $this->insertFieldChanges($changeId, $entity, $this->entityMonitor->createInitialState($entity), $event['data']);
        }
        if ($event['context'] === 'beforeUpdate') {
            $entityId = $event['data'][$keyName];
            $this->entityMonitor->storeOldState($entity, $entityId);
        }
        if ($event['context'] === 'onUpdate') {
            $entityId = $event['data'][$keyName];
            $latestChange = $this->entityMonitor->getLatestChange($entity, $entityId);
            $nextChangeId = $this->dataModel->insert('entitychange', [
                'entityName' => $entity->getName(),
                'entityId' => $entityId,
                'next' => null,
                'previous' => $latestChange['id'],
                'isHead' => true,
                'path' => 0
            ]);
            $this->dataModel->update('entitychange', ['id' => $latestChange['id'], 'isHead' => false, 'next' => $nextChangeId]);
            $oldState = $this->entityMonitor->getOldState($entity, $entityId);
            $fieldChanges = $this->insertFieldChanges($nextChangeId, $entity, $oldState, $event['data']);

            if (count($fieldChanges) && $this->notification) {
                $change = $this->dataModel->read('entitychange', [
                    'filter' => ['id' => $nextChangeId ],
                    'flatten' => 'singleResult'
                ]);
                $metaData = $this->dataModel->read('changemetadata', [
                    'filter' => [ 'id' => $change['meta'] ],
                    'flatten' => 'singleResult'
                ]);
                $subject = $this->notification['subject'];
                $body = $this->notification['body'];
                FlexAPI::sendMail([
                    'from' => $this->notification['from'],
                    'to' => $this->notification['to'],
                    'subject' => $subject($entity->getName()),
                    'body' => $body($entity->getName(), $entityId, $metaData, $fieldChanges)
                ]);
            }
        }
        if ($event['context'] === 'onDelete') {
            
        }
    }

    protected function insertFieldChanges($changeId, $entity, $oldState, $newState) {
        $keyName = $entity->uniqueKey();
        $fieldChanges = [];
        foreach ($entity->fieldNames() as $fieldName) {
            $isNotKey = $fieldName !== $keyName;
            $isInData = array_key_exists($fieldName, $newState);
            if ($isNotKey && $isInData) {
                $valueChanged = $oldState[$fieldName] != $newState[$fieldName];
                if ($valueChanged) {
                    array_push($fieldChanges, [
                        'changeId' => $changeId,
                        'fieldName' => $fieldName,
                        'oldValue' => $oldState[$fieldName],
                        'newValue' => $newState[$fieldName]
                    ]);
                }
            }
        }
        $this->dataModel->insert('fieldchange', $fieldChanges);
        return $fieldChanges;
    }
}

class ChangeMetaData extends IdEntity {
    public function __construct() {
        parent::__construct('changemetadata');
        $this->addFields([
            ['name' => 'timeStamp' , 'type' => 'int'],
            ['name' => 'user' , 'type' => 'varchar', 'length' => 64]
        ]);
    }

    public function observationUpdate($event) {
        if ($event['context'] === 'onInsert') {
            $metaId = $this->dataModel->insert('changemetadata', [
                'timeStamp' => time(),
                'user' => FlexAPI::guard()->getUsername()
            ]);
            $change = $this->dataModel->read('entitychange', [
                'filter' => [ 'id' => $event['insertId'] ],
                'flatten' => 'singleResult'
            ]);
            $change['meta'] = $metaId;
            $this->dataModel->update('entitychange', $change);
        }
    }
}

class FieldChange extends DataEntity {
    public function __construct() {
        parent::__construct('fieldchange');
        $this->addFields([
            ['name' => 'changeId'  , 'type' => 'int'],
            ['name' => 'fieldName' , 'type' => 'varchar', 'length' => 64],
            ['name' => 'oldValue'  , 'type' => 'text', 'notNull' => false],
            ['name' => 'newValue'  , 'type' => 'text', 'notNull' => false]
        ]);
    }

    public function observationUpdate($event) {

    }
}

class ChangePath extends DataEntity {
    public function __construct() {
        parent::__construct('changepath');
        $this->addFields([
            ['name' => 'changeId'  , 'type' => 'int'],
            ['name' => 'pathId'  , 'type' => 'int']
        ]);
    }

    public function observationUpdate($event) {

    }
}