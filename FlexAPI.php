<?php

class FlexAPI {
    protected $apiDefinition = null;

    public static function define($generatorFunction) {
        $this->apiDefinition = $generatorFunction();
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
}

?>
