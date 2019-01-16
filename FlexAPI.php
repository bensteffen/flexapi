<?php

class FlexAPI {
    protected static $apiDefinition = null;
    protected static $apiSettings = [];

    public static function define($generatorFunction) {
        FlexAPI::$apiDefinition = $generatorFunction();
    }

    public static function dataModel() {
        if (FlexAPI::$apiDefinition === null) {
            throw(new Exception('Get data model: FlexAPI not defined yet.', 500));
        }
        return FlexAPI::$apiDefinition['dataModel'];
    }

    public static function guard() {
        if (FlexAPI::$apiDefinition === null) {
            throw(new Exception('Get guard: FlexAPI not defined yet.', 500));
        }
        return FlexAPI::$apiDefinition['guard'];
    }

    public static function set($name, $value) {
        FlexAPI::$apiSettings[$name] = $value;
    }

    public static function get($name) {
        return FlexAPI::$apiSettings[$name];
    }
}

?>
