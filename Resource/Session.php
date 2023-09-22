<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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

use ZfExtended_Authentication as Auth;

/**
 * - legt die Variable $session->internalSessionUniqId im allgemeinen
 *   Session-Namespace fest. Sie wird nur intern in der Programmierung und für
 *   Flag-Files verwendet und darf aus Sicherheitsgründen nicht nicht in Cookies
 *   gesetzt werden. Sie ist persistent über die gesamte Session
 *   It is also used as sessionToken for setting the session from external (API auth),
 *      to keep security internalSessionUniqId is new generated after setting a session by the token
 * - hinterlegt in der Tabelle sessionMapInternalUniqId ein Mapping zwischen der
 *   session_id (die durch ZfExtended_Controllers_Plugins_SessionRegenerate bei
 *   jedem Aufruf neu gesetzt wird) und der $session->internalSessionUniqId
 */
class ZfExtended_Resource_Session extends Zend_Application_Resource_ResourceAbstract
{
    /**
     * Session Data (tables session & sessionMapInternalUniqId) potentially is updated with every single request.
     * To minimize excessively updating the session in situations like loading the app,
     * we introduce a delay that is acceptable, meaning this delay may is deducted from the global session-lifetime
     * to reduce the neccessary updates on these tables. A delay of 5 sec reduces the DB-writes from 13 to 5
     */
    const TIMESTAMP_UPDATE_DELAY = 5;
    /**
     * @var array config Konfiguration der Parameter für die DB-Sessioninitialisierung
     * TODO FIXME: why a combined primary-key )?? see also ZfExtended_Models_Db_Session
     */
    private array $sessionConfig = [
        'name'              => 'session', //table name as per Zend_Db_Table
        'primary'           => [
            'session_id',   //the sessionID given by PHP
            'name',         //session name
        ],
        'primaryAssignment' => [
            //you must tell the save handler which columns you
            //are using as the primary key. ORDER IS IMPORTANT
            'sessionId', //first column of the primary key is of the sessionID
            'sessionName', //second column of the primary key is the session name
        ],
        'modifiedColumn'    => 'modified',     //time the session should expire
        'dataColumn'        => 'session_data', //serialized data
        'lifetimeColumn'    => 'lifetime',     //end of life for a specific record
    ];

    /**
     * Evaluates if a session timestamp needs to be updated in the DB
     * @param int|null $dbTimestamp
     * @param int|null $newTimestamp
     * @return bool
     */
    public static function doUpdateTimestamp(?int $dbTimestamp, ?int $newTimestamp): bool
    {
        if($dbTimestamp === $newTimestamp){
            return false;
        }
        if(is_null($dbTimestamp) || is_null($newTimestamp)){
            return true;
        }
        return $dbTimestamp < ($newTimestamp - self::TIMESTAMP_UPDATE_DELAY);
    }

    /**
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     * @throws Zend_Exception
     * @throws Zend_Session_SaveHandler_Exception
     * @throws Zend_Session_Exception
     * @throws ZfExtended_NotAuthenticatedException
     * @throws Zend_Db_Statement_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     */
    public function init()
    {
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('db');
        $bootstrap->bootstrap('ZfExtended_Resource_ErrorHandler');
        $bootstrap->bootstrap('ZfExtended_Resource_DbConfig');
        $bootstrap->bootstrap('ZfExtended_Resource_GarbageCollector');
        $config = new Zend_config($bootstrap->getOptions());
        // the session-name should be configurable by installation.ini, the lifetime via config
        $resconf = $config->resources->ZfExtended_Resource_Session->toArray();
        $resconf['cookie_lifetime'] =
        $resconf['gc_maxlifetime'] =
        $resconf['remember_me_seconds'] = $resconf['lifetime'];

        unset($resconf['lifetime']);

        if (ZfExtended_Utils::isHttpsRequest()) {
            //None needed so far for Features like OpenID connect and session auth token,
            // but non works only with HTTPS!
            $resconf['cookie_samesite'] = 'None';
            $resconf['cookie_secure'] = 1;
        } else {
            $resconf['cookie_samesite'] = 'Lax';
        }
        
        //we may set the options only, if unitTests are disabled (used to mask CLI usage)
        if (!Zend_Session::$_unitTestEnabled) {
            Zend_Session::setOptions($resconf);
        }

        // set the safe-handler
        $saveHandler = new ZfExtended_Session_SaveHandler_DbTable($this->sessionConfig);
        Zend_Session::setSaveHandler($saveHandler);

        //makes a redirect if successfull
        $this->handleSessionToken();

        $this->handleAuthToken();
        
        // default handling
        Zend_Session::start();
        $this->setInternalSessionUniqId();
    }
    
    private function reload(): void
    {
        Zend_Session::writeClose();
        $url = $_SERVER['REQUEST_URI'];
        $url = explode('?', $url);
        $url = rtrim($url[0], '/');
        //preserve a redirect parameter if existing
        if (!empty($_REQUEST['redirect'])) {
            $url .= '?redirect='.$_REQUEST['redirect'];
        }
        header('Location: '.$url);
        exit;
    }

