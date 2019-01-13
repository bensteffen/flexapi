<?php

include_once __DIR__ . '/database/SqlConnection.php';
include_once __DIR__ . '/accesscontrol/ACL/ACLGuard.php';
include_once __DIR__ . '/tjournl/TjournlFactory.php';

class API extends AbstractAPI {
    protected static function defineAPI() {
        $databaseConnection = new SqlConnection([
            'host'     => 'localhost',
            'username' => 'root',
            'password' => '',
            'database' => 'tourline'
        ]);
        
        API::$guard = new ACLGuard(new SqlConnection([
                'host'     => 'localhost',
                'username' => 'root',
                'password' => '',
                'database' => 'users'
        ]));
        API::$guard->setTokenValidity(6*3600);
        
        $modelFactory = new TjournlFactory($databaseConnection, API::$guard);
        API::$dataModel = $modelFactory->createDataModel();
    }
}

class AbstractAPI {
    protected static $guard = null;
    protected static $dataModel = null;

    protected static function defineApi() {

    }

    public static function dataModel() {
        if (API::$dataModel === null) {
            API::defineApi();
        }
        return API::$dataModel;
    }

    public static function guard() {
        if (API::$guard === null) {
            API::defineApi();
        }
        return API::$guard;
    }
}

?>