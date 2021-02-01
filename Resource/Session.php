<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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
 * Startet die Session
 *
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
class ZfExtended_Resource_Session extends Zend_Application_Resource_ResourceAbstract {
    /**
     * @var array config Konfiguration der Parameter für die DB-Sessioninitialisierung
     */
    private $_sessionConfig = array(
        'name'              => 'session', //table name as per Zend_Db_Table
        'primary'           => array(
            'session_id',   //the sessionID given by PHP
            'name',         //session name
        ),
        'primaryAssignment' => array(
            //you must tell the save handler which columns you
            //are using as the primary key. ORDER IS IMPORTANT
            'sessionId', //first column of the primary key is of the sessionID
            'sessionName', //second column of the primary key is the session name
        ),
        'modifiedColumn'    => 'modified',     //time the session should expire
        'dataColumn'        => 'session_data', //serialized data
        'lifetimeColumn'    => 'lifetime',     //end of life for a specific record
    );
    
    public function init()
    {
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('db');
        $bootstrap->bootstrap('ZfExtended_Resource_ErrorHandler');
        $bootstrap->bootstrap('ZfExtended_Resource_DbConfig');
        $bootstrap->bootstrap('ZfExtended_Resource_GarbageCollector');
        $config = new Zend_config($bootstrap->getOptions());
        $resconf = $config->resources->ZfExtended_Resource_Session->toArray();
        unset($resconf['garbageCollectorLifetime']); //Zend_Session does not know this value!
        
        if($this->isHttpsRequest()) {
            //None needed so far for Features like OpenID connect and session auth token,
            // but non works only with HTTPS!
            $resconf['cookie_samesite'] = 'None';
            $resconf['cookie_secure'] = 1;
        }
        else {
            $resconf['cookie_samesite'] = 'Lax';
        }
        
        //we may set the options only, if unitTests are disabled (used to mask CLI usage)
        if(!Zend_Session::$_unitTestEnabled) {
            Zend_Session::setOptions($resconf);
        }
        //im if: wichtiger workaround für swfuploader, welcher in awesomeuploader
        //verwendet wird. flash überträgt keine session-cookies außerhalb IE korrekt,
        //daher wird hier die session im post übergeben
        if (isset($_POST[$resconf['name']])) {
            Zend_Session::setId($_POST[$resconf['name']]);
        }
        
        Zend_Session::setSaveHandler(new ZfExtended_Session_SaveHandler_DbTable($this->_sessionConfig));

        $this->handleAuthToken(); //makes a redirect if successfull!
        
        //default handling
        Zend_Session::start();
        $this->setInternalSessionUniqId();
    }
    
    private function reload() {
        Zend_Session::writeClose();
        if($this->isHttpsRequest()){
            $url = 'https://';
        }
        else {
            $url = 'http://';
        }
        $url .= $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $url = explode('?', $url);
        $url = rtrim($url[0], '/');
        //preserve a redirect parameter if existing
        if(!empty($_REQUEST['redirect'])){
            $url .= '?redirect='.$_REQUEST['redirect'];
        }
        header('Location: '.$url);
        exit;
    }
    
    
    /**
     * returns true if the request was made with SSL.
     *  Our internal config server.protocol can not be used here,
     *  since the config resource is loaded after the session resource
     * @return bool
     */
    public function isHttpsRequest(): bool {
        //from https://stackoverflow.com/a/41591066/1749200
        return (( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ( ! empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
            || ( ! empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == 443)
            || (isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] == 'https')
            );
    }
    
    /**
     * returns the session_id to be used when a valid sessionToken was provided
     * @return string|NULL
     */
    private function handleAuthToken() {
        if(empty($_REQUEST['sessionToken']) || !preg_match('/[a-zA-Z0-9]{32}/', $_REQUEST['sessionToken'])) {
            return;
        }
        
        if(!$this->isHttpsRequest()) {
            throw new ZfExtended_NotAuthenticatedException('Due Cookie restrictions this feature can only be used with HTTPS enabled!');
        }
        
        $sessionDb = ZfExtended_Factory::get('ZfExtended_Models_Db_Session');
        /* @var $sessionDb ZfExtended_Models_Db_Session */
        $row = $sessionDb->fetchRow(['authToken = ?' => $_REQUEST['sessionToken']]);
        if(empty($row) || empty($row->session_id)) {
            $this->authTokenLog('No matching sessionToken found in DB: '.$_REQUEST['sessionToken']);
            $this->reload(); //making exit
        }
        Zend_Session::setId($row->session_id);
        Zend_Session::start();
        $sessionDb->updateAuthToken($row->session_id);
        $session = new Zend_Session_Namespace();
        $user = new Zend_Session_Namespace('user');
        $this->authTokenLog('Spawning session for sessionToken '.$_REQUEST['sessionToken'].' user: '.print_r($user->data,1));
        //since we changed the sessionId, we have to reset the internalSessionUniqId too
        unset($session->internalSessionUniqId);
        $this->setInternalSessionUniqId();
        //reload redirect to remove authToken from parameter
        //or doing this in access plugin because there are several helpers?
        $this->reload(); //making exit
    }
    
    private function authTokenLog($msg) {
        if(ZfExtended_Debug::hasLevel('core', 'apiLogin')) {
            error_log($msg);
        }
    }
    
    /**
     * Setzt internalSessionUniqId wie im Klassenkopf beschrieben
     */
    private function setInternalSessionUniqId(){
        $session = new Zend_Session_Namespace();
        if(!isset($session->internalSessionUniqId)){
            $sessionId = Zend_Session::getId();
            $session->internalSessionUniqId =  md5($sessionId . uniqid(__FUNCTION__, true));
            $row = ZfExtended_Factory::get('ZfExtended_Models_Entity',
                        array('ZfExtended_Models_Db_SessionMapInternalUniqId',
                            array()));
            $row->setSession_id($sessionId);
            $row->setInternalSessionUniqId($session->internalSessionUniqId);
            $row->setModified(time());
            $row->save();
        }
    }
}
