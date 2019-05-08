<?php

include_once __DIR__ . '/../Guard.php';
include_once __DIR__ . '/../../datamodel/DataModel.php';
include_once __DIR__ . '/../../database/FilterParser.php';
include_once __DIR__ . '/User.php';
include_once __DIR__ . '/RoleAssignment.php';
include_once __DIR__ . '/CrudPermission.php';
include_once __DIR__ . '/Permission.php';
include_once __DIR__ . '/JwtBlackList.php';
include_once __DIR__ . '/../../../bs-php-utils/utils.php';

class ACLGuard extends Guard {
    protected $acModel;
    protected $permission;
    protected $userRoles;
    protected $crudPermissions;

    public function __construct($connection) {
        $this->permission = new Permission();
        $this->permission->setGuard($this);

        $this->acModel = new DataModel();
        $this->acModel->setConnection($connection);
        $this->acModel->addEntity(new User());
        $this->acModel->addEntity($this->permission);
        $this->acModel->addEntity(new JwtBlackList());
        $this->acModel->addEntity(new RoleAssignment());
        $this->acModel->addEntity(new CrudPermission());
    }

    public function registerUser($username, $password) {
        $userExists = $this->acModel->read('user', ['filter' => ['name' => $username]]);
        if ($userExists) {
            throw(new Exception("User with name '$username' already exists.", 400));
        }
        $this->acModel->insert('user', [
            'name' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT)
        ]);
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
            $this->crudPermissions = $this->getCrudPermissions();

            return $this->createJWT();
        } else {
            $jwtInBlacklist = $this->acModel->read('jwtblacklist', ['filter' => ['jwt' => $auth]]);
            if ($jwtInBlacklist) {
                throw(new Exception("Authentication not valid anymore.", 401));
            }
            $token = $this->decodeJWT($auth);
            $payload = (array) $token['data']; 
            
            $this->username = $payload['user'];
            $this->crudPermissions = $this->getCrudPermissions();

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
                'filter' => ['role' => $role]
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

    public function readPermitted($connection, $entity, $filter, $selection, $onlyOwn = false) {
        $filter = $this->addPermissionFilter('R', $entity, $filter, $onlyOwn);
        return $connection->readFromDataBase($entity, $filter, $selection, true);
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

        $userRoles = $this->getUserRoles();

        $entityEq = new QueryCondition(
            new QueryColumn('entityName', 'permission', null, ['ACL' => $permissionRef ]),
            new QueryValue($entityName)
        );
        $keyEq = new QueryCondition(
            new QueryColumn('entityId', 'permission', null, ['ACL' => $permissionRef ]),
            new QueryColumn($keyName, $entityName)
        );
        $accessLevelCheck = new QueryCondition(
            new QueryColumn('methods', 'permission', null, ['ACL' => $permissionRef ]),
            new QueryValue($method),
            'con' // >=
        );

        $permissionRef[0]['referenceCondition'] = new QueryAnd(new QueryAnd(new QueryAnd($userCheck, $entityEq), $keyEq), $accessLevelCheck);
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
    

    protected function getUserAccesLevel() {
        return $this->userAccessLevel;
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

