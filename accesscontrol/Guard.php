<?php

require __DIR__ . '/../../../autoload.php';
use \Firebase\JWT\JWT;

abstract class Guard {
    protected $protectDataModel;
    protected $username = null;
    protected $userAccessLevel = null;
    protected $tokenValidity = 3600; // [seconds]

    protected static $JWT_SECRET = "itsVeryRainy2Day!";

    public function setTokenValidity($tokenValidity) {
        $this->tokenValidity = $tokenValidity;
    }

    public function getUsername() {
        return $this->username;
    }

    public function addEntityToProtect($entityName) {
        $this->onNewEntity($entityName);
    }

    public function setModelToProtect($dataModel) {
        $this->protectDataModel = $dataModel;
        $this->onModelConnection();
        foreach($this->protectDataModel->getEntityNames() as $entityName) {
            $this->addEntityToProtect($entityName);
        }
    }

    protected function createJWT() {
        $validity = FlexAPI::get('jwtValidityDuration');
        $tokenId  = bin2hex(openssl_random_pseudo_bytes(32));
        $issuedAt = time();
        $expire   = $issuedAt + $validity;            // Adding session length in seconds

        $data = [
            'iat'  => $issuedAt,         // Issued at: time when the token was generated
            'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
            'exp'  => $expire,           // Expire
            'data' => [
                'user' => $this->username,
                'isValid' => true
            ]
        ];

        $token = JWT::encode(
            $data,      //Data to be encoded in the JWT
            base64_decode($this->getJwtSecret()), // The signing key
            'HS512'     // Algorithm used to sign the token, see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40#section-3
        );
        return $token;
    }

    protected function decodeJWT($jwt) {
        try {
            $token = (array) JWT::decode($jwt, base64_decode($this->getJwtSecret()), array('HS512'));
        } catch (Exception $exc) {
            throw(new Exception("Invalid authentication: ".$exc->getMessage(), 401));
        }
        return $token;
    }

    protected function getJwtSecret() {
        if (Guard::$JWT_SECRET === null) {
            $jwtConf = (array) json_decode(file_get_contents(__DIR__."/jwt.json"), true);
            Guard::$JWT_SECRET = $jwtConf['secret'];
        }
        return Guard::$JWT_SECRET;
    }

    public abstract function login($authentification);
    // $authentification can be ['username' => '...', 'password' => '...'] or a JWT
    // method sets $this->username

    public abstract function logout($jwt);

    public abstract function publish($rootEntityName, $filter, $accessLevel, $guestPassword);

    public abstract function isLoggedIn();

    public abstract function userMay($method, $entiyName);

    public abstract function permissionsNeeded($entityName);

    public abstract function readPermitted($connection, $entity, $filter, $selection);

    public abstract function updatePermitted($connection, $entity, $filter, $data);

    public abstract function deletePermitted($connection, $entity, $filter);

    protected abstract function getUserAccesLevel();

    protected abstract function onModelConnection();

    protected abstract function onNewEntity($entityName);

}

