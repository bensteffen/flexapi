<?php

abstract class DataModelFactory {
    protected $databaseConnection;
    protected $guard;

    public function createDataModel($databaseConnection, $guard) {
        $this->databaseConnection = $databaseConnection;
        $this->guard = $guard;
        $model = $this->buildDataModel();
        $model->setConnection($databaseConnection);
        $model->setGuard($guard);
        return $model;
    }

    public function __construct($databaseConnection = null, $guard = null) {
        $this->databaseConnection = $databaseConnection;
        $this->guard = $guard;
    }

    public abstract function buildDataModel();
}
