<?php

interface IfDatabseConnection {
    public function establish($credentials);

    public function createEntity($entity);

    public function insertIntoDatabase($entity, $data);

    public function readFromDatabase($entity, $filter, $fieldSelection, $distinct, $order, $pagination);

    public function updateDatabase($entity, $data);

    public function deleteFromDatabase($entity, $filter);

    public function clearEntity($entity);

    public function clearEntity($entity);

    function prepareData($entity, $data);

    function finishData($entity, $data);
}

