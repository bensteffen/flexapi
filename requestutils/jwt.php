<?php

function getJWT() {
    $http_header = getallheaders();
    $jwt = null;
    if (array_key_exists('Authorization', $http_header)) {
        list($jwt) = sscanf($http_header['Authorization'], 'Bearer %s');
    }
    return $jwt;
}

?>