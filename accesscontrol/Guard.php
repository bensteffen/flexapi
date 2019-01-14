<?php

require __DIR__ . '/../../../autoload.php';
use \Firebase\JWT\JWT;

abstract class Guard {
    protected $protectDataModel;
    protected $username = null;
    protected $userAccessLevel = null;
    protected $tokenValidity = 3600; // 1 hour

    protected static $JWT_SECRET = "itsVeryRainy2Day!";

    public function setTokenValidity($tokenValidity) {
        $this->tokenValidity = $tokenValidity;
    }

    public function userAccess() {
        if (!$this->isLoggedIn()) {
            throw(new Exception("No user is logged in.", 403));
            // return 0;
        } else {
            return $this->getUserAccesLevel();
        }
    }

    public function getUsername() {
        return $this->username;
    }

    public function userCanRead() {
        return $this->userAccess() > 0;
    }

    public function userCanInsert() {
        return $this->userAccess() > 1;
    }

    public function userCanUpdate() {
        return $this->userAccess() > 2;
    }

    public function userCanDelete() {
        return $this->userAccess() > 3;
    }

    public function userNeedsPermission() {
        return $this->userAccess() < 5;
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

    protected function createJWT($validity /*in seconds*/) {
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
            base64_decode(Guard::$JWT_SECRET), // The signing key
            'HS512'     // Algorithm used to sign the token, see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40#section-3
        );
        return $token;
    }

    protected function decodeJWT($jwt) {
        try {
            $token = (array) JWT::decode($jwt, base64_decode(Guard::$JWT_SECRET), array('HS512'));
        } catch (Exception $exc) {
            throw(new Exception("Invalid authentication: ".$exc->getMessage(), 401));
        }
        return $token;
    }

    public abstract function login($authentification);
    // $authentification can be ['username' => '...', 'password' => '...'] or a JWT
    // method sets $this->username

    public abstract function logout($jwt);

    public abstract function publish($rootEntityName, $filter, $accessLevel, $guestPassword);

    public abstract function isLoggedIn();

    public abstract function deliverPermitted($connection, $entity, $filter, $selection);

    protected abstract function getUserAccesLevel();

    protected abstract function onModelConnection();

    protected abstract function onNewEntity($entityName);

}

?>
