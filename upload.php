<?php

include_once __DIR__ . '/FlexAPI.php';
include_once __DIR__ . '/requestutils/jwt.php';
include_once __DIR__ . '/../bs-php-utils/utils.php';

try {

    $response = [
        'serverity' => 1,
        'message' => "Upload successful",
        'code' => 200
    ];

    $fileName = $_POST['fileName'];
    $file = $_FILES['file'];

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
        'source' => $resourcePath.$saveName
    ], assocIgnore($_POST, ['format', 'filename']));

    $response['id'] = $id;
    echo jsenc($response); 

} catch (Exception $exc) {
    echo jsenc([
        'serverity' => 3,
        'message' => $exc->getMessage(),
        'code' => $exc->getCode()
    ]);
}

