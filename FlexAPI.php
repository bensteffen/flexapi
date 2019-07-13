<?php

include_once  __DIR__ . '/crud.php';
include_once  __DIR__ . '/portal.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class FlexAPI {
    protected static $apiDefinition = null;
    protected static $apiSettings = null;
    protected static $setupCallback = null;
    protected static $callbackRegister = [];

    public static function crud() {
        return jsenc(flexapiCrud());
    }

    public static function portal() {
        return jsenc(flexapiPortal());
    }

    public static function define($generatorFunction) {
        FlexAPI::$apiDefinition = $generatorFunction();

        $factory = FlexAPI::$apiDefinition['factory'];

        $connection = FlexAPI::$apiDefinition['connection'];
        $guard = FlexAPI::$apiDefinition['guard'];
        FlexAPI::$apiDefinition['dataModel'] = $factory->createDataModel($connection, $guard);
        $voidGuard = new VoidGuard();
        FlexAPI::$apiDefinition['superAccess'] = $factory->createDataModel($connection, $voidGuard);

        FlexAPI::sendEvent([
            'eventId' => 'api-defined'
        ]);
    }

    public static function setup($request) {
        if (FlexAPI::$setupCallback !== null) {
            $setupCallback = FlexAPI::$setupCallback;
            $setupCallback($request);
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

    public static function superAccess() {
        if (FlexAPI::$apiDefinition === null) {
            throw(new Exception('Get data model: FlexAPI not defined yet.', 500));
        }
        return FlexAPI::$apiDefinition['superAccess'];
    }

    public static function guard() {
        if (FlexAPI::$apiDefinition === null) {
            throw(new Exception('Get guard: FlexAPI not defined yet.', 500));
        }
        return FlexAPI::$apiDefinition['guard'];
    }

    public static function setConfig($configName) {
        // $config = (array) json_decode(file_get_contents(__DIR__."/../../../$configName.conf.json"), true);
        $config = include('api.config.php');
        // if (!array_key_exists('environment', $config)) {
        //     throw(new Exception('FlexAPI-Congiguration must contain entry "environment".', 400));
        // }
        // $environmentName = $config['environment'];
        // $environment = (array) json_decode(file_get_contents(__DIR__."/../../../$environmentName.env.json"), true);
        // FlexAPI::$apiSettings = array_merge($config, $environment);
        FlexAPI::$apiSettings = $config;
    }

    public static function set($name, $value) {
        if (FlexAPI::$apiSettings === null) {
            throw(new Exception('Connot set configuration; API not configured, yet.', 400));
        }
        FlexAPI::$apiSettings[$name] = $value;
    }

    public static function get($name) {
        if (FlexAPI::$apiSettings === null) {
            throw(new Exception('Connot get configuration; API not configured, yet.', 400));
        }
        if (!array_key_exists($name, FlexAPI::$apiSettings)) {
            throw(new Exception("Could not find FlexAPI-configuration '$name'", 500));
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

    public static function navigateTo($url) {
        header('Content-Type: text/html');
        echo '<script>window.location.href="'.$url.'"</script>';
        die;
    }

    public static function sendMail($data) {
        $settings = FlexAPI::get('mailing');
        
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 2;            // Enable verbose debug output
        $mail->isSMTP();
        $mail->Host = $settings['smtp']['host'];
        $mail->Port = $settings['smtp']['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['smtp']['username'];
        $mail->Password = $settings['smtp']['password'];
        // $mail->SMTPSecure = 'starttls'; // Enable TLS encryption, `ssl` also accepted
    
        $from = $settings['from'][$data['from']];
        $mail->setFrom($from['address'], $from['name']);

        $addresses = $data['to'];
        if (!is_array($addresses)) {
            $addresses = [$addresses];
        }
        foreach ($addresses as $address) {
            $mail->addAddress($address, '');
        }

        $data = setFieldDefault($data, 'altBody', '');

        $mail->isHTML(true);
        $mail->Subject = $data['subject'];
        $mail->Body    = $data['body'];
        $mail->AltBody = $data['altBody']; // for non-HTML clients
    
        $mail->send();
    }

}

