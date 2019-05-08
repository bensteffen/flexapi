<?php

include_once __DIR__ . '/FlexAPI.php';

include_once __DIR__ . '/requestutils/url.php';
include_once __DIR__ . '/requestutils/jwt.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    echo "";
    die;
}

try {
    $response = [];
    $parameters = parseUrlParameters($_GET);

    switch ($method) {
        case 'GET':
            $response = FlexAPI::dataModel()->read($parameters['entity'], [
                'filter'     => $parameters['filter'],
                'references' => $parameters['refs'],
                'selection'  => $parameters['select'],
                'flatten'    => $parameters['flatten']
            ]);
            break;
        case 'POST':
            $requestBody = (array) json_decode(file_get_contents("php://input"));
            $id = FlexAPI::dataModel()->insert($parameters['entity'], $requestBody);
            $response = [
                'serverity' => 1,
                'message' => "Resource '".$parameters['entity']."' sucessfully created.",
                'id' => $id,
                'code' => 200
            ];
            break;
        case 'PUT':
            $requestBody = (array) json_decode(file_get_contents("php://input"));
            FlexAPI::dataModel()->update($parameters['entity'], $requestBody);
            $response = [
                'serverity' => 1,
                'message' => "Resource '".$parameters['entity']."' sucessfully updated.",
                'code' => 200
            ];
            break;
        case 'DELETE':
            FlexAPI::dataModel()->delete($parameters['entity'], $parameters['filter']);
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

