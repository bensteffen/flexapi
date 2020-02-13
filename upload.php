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
    $saveName = time()."--".$fileName;

    if (array_key_exists('mimeType', $_POST)) {
        $file['type'] = $_POST['mimeType'];
    }

    $formatFolders = [
        'application/gpx+xml' => '/gps',
        'image/jpeg' => '/img'
    ];

    $resourcePath = 
         FlexAPI::get('appPath')
        .FlexAPI::get('uploadFolder')
        .'/'.FlexAPI::guard()->getUsername()
        .$formatFolders[$file['type']];
    $absPath = $_SERVER['DOCUMENT_ROOT'].$resourcePath;

    if (!is_dir($absPath)) {
        mkdir($absPath, 0777, true);
    }
    $absPath .= '/'.$saveName;
    rename($file['tmp_name'], $absPath);

    $resourcePath .= '/'.$saveName;
    $upload = [
        'name' => $fileName,
        'mimeType' => $file['type'],
        'path' => $resourcePath,
        'source' => $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].$resourcePath
    ];

    $id = FlexAPI::dataModel()->insert('upload', $upload , assocIgnore($_GET, ['format', 'filename']));

    $response['id'] = $id;
    echo jsenc($response);

} catch (Exception $exc) {
    echo jsenc([
        'serverity' => 3,
        'message' => $exc->getMessage(),
        'code' => $exc->getCode()
    ]);
}

