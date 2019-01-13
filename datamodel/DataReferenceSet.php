<?php

include_once __DIR__ . "/../../utils/utils.php";

class DataReferenceSet {

    private $references;

    public function __construct() {
        $this->references = [];
    }

    public function registerEntity($entityName) {
        $this->references[$entityName] = [];
    }

    public function addReference($ref) {
        if(!array_key_exists('direction', $ref)) {
            $ref['direction'] = 'regular';
        }

        $direction = $ref['direction'];
        if ($direction !== 'regular' && $direction !== 'inverse') {
            throw(new Exception("Unknown reference direction '$direction'", 400));
        }

        $this->insertReference($ref);
    }

    public function getAll() {
        return $this->references;
    }

    public function getRegular($referencingEntityName) {
        return array_filter($this->references[$referencingEntityName], function($ref) {
            return $ref['direction'] === 'regular';
        });
    }

    public function getInverse($referencingEntityName) {
        return array_filter($this->references[$referencingEntityName], function($ref) {
            return $ref['direction'] === 'inverse';
        });
    }

    public function getList($entityName) {
        $refList = [];
        foreach ($this->regular as $ref) {
            array_push($refList, [
                'direction' => 'regular',
                'entity' => $ref['referencedEntity'],
                'field' => $ref['referenceField']
            ]);
        }
        foreach ($this->inverse as $ref) {
            array_push($refList, [
                'direction' => 'inverse',
                'entity' => $ref['referencedEntity'],
                'field' => $ref['containerField']
            ]);
        }
        return $refList;
    }

    private function insertReference($ref) {
        array_push($this->references[$ref['referencingEntity']], $ref);
    }
}

?>