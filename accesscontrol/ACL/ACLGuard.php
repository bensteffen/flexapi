<?php

include_once __DIR__ . '/../Guard.php';
include_once __DIR__ . '/../../datamodel/DataModel.php';
include_once __DIR__ . '/User.php';
include_once __DIR__ . '/RoleAssignment.php';
include_once __DIR__ . '/CrudPermission.php';
include_once __DIR__ . '/Permission.php';
include_once __DIR__ . '/JwtBlackList.php';
include_once __DIR__ . '/PasswordChange.php';
include_once __DIR__ . '/../../services/jwt/FirebaseJwtService.php';
include_once __DIR__ . '/../../../../bensteffen/bs-php-utils/utils.php';

class ACLGuard extends Guard {
    protected $acModel;
    protected $permission;
    protected $userRoles;
    protected $crudPermissions;

    public function __construct($connection, $jwtService = null, $verificationService = null) {
        $this->permission = new Permission();
        $this->permission->setGuard($this);

        $this->acModel = new DataModel();
        $this->acModel->setConnection($connection);
        $this->acModel->addEntity(new User());
        $this->acModel->addEntity($this->permission);
        $this->acModel->addEntity(new JwtBlackList());
        $this->acModel->addEntity(new PasswordChange());
        $this->acModel->addEntity(new RoleAssignment());
        $this->acModel->addEntity(new CrudPermission());

        if (!$jwtService) {
            $jwtService = new FirebaseJwtService(FlexAPI::get('jwtSecret'));
        }
        $this->jwtService = $jwtService;

        $this->verificationService = $verificationService;
        if ($this->verificationService) {
            $this->verificationService->setDataModel($this->acModel);
        }
    }

    public function reset() {
        $this->acModel->reset();
    }

    public function registerUser($username, $password, $verificationEnabled = null) {
        $userExists = $this->acModel->read('user', ['filter' => ['name' => $username]]);
        if ($userExists) {
            throw(new Exception("User with name '$username' already exists.", 400));
        }

        if ($verificationEnabled === null) {
            $verification = FlexAPI::get('userVerification');
            $verificationEnabled = $verification['enabled'];
        }

        $verificationData = [];
        if ($verificationEnabled) {
            $verificationData = $this->verificationService->startVerification([
                'username' => $username,
                'address' => $username
            ]);
        }

        $this->acModel->insert('user', [
            'name' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'isVerified' => !$verificationEnabled
        ]);

        return $verificationData;
    }

    public function verifyUser($data) {
        $responseData = $this->verificationService->finishVerification($data);
        if ($responseData['verificationSuccessfull']) {
            $this->acModel->update('user', [
                'name' => $responseData['username'],
                'isVerified' => true
            ]);
        }
        FlexAPI::sendEvent([
            'eventId' => 'after-user-verification',
            'response' => $responseData
        ]);
        return $responseData;
    }

    public function unregisterUser($username, $password) {
        // TODO
        $this->login([
            'username' => $username,
            'password' => $password
        ]);
        foreach ($this->getUserRoles() as $role) {
            $this->withdrawRole($role, $this->username);
        }
        $this->acModel->delete('user', ['name' => $this->username]);
        $this->acModel->delete('permission', ['user' => $this->username]);
    }

    public function changePassword($auth, $newPassword) {
        $this->login($auth);
        $this->acModel->update('user', [
            'name' => $this->username,
            'password' => password_hash($newPassword, PASSWORD_DEFAULT)
        ]);
        if (is_string($auth)) { // if $auth was JWT, void old JWT and return new JWT
            $this->logout($auth);
            return $this->login([
                'username' => $this->username,
                'password' => $newPassword
            ]);
        }
        return null;
    }

    public function requestPasswordChange($email, $newPassword) {
        $validity = FlexAPI::get('passwordChangeValidityDuration');
        $token = $this->jwtService->encode([
            'validity' => $validity,
            'payload' => [ 'user' => $email, 'newPassword' => $newPassword ]
        ]);
        $this->acModel->insert('passwordchange', [
            'expires' => time() + $validity,
            'token' => $token
        ]);
        $url = FlexAPI::get('frontendBaseUrl').'/#/passwordChangeToken/'. $token;

        FlexAPI::sendMail([
            'from' => 'verification',
            'to' => $email,
            'subject' => 'Passwort vergesssen?',
            'body' => sprintf(
                'Hallo,<br>'.
                'wenn Du eine Passwort&auml;nderung beauftragt hast, klicke bitte hier  <a href="%s">hier</a>, um die Passwort&auml;nderung vom ADFC Tempo30 vor sozialen Einrichtungen abzuschlie&szlig;en.',
                $url
            )
        ]);
    }

