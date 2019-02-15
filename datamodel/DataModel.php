<?php

include_once __DIR__ . "./DataReferenceSet.php";
include_once __DIR__ . "/../../bs-php-utils/Options.php";
include_once __DIR__ . "/../accesscontrol/WorstGuardAtAll.php";
include_once __DIR__ . "/../../bs-php-utils/utils.php";

class DataModel {
    protected $connection = null;
    protected $guard = null;
    protected $entities = [];
    protected $observations = [];
    protected $referenceSet;
    protected $references = [];
    protected $readOptions;
    protected $observationOptions;
    protected $filterParser;

    public function __construct() {
        $this->readOptions = new Options([
            'filter' => [],
            'selection' => [],
            'references' => [
                'depth' => 1, // options: 'deep' (as deep as possible) or 0 (don't resolve) or 1 or 2 or ...
                'format' => 'url'   // options: 'data', 'keys', 'url'
            ],
            'flatten' => false
        ]);
        $this->references = new DataReferenceSet();
        $this->setGuard(new WorstGuardAtAll());
        $this->filterParser =  new FilterParser();
    }

    public function setConnection($connection) {
        $this->connection = $connection;
        foreach ($this->entities as $name => $entity) {
            $this->connection->createEntity($entity);
        }
    }

    public function setGuard($guard) {
        $this->guard = $guard;
        $this->guard->setModelToProtect($this);
    }

    public function getGuard() {
        return $this->guard;
    }

    public function addEntity($entity) {
        $name = $entity->getName();
        $this->entities[$name] = $entity;
        $this->references->registerEntity($name);
        $this->observations[$name] = [];
        if ($this->connection) {
            $this->connection->createEntity($entity);
        }
        $entity->setDataModel($this);

        $this->guard->addEntityToProtect($name);
    }

    public function addEntities($entities) {
        foreach ($entities as $entity) {
            $this->addEntity($entity);
        }
    }

    public function getEntity($name) {
        if (!array_key_exists($name, $this->entities)) {
            throw(new Exception("Unknown entity '$name'", 404));
        }
        return $this->entities[$name];
    }

    public function getEntityNames() {
        return array_map(function($entity) {
            return $entity->getName();
        }, $this->entities);
    }

    public function insert($entityName, $data = [], $metaData = []) {
        if (!$this->guard->userCanInsert()) {
            throw(new Exception("User is not allowed to insert into $entityName.", 403));
        }

        $data = (array) $data;

        if (isAssoc($data)) {
            $entity = $this->getEntity($entityName);

            $data = $this->insertRegularReferences($entityName, $data);
            $this->throwExceptionOnBadReference($entityName, $data);

            $id = $this->connection->insertIntoDatabase(
                $this->getEntity($entityName),
                extractArray($entity->fieldNames(),$data)
            );
            if (!$id) {
                $unqiueKey = $entity->uniqueKey();
                if ($unqiueKey) {
                    $id = $data[$unqiueKey];
                } else {
                    $id = extractArray($entity->primaryKeys(), $data);
                }
            }

            $this->notifyObservers([
                'subjectName' => $entityName,
                'data'        => $data,
                'user'        => $this->guard->getUsername(),
                'insertId'    => $id,
                'context'     => 'onInsert',
                'metaData'    => $metaData
            ]);
    
            $this->insertInverseReferences($entityName, $id, $data);
    
            return $id;
        } else {
            $ids = [];
            foreach($data as $d) {
                $id = $this->insert($entityName, $d, $metaData);
                array_push($ids, $id);
            }
            return $ids;
        }
    }

    private function insertRegularReferences($entityName, $data) {
        foreach ($this->references->getRegular($entityName) as $ref) {
            $field = $ref['referenceField'];
            if (array_key_exists($field, $data)) {
                $refData = (array) $data[$field];
                if (isAssoc($refData)) {
                    $data[$field] = $this->upsert($ref['referencedEntity'], $refData, $refData);
                }
            }
        }
        return $data;
    }

