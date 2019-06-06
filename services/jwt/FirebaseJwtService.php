<?php

include_once __DIR__ .'/IfJwtService.php';

require __DIR__ . '/../../../../autoload.php';
use \Firebase\JWT\JWT;
// use \Exception as Exception;

class FirebaseJwtService implements IfJwtService {
    protected $validityDuration;
    protected $algorithm;

    public function __construct($secret, $validityDuration = 3600, $algorithm = 'HS512') {
        $this->secret = $secret;
        $this->validityDuration = $validityDuration;
        $this->algorithm = $algorithm;
    }

    public function encode($data) {
        $validity = $this->validityDuration;
        $tokenId  = bin2hex(openssl_random_pseudo_bytes(32));
        $issuedAt = time();
        $expire   = $issuedAt + $validity;            // Adding session length in seconds

        $jwtData = [
            'iat'  => $issuedAt,         // Issued at: time when the token was generated
            'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
            'exp'  => $expire,           // Expire
            'data' => $data['payload']
        ];

        return JWT::encode(
            $jwtData,            //Data to be encoded in the JWT
            $this->secret, // The signing key
            // base64_decode($this->secret), // The signing key
            $this->algorithm    // Algorithm used to sign the token, see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40#section-3
        );
    }

    public function decode($token) {
        return (array) JWT::decode($token, $this->secret, array($this->algorithm));
    }
}
