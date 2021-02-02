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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * methods needed vor login and password handling
 *
 */
abstract class ZfExtended_Controllers_Login extends ZfExtended_Controllers_Action {
    use ZfExtended_Controllers_MaintenanceTrait;
    /**
     * @var ZfExtended_Models_SessionUserInterface
     */
    protected  $_userModel;
    /**
     * @var Zend_Session_Namespace
     */
    protected  $_user;
    /**
     * @var Zend_Session_Namespace
     */
    protected  $_session;
    /**
     * @var ZfExtended_Zendoverwrites_Translate
     */
    protected  $_translate;
    /**
     * @var ZfExtended_Zendoverwrites_Form
     */
    protected  $_form;
    
    public function init(){
        parent::init();
        $this->_translate = ZfExtended_Zendoverwrites_Translate::getInstance();
        $this->_session = new Zend_Session_Namespace();
        $this->_user = new Zend_Session_Namespace('user');
        $this->_userModel = ZfExtended_Factory::get(Zend_Registry::get('config')->authentication->userEntityClass);
    }

    /**
     * does the login-handling
     *
     * - checks if login has been blocked
     * - blocks login, if necessary
     * @return bool
     */
    public function indexAction() {
        
        $this->_form->setTranslator($this->_translate);
        $this->view->form = $this->_form;
        //if the user click on the openid redirect link in the login form
        if($this->isOpenIdRedirect()){
            //set login status to 'login needed'
            $this->view->loginStatus=ZfExtended_Models_SessionUserInterface::LOGIN_STATUS_OPENID;
            return ;
        }
        if($this->isMaintenanceLoginLock()){
            //set login status to 'maintenance'
            $this->_form->addError($this->_translate->_("Eine Wartung steht unmittelbar bevor, Sie können sich daher nicht anmelden. Bitte versuchen Sie es in Kürze erneut."));
            $this->view->loginStatus=ZfExtended_Models_SessionUserInterface::LOGIN_STATUS_MAINTENANCE;
            return;
        }
        
        if($this->isLoginRequest()){
            //set the translate5 login status
            $this->view->loginStatus= $this->isValidLogin() ? ZfExtended_Models_SessionUserInterface::LOGIN_STATUS_SUCCESS : ZfExtended_Models_SessionUserInterface::LOGIN_STATUS_REQUIRED;
            return;
        }
        //redirect the user if the session contains already a user
        if($this->isAuthenticated()) {
            //set login status to 'authenticated'
            $this->view->loginStatus=ZfExtended_Models_SessionUserInterface::LOGIN_STATUS_AUTHENTICATED;
            $this->initDataAndRedirect();
            return;
        }
        
        //set login status to 'login with openid'
        $this->view->loginStatus=ZfExtended_Models_SessionUserInterface::LOGIN_STATUS_OPENID;
    }
    
    /**
     * @deprecated
     * returns a REST like login status information.
     * HTTP 200 and a JSON Representation of the user if authenticated
     * HTTP 404 (!=200) if not authenticated
     */
    public function statusAction() {
        throw new BadMethodCallException('"API login/status" is deprecated use "API session/SESSIONID" instead.');
    }
    
    /**
     * returns true if a user is already registered in this session
     * @return boolean
     */
    protected function isAuthenticated() {
        return !empty($this->_user->data->userGuid);
    }
    
    /**
     * checks if login-request is made
     * @return boolean
     */
    protected function isLoginRequest(){
        return ($this->getRequest()->getParam('login') || $this->getRequest()->getParam('passwd'));
    }
    /**
     * checks if login-request is valid and does corresponding handling
     * @return boolean
     */
    protected function isValidLogin(){
        if (! $this->_form->isValid($this->_request->getParams())) {
            return false;
        }
        $login = trim($this->_form->getValue('login'));
        $passwd = trim($this->_form->getValue('passwd'));
        //ensure that empty passwd can never pass, regardless of what is defined in login.ini, because passwd-default is null in db
        if($passwd == '') {
            return false;
        }
        $invalidLoginCounter = ZfExtended_Factory::get('ZfExtended_Models_Invalidlogin',array($login));
        /* @var $invalidLoginCounter ZfExtended_Models_Invalidlogin */
        if($this->hasMaximumInvalidations($invalidLoginCounter)){
            return false;
        }
        if($this->hasUserAlreadyASession($login)) {
            return false;
        }
        if ($this->authIsValid($login, $passwd)) {
            $invalidLoginCounter->resetCounter(); // bei erfolgreichem login den counter zurücksetzen
            $this->_userModel->setUserSessionNamespaceWithPwCheck($login, $passwd);
            ZfExtended_Models_LoginLog::addSuccess($this->_userModel, "plainlogin");
            $this->getFrontController()->getPlugin('ZfExtended_Controllers_Plugins_SessionRegenerate')->updateSession(true);
            $this->initDataAndRedirect();
            return true;
        }
        ZfExtended_Models_LoginLog::addFailed($login, "plainlogin");
        $invalidLoginCounter->increment();
        if($this->hasMaximumInvalidations($invalidLoginCounter)) {
            return false;
        }
        $this->view->errors = true;
        $this->_form->addError(sprintf($this->_translate->_('Ungültige Logindaten!<br/>Haben Sie Ihr Passwort vergessen oder bislang noch kein Passwort für Ihren Login gesetzt?  Sie können jederzeit einen neuen Link %shier%s anfordern.'),
                '<a href="'. APPLICATION_RUNDIR .'/login/passwdreset">','</a>'));
        return false;
    }
    
