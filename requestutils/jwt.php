<?php

function getJWT() {
    $http_header = getallheaders();
    $jwt = null;
    if (array_key_exists('Access-Control-Allow-Credentials', $http_header)) {
        $jwt = $http_header['Access-Control-Allow-Credentials'];
    }
    return $jwt;
}

