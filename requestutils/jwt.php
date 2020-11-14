<?php

function getJWT($header_name = 'Authorization') {
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
    if (array_key_exists($header_name, $http_header)) {
        $jwt = $http_header[$header_name];
    } elseif (array_key_exists(strtolower($header_name), $http_header)) {
        $jwt = $http_header[strtolower($header_name)];
    }
    $jwt = str_replace('Bearer ','', $jwt);
    return $jwt;
}

?>
