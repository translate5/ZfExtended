<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * Plugin zur Überprüfung von Browser und IP zur Erhöhung der Session-Sicherheit
 *
 * - speichert den Browser User Agent und die Remote IP in der Session
 * - bei erneutem Zugriff werden die Werte gegengeprüft
 * - bei Unstimmigkeit wird der benutzer ausgeloggt und auf die Loginseite umgeleitet.
 */
class ZfExtended_Controllers_Plugins_SessionRestriction extends Zend_Controller_Plugin_Abstract {
    /**
     * Session Restriction Storage
     * @var Zend_Session_Namespace
     */
    protected $storage = null;

    /**
     * @var Zend_Controller_Request_Abstract
     */
    protected $request = null;

    /**
     * Wird vor dem Start des Dispatcher Laufes ausgeführt
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function RouteShutdown(Zend_Controller_Request_Abstract $request)
    {
        $this->request = $request;
        $this->storage = new Zend_Session_Namespace('sessionRestrictionStorage');
        $this->session = new Zend_Session_Namespace();
        if($this->session->isFork === false){
            try{
                $this->validateBrowser();
                $this->validateRemoteAddress();
                $this->validateForSeleniumTest();
            } catch(Zend_Exception $e) {#exit('session restriction: '.$e);
                $log = new ZfExtended_Log(false);
                $log->logException($e);
                $this->immediateLogout();
            }
        }
    }

    /**
     * Überprüft ob sich die Browserkennung geändert hat
     * @throws Zend_Exception
     * @return void
     */
    protected function validateBrowser() {
        settype($_SERVER['HTTP_USER_AGENT'], 'string');
        $isFlash = strpos($_SERVER['HTTP_USER_AGENT'], 'Flash')!== false;
        if($isFlash and empty($this->storage->flash)){
            $this->storage->flash = $_SERVER['HTTP_USER_AGENT'];
            return;
        }
        if(!$isFlash and empty($this->storage->browser)){
            $this->storage->browser = $_SERVER['HTTP_USER_AGENT'];
            return;
        }
        if($isFlash and $this->storage->flash != $_SERVER['HTTP_USER_AGENT']){
            throw new Zend_Exception('Browservalidierung schlug fehl - Flash, aber storage-flash '.
                    $this->storage->flash.' ungleich HTTP_USER_AGENT '.$_SERVER['HTTP_USER_AGENT']);
        }
        if(!$isFlash and $this->storage->browser != $_SERVER['HTTP_USER_AGENT']){
            throw new Zend_Exception('Browservalidierung schlug fehl - kein Flash, aber storage-browser '.
                    $this->storage->browser.' ungleich HTTP_USER_AGENT '.$_SERVER['HTTP_USER_AGENT']);
        }
    }

    /**
     * Überprüft ob sich die IP Adresse geändert hat
     * @throws Zend_Exception
     * @return void
     */
    protected function validateRemoteAddress() {
        settype($_SERVER['REMOTE_ADDR'], 'string');
        if(empty($this->storage->address)){
            $this->storage->address = $_SERVER['REMOTE_ADDR'];
            return;
        }
        if($this->storage->address != $_SERVER['REMOTE_ADDR']){
            throw new Zend_Exception('IP-Validierung schlug fehl - storage-address '.
                    $this->storage->address.' ungleich REMOTE_ADDR '.$_SERVER['REMOTE_ADDR']);
        }
    }

    /**
     * resets the stored values to compare against
     */
    public function reset() {
        $storage = new Zend_Session_Namespace('sessionRestrictionStorage');
        unset ($storage->address, $storage->flash, $storage->browser);
    }
    
    /**
     * Mit Selenium lassen sich die Rahmenparamter REMOTE_ADDR und HTTP_USER_AGENT nicht ändern
     * Um das Verhalten des Plugins dennoch testen zu können, wird ein entsprechender Parameter gesetzt
     *
     * @throws Zend_Exception
     * @return void
     */
    protected function validateForSeleniumTest() {
        if(isset($_GET['SessionRestriction']) && $_GET['SessionRestriction'] == 'validateForSeleniumTest'){
            throw new Zend_Exception();
        }
    }

    /**
     * loggt den Benutzer aus
     * @return void
     */
    protected function immediateLogout(){
        $general = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'general'
        );
        $general->logoutUser();

        $redirector = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'Redirector'
        );
        /* @var $redirector Zend_Controller_Action_Helper_Redirector */
        $redirectUrl = "/";
        if(isset($this->session->runtimeOptions->loginUrl)){
            $redirectUrl = $this->session->runtimeOptions->loginUrl;
        }
        elseif(defined(APPLICATION_RUNDIR) && APPLICATION_RUNDIR != ''){
            $redirectUrl = APPLICATION_RUNDIR;
        }
        $redirector->gotoUrlAndExit($redirectUrl);
    }
}