    private function insertInverseReferences($entityName, $entityId, $data) {
        foreach ($this->references->getInverse($entityName) as $ref) {
            $field = $ref['containerField'];
            if (array_key_exists($field, $data)) {
                $refDataArray = (array) $data[$field];
                foreach ($refDataArray as $refData) {
                    $refData = (array) $refData;
                    if (isAssoc($refData)) {
                        $refData[$ref['referenceField']] = $entityId;
                        $this->insert($ref['referencedEntity'], $refData);
                    } elseif (count($refData) === 1) {
                        $refId = $refData[0];
                        $refIdName = $this->getEntity($ref['referencedEntity'])->uniqueKey();
                        $this->update($ref['referencedEntity'], [
                            $refIdName => $refId,
                            $ref['referenceField'] => $entityId
                        ]);
                    }
                }
            }
        }
        return $data;
    }

    public function read($entityName, $options = []) {    
        $this->readOptions->setValues($options);
        $filter    = $this->filter2Query($this->readOptions->valueOf('filter'), $entityName);
        $selection = $this->reshapeSelection($entityName, $this->readOptions->valueOf('selection'));
        $referenceConfig = $this->reshapeReferenceConfig($this->readOptions->valueOf('references'));
        $flatten = $this->readOptions->valueOf('flatten');
        
        if (!$this->guard->userCanRead()) {
            throw(new Exception("User is not allowed to read from '$entityName' specified by " . jsenc($filter) . ".", 403));
        }

        $entity = $this->getEntity($entityName);
        if ($this->guard->userNeedsPermission()) {
            $data = $this->guard->deliverPermitted($this->connection, $entity, $filter, $selection['regular']);
        } else {
            $data = $this->connection->readFromDatabase($entity, $filter, $selection['regular']);
        }

        $this->notifyObservers([
            'subjectName' => $entityName,
            'filter' => $filter,
            'context' => 'onRead'
        ]);

        if ($referenceConfig['depth'] === 'deep' || $referenceConfig['depth'] > 0) {
            $data = $this->resolveReferences($referenceConfig, $entityName, $data);
        }

        if ($selection['added']) {
            foreach ($data as $i => $d) {
                $data[$i] = assocIgnore($data[$i], [$selection['added']]);
            }
        }

        if ($flatten === 'singleResult' && count($data) === 1) {
            $data = $data[0];
        } elseif ($flatten === 'singleField') {
            foreach ($data as $i => $d) {
                $k = array_keys($d);
                $data[$i] = $d[$k[0]];
            }
        }

        if (count($data) === 0) {
            return null;
        }
        return $data;
    }

    public function update($entityName, $data) {
        $primaryKeyData = $this->getEntity($entityName)->primaryKeyData($data);
        if ($primaryKeyData === null) {
            throw(new Exception("Can't identify object to update, because primary key data is missing.", 400));
        }
        if (!$this->guard->userCanUpdate()) {
            throw(new Exception("User is not allowed to update $entityName identified by ". jsenc($primaryKeyData), 403));
        }
        $this->throwExceptionOnBadReference($entityName, $data);
        $this->connection->updateDatabase($this->getEntity($entityName), $data);

        $this->notifyObservers([
            'subjectName' => $entityName,
            'data' => $data,
            'context' => 'onUpdate'
        ]);
    }

    public function upsert($entityName, $filter, $data) {
        $entity = $this->getEntity($entityName);
        $existing = $this->read($entityName, [
            'filter' => $filter,
            'references' => ['format' => 'key']
        ]);
        if ($existing) {
            if (count($existing) > 1) {
                throw(new Exception("upsert-filter does not deliver an unique result", 400));
            }
            $existing = $existing[0];
            foreach($data as $field => $value) {
                $existing[$field] = $value;
            }
            $this->update($entityName, $existing);
            return extractArray($entity->primaryKeys(), $existing, true);
        } else {
            return $this->insert($entityName, $data);
        }
    }

    public function delete($entityName, $filter = []) {
        $filter = $this->filter2Query($filter, $entityName);

        if (!$this->guard->userCanDelete()) {
            throw(new Exception("User is not allowed to delete '$entityName'", 403));
        }

        $this->notifyObservers([
            'subjectName' => $entityName,
            'filter' => $filter,
            'context' => 'onDelete'
        ]);

        $this->connection->deleteFromDatabase($this->getEntity($entityName), $filter);
    }

    public function resourceExists($entityName, $filter) {
        $data = $this->read($entityName, [
            'filter' => $filter,
            'references' => ['format' => 'key']
        ]);
        return $data !== null;
    }