    /**
     * Shortcut method for convenience
     * @param string $login
     * @param string $passwd
     */
    protected function authIsValid($login, $passwd) {
        return $this->_helper->auth->isValid($login,$passwd);
    }
    
    /**
     * ensures - if enabled by configuration - that a unique user is only logged in once
     * is enabled by setting $config->runtimeOptions->singleUserRestriction to true
     * @param string $login
     * @return boolean
     */
    protected function hasUserAlreadyASession($login) {
        $config = Zend_Registry::get('config');
        if(! $config->runtimeOptions->singleUserRestriction) {
            return false;
        }
        $lock = ZfExtended_Factory::get('ZfExtended_Models_Db_SessionUserLock');
        /* @var $lock ZfExtended_Models_Db_SessionUserLock */
        
        try {
            $lock->insert(array(
                'login' => $login,
                'internalSessionUniqId' => $this->_session->internalSessionUniqId,
            ));
            return false;
        }
        catch(Zend_Db_Statement_Exception $e) {
            $m = $e->getMessage();
            $isSqlState = strpos($m,'SQLSTATE') === 0;
            $isMissingLogin = stripos($m,'FOREIGN KEY (`login`)') !== false && stripos($m,'a foreign key constraint fails') !== false;
            //if entered a missing login we get an contraint error here,
            //we return false here since this user does not have a session.
            //The not existence of the login is then checked correctly later.
            if($isSqlState && $isMissingLogin) {
                return false;
            }
            //if error is no "duplicate entry" error we throw it regularly!
            if(!$isSqlState || stripos($m,'Duplicate entry') === false) {
                throw $e;
            }
        }
        
        $this->view->errors = true;
        $this->_form->addError($this->_translate->_('Dieser Benutzer wird bereits verwendet. <br/>Bitte warten Sie bis der Benutzer wieder verfügbar ist, oder benutzen Sie einen anderen!'));
        return true;
    }
    
    /**
     *
     * @param ZfExtended_Models_Invalidlogin $invalidLogin
     * @return boolean
     */
    protected function hasMaximumInvalidations(ZfExtended_Models_Invalidlogin $invalidLogin) {
        if($invalidLogin->hasMaximumInvalidations()) {
            $passwdreset = ZfExtended_Factory::get('ZfExtended_Models_Passwdreset');
            /* @var $passwdreset ZfExtended_Models_Passwdreset */
            $passwdreset->reset($this->_form->getValue('login'));
            $this->view->errors = true;
            $this->_form->addError(sprintf($this->_translate->_('Ungültige Logindaten - Zugang gesperrt!<br/>Sie haben Ihr Passwort in den letzten 24 Stunden mehrmals falsch eingegeben, ihr Login wurde gesperrt.<br />Per E-Mail wurde Ihnen ein Link zugesandt, mit welchem Sie Ihr Passwort neu setzen können. Dieser Link ist 30 min gültig und funktioniert nur, so lange Sie Ihren Browser nicht zwischenzeitlich geschlossen haben. Sie können jederzeit einen neuen Link %shier%s anfordern.'),
                    '<a href="'. APPLICATION_RUNDIR .'/login/passwdreset">','</a>'));
            return true;
        }
        return false;
    }
    
    /***
     * Check if the current request is openid redirect
     * @return boolean
     */
    protected function isOpenIdRedirect(){
        return $this->getRequest()->getParam('openidredirect')!=null && $this->getRequest()->getParam('openidredirect')=='openid';
    }
    
    abstract protected function initDataAndRedirect();
    
