<?php

include_once __DIR__ . "/../../bs-php-utils/utils.php";

class DataReferenceSet {

    private $references;

    public function __construct() {
        $this->references = [];
    }

    public function registerEntity($entityName) {
        $this->references[$entityName] = [];
    }

    public function addReference($ref) {
        if (is_string($ref)) {
            $ref = $this->parseRefString($ref);
            if (!$ref) {
                throw(new Exception("Could not parse reference string", 400));
            }
        }

        $ref = setFieldDefault($ref, 'direction', 'regular');
        $ref = setFieldDefault($ref, 'onBatchInsertion', 'insert'); /* options: 'insert' or 'upsert' */

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

    public function fieldToEntityName($referencingEntityName, $refrenceFieldName) {
        $refList = $this->getRegular($referencingEntityName);
        $ref = array_filter($refList, function($r) use($refrenceFieldName) {
            return $r['referenceField'] === $refrenceFieldName;
        });
        if (count($ref) === 1) {
            $ref = array_pop($ref);
            return $ref['referencedEntity'];
        }
        return null;
    }

    public function parseRefString($ref) {
        $regRes = explode('->' ,$ref);
        if (count($regRes) === 2) {
            $itemA = trim($regRes[0]);
            $itemB = trim($regRes[1]);
            $dotSplit = explode('.', $itemA);
            return [
                'referencingEntity' => $dotSplit[0],
                'referenceField' => $dotSplit[1],
                'referencedEntity' => $itemB
            ];
        }
        $invRes = explode('<-' ,$ref);
        if (count($invRes) === 2) {
            $itemA = trim($invRes[0]);
            $itemB = trim($invRes[1]);
            $dotSplitA = explode('.', $itemA);
            $dotSplitB = explode('.', $itemB);
            return [
                'direction' => 'inverse',
                'referencingEntity' => $dotSplitA[0],
                'containerField' => $dotSplitA[1],
                'referencedEntity' => $dotSplitB[0],
                'referenceField' => $dotSplitB[1]
            ];
        }
        return null;
    }

    private function insertReference($ref) {
        array_push($this->references[$ref['referencingEntity']], $ref);
    }
}

