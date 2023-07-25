<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library

 Copyright (c) 2013 - 2022 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file lgpl3-license.txt
 included in the packaging of this file.  Please review the following information
 to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
https://www.gnu.org/licenses/lgpl-3.0.txt

 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU LESSER GENERAL PUBLIC LICENSE version 3
			 https://www.gnu.org/licenses/lgpl-3.0.txt

END LICENSE AND COPYRIGHT
*/

use ZfExtended_Models_User as User;

/**
 * Handles authentication and password management
 * Main API to access the authenticated user (which has more roles than the underlying user object)
 */
final class ZfExtended_Authentication
{
    const LOGIN_STATUS_MAINTENANCE      = 'maintenance';
    const LOGIN_STATUS_SUCCESS          = 'success';
    const LOGIN_STATUS_AUTHENTICATED    = 'authenticated';
    const LOGIN_STATUS_REQUIRED         = 'required';
    const LOGIN_STATUS_OPENID           = 'openid';

    const AUTH_ALLOWED                  = 1;
    const AUTH_ALLOWED_LOAD             = 2;
    const AUTH_DENY_NO_SESSION          = 3;
    const AUTH_DENY_USER_NOT_FOUND      = 4;

    const APPLICATION_TOKEN_HEADER = 'Translate5AuthToken';

    //when updating from md5 to newer hash, the hashes containing old md5 hashes are marked with that prefix
    const COMPAT_PREFIX = 'md5:';

    /**
     * @var self|null
     */
    private static ?self $_instance = null;

    /**
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Checks if the authenticated user was authenticated with an App-Token
     * TODO FIXME: this should be routed to ZfExtended_Authentication::getInstance()->isAuthenticatedByToken. Must be tested ...
     * @return bool
     */
    public static function isAppTokenAuthenticated(): bool
    {
        $userSession = new Zend_Session_Namespace('user');
        return ($userSession->data?->isTokenAuth === true);
    }

    private ?User $authenticatedUser = null;

    /**
     * Temporarily holds the unauthenticated user during the authentication-process
     */
    private User $authenticatingUser;

    /**
     * The roles of the authenticated user. These roles differ from the underlying user-objects roles
     * For ACL evaluations, these roles always must be taken
     * @var string[]
     */
    private array $authenticatedRoles;

    /**
     * If set, the user was authenticated using an app-token
     * This steers if the CSRF-protection is active
     */
    private bool $isTokenAuth = false;

    /**
     * The to be used algorithm
     */
    private string $algorithm;

    /**
     * The Authorization status representing the way the user authenticated
     */
    private int $authStatus;


    private function __construct()
    {
        $this->algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $this->authenticatedRoles = ['noRights'];
        $this->authStatus = $this->authenticateBySession();
    }

    /**
     * Checks in the session if a user is authenticated
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->authenticatedUser !== null;
    }

    /**
     * @return bool
     */
    public function isAuthenticatedByToken(): bool
    {
        return $this->isTokenAuth;
    }

    /**
     * Returns one of the constants ::AUTH_ALLOWED, ::AUTH_ALLOWED_LOAD, ::AUTH_DENY_NO_SESSION, ::AUTH_DENY_USER_NOT_FOUND
     * @return int
     */
    public function getAuthStatus(): int
    {
        return $this->authStatus;
    }

    /**
     * Logs the user out
     * This will not delete the currently set user/data so the request can finish without quirks
     */
    public function logoutUser(): void
    {
        $session = new Zend_Session_Namespace();
        $internalSessionUniqId = $session->internalSessionUniqId;
        $sessionId = Zend_Session::getId();
        $sessionMapDB = ZfExtended_Factory::get(ZfExtended_Models_Db_SessionMapInternalUniqId::class);
        $sessionMapDB->delete("internalSessionUniqId  = '" . $internalSessionUniqId . "'");
        $auth = Zend_Auth::getInstance();
        // Delete the information from the session
        $auth->clearIdentity();
        Zend_Session::destroy(true);
        Zend_Registry::set('logoutDeletedSessionId', [
            'sessionId' => $sessionId,
            'internalSessionUniqId' => $internalSessionUniqId
        ]);
    }

    /**
     * Creates a secure password out of a plain one
     * @param string $plainPassword
     * @return string
     * @throws Zend_Exception
     */
    public function createSecurePassword(string $plainPassword): string
    {
        $secret = Zend_Registry::get('config')->runtimeOptions?->authentication?->secret ?? 'translate5';
        return $this->encryptPassword($plainPassword, $secret);
    }

    /**
     * Encrypting a plaintext password securely
     * @param string $plainPassword
     * @param string $secret
     * @return string
     */
    public function encryptPassword(string $plainPassword, string $secret): string
    {
        return password_hash($this->addPepper($plainPassword, $secret), $this->algorithm);
    }

