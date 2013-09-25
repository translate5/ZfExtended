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
     * @var Zend_Translate
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
        $this->_translate = Zend_Registry::get('Zend_Translate');
        $this->_session = new Zend_Session_Namespace();
        $this->_user = new Zend_Session_Namespace('user');
    }

    /**
     * does the login-handling
     *
     * - checks if login has been blocked
     * - blocks login, if necessary
     * @return bool
     *
     */
    public function indexAction() {
        if($this->isLoginRequest() && $this->isValidLogin()){
            return;
        }
        $this->view->form = $this->_form;
    }
    /**
     * checks if login-request is made
     * @return boolean
     */
    protected function isLoginRequest(){
        if (!($this->getRequest()->getParam('login') || $this->getRequest()->getParam('passwd'))) {
            return false;
        }
        return true;
    }
    /**
     * checks if login-request is valid and does corresponding handling
     * @return boolean
     */
    protected function isValidLogin(){
        if ($this->_form->isValid($this->_request->getParams())) {
            $login = strtolower($this->_form->getValue('login'));
            $passwd = $this->_form->getValue('passwd');
            $invalidLoginCounter = ZfExtended_Factory::get('ZfExtended_Models_Invalidlogin',array($login));

            if ($this->_helper->auth->isValid($login,$passwd,$this->_authTableName,
                    $this->_identityColumn,$this->_credentialColumn,
                    $this->_credentialTreatment)) {
                $invalidLoginCounter->resetCounter(); // bei erfolgreichem login den counter zurücksetzen
                $this->_userModel->setUserSessionNamespaceWithPwCheck($login, $passwd);
                $this->initDataAndRedirect();
                return true;
            }
            $invalidLoginCounter->increment();
            if($invalidLoginCounter->hasMaximumInvalidations()) {
                $this->passwdReset($login);
                $invalidLoginCounter->resetCounter();
                $this->view->errors = true;
                $this->_form->addError(sprintf($this->_translate->_('Ungültige Logindaten - Zugang gesperrt!<br/>Sie haben Ihr Passwort in den letzten 24 Stunden mehrmals falsch eingegeben, ihr Login wurde gesperrt.<br />Per E-Mail wurde Ihnen ein Link zugesandt, mit welchem Sie Ihr Passwort neu setzen können. Dieser Link ist 30 min gültig und funktioniert nur, so lange Sie Ihren Browser nicht zwischenzeitlich geschlossen haben. Sie können jederzeit einen neuen Link %shier%s anfordern.'),
                        '<a href="'. APPLICATION_RUNDIR .'/login/passwdreset">','</a>'));
                return false;
            }
            $this->view->errors = true;
                $this->_form->addError(sprintf($this->_translate->_('Ungültige Logindaten!<br/>Haben Sie Ihr Passwort vergessen oder bislang noch kein Passwort für Ihren Login gesetzt?  Sie können jederzeit einen neuen Link %shier%s anfordern.'),
                        '<a href="'. APPLICATION_RUNDIR .'/login/passwdreset">','</a>'));
                return false;
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
        
        $session = new Zend_Session_Namespace();
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
            if($this->passwdReset($this->_form->getValue('login'))){
                $this->view->message = $this->_translate->_('Per E-Mail wurde Ihnen ein Link zugesandt, mit welchem Sie Ihr Passwort neu setzen können. Dieser Link ist 30 min gültig und funktioniert nur, so lange Sie Ihren Browser nicht zwischenzeitlich geschlossen haben. Sie können jederzeit einen neuen Link über dieses Formular anfordern.');
            }
            else{
                $this->view->errors = true;
                $this->_form->addError($this->_translate->_('Der angegebene Benutzername existiert nicht!'));
            }
        }
        $this->view->form = $this->_form;
    }
    
    //@todo aktive Session für passwdnew da?
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
                $passwdreset->deleteOldHashes();
                /* @var $passwdreset ZfExtended_Models_Passwdreset */
                try {
                    $s = $passwdreset->db->select();
                    $s->where('resetHash = ?', $this->_form->getValue('resetHash'))
                      ->where('internalSessionUniqId = ?',$this->_session->internalSessionUniqId);
                    #echo $s->assemble();exit;
                    $passwdreset->loadRowBySelect($s);
                } catch (ZfExtended_Models_Entity_NotFoundException $exc) {
                     $this->passwdResetHashNotValid();
                    return;
                }

                $user = ZfExtended_Factory::get('ZfExtended_Models_User');
                /* @var $user ZfExtended_Models_User */
                $user->load($passwdreset->getUserId());
                $user->setPasswd(md5($this->_form->getValue('passwd')));
                $user->setPasswdReset(FALSE);
                $user->validate();
                $user->save();
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
     * reset password
     * @param string $login
     * @return boolean
     */
    protected function passwdReset(string $login) {
        $session = new Zend_Session_Namespace();
        $user = ZfExtended_Factory::get('ZfExtended_Models_User');
        /* @var $user ZfExtended_Models_User */
        try {
            $user->loadRow('login = ?', $login);
        } catch (ZfExtended_Models_Entity_NotFoundException $exc) {//catch the 404 thrown, if no user found
            return false;
        }

        $session->resetHash = md5($this->_helper->guid->create());
        
        $passwdreset = ZfExtended_Factory::get('ZfExtended_Models_Passwdreset');
        /* @var $passwdreset ZfExtended_Models_Passwdreset */
        
        $passwdreset->setUserId($user->getId());
        $passwdreset->setResetHash($session->resetHash);
        $passwdreset->setExpiration(time()+1800);
        $passwdreset->setInternalSessionUniqId($session->internalSessionUniqId);
        
        $passwdreset->validate();
        $passwdreset->save();
        
        $this->_helper->general->mail(
                $user->getEmail(),
                '',
                $this->_translate->_('Passwort neu setzen'),
                array(
                    'gender' =>$user->getGender(),
                    'surname' =>$user->getSurName(),
                    'resetHash' =>$session->resetHash
                )
        );
        return true;
    }
}