    public function finishPasswordChange($jwt) {
        $decoded = $this->jwtService->decode($jwt);
        $payload = (array) $decoded['data'];
        $this->acModel->update('user', [
            'name' => $payload['user'],
            'password' => password_hash($payload['newPassword'], PASSWORD_DEFAULT)
        ]);
        $this->acModel->delete('passwordchange', [ 'jwt' => $jwt ]);
    }

    public function login($auth /*username and password OR JWT*/) {
        $this->username = null;
        if (is_array($auth)) {
            $result = $this->acModel->read('user', [
                'filter' => [ 'name' => $auth['username'] ],
                'flatten' => 'singleResult'
            ]);
            if (!$result['isVerified']) {
                throw(new Exception("Account is not verified, yet.", 401));
            }
            if (!password_verify($auth['password'], $result['password'])) {
                throw(new Exception("Bad user name or password.", 401));
            }
            $this->username = $result['name'];
            $this->crudPermissions = $this->getCrudPermissions();

            return $this->jwtService->encode([
                'validity' => FlexAPI::get('jwtValidityDuration'),
                'payload' => [ 'username' => $this->username, 'purpose' => 'authentification' ]
            ]);
        } else {
            $jwtInBlacklist = $this->acModel->read('jwtblacklist', ['filter' => ['jwt' => $auth]]);
            if ($jwtInBlacklist) {
                throw(new Exception("Authentication not valid anymore.", 401));
            }
            try {
                $decoded = $this->jwtService->decode($auth);
            } catch (Exception $exc) {
                throw(new Exception("Invalid authentication: ".$exc->getMessage(), 401));
            }
            $payload = (array) $decoded['data'];

            $this->username = $payload['username'];
            $this->crudPermissions = $this->getCrudPermissions();

            return $auth;
        }
    }

    public function extendLogin($jwt) {
        $decoded = $this->jwtService->decode($jwt);
        $payload = (array) $decoded['data'];
        if (!array_key_exists('purpose', $payload) || $payload['purpose'] !== 'authentification') {
            throw(new Exception("Given token can not be extended.", 400));
        }
        $extension = FlexAPI::get('jwtValidityExtension');
        if ($decoded['exp'] - time() < $extension) {
            return $this->jwtService->encode([
                'validity' => $extension,
                'payload' => [ 'username' => $payload['username'], 'purpose' => 'authentification' ]
            ]);
        }
        return $jwt;
    }

    public function logout($jwt) {
        $token = $this->jwtService->decode($jwt);
        $this->acModel->insert('jwtblacklist',[
            'jwt' => $jwt,
            'expire' => $token['exp']
        ]);
    }

    public function isLoggedIn() {
        return $this->username !== null;
    }

    public function assignRole($roleName, $userName) {
        $this->acModel->insert('roleassignment', [
            'role' => $roleName,
            'user' => $userName
        ]);
    }

    public function withdrawRole($roleName, $userName) {
        $this->acModel->delete('roleassignment', [
            'role' => $roleName,
            'user' => $userName
        ]);
    }

    public function getUserRoles() {
        return $this->acModel->read('roleassignment', [
            'filter' => ['user' => $this->username],
            'selection' => ['role'],
            'flatten' => 'singleField',
            'emptyResult' => []
        ]);
    }

    public function getCrudPermissions() {
        $permissions = [];
        foreach ($this->protectDataModel->getEntityNames() as $entityName) {
            $permissions[$entityName] = [
                'create' => false,
                'read' => false,
                'update' => false,
                'delete' => false,
                'permissionNeeded' => true
            ];
        }
        $userRoles = $this->getUserRoles();
        foreach($userRoles as $role) {
            $pemissionForRole = $this->acModel->read('crudpermission', [
                'filter' => ['role' => $role],
                'emptyResult' => []
            ]);
            foreach($pemissionForRole as $p) {
                foreach(['create', 'read', 'update', 'delete'] as $method) {
                    $may = $permissions[$p['entity']][$method] || $p[$method];
                    $permissions[$p['entity']][$method] = $may;
                }
                $flag = !$permissions[$p['entity']]['permissionNeeded'] || !$p['permissionNeeded'];
                $permissions[$p['entity']]['permissionNeeded'] = !$flag;
            }
        }
        return $permissions;
    }

    public function allowCRUD($roleName, $methodString, $entityName, $permissionNeeded = true) {
        $crudPermission = ['role' => $roleName, 'entity' => $entityName, 'permissionNeeded' => $permissionNeeded];
        if (preg_match('/C/', $methodString)) {
            $crudPermission['create'] = true;
        }
        if (preg_match('/R/', $methodString)) {
            $crudPermission['read'] = true;
        }
        if (preg_match('/U/', $methodString)) {
            $crudPermission['update'] = true;
        }
        if (preg_match('/D/', $methodString)) {
            $crudPermission['delete'] = true;
        }
        $this->acModel->insert('crudpermission', $crudPermission);
    }

