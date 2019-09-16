<?php

include_once __DIR__ . '/IfTokenService.php';

class AlphaNumericTokenService implements IfTokenService {

    private $length;
    private $digits = '0123456789';
    private $uppers = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    private $lowers = 'abcdefghijklmnopqrstuvwxyz';
    private $include = ['digits', 'upperCaseLetters'];

    public function __construct($length = 6) {
        $this->length = $length;
    }

    public function generate() {
        $tokenSet = '';
        if (in_array('digits', $this->include)) {
            $tokenSet .= $this->digits;
        }
        if (in_array('upperCaseLetters', $this->include)) {
            $tokenSet .= $this->uppers;
        }
        if (in_array('lowerCaseLetters', $this->include)) {
            $tokenSet .= $this->lowers;
        }

        $token = '';
        for($i = 0; $i < $this->length; $i++) {
            $r = rand(0, strlen($tokenSet) - 1);
            $token .= substr($tokenSet, $r, 1);
        }

        return $token;
    }
}