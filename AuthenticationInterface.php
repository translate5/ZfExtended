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

/**
 * Handles authentication and password management
 * Main API to access the authenticated user (which has more roles than the underlying user object)
 */
interface ZfExtended_AuthenticationInterface
{
    /**
     * Checks in the session if a user is authenticated
     */
    public function isAuthenticated(): bool;

    public function isAuthenticatedByToken(): bool;

    /**
     * Returns one of the constants ::AUTH_ALLOWED, ::AUTH_ALLOWED_LOAD, ::AUTH_DENY_NO_SESSION, ::AUTH_DENY_USER_NOT_FOUND
     */
    public function getAuthStatus(): int;

    /**
     * Logs the user out
     * This will not delete the currently set user/data so the request can finish without quirks
     */
    public function logoutUser(): void;

    /**
     * Creates a secure password out of a plain one
     * @throws Zend_Exception
     */
    public function createSecurePassword(string $plainPassword): string;

    /**
     * Encrypting a plaintext password securely
     */
    public function encryptPassword(string $plainPassword, string $secret): string;

    /**
     * Check if the provided password is valid for login. In case the password is not valid, this will check if the
     * given password is valid application token
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     */
    public function authenticatePasswordAndToken(string $login, string $passwordOrToken): bool;

    /**
     * returns true if the given $login and $password are valid so that it can be authenticated
     * @return bool false if password invalid or user not found
     * @throws Zend_Exception
     */
    public function authenticate(string $login, string $password): bool;

    /***
     * @param string $password
     * @param string $passwordHash
     * @return bool
     * @throws Zend_Exception
     */
    public function isPasswordEqual(string $password, string $passwordHash): bool;

    /**
     * Authenticates the user given by login - does not check password!
     * @return bool false if user could not be found
     */
    public function authenticateByLogin(string $login): bool;

    /**
     * Authenticates the user given by login - does not check password!
     * @return bool false if user could not be found
     */
    public function authenticateBySessionData(stdClass $sessionData): bool;

    /**
     * Authenticates the user given by user instance - does not check password!
     * @return bool false if user could not be found
     */
    public function authenticateUser(\ZfExtended_Models_User $user): bool;

    public function getUser(): ?ZfExtended_Models_User;

    /**
     * Shorthand to retrieve the guid of the authenticated user
     */
    public function getUserGuid(): ?string;

    /**
     * Shorthand to get the id of the authenticated user
     * For convenience "0" is returned if not authenticated to not destroy sql statements
     */
    public function getUserId(): int;

    public function getLogin(): ?string;

    /**
     * Retrieves the roles of the authenticated user
     * @return string[]
     */
    public function getUserRoles(): array;

    /**
     * The main API to check if the authenticated user is allowed to do stuff
     */
    public function isUserAllowed(string $resource, string $right): bool;

    public function isUserClientRestricted(): bool;

    /**
     * Checks, if the user has a certain role
     * HINT: Checking access rights must be done with ACLs and not user roles, see ::isUserAllowed
     */
    public function hasUserRole(string $role): bool;

    /**
     * Retrieves the anonymized data-object of the authenticated user
     * Mainly used to fill the session
     */
    public function getUserData(): stdClass;

    /**
     * Check and validate if the application token is provided as password. If it matches the provided user
     * and it is valid this will be valid authentication
     */
    public function authenticateByToken(string $token): bool;

    public function getUsedToken(): ?string;
}