    public function listResources($entityName, $filter, $result = []) {
        $refs = $this->references->getList($entityName);
        $data = $this->read($entityName,[
            'filter'     => $filter,
            'references' => ['format' => 'key'],
            'selection'  => array_column($containers, 'fields')
        ]);
        foreach ($data as $d) {
            foreach ($refs as $r) {
                $refEntity = $this->getEntity($r['entity']);
                $keyName = $refEntity->uniqueKey();
                $key = $d[$r['field']];
                if (!is_array($key)) {
                    $key = [[$keyName => $key]];
                }
                foreach ($key as $k) {
                    array_push($result, [
                        'entity' => $refEntity->getName(),
                        'key' => $k[$keyName]
                    ]);
                    $result = $this->listResources(
                        $refEntity->getName(),
                        $k,
                        $result
                    );
                }
            }
        }
        return $result;
    }

    // references
    public function addReference($reference) {
        // definiton example:
        // ['referencingEntity' => 'employee', 'referenceField' => 'homeAddress', 'referencedEntity' => 'address']
        $this->references->addReference($reference);
    }

    protected function resolveReferences($referenceConfig, $referencingEntityName, $data) {
        $regularReferences = $this->references->getRegular($referencingEntityName);
        $inverseReferences = $this->references->getInverse($referencingEntityName);
        if ($referenceConfig['depth'] !== 'deep') {
            $referenceConfig['depth'] -= 1;
        }
        for ($i = 0; $i < count($data); $i++) {
            foreach ($regularReferences as $regularRef) {
                if (array_key_exists($regularRef['referenceField'], $data[$i])) {
                    $data[$i] = $this->resolveRegular($referenceConfig, $regularRef, $data[$i]);
                }
            }
            foreach ($inverseReferences as $inverseRef) {
                $data[$i] = $this->resolveInverse($referenceConfig, $inverseRef, $data[$i]);
            }
        }
        return $data;
    }

    protected function resolveRegular($config, $reference, $data) {
        $entity = $this->getEntity($reference['referencedEntity']);
        $referenceField = $reference['referenceField'];
        $referenceIdName = $entity->uniqueKey();
        $referenceId = $data[$referenceField];
        if ($config['format'] === "key") {
            $content = $referenceId;
        } elseif ($config['format'] === "data") {
            $content = $this->read($reference['referencedEntity'], [
                'references' => $config,
                'filter' => [$referenceIdName => $referenceId],
                'flatten' => 'singleResult'
            ]);
        } elseif ($config['format'] === "url") {
            $content = $this->makeResourceUrl($reference['referencedEntity'], $referenceIdName, $referenceId);
        }
        $data[$referenceField] = $content;
        return $data;
    }

    protected function resolveInverse($config, $reference, $data) {
        $referenceId = $reference['referenceField'];
        $referencingIdName = $this->getEntity($reference['referencingEntity'])->uniqueKey();
        $referencedIdName = $this->getEntity($reference['referencedEntity'])->uniqueKey();
        if (!array_key_exists($referencingIdName, $data)) {
            return $data;
        }
        if ($config['format'] === "key" || $config['format'] === "url") {
            $content = $this->read($reference['referencedEntity'], [
                'references' => $config,
                'filter' => [$referenceId => $data[$referencingIdName]],
                'selection' => [$referencedIdName]
            ]);
            if ($content === null) {
                $content = [];
            }
            if ($config['format'] === "url") {
                $content = array_map(function($x) use($reference, $referencedIdName, $referenceId) {
                    return $this->makeResourceUrl($reference['referencedEntity'], $referencedIdName, $x[$referencedIdName]);
                }, $content);
            }
        } elseif ($config['format'] === "data") {
            $content = $this->read($reference['referencedEntity'], [
                'references' => $config,
                'filter' => [$referenceId => $data[$referencingIdName]]
            ]);
        }
        if ($content !== null) {
            $data[$reference['containerField']] = $content;
        }
        return $data;
    }

