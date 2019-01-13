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

    public function deliverPermitted($connection, $entity, $filter, $selection) {
        return null;
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

?>