    /**
     * @param string $plainPassword
     * @param string $secret
     * @return string
     */
    private function addPepper(string $plainPassword, string $secret): string
    {
        return hash_hmac('sha256', $plainPassword, $secret);
    }

    /**
     * Check if the provided password is valid for login. In case the password is not valid, this will check if the
     * given password is valid application token
     * @param string $login
     * @param string $passwordOrToken
     * @return bool
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     */
    public function authenticatePasswordAndToken(string $login, string $passwordOrToken): bool
    {
        //first check if it is a valid password
        if ($this->authenticate($login, $passwordOrToken)) {
            return true;
        }
        // if the default validation fail, check token authentication
        if ($this->authenticateByToken($passwordOrToken)) {
            if ($login !== $this->authenticatedUser->getLogin()) {
                $this->unsetAuthenticatedUser();
                return false;
            }
            return true;
        }

        return false;
    }

    /**
     * returns true if the given $login and $password are valid so that it can be authenticated
     * @param string $login
     * @param string $password
     * @return bool false if password invalid or user not found
     * @throws Zend_Exception
     */
    public function authenticate(string $login, string $password): bool
    {
        $isOldPassword = false;
        $valid = $this->loadUserAndValidate($login, function () use ($password, & $isOldPassword) {
            // default to empty string in case the user does not have password
            $passwordHash = $this->authenticatingUser->getPasswd() ?? '';
            $isOldPassword = str_starts_with($passwordHash, self::COMPAT_PREFIX);
            if ($isOldPassword) {
                //remove md5:
                $passwordHash = substr($passwordHash, strlen(self::COMPAT_PREFIX));
                //old passwords have the old md5 hash encrypted inside
                $password = md5($password);
            }
            return $this->isPasswordEqual($password, $passwordHash);
        });
        if ($valid && $isOldPassword) {
            $this->authenticatedUser->setPasswd($this->createSecurePassword($password));
            $this->authenticatedUser->save();
        }
        return $valid;
    }

    /***
     * @param string $password
     * @param string $passwordHash
     * @return bool
     * @throws Zend_Exception
     */
    public function isPasswordEqual(string $password, string $passwordHash): bool
    {
        $secret = Zend_Registry::get('config')->runtimeOptions->authentication->secret;
        return password_verify($this->addPepper($password, $secret), $passwordHash);
    }

    /**
     * Authenticates the user given by login - does not check password!
     * @param string $login
     * @return bool false if user could not be found
     */
    public function authenticateByLogin(string $login): bool
    {
        return $this->loadUserAndValidate($login, function () {
            return true;
        });
    }

    /**
     * Authenticates the user given by login - does not check password!
     * @param stdClass $sessionData
     * @return bool false if user could not be found
     */
    public function authenticateBySessionData(stdClass $sessionData): bool
    {
        return $this->loadUserAndValidate($sessionData->login, function () {
            return true;
        }, updateUserInSession: false);
    }

