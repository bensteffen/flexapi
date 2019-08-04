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
        if ($method === 'GET') {
            if (array_key_exists('verify', $_GET)) {
                $request = [ 'concern' => 'verify', 'token' => $_GET['verify'] ];
                $methodOk = true;
            }
            if (array_key_exists('passwordChange', $_GET)) {
                $request = [ 'concern' => 'passwordChange', 'token' => $_GET['passwordChange'] ];
                $methodOk = true;
            }
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

        } elseif ($request["concern"] === "passwordChange") {

            if (array_key_exists('newPassword', $request)) {
                $jwt = getJWT();
                if ($jwt) { // password change for logged in users
                    $newToken = FlexAPI::guard()->changePassword($jwt, $request['newPassword']);
                    $response['message'] = 'Password changed.';
                    $response['token'] = $newToken;

                } elseif (array_key_exists('email', $request)) { // for password forgotten
                    FlexAPI::guard()->requestPasswordChange($request['email'], $request['newPassword']);
                    $response['message'] = 'Password change mail was sent.';
                } else {
                    throw(new Exception('Could not process password change due to missing parameters.', 400));
                }

            } elseif (array_key_exists('token', $request)) {
                FlexAPI::guard()->finishPasswordChange($request['token']);
                $response['message'] = 'Password changed.';

            } else {
                throw(new Exception('Could not process password change due to missing parameters.', 400));
            }
        } elseif ($request["concern"] === "publish") {
        } elseif ($request["concern"] === "roleAdministration") {
            FlexAPI::sendEvent([
                'eventId' => 'before-role-change',
                'request' => $request
            ]);
            if (array_key_exists('assignTo', $request)) {
                FlexAPI::guard()->assignRole($request['role'], $request['assignTo']);
            }
            if (array_key_exists('withdrawFrom', $request)) {
                FlexAPI::guard()->withdrawRole($request['role'], $request['withdrawFrom']);
            }
            FlexAPI::sendEvent([
                'eventId' => 'after-role-change',
                'request' => $request
            ]);
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


