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

namespace MittagQI\ZfExtended;

use Zend_Config;
use Zend_Controller_Request_Exception;
use Zend_Controller_Request_Http;
use Zend_Exception;
use Zend_Http_Client;
use Zend_Http_Client_Exception;
use Zend_Session_Namespace;
use ZfExtended_Authentication;
use ZfExtended_Exception;
use ZfExtended_NotAuthenticatedException;
use Zend_Registry;

/**
 * Handles the token that secures API requests in the client against CSRF attacks
 * This CSRF protection is active for all Endpoints of controllers inherited from ZfExtended_RestController, other endpoints must implement on their own to be secured
 * The CSRF protection expects all requests to be sent with a header-field "CsrfToken" to contain the valid token
 * This token then is validated against the token stored in the session (or in a temporary token-file in case of a API-test)
 */
final class CsrfProtection
{
    /**
     * The name of the header field
     */
    const HEADER_NAME = 'CsrfToken';

    /**
     * Dev option to completely deactivate feature. Must be true for production !!
     */
    const ACTIVE = true;

    /**
     * The file to store the token for API-tests
     */
    const APITEST_TOKENFILE = 'apitest-csrf.token';

    /**
     * To be called by API-test commands to generate a token and save it to the test token-file
     * This file will be the store of the token when running tests instead of the session
     * @return string
     * @throws Zend_Exception
     * @throws ZfExtended_Exception
     */
    public static function createApiTestToken(): string
    {
        $config = Zend_Registry::get('config');
        $tokenFile = $config->runtimeOptions->dir->tmp.'/'.self::APITEST_TOKENFILE;
        $token = self::generateToken();
        if(!file_put_contents($tokenFile, $token)){
            throw new ZfExtended_Exception('CsrfProtection::createApiTestToken: Could not generate token-file in tmp-dir '.$tokenFile);
        }
        // this API is probably called as root, the token must be readable for the apache user nevertheless
        chmod($tokenFile, 0777);
        return $token;
    }

    /**
     * @return string
     * @throws \Exception
     */
    private static function generateToken(): string
    {
        return bin2hex(random_bytes(20));
    }

    /**
     * @var self|null
     */
    private static ?self $_instance = null;

    /**
     * @return CsrfProtection
     */
    public static function getInstance(): self
    {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        if(self::ACTIVE){
            // Crucial: if a request was initiated with an app-token, CSRF protection must be inactive
            return !ZfExtended_Authentication::isAppTokenAuthenticated();
        }
        return false;
    }

    /**
     * @param Zend_Http_Client $client
     * @param Zend_Config $config
     * @throws Zend_Http_Client_Exception
     * @throws ZfExtended_NotAuthenticatedException
     */
    public function addRequestHeaders(Zend_Http_Client $client, Zend_Config $config){
        if($this->isActive()){
            $token = ($config?->testSettings?->isApiTest == '1') ? $this->getApiTestToken($config) : $this->getToken();
            $client->setHeaders(self::HEADER_NAME, $token);
        }
    }

    /**
     * @return string
     * @throws ZfExtended_NotAuthenticatedException
     * @throws Zend_Exception
     */
    public function getHeaderString(): string
    {
        if($this->isActive()){
            $config = Zend_Registry::get('config');
            $token = ($config?->testSettings?->isApiTest == '1') ? $this->getApiTestToken($config) : $this->getToken();
            return self::HEADER_NAME.': '.$token."\r\n";
        }
        return '';
    }

    /**
     * @param Zend_Controller_Request_Http $request
     * @return bool
     * @throws Zend_Exception
     * @throws ZfExtended_NotAuthenticatedException
     * @throws Zend_Controller_Request_Exception
     */
    public function validateRequest(Zend_Controller_Request_Http $request): bool
    {
        if($this->isActive()){
            $token = $request->getHeader(self::HEADER_NAME);
            if(!$token){
                $this->throwException('Request '.$request->getRequestUri().' had no '.self::HEADER_NAME.' header');
            }
            return $this->validateToken($token);
        }
        return true;
    }

    /**
     * @param string $token
     * @return bool
     * @throws Zend_Exception
     * @throws ZfExtended_NotAuthenticatedException
     */
    public function validateToken(string $token): bool
    {
        if($this->isActive()){
            $session = $this->getSession();
            if(empty($token)) {
                $this->throwException('token was empty');
            }
            // compare token with the session token or a file-based token in case of unit tests
            if($session?->token !== $token){
                // we may be in an API-test, let's check
                $config = Zend_Registry::get('config');
                if($config?->testSettings?->isApiTest == '1'){
                    // when API-testing, the token is stored in a temporary file for the test
                    $storedToken = $this->getApiTestToken($config);
                    if($storedToken === $token){
                        return true;
                    }
                    $this->throwException('the sent test-token "'.$token.'" does not match the stored token '.$storedToken);
                } else {
                    $this->throwException('sent token "'.$token.'" does not match the session token: "'.($session?->token ?? 'NO SESSION TOKEN SET').'"');
                }
            }
        }
        return true;
    }


    /**
     * Retrieves the current CSRF token and starts the session for it
     * This API must only be used in IndexController where the app is served
     * This is not suitable to retrieve a token as used in api-tests
     * @return string
     * @throws \Exception
     */
    public function getToken(): string
    {
        if($this->isActive()){
            $session = $this->getSession();
            if(empty($session?->token)){
                $session->token = self::generateToken();
            }
            return $session->token;
        }
        return '';
    }

    /**
     * Retrieves the CSRF token in an api-test scenario
     * @param Zend_Config $config
     * @return string
     * @throws ZfExtended_NotAuthenticatedException
     */
    private function getApiTestToken(Zend_Config $config): string
    {
        $tokenFile = $config->runtimeOptions->dir->tmp.'/'.self::APITEST_TOKENFILE;
        $token = file_exists($tokenFile) ? file_get_contents($tokenFile) : false;
        if(!$token){
            $this->throwException('the test-token-file "'.$tokenFile.'" is missing or not readable');
        }
        return $token;
    }

    /**
     * @param string $additionalMessage
     * @throws ZfExtended_NotAuthenticatedException
     */
    private function throwException(string $additionalMessage)
    {
        error_log('CSRF Protection failed, token invalid [ '.$additionalMessage.' ]');
        throw new ZfExtended_NotAuthenticatedException();
    }

    /**
     * @return Zend_Session_Namespace
     */
    private function getSession(): Zend_Session_Namespace
    {
        return new Zend_Session_Namespace('csrf');
    }
}
