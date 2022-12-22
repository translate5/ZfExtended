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
 */
class ZfExtended_Authentication
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

    const APPLICATION_TOKEN_HEADER             = 'Translate5AuthToken';

    //when updating from md5 to newer hash, the hashes containing old md5 hashes are marked with that prefix
    const COMPAT_PREFIX                 = 'md5:';

    /**
     * The to be used algorithm
     * @var string
     */
    protected string $algorithm;

    /**
     * @var self|null
     */
    private static ?self $_instance = null;

    private ?User $authenticatedUser = null;

    private bool $isTokenAuth = false;


    /**
     * @return self
     */
    public static function getInstance() : self
    {
        if (self::$_instance == null) {
            self::$_instance = new self();
            self::$_instance->algorithm = defined('PASSWORD_ARGON2ID') ?  PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        }
        return self::$_instance;
    }

    /**
     * Checks in the session if a user is authenticated
     * @param int $authStatus
     * @return bool
     */
    public function isAuthenticated(int &$authStatus = 0): bool
    {
        if (!is_null($this->authenticatedUser)) {
            $authStatus = self::AUTH_ALLOWED;
            return true;
        }
        $session = new Zend_Session_Namespace('user');

        if (empty($session->data->login) || empty($session->data->id)) {
            $authStatus = self::AUTH_DENY_NO_SESSION;
            return false;
        }

        if ($this->authenticateByLogin($session->data->login)) {
            $authStatus = self::AUTH_ALLOWED_LOAD;
            return true;
        }

        $authStatus = self::AUTH_DENY_USER_NOT_FOUND;
        return false;
    }

    /**
     * logs the user out
     */
    public function logoutUser(): void
    {
        $session = new Zend_Session_Namespace();
        $internalSessionUniqId = $session->internalSessionUniqId;
        $sessionId = Zend_Session::getId();
        $sessionMapDB = ZfExtended_Factory::get(ZfExtended_Models_Db_SessionMapInternalUniqId::class);
        $sessionMapDB->delete("internalSessionUniqId  = '".$internalSessionUniqId."'");
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
                $this->authenticatedUser = null;
                return false;
            }
            $this->setIsTokenAuth(true);
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
            $passwordHash = $this->authenticatedUser->getPasswd();
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
     * Authenticates the user given by user instance - does not check password!
     * @param ZfExtended_Models_User $user
     * @return bool false if user could not be found
     */
    public function authenticateUser(User $user): bool
    {
        if ($user->getId() > 0 && strlen($user->getLogin()) > 0) {
            $this->authenticatedUser = $user;
            $this->setUserDataInSession();
            return true;
        }
        return false;
    }

    /**
     * try to authenticate the user given by login, validated by given callback which should return bool
     * @param string $login
     * @param Closure $loginValidator
     * @return bool
     */
    private function loadUserAndValidate(string $login, Closure $loginValidator): bool
    {
        $this->authenticatedUser = ZfExtended_Factory::get(User::class);
        try {
            $this->authenticatedUser->loadByLogin($login);
            if ($loginValidator()) {
                $this->setUserDataInSession();
                editor_User::create($this->authenticatedUser);
                return true;
            }
            $this->authenticatedUser = null;
            return false;
        } catch (ZfExtended_Models_Entity_NotFoundException) {
            $this->authenticatedUser = null;
            return false;
        }
    }

    /**
     * @return User|null
     */
    public function getUser(): ?ZfExtended_Models_User
    {
        return $this->authenticatedUser;
    }

    /**
     * there may be gap in access control between the user and the auth roles due basic and noRights role
     * so this method may return more roles as the user getRoles
     */
    public function getRoles(): array
    {
        $userSession = new Zend_Session_Namespace('user');
        return $userSession->data?->roles ?? ['noRights'];
    }

    /**
     * sets the current user data into the session
     */
    protected function setUserDataInSession(): void
    {
        $userSession = new Zend_Session_Namespace('user');
        $userData = $this->authenticatedUser->getDataObject();
        $userData->roles = $this->authenticatedUser->getRoles();
        $userData->roles[] = 'basic';
        $userData->roles[] = 'noRights'; //the user always has this roles
        $userData->roles = array_unique($userData->roles);
        $userData->userName = $userData->firstName.' '.$userData->surName;
        $userData->loginTimeStamp = $_SERVER['REQUEST_TIME'];
        $userData->passwd = '********'; // We don't need and don't want the PW hash in the session
        $userData->openIdIssuer='';
        foreach ($userData as &$value) {
            if (is_numeric($value)) {
                $value = (int)$value;
            }
        }
        $userData->isTokenAuth = $this->isTokenAuth;
        $userSession->data = $userData;

    }

    /**
     * @param bool $isTokenAuth
     */
    public function setIsTokenAuth(bool $isTokenAuth): void
    {
        $this->isTokenAuth = $isTokenAuth;
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
            $user->load($tokenModel->getUserId());
            return $this->loadUserAndValidate($user->getLogin(), function () use ($tokenModel, $parsedToken) {
                return $this->isPasswordEqual($parsedToken->getToken(), $tokenModel->getToken());
            });

        } catch (ZfExtended_Models_Entity_NotFoundException) {
            return false;
        }
    }
}
