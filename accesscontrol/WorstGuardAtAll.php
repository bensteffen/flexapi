<?php

include_once __DIR__ . '/Guard.php';

class WorstGuardAtAll extends Guard {
    public function login($authentification) {

    }

    public function logout($jwt) {

    }

    public function isLoggedIn() {
        return true;
    }

    public function userMay($method, $entiyName = null) {
        return true;
    }

    public function permissionsNeeded($entityName) {
        return false;
    }

    public function readPermitted($connection, $entity, $filter, $selection) {
        return null;
    }

    public function updatePermitted($connection, $entity, $filter, $data) {

    }

    public function deletePermitted($connection, $entity, $filter) {
        
    }

    public function publish($rootEntityName, $filter, $accessLevel, $guestPassword) {

    }

    protected function getUserAccesLevel() {
        return 5;
    }

    protected function onModelConnection() {

    }

    protected function onNewEntity($entityName) {
        
    }
}

