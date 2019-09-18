<?php

abstract class AbstractReadQueryFactory {
    protected $entity;

    public function setEntity($entity) {
        $this->entity = $entity;
    }
    
    abstract public function makeQuery($filter, $fieldSelection, $distinct, $order, $pagination);
}