    /**
     * Authenticates the user given by user instance - does not check password!
     * @param ZfExtended_Models_User $user
     * @return bool false if user could not be found
     */
    public function authenticateUser(User $user): bool
    {
        if ($user->getId() > 0 && strlen($user->getLogin()) > 0) {
            $this->setAuthenticatedUser($user, true);
            return true;
        }
        return false;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?ZfExtended_Models_User
    {
        return $this->authenticatedUser;
    }

    /**
     * Shorthand to retrieve the guid of the authenticated user
     * @return string|null
     */
    public function getUserGuid(): ?string
    {
        return $this->authenticatedUser?->getUserGuid();
    }

    /**
     * Shorthand to get the id of the authenticated user
     * For convenience "0" is returned if not authenticated to not destroy sql statements
     * @return int
     */
    public function getUserId(): int
    {
        return ($this->authenticatedUser === null) ? 0 : (int) $this->authenticatedUser->getId();
    }

    /**
     * Retrieves the roles of the authenticated user
     * @return string[]
     */
    public function getUserRoles(): array
    {
        return $this->authenticatedRoles;
    }

    /**
     * The main API to check if the authenticated user is allowed to do stuff
     * @param string $resource
     * @param string $right
     * @return bool
     */
    public function isUserAllowed(string $resource, string $right): bool
    {
        try {
            return ZfExtended_Acl::getInstance()->isInAllowedRoles($this->authenticatedRoles, $resource, $right);
        } catch (Zend_Acl_Exception) {
            return false;
        }
    }

    public function isUserClientRestricted(): bool
    {
        return $this->authenticatedUser?->isClientRestricted() ?? false;
    }

    /**
     * Checks, if the user has a certain role
     * HINT: Checking access rights must be done with ACLs and not user roles, see ::isUserAllowed
     * @param string $role
     * @return bool
     */
    public function hasUserRole(string $role): bool
    {
        return in_array($role, $this->authenticatedRoles);
    }

    /**
     * Retrieves the anonymized data-object of the authenticated user
     * Mainly used to fill the session
     * @return stdClass
     */
    public function getUserData(): stdClass
    {
        if($this->authenticatedUser === null){
            return new stdClass();
        }
        $data = $this->authenticatedUser->getDataObject();
        $data->roles = $this->authenticatedRoles;
        $data->userName = $this->authenticatedUser->getUserName();
        $data->loginTimeStamp = $_SERVER['REQUEST_TIME'];
        $data->passwd = '********'; // We don't need and don't want the PW hash in the session
        $data->isTokenAuth = $this->isTokenAuth;
        $data->isClientRestricted = $this->authenticatedUser->isClientRestricted();
        $data->restrictedClientIds = $this->authenticatedUser->getRestrictedClientIds();
        // unset OAuth stuff
        unset($data->openIdSubject);
        unset($data->openIdIssuer);

        // TODO FIXME: why is that done ?
        foreach ($data as &$value) {
            if (is_numeric($value)) {
                $value = (int)$value;
            }
        }
        return $data;
    }

    /**
     * Check and validate if the application token is provided as password. If it matches the provided user
     * and it is valid this will be valid authentication
     * @param string $token
     * @return bool
     */
    public function authenticateByToken(string $token): bool
    {
        $parsedToken = ZfExtended_Factory::get(ZfExtended_Auth_Token_Token::class, [$token]);

        // check if the token has valid form
        if (empty($parsedToken->getToken())) {
            return false;
        }

        $tokenModel = ZfExtended_Factory::get(ZfExtended_Auth_Token_Entity::class);
        try {
            $user = ZfExtended_Factory::get(User::class);
            $tokenModel->load($parsedToken->getPrefix());
            $expires = $tokenModel->getExpires();
            if (!empty($expires) && NOW_ISO > $expires) {
                return false;
            }
            $user->load($tokenModel->getUserId());
            return $this->loadUserAndValidate(
                $user->getLogin(),
                function () use ($tokenModel, $parsedToken) {
                    return $this->isPasswordEqual($parsedToken->getToken(), $tokenModel->getToken());
                },
                true
            );

        } catch (ZfExtended_Models_Entity_NotFoundException) {
            return false;
        }
    }

    /**
     * try to authenticate the user given by login, validated by given callback which must return bool
     * @param string $login
     * @param Closure $loginValidator
     * @param bool $isLoginByAppToken
     * @param bool $updateUserInSession
     * @return bool
     */
    private function loadUserAndValidate(
        string $login,
        Closure $loginValidator,
        bool $isLoginByAppToken = false,
        bool $updateUserInSession = true
    ): bool
    {
        $this->isTokenAuth = false;
        $this->authenticatingUser = ZfExtended_Factory::get(User::class);
        try {
            $this->authenticatingUser->loadByLogin($login);
            if ($loginValidator()) {
                $this->isTokenAuth = $isLoginByAppToken;
                $this->setAuthenticatedUser($this->authenticatingUser, $updateUserInSession);
                unset($this->authenticatingUser);
                return true;
            }
            $this->unsetAuthenticatedUser();
            return false;
        } catch (ZfExtended_Models_Entity_NotFoundException) {
            $this->unsetAuthenticatedUser();
            return false;
        }
    }

    /**
     * tries to authenticate by user session
     * @return int
     */
    private function authenticateBySession(): int
    {
        if (!is_null($this->authenticatedUser)) {
            return self::AUTH_ALLOWED;
        }

        $session = new Zend_Session_Namespace('user');

        if (empty($session->data->login) || empty($session->data->id)) {
            return self::AUTH_DENY_NO_SESSION;
        }

        if ($this->authenticateBySessionData($session->data)) {
            return self::AUTH_ALLOWED_LOAD;
        }

        return self::AUTH_DENY_USER_NOT_FOUND;
    }

    /**
     * @param ZfExtended_Models_User $user
     * @param bool $updateSessionData
     * @return void
     */
    private function setAuthenticatedUser(User $user, bool $updateSessionData): void
    {
        $this->authenticatedUser = $user;
        $this->authenticatedRoles = array_values(array_unique(array_merge($user->getRoles(), ['basic', 'noRights'])));
        if ($updateSessionData) {
            $userSession = new Zend_Session_Namespace('user');
            $userSession->data = $this->getUserData();
        }
    }

    /**
     * @return void
     */
    private function unsetAuthenticatedUser(): void
    {
        $this->authenticatedUser = null;
        $this->authenticatedRoles = ['noRights'];
        $this->isTokenAuth = false;
        $this->authStatus = 0;
    }
}
