<?php

include_once __DIR__ . '/Guard.php';

class VoidGuard extends Guard {
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

    public function readPermitted($connection, $entity, $filter, $selection, $sort, $onlyOwn, $pagination) {
        return null;
    }
	

    public function updatePermitted($connection, $entity, $filter, $data) {

    }

    public function deletePermitted($connection, $entity, $filter) {

    }

    public function publish($rootEntityName, $filter, $accessLevel, $guestPassword) {

    }

    protected function onModelConnection() {

    }

    protected function onNewEntity($entityName) {

    }
}

