<?php

include_once __DIR__ . '/FlexAPI.php';
include_once __DIR__ . '/requestutils/jwt.php';
include_once __DIR__ . '/../../bensteffen/bs-php-utils/utils.php';

header("Content-Type: multipart/form-data; charset=UTF-8");

try {

    $response = [
        'serverity' => 1,
        'message' => "Upload successful",
        'code' => 200
    ];

    if (!count($_FILES)) {
        throw(new Exception("No files found in upoad-request", 400));
    }

    $file = $_FILES['file'];
    $fileName = $file['name'];

    if (array_key_exists('mimeType', $_POST)) {
        $file['type'] = $_POST['mimeType'];
    }

    $formatFolders = [
        'application/gpx+xml' => 'gps/',
        'image/jpeg' => 'img/'
    ];

    $resourcePath = $formatFolders[$file['type']];

    $rootFolder = FlexAPI::get('appRoot');
    $uploadFolder = FlexAPI::get('uploadFolder');

    $resourcePath = $uploadFolder.'/'.FlexAPI::guard()->getUsername().'/'.$resourcePath;
    $completePath = $rootFolder.$resourcePath;
    if (!is_dir($completePath)) {
        mkdir($completePath, 0777, true);
    }
    $saveName = time()."--".$fileName;
    $savePath = $completePath.$saveName;
    rename($file['tmp_name'], $savePath);

    $id = FlexAPI::dataModel()->insert('upload', [
        'name' => $fileName,
        'mimeType' => $file['type'],
        'path' => $resourcePath.$saveName,
        'source' => FlexAPI::get('appPath').$resourcePath.$saveName
    ], assocIgnore($_GET, ['format', 'filename']));

    $response['id'] = $id;
    echo jsenc($response);

} catch (Exception $exc) {
    echo jsenc([
        'serverity' => 3,
        'message' => $exc->getMessage(),
        'code' => $exc->getCode()
    ]);
}

