<?php

interface IfDatabseConnection {
    public function establish($credentials);

    public function createEntity($entity);

    public function insertIntoDatabase($entity, $data);

    public function readFromDatabase($entity, $filter, $fieldSelection, $distinct);

    public function updateDatabase($entity, $data);

    public function deleteFromDatabase($entity, $filter);

    public function joinTables($type, $baseEntity, $joinedEntity, $joinConditions, $selection, $filter);

    function prepareData($entity, $data);

    function finishData($entity, $data);
}

