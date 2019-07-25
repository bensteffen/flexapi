<?php

require __DIR__ . '/../../../autoload.php';

abstract class Guard {
    protected $protectDataModel;
    protected $verificationService = null;
    protected $username = null;
    protected $userAccessLevel = null;

    public function getUsername() {
        return $this->username;
    }

    public function addEntityToProtect($entityName) {
        $this->onNewEntity($entityName);
    }

    public function setModelToProtect($dataModel) {
        $this->protectDataModel = $dataModel;
        $this->onModelConnection();
        foreach($this->protectDataModel->getEntityNames() as $entityName) {
            $this->addEntityToProtect($entityName);
        }
    }

    public abstract function login($authentification);
    // $authentification can be ['username' => '...', 'password' => '...'] or a JWT
    // method sets $this->username

    public abstract function logout($jwt);

    public abstract function publish($rootEntityName, $filter, $accessLevel, $guestPassword);

    public abstract function isLoggedIn();

    public abstract function userMay($method, $entiyName);

    public abstract function permissionsNeeded($entityName);

    public abstract function readPermitted($connection, $entity, $filter, $selection, $sort);

    public abstract function updatePermitted($connection, $entity, $filter, $data);

    public abstract function deletePermitted($connection, $entity, $filter);

    protected abstract function onModelConnection();

    protected abstract function onNewEntity($entityName);

}

