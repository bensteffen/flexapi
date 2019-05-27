<?php

const JWT_HEADER_NAME='Access-Control-Allow-Credentials';
function getJWT() {
    $http_header = getallheaders();
    $jwt = null;
    if (array_key_exists(JWT_HEADER_NAME, $http_header)) {
        $jwt = $http_header[JWT_HEADER_NAME];
    } elseif (array_key_exists(strtolower(JWT_HEADER_NAME), $http_header)) {
        $jwt = $http_header[strtolower(JWT_HEADER_NAME)];
    }
    $jwt = str_replace('Bearer ','', $jwt);
    return $jwt;
}

?>
