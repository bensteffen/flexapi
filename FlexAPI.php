<?php

class FlexAPI {
    protected static $apiDefinition = null;
    protected static $apiSettings = [];
    protected static $setupCallback = null;
    protected static $callbackRegister = [];

    public static function define($generatorFunction) {
        FlexAPI::$apiDefinition = $generatorFunction();
    }

    public static function setup() {
        if (FlexAPI::$setupCallback !== null) {
            $setupCallback = FlexAPI::$setupCallback;
            $setupCallback();
        }
    }

    public static function onSetup($setupCallback) {
        FlexAPI::$setupCallback = $setupCallback;
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
        if (!array_key_exists($name, FlexAPI::$apiSettings)) {
            return FlexAPI::getDefault($name);
        }
        return FlexAPI::$apiSettings[$name];
    }

    public static function onEvent($eventId, $callback) {
        FlexAPI::$callbackRegister = setFieldDefault(FlexAPI::$callbackRegister, $eventId, []);
        array_push(FlexAPI::$callbackRegister[$eventId], $callback);
    }

    public static function sendEvent($event) {
        if (!array_key_exists('eventId', $event)) {
            throw(new Exception('FlexAPI event must contain a field "eventId".', 500));
        }
        $eventId = $event['eventId'];
        if (array_key_exists($eventId, FlexAPI::$callbackRegister)) {
            $callbacks = FlexAPI::$callbackRegister[$eventId];
            foreach ($callbacks as $callback) {
                $callback($event);
            }
        }
    }

    private static function getDefault($name) {
        $defaults = [
            'registerAccessLevel' => 4,
            'jwtValidityDuration' => 3600 /* seconds */
        ];
        if (!array_key_exists($name, $defaults)) {
            throw(new Exception("No default value found for '$name'"));
        }
        return $defaults[$name];
    }


}

