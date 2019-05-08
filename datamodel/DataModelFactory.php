<?php

abstract class DataModelFactory {
    protected $databaseConnection;
    protected $guard;

    public function createDataModel() {
        $model = $this->buildDataModel();
        $model->setConnection($this->databaseConnection);
        $model->setGuard($this->guard);
        return $model;
    }

    function __construct($databaseConnection = null, $guard = null) {
        $this->databaseConnection = $databaseConnection;
        $this->guard = $guard;
    }

    public abstract function buildDataModel();
}

