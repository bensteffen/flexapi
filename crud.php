<?php

include_once __DIR__ . '/api.php';

include_once __DIR__ . '/requestutils/url.php';
include_once __DIR__ . '/requestutils/jwt.php';

header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    echo "";
    die;
}

try {
    // $jwt = getJWT();
    // if (!$jwt) {
    //     throw(new Exception('Missing JWT', 401));
    // }
    // API::guard()->login($jwt);
    API::guard()->login(['username' => 'floderflo', 'password' => '123']);
    // API::guard()->login(['username' => 'bensteffen', 'password' => 'abc']);
    // API::guard()->login(['username' => 'admin', 'password' => 'pw']);
    $parameters = parseUrlParameters($_GET);

    $response = [];

    switch ($method) {
        case 'GET':
            $response = API::dataModel()->read($parameters['entity'], [
                'filter'     => $parameters['filter'],
                'references' => $parameters['refs'],
                'selection'  => $parameters['select'],
                'flatten'    => $parameters['flatten']
            ]);
            break;
        case 'POST':
            $requestBody = (array) json_decode(file_get_contents("php://input"));
            $id = API::dataModel()->insert($parameters['entity'], $requestBody);
            $response = [
                'serverity' => 1,
                'message' => "Resource '".$parameters['entity']."' sucessfully created.",
                'id' => $id,
                'code' => 200
            ];
            break;
        case 'PUT':
            $requestBody = (array) json_decode(file_get_contents("php://input"));
            API::dataModel()->update($parameters['entity'], $requestBody);
            $response = [
                'serverity' => 1,
                'message' => "Resource '".$parameters['entity']."' sucessfully updated.",
                'code' => 200
            ];
            break;
        case 'DELETE':
            API::dataModel()->delete($parameters['entity'], $parameters['filter']);
            $response = [
                'serverity' => 1,
                'message' => "Resource '".$parameters['entity']."' sucessfully deleted.",
                'code' => 200
            ];
            break;
    }

    if ($response === null) {
        throw(new Exception('No data found.', 404));
    }

    echo jsenc($response);

} catch (Exception $exc) {
    http_response_code($exc->getCode());
    echo jsenc([
        'serverity' => 3,
        'message' => $exc->getMessage(),
        'code' => $exc->getCode()
    ]);
}

?>

