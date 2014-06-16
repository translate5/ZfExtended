<?php
 /*
   START LICENSE AND COPYRIGHT

  This file is part of the ZfExtended library and build on Zend Framework

  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

  This file is multi-licensed under the EPL, LGPL and GPL licenses. 

  It may be used under the terms of the Eclipse Public License - v 1.0
  as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
  included in the packaging of this file.  Please review the following information 
  to ensure the Eclipse Public License - v 1.0 requirements will be met:
  http://www.eclipse.org/legal/epl-v10.html.

  Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
  or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
  Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
  included in the packaging of this file.  Please review the following information 
  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
  GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
  http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
  
  @copyright  Marc Mittag, MittagQI - Quality Informatics
  @author     MittagQI - Quality Informatics
  @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html

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
        /**
     * @var stdclass
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
    /**
     * @var string the name of the table against the login is validated
     */
    protected $_authTableName;
    /**
     * @var string the identityColumn in the authTable
     */
    protected $_identityColumn;
    /**
     * @var string the credentialColumn in the authTable
     */
    protected $_credentialColumn;
    /**
     * @var string the credentialTreatment in the authTable
     */
    protected $_credentialTreatment;
    
    public function init(){
        parent::init();
        $this->_translate = ZfExtended_Zendoverwrites_Translate::getInstance();
        $this->_session = new Zend_Session_Namespace();
        $this->_user = new Zend_Session_Namespace('user');
    }

    /**
     * does the login-handling
     *
     * - checks if login has been blocked
     * - blocks login, if necessary
     * @return bool
     */
    public function indexAction() {
        if($this->isLoginRequest() && $this->isValidLogin()){
            return;
        }
        //redirect the user if the session contains already a user
        if($this->isAuthenticated()) {
            $this->initDataAndRedirect();
            return;
        }
        $this->view->form = $this->_form;
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
        $login = $this->_form->getValue('login');
        $passwd = $this->_form->getValue('passwd');
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
            $this->initDataAndRedirect();
            return true;
        }
        $invalidLoginCounter->increment();
        if($this->hasMaximumInvalidations($invalidLoginCounter))
            return false;
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
        return $this->_helper->auth->isValid($login,$passwd,$this->_authTableName,
                    $this->_identityColumn,$this->_credentialColumn,
                    $this->_credentialTreatment);
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
    
    abstract protected function initDataAndRedirect();
    
    /**
     * deletes all sessiondata
     *
     * - redirects to root
     * - if($this->getRequest()->getParam('redirect', true) == false, no redirection is done
     * @return void
     *
     */
    public function logoutAction() {
        // Shutdown view script and layout
        $this->_helper->viewRenderer->setNoRender(true);
        Zend_Layout::getMvcInstance()->disableLayout();

        $this->doOnLogout();
        
        $this->_helper->general->logoutUser();
        if($this->getRequest()->getParam('redirect', true)){
            header('Location: '.APPLICATION_RUNDIR.'/');
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
                $this->view->heading = $this->_translate->_('Login');
                $this->view->message = $this->_translate->_('Ihr Passwort wurde neu gesetzt. Sie können sich nun einloggen.');
            }
            $this->view->form = $this->_form;
            return;
        }
        $this->_redirect('/login');
    }
    
    /**
      * render passwdNew when resetHash not valid
     */
    protected function passwdResetHashNotValid() {
        $this->_form = new ZfExtended_Zendoverwrites_Form('loginPasswdreset.ini');
        $this->view->errors = true;
        $this->_form->addError('Der ResetHash oder die Browsersitzung ist nicht (mehr) gültig. Bitte fordern Sie über das nebenstehende Formular eine E-Mail mit einem neuen Link an.');
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

