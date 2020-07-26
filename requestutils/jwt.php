<?php

const JWT_HEADER_NAME='Access-Control-Allow-Credentials';
function getJWT() {
    if (!function_exists('getallheaders')) {
        function getallheaders() {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
            return $headers;
        }
    }
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