    /**
     * returns the session_id to be used when a valid sessionToken was provided
     *
     * @return void
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws Zend_Session_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     * @throws ZfExtended_NotAuthenticatedException
     * @throws Exception
     */
    private function handleSessionToken(): void
    {
        if (empty($_REQUEST['sessionToken']) || !preg_match('/[a-zA-Z0-9]{32}/', $_REQUEST['sessionToken'])) {
            return;
        }

        if (!ZfExtended_Utils::isHttpsRequest() && !defined('APPLICATION_APITEST')) {
            //without HTTPS we have to use samesite = LAX which then prevents the proper functionality of this feature,
            // therefore we just disable sessionToken auth in that case
            throw new ZfExtended_NotAuthenticatedException(
                'Due to Cookie restrictions this feature can only be used with HTTPS enabled!'
            );
        }
        
        $sessionDb = new ZfExtended_Models_Db_Session();
        $row = $sessionDb->fetchRow(['authToken = ?' => $_REQUEST['sessionToken']]);
        
        /* @var ZfExtended_Logger $sysLog */
        $sysLog = Zend_Registry::get('logger');

        if (empty($row) || empty($row->session_id)) {
            // delete invalid row
            if(!empty($row)){
                $row->delete();
            }
            $sysLog->warn('E1332', 'Authentication: No matching sessionToken found in DB: {token}', [
                'token' => $_REQUEST['sessionToken']
            ]);
            $this->reload(); //making exit
        }
        Zend_Session::setId($row->session_id);
        Zend_Session::start();

        $session = new Zend_Session_Namespace();
        $user = new Zend_Session_Namespace('user');
        $userId = empty($user->data->id) ? null : intval($user->data->id);
        $sessionDb->updateAuthToken($row->session_id, $userId);
        
        //since we have no user instance here, we create the success log by hand
        $loginLog = ZfExtended_Models_LoginLog::createLog("sessionToken");
        $loginLog->setLogin($user->data->login);
        $loginLog->setUserGuid($user->data->userGuid);
        $loginLog->setStatus($loginLog::LOGIN_SUCCESS);
        $loginLog->save();
        
        $sysLog->debug('E1332', 'Authentication: Spawning session for sessionToken {token} and user {login}', [
            'token' => $_REQUEST['sessionToken'],
            'login' => $user->data->login,
            'userGuid' => $user->data->userGuid,
        ]);

        //since we changed the sessionId, we have to reset the internalSessionUniqId too
        unset($session->internalSessionUniqId);
        // create a internalSessionUniqId entry with the same timestamp
        $this->setInternalSessionUniqId(ZfExtended_Utils::parseDbInt($row->modified));
        //reload redirect to remove authToken from parameter
        //or doing this in access plugin because there are several helpers?
        $this->reload(); //making exit
    }

    /**
     * Handle authentication via app token
     * @return void
     * @throws Zend_Exception
     */
    private function handleAuthToken(): void
    {
        $auth = Auth::getInstance();
        $tokenParam = $_POST[$auth::APPLICATION_TOKEN_HEADER] ?? $this->getFromHeader() ?? false;

        if (empty($tokenParam)) {
            return;
        }

        if ($auth->authenticateByToken($tokenParam)) {
            ZfExtended_Models_LoginLog::addSuccess($auth, "authtoken");
            return;
        }

        $sysLog = Zend_Registry::get('logger');
        /* @var ZfExtended_Logger $sysLog */
        $sysLog->error('E1443', 'Authentication Token: The token is not valid');
        //since we are in an early stage of bootstrapping we must return the HTTP directly (no response available)
        header('HTTP/1.1 401 Unauthorized', true, 401);
        if (ZfExtended_Utils::requestAcceptsJson()) {
            die('{"success": false, "httpStatus": 401, "errorMessage": '
                .'"<b>Fatal: Authentication Token: The token is not valid</b>"}');
        } else {
            die('Authentication Token: The token is not valid!');
        }
    }

    /**
     * Stores the the internalSessionUniqId in a seperate table
     * TODO FIXME: this is a completely superflous DB-operation (which happens on every request!)
     * as the internalSessionUniqId could easily be saved in the session-table as well
     * @param int|null $modified
     * @return void
     */
    private function setInternalSessionUniqId(int $modified = null): void
    {
        $session = new Zend_Session_Namespace();
        if (!isset($session->internalSessionUniqId)) {

            $sessionId = Zend_Session::getId();
            $session->internalSessionUniqId =  md5($sessionId . uniqid(__FUNCTION__, true));

            $table = new ZfExtended_Models_Db_SessionMapInternalUniqId();
            $table->createOrUpdateRow($sessionId, $session->internalSessionUniqId, $modified ?? time());
        }
    }

    /**
     * returns the app token header in a normalized way
     * @return string|null
     */
    private function getFromHeader(): ?string
    {
        //in CLI usage the method does not exist:
        if (! function_exists('apache_request_headers')) {
            return null;
        }
        //normalize all to lowercase, according to RFC 2616 the names are case insensitive!
        $headers = array_change_key_case((array) apache_request_headers(), CASE_LOWER);
        $key = strtolower(Auth::APPLICATION_TOKEN_HEADER);
        if (array_key_exists($key, $headers)) {
            return $headers[$key];
        }
        return null;
    }
}
