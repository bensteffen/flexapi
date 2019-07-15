<?php

include_once __DIR__ . '/FlexAPI.php';
include_once __DIR__ . '/requestutils/jwt.php';

function flexapiPortal() {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'OPTIONS') {
        echo "";
        die;
    }
    
    try {
        $request = (array) json_decode(file_get_contents("php://input"));
        $response = [];
    
        $methodOk = false;
        if ($method === 'GET' && array_key_exists('verify', $_GET)) {
            $request = [
                'concern' => 'verify',
                'token' => $_GET['verify']
            ];
            $methodOk = true;
        } elseif ($method === 'POST') {
            $methodOk = true;
        }
    
        if (!$methodOk) {
            throw(new Exception("Wrong method for portal.", 400));
        }
    
        if ($request["concern"] === "login") {
            $token = FlexAPI::guard()->login($request);
            $response = [
                "message" => "Login sucessfull",
                "token" => $token,
            ];
        // TODO: add concern "verify (registration)"
        } elseif ($request["concern"] === "logout") {
            FlexAPI::guard()->logout(getJWT());
            $response = ["message" => "Logout sucessfull"];
    
        } elseif ($request["concern"] === "register") {
            FlexAPI::sendEvent([
                'eventId' => 'before-user-registration',
                'request' => $request
            ]);
    
            $verificationData = FlexAPI::guard()->registerUser($request['username'], $request['password']);
    
            FlexAPI::sendEvent([
                'eventId' => 'after-user-registration',
                'request' => $request
            ]);
            $response = ["message" => "User was created."];
            $response = array_merge($response, $verificationData);
        } elseif ($request["concern"] === "unregister") {
            FlexAPI::sendEvent([
                'eventId' => 'before-user-unregistration',
                'username' => $request['username']
            ]);
    
            FlexAPI::guard()->unregisterUser($request['username'], $request['password']);
    
            FlexAPI::sendEvent([
                'eventId' => 'after-user-unregistration',
                'username' => $request['username']
            ]);
            $response = ["message" => "User was deleted."];
        } elseif ($request['concern'] === 'verify') {
            FlexAPI::guard()->verifyUser($request['token']);
            $response = ["message" => "Account was verfified."];
        } elseif ($request["concern"] === "publish") {
            
        } else {
            throw(new Exception("Unknown concern ".$request['concern'].".", 401));
        }

        $response['code'] = 200;

    } catch (Exception $exc) {
        http_response_code($exc->getCode());
        $msg = "Could not process ".$request['concern'].": " . $exc->getMessage();
        $response = [
            'message' => $msg,
            "code" => $exc->getCode()
        ];
    }
    
    http_response_code($response['code']);
    return $response;
}