    protected function throwExceptionOnBadReference($entityName, $data) {
        foreach ($this->references as $referencingEntity => $entityRefs) {
            foreach ($entityRefs as $ref) {
                if (array_key_exists('referenceField', $ref) && array_key_exists($ref['referenceField'], $data)) {
                    if ($ref['direction'] === 'regular' && $entityName === $referencingEntity) {
                        $entityToCheck = $this->getEntity($ref['referencedEntity']);
                    }
                    if ($ref['direction'] === 'inverse' && $entityName === $ref['referencedEntity']) {
                        $entityToCheck = $this->getEntity($referencingEntity);
                    }
                    $keyValue = $data[$ref['referenceField']];
                    $keyName = $entityToCheck->uniqueKey();
                    $exists = $this->resourceExists($entityToCheck->getName(), [$keyName => $keyValue]);
                    if (!$exists) {
                        throw(new Exception("Cannot set reference to '".$entityToCheck->getName()."', because element with $keyName = ".jsenc($keyValue)." does not exist.", 400));
                    }
                }
            }
        }
    }

    // observations
    public function addObservation($definition) {
        // definition example:
        // $definition = ['subjectName' => 'employee' , 'observerName' => 'employeesStatistic', 'onInsert' => true, 'onDelete' => true];
        // TODO: write observation definiton class

        if(!is_array($definition['context'])) {
            $definition['context'] = [$definition['context']];
        }
        $definition = setFieldDefault($definition, 'priority', 'normal');
        if ($definition['priority'] === 'high') {
            array_unshift($this->observations[$definition['subjectName']], $definition);
        } else {
            array_push($this->observations[$definition['subjectName']], $definition);
        }
    }

    protected function notifyObservers($updateEvent) {
        $updateEvent = setFieldDefault($updateEvent, 'data', []);
        $observationDefinitions = $this->observations[$updateEvent['subjectName']];
        $context = $updateEvent['context'];
        foreach($observationDefinitions as $definition) {
            $eventOk = true;
            if (array_key_exists('triggerCondition', $definition)) {
                $triggerCondition = $definition['triggerCondition'];
                $eventOk = $triggerCondition($updateEvent);
            }
            if ($eventOk && in_array($context, $definition['context'])) {
                if (array_key_exists('observer', $definition)) {
                    $observingEntity = $definition['observer'];
                } else {
                    $observingEntity = $this->getEntity($definition['observerName']);
                }
                $updateEvent['subjectEntity'] = $this->getEntity($updateEvent['subjectName']);
                $observingEntity->observationUpdate($updateEvent);
            }
        }
    }

    protected function filter2Query($filter, $entityName) {
        $this->filterParser->defaultEntity = $entityName;
        if (is_array($filter)) {
            return $this->filterParser->parseFilterArray($filter);
        }
        if (is_string($filter)) {
            return $this->filterParser->parseQueryString($filter);
        }
        throw(new Exception('Cannot convert filter to query.', 500));
    }

    protected function reshapeSelection($entityName, $selection) {
        $invRefs = $this->references->getInverse($entityName);

        $invSelection = [];
        foreach($selection as $field) {
            $result = array_filter($invRefs, function($r) use($field) { return $r['containerField'] === $field; });
            if (count($result) > 0) {
                array_push($invSelection, $field);
            }
        }
        $addedId = false;
        if(count($invSelection) > 0) {
            $idName = $this->getEntity($entityName)->uniqueKey();
            if(!in_array($idName, $selection)) {
                array_unshift($selection, $idName);
                $addedId = $idName;
            }
        }
        $regSelection = arrayIgnore($selection, $invSelection);
        
        $output = [
            'regular' => $regSelection,
            'inverse' => $invSelection,
            'added'   => $addedId
        ];
        return $output;
    }

    protected function reshapeReferenceConfig($config) {
        $config = setFieldDefault($config, 'depth', 1);
        $config = setFieldDefault($config, 'format', 'url');
        if ($config['format'] !== 'data' && ($config['depth'] === 'deep' || $config['depth'] > 1)) {
            $config['depth'] = 1;
        }
        return $config;
    }

    protected function makeResourceUrl($entityName, $keyName, $keyValue) {
        return sprintf("http://%s:%s/tourline/api/v2/crud.php?entity=%s&filter=%s",
            $_SERVER['SERVER_NAME'],
            $_SERVER['SERVER_PORT'],
            $entityName,
            "[$keyName,$keyValue]"
        );
    }
}

?>