    public function allowPortal($method, $roleName) {
        $this->acModel->insert('portalpermission', [$method => true, 'role' => $roleName]);
    }

    public function userMay($method, $entityName) {
        switch ($method) {
            case 'create':
            case 'read':
            case 'update':
            case 'delete':
            return $this->crudPermissions[$entityName][$method];
            break;
            case 'register':
            case 'unregister':
            $this->acModel->read('portalpermission', [
                ''
            ]);
            break;
        }
    }

    public function permissionsNeeded($entityName) {
        return $this->crudPermissions[$entityName]['permissionNeeded'];
    }

    public function readPermitted($connection, $entity, $filter, $selection, $sort, $onlyOwn = false) {
        $filter = $this->addPermissionFilter('R', $entity, $filter, $onlyOwn);
        return $connection->readFromDataBase($entity, $filter, $selection, $onlyOwn, $sort);
    }

    public function updatePermitted($connection, $entity, $filter, $data) {
        $filter = $this->addPermissionFilter('U', $entity, $filter);
        if (!$connection->readFromDataBase($entity, $filter, $entity->primaryKeys())) {
            throw(new Exception('No resource to update found.', 400));
        }
        $connection->updateDataBase($entity, $data);
    }

    public function deletePermitted($connection, $entity, $filter) {
        $filter = $this->addPermissionFilter('D', $entity, $filter);
        $keyName = $entity->uniqueKey();
        $result = $connection->readFromDataBase($entity, $filter, [$keyName]);
        if (count($result) === 0) {
            throw(new Exception('No resource to delete found.', 400));
        }
        $ids = extractByKey($keyName, $result);
        $connection->deleteFromDatabase($entity, $filter);
        return $ids;
    }

    protected function addPermissionFilter($method, $entity, $filter, $onlyOwn = false) {
        $entityName = $entity->getName();
        $keyName = $entity->uniqueKey();

        if ($entityName === 'permission') {
            return $filter;
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

        $userCheck = new QueryCondition(
            new QueryColumn('user', 'permission', null, ['ACL' => $permissionRef ]),
            new QueryValue($this->getUsername())
        );

        $onlyOwn = true;
        if (!$onlyOwn) {
            $userRoles = $this->getUserRoles();
            if (count($userRoles) > 0) {
                $roleCheck = null;
                $itemA = $userCheck;
                foreach ($userRoles as $role) {
                    $itemB = new QueryCondition(
                        new QueryColumn('user', 'permission', null, ['ACL' => $permissionRef ]),
                        new QueryValue($role)
                    );
                    $roleCheck = new QueryOr($itemA, $itemB);
                    $itemA = $roleCheck;
                }
                $userCheck= new QueryGroup($roleCheck);
            }
        }

        $entityEq = new QueryCondition(
            new QueryColumn('entityName', 'permission', null, ['ACL' => $permissionRef ]),
            new QueryValue($entityName)
        );
        $keyEq = new QueryCondition(
            new QueryColumn('entityId', 'permission', null, ['ACL' => $permissionRef ]),
            new QueryColumn($keyName, $entityName)
        );
        $methodCheck = new QueryCondition(
            new QueryColumn('methods', 'permission', null, ['ACL' => $permissionRef ]),
            new QueryValue($method),
            'con'
        );

        $permissionRef[0]['referenceCondition'] = new QueryAnd(new QueryAnd(new QueryAnd($userCheck, $entityEq), $keyEq), $methodCheck);
        $filter['references']['ACL'] = $permissionRef;

        return $filter;
    }

    public function publish($rootEntityName, $filter, $accessLevel, $guestPassword) {
        $resourcesList= $this->protectDataModel->listResources($rootEntityName, $filter);
    }

    public function publishResource($username, $entityName, $entityId, $methods) {
        $this->acModel->insert('permission', [
            'key' => Permission::toPermissionKey($username, $entityName, $entityId),
            'user' => $username,
            'methods' => $methods,
            'entityName' => $entityName,
            'entityId' => $entityId
        ]);
    }

    protected function onModelConnection() {
        $this->permission->setProtectedDataModel($this->protectDataModel);
    }

    protected function onNewEntity($entityName) {
        if ($this->protectDataModel !== null) {
            $this->protectDataModel->addObservation([
                'observer' => $this->acModel->getEntity('permission'),
                'subjectName' => $entityName,
                'context' => ['onInsert', 'onDelete'],
                'priority' => "high"
            ]);
        }
    }
}
