<?php

include_once __DIR__ . '/api.php';
include_once __DIR__ . '/requestutils/jwt.php';

header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');
// header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    echo "";
    die;
}

try {
    if ($method !== 'POST') {
        throw(new Exception("Wrong method for portal. Must be POST", 400));
    }

    $request = (array) json_decode(file_get_contents("php://input"));

    if ($request["concern"] === "login") {
        $token = API::guard()->login($request);
        $response = [
            "message" => "Login sucessfull",
            "token" => $token,
        ];

    } elseif ($request["concern"] === "logout") {
        API::guard()->logout(getJWT());
        $response = ["message" => "Logout sucessfull"];

    } elseif ($request["concern"] === "register") {
        API::guard()->registerUser($request['username'], $request['password'], $request['accessLevel']);
        $response = ["message" => "User was created."];

    } elseif ($request["concern"] === "publish") {
        
    } else {
        throw(new Exception("Unknown concern ".$request['concern'].".", 401));
    }

} catch (Exception $exc) {
    http_response_code($exc->getCode());
    $msg = "Could not process ".$request['concern'].": " . $exc->getMessage();
    echo jsenc([
        'message' => $msg,
        "code" => $exc->getCode()
    ]);
    die;
}

http_response_code(200);
$response['code'] = 200;
echo jsenc($response);

?>
