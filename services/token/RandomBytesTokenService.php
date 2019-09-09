<?php

include_once __DIR__ . '/IfTokenService.php';

class RandomBytesTokenService implements IfTokenService {

    private $length;

    public function __construct($length = 8) {
        $this->length = $length;
    }

    public function generate() {
        return bin2hex(openssl_random_pseudo_bytes($this->length));
        // return bin2hex(random_bytes($this->length));
    }
}