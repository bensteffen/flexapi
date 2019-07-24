<?php

include_once __DIR__ .'/IfJwtService.php';

require __DIR__ . '/../../../../autoload.php';
use \Firebase\JWT\JWT;
// use \Exception as Exception;

class FirebaseJwtService implements IfJwtService {
    protected $secret;
    protected $algorithm;

    public function __construct($secret, $algorithm = 'HS512') {
        $this->secret = $secret;
        $this->algorithm = $algorithm;
    }

    public function encode($data) {
        $tokenId  = bin2hex(openssl_random_pseudo_bytes(32));
        $issuedAt = time();
        $data = setFieldDefault($data, 'validity', 2*3600);
        $validity = $data['validity'];
        $data = setFieldDefault($data, 'expires', $issuedAt + $validity);

        $jwtData = [
            'iat'  => $issuedAt,         // Issued at: time when the token was generated
            'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
            'exp'  => $data['expires'],           // Expire
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