    /**
     * deletes all sessiondata
     *
     * - redirects to root
     * - if($this->getRequest()->getParam('openidredirect', true) == false, no redirection is done
     * @return void
     *
     */
    public function logoutAction() {
        // Shutdown view script and layout
        $this->_helper->viewRenderer->setNoRender(true);
        Zend_Layout::getMvcInstance()->disableLayout();

        $this->doOnLogout();
        $this->_helper->general->logoutUser();
        $this->postDispatch(); //trigger after action events before redirecting
        if($this->getRequest()->getParam('openidredirect', true)){
            header('Location: '.APPLICATION_RUNDIR.'/');
            exit();
        }
    }
    /**
     * additional handling on logout
     */
    protected function doOnLogout(){
    }
    /**
     * reset password and sent hash to set new password by mail
     *
     *
     * @return bool
     *
     */
    public function passwdresetAction() {
        $this->_form = new ZfExtended_Zendoverwrites_Form('loginPasswdreset.ini');
        $this->_form->setTranslator($this->_translate);
        
        if($this->getRequest()->getParam('login')
                && $this->_form->isValid($this->_request->getParams())){
            $passwdreset = ZfExtended_Factory::get('ZfExtended_Models_Passwdreset');
            /* @var $passwdreset ZfExtended_Models_Passwdreset */
            if($passwdreset->reset($this->_form->getValue('login'))){
                $this->view->message = $this->_translate->_('Per E-Mail wurde Ihnen ein Link zugesandt, mit welchem Sie Ihr Passwort neu setzen können. Dieser Link ist 30 min gültig und funktioniert nur, so lange Sie Ihren Browser nicht zwischenzeitlich geschlossen haben. Sie können jederzeit einen neuen Link über dieses Formular anfordern.');
            }
            else{
                $this->view->errors = true;
                $this->_form->addError($this->_translate->_('Der angegebene Benutzername existiert nicht!'));
            }
        }
        $this->view->form = $this->_form;
    }
    
    /**
     * sets the password and passwdReset to false in the LEK_user-table
     *
     * - needs a resetHash as getParam, as for this user is stored in the database
     * - on succes shows login
     *
     * @return bool
     *
     */
     public function passwdnewAction() {
        $this->view->passwdResetInfo = false;
        if($this->getRequest()->getParam('resetHash',false)){
            $this->_form = new ZfExtended_Zendoverwrites_Form('loginPasswdnew.ini');
            $this->_form->setTranslator($this->_translate);
            $md5Validator = new ZfExtended_Validate_Md5();
            if(!$md5Validator->isValid($this->getRequest()->getParam('resetHash'))){
                $this->passwdResetHashNotValid();
                return;
            }
            
            $resetHashElement = $this->_form->getElement('resetHash');
            $resetHashElement->setValue($this->getRequest()->getParam('resetHash'));

            if($this->getRequest()->getParam('passwd',false) &&
                    $this->_form->isValid($this->_request->getParams())){

                $passwdreset = ZfExtended_Factory::get('ZfExtended_Models_Passwdreset');
                /* @var $passwdreset ZfExtended_Models_Passwdreset */
                $passwdreset->deleteOldHashes();
                
                if(!$passwdreset->hashMatches($this->_form->getValue('resetHash'))){
                    $this->passwdResetHashNotValid();
                    return;
                }

                $user = ZfExtended_Factory::get('ZfExtended_Models_User');
                /* @var $user ZfExtended_Models_User */
                $user->load($passwdreset->getUserId());
                $user->setNewPasswd($this->_form->getValue('passwd'));
                
                $invalidLogin = ZfExtended_Factory::get('ZfExtended_Models_Invalidlogin',array($user->getLogin()));
                /* @var $invalidLogin ZfExtended_Models_Invalidlogin */
                $invalidLogin->resetCounter();
                
                $this->_form = new ZfExtended_Zendoverwrites_Form('loginIndex.ini');
                $this->_form->setTranslator($this->_translate);
                $this->view->heading = $this->_translate->_('Login');
                $this->view->message = $this->_translate->_('Ihr Passwort wurde neu gesetzt. Sie können sich nun einloggen.');
            }
            $this->view->form = $this->_form;
            return;
        }
        $this->redirect('/login');
    }
    
    /**
      * render passwdNew when resetHash not valid
     */
    protected function passwdResetHashNotValid() {
        $this->_form = new ZfExtended_Zendoverwrites_Form('loginPasswdreset.ini');
        $this->_form->setTranslator($this->_translate);
        $this->view->errors = true;
        $this->_form->addError($this->_translate->_('Der ResetHash oder die Browsersitzung ist nicht (mehr) gültig. Bitte fordern Sie über das nebenstehende Formular eine E-Mail mit einem neuen Link an.'));
        $this->view->form = $this->_form;
        $this->render('passwdreset');
    }
    
    /**
     * updates the session locale by the locale stored in the DB for this user
     */
    protected function localeSetup() {
        $locale = $this->_user->data->locale;
        if(Zend_Locale::isLocale($locale)){
            $this->_setLocale($locale);
        }
        else{
            //if there user has no valid locale in the DB we set the current locale
            $this->_userModel->setLocale($this->_session->locale);
        }
    }
    
    /**
     * internal, overwriteable helper function to set the locale information
     * @param string $locale
     */
    protected function _setLocale($locale) {
        $Zend_Locale = new Zend_Locale($locale);
        // save locale in session
        $this->_session->locale = $locale;
        // set locale as Zend App default locale
        Zend_Registry::set('Zend_Locale', $Zend_Locale);
    }
    
}

