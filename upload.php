<?php

include_once __DIR__ . '/FlexAPI.php';
include_once __DIR__ . '/requestutils/jwt.php';
include_once __DIR__ . '/../bs-php-utils/utils.php';

try {

    FlexAPI::guard()->login(['username' => 'floderflo', 'password' => '123']);
    // FlexAPI::guard()->login(['username' => 'bensteffen', 'password' => 'abc']);
    // FlexAPI::guard()->login(getJWT());

    $response = [
        'serverity' => 1,
        'message' => "Upload successful",
        'code' => 200
    ];

    $fileFormat = $_GET['format'];
    $fileName = $_GET['filename'];
    $formatFolders = [
        'gpx' => 'gps/',
        'jpg' => 'img/'
    ];

    $resourcePath = $formatFolders[$fileFormat];

    $rootFolder = "../../";
    $uploadFolder = "upload/";
    
    $file = file_get_contents("php://input");
    $tempName = tempnam($rootFolder.$uploadFolder,'');
    $fid = fopen($tempName, 'w');
        if (!$fid) {
            throw(new Exception("Couldn't create file", 500));
        }
        fwrite($fid, $file);
    fclose($fid);

    $resourcePath = $uploadFolder.FlexAPI::guard()->getUsername().'/'.$resourcePath;
    $completePath = $rootFolder.$resourcePath;
    if (!is_dir($completePath)) {
        mkdir($completePath, 0777, true);
    }
    $saveName = time()."--".$fileName;
    $savePath = $completePath.$saveName;
    rename($tempName, $savePath);

    $id = FlexAPI::dataModel()->insert('upload', [
        'name' => $fileName,
        'format' => $fileFormat,
        'source' => $resourcePath.$saveName
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

?>