<?php

include_once __DIR__ . '/../Guard.php';
include_once __DIR__ . '/../../datamodel/DataModel.php';
include_once __DIR__ . '/../../database/FilterParser.php';
include_once __DIR__ . '/User.php';
include_once __DIR__ . '/Permission.php';
include_once __DIR__ . '/JwtBlackList.php';
include_once __DIR__ . '/../../../bs-php-utils/utils.php';

class ACLGuard extends Guard {
    protected $acModel;

    public function __construct($connection) {
        $this->acModel = new DataModel();
        $this->acModel->setConnection($connection);
        $this->acModel->addEntity(new User());
        $this->acModel->addEntity(new JwtBlackList());
    }

    public function registerUser($username, $password, $accessLevel) {
        if (count($this->acModel->read('user', ['filter' => ['name' => $username]])) > 0) {
            throw(new Exception("User with name '$username' already exists.", 400));
        }
        $this->acModel->insert('user', [
            'name' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'accessLevel' => $accessLevel
        ]);
    }

    public function login($auth /*username and password OR JWT*/) {
        $this->username = null;
        if (is_array($auth)) {
            $result = $this->acModel->read('user', [
                'filter' => [ 'name' => $auth['username'] ],
                'flatten' => 'singleResult'
            ]);
            if (!password_verify($auth['password'], $result['password'])) {
                throw(new Exception("Bad user name or password.", 401));
            }
            $this->username = $result['name'];
            $this->userAccessLevel = $result['accessLevel'];
            return $this->createJWT($this->tokenValidity);
        } else {
            $result = $this->acModel->read('jwtblacklist', [
                'filter' => ['jwt' => $auth]
            ]);
            if (count($result) > 0) {
                throw(new Exception("Authentication not valid anymore.", 401));
            }
            $token = $this->decodeJWT($auth);
            $payload = (array) $token['data']; 
            
            $result = $this->acModel->read('user', [
                'filter' => [ 'name' => $payload['user'] ],
                'selection' => ['accessLevel'],
                'flatten' => 'singleResult'
            ]);
            $this->username = $payload['user'];
            $this->userAccessLevel = $result['accessLevel'];

            return $auth;
        }
    }

    public function logout($jwt) {
        $token = $this->decodeJWT($jwt);
        $payload = (array) $token['data']; 
        $this->acModel->insert('jwtblacklist',[
            'jwt' => $jwt,
            'expire' => $payload['expire']
        ]);
    }

    public function isLoggedIn() {
        return $this->username !== null;
    }

    public function deliverPermitted($connection, $entity, $filter, $selection) {
        $entityName = $entity->getName();
        $keyName = $entity->uniqueKey();

        if (count($selection) === 0) {
            $selection = $entity->fieldNames();
        }

        if (!$keyName) {
            throw(new Exception("A permission can only be attached to entities with exactly 1 primary key.", 400));
        }

        $permissionRef = [[
            'referencingEntity' => $entityName,
            'referencedEntity' => 'permission',
            'referenceField' => 'permission',
            'referenceCondition' => null
        ]];

        $userEq = new QueryCondition(
            new QueryColumn('user', 'permission', null, ['ACL' => $permissionRef ]),
            new QueryValue($this->getUsername())
        );
        $entityEq = new QueryCondition(
            new QueryColumn('entityName', 'permission', null, ['ACL' => $permissionRef ]),
            new QueryValue($entityName)
        );
        $keyEq = new QueryCondition(
            new QueryColumn('entityId', 'permission', null, ['ACL' => $permissionRef ]),
            new QueryColumn($keyName)
        );

        $permissionRef[0]['referenceCondition'] = new QueryAnd(new QueryAnd($userEq, $entityEq), $keyEq);
        $filter['references']['ACL'] = $permissionRef;

        return $connection->readFromDataBase($entity, $filter, $selection);
    }

    public function publish($rootEntityName, $filter, $accessLevel, $guestPassword) {
        $resourcesList= $this->protectDataModel->listResources($rootEntityName, $filter);
        
    }

    protected function getUserAccesLevel() {
        return $this->userAccessLevel;
    }

    protected function onModelConnection() {
        $this->protectDataModel->addEntity(new Permission());
    }

    protected function onNewEntity($entityName) {
        if ($this->protectDataModel !== null) {
            $this->protectDataModel->addObservation([
                'observer' => $this->protectDataModel->getEntity('permission'),
                'subjectName' => $entityName,
                'context' => 'onInsert',
                'priority' => "high"
            ]);
        }
    }
}

?>
