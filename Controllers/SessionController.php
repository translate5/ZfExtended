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

class ZfExtended_SessionController extends ZfExtended_RestController {
    
    const STATE_AUTHENTICATED = 'authenticated';
    const STATE_NOT_AUTHENTICATED = 'not authenticated';

    /**
     * inits the internal entity Object, handels given limit, filter and sort parameters
     * @see Zend_Controller_Action::init()
     */
    public function init() {
        $this->initRestControllerSpecific();
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::indexAction()
     * returns a REST like login status information.
     * HTTP 200 and a JSON Representation of the user if authenticated
     * HTTP 404 (!=200) if not authenticated
     */
    public function getAction () {
        $user = new Zend_Session_Namespace('user');
        if(empty($user->data->userGuid)) {
            $this->_response->setHttpResponseCode(404);
            $this->view->state = self::STATE_NOT_AUTHENTICATED;
            $this->view->user = null;
            return;
        }
        $this->view->state = self::STATE_AUTHENTICATED;
        $this->view->user = clone $user->data;
        $this->view->user->passwd = '********';
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::putAction()
     */
    public function putAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->put');
    }
    
    public function indexAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->index');
        
        //for debugging:
        echo '<form method="POST" action="'.APPLICATION_RUNDIR.'/editor/session/">';
        echo '<input type="text" name="login" value="login"/><br />';
        echo '<input type="text" name="passwd" value="passwd"/><br />';
        echo '<input type="text" name="taskGuid" value="taskGuid"/><br />';
        echo '<input type="submit" value="POST"/>';
        echo '</form>';
        exit;
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::postAction()
     * @return boolean true if login was successful, false otherwise
     */
    public function postAction() {
        $this->decodePutData();
        settype($this->data->login, 'string');
        settype($this->data->passwd, 'string');
        //enabling passing credentials by plain form requests or given data object
        $login = $this->getParam('login', $this->data->login);
        $passwd = $this->getParam('passwd', $this->data->passwd);
        $errors = [];
        $t = ZfExtended_Zendoverwrites_Translate::getInstance();
        /* @var $t ZfExtended_Zendoverwrites_Translate */;;
        if(empty($login)) {
             $errors['login'] = $t->_('Kein Benutzername angegeben.');
        }
        
        if(empty($passwd)) {
             $errors['passwd'] = $t->_('Kein Passwort angegeben.');
        }
        if(!empty($errors)) {
            $e = new ZfExtended_ValidateException();
            $e->setErrors($errors);
            $this->handleValidateException($e);
            $this->log('User authentication by API failed with error: '.print_r($errors, 1));
            return false;
        }
        
        $invalidLoginCounter = ZfExtended_Factory::get('ZfExtended_Models_Invalidlogin',array($login));
        /* @var $invalidLoginCounter ZfExtended_Models_Invalidlogin */
        
        if($this->_helper->auth->isValid($login, $passwd)) {
            $userClass = Zend_Registry::get('config')->authentication->userEntityClass;
            $userModel = ZfExtended_Factory::get($userClass);
            /* @var $userModel ZfExtended_Models_SessionUserInterface */
            $userModel->setUserSessionNamespaceWithPwCheck($login, $passwd);
            $session = new Zend_Session_Namespace();
            $this->setLocale($session, $userModel);
            $this->view->sessionId = session_id();
            $this->view->sessionToken = $session->internalSessionUniqId;
            $userSession = new Zend_Session_Namespace('user');
            //set a flag to identify that this session was started by API 
            $userSession->loginByApiAuth = true;
            $this->log('User authentication by API successful for '.$login);
            $invalidLoginCounter->resetCounter();
            return true;
        }
        $invalidLoginCounter->increment();
        //throwing a 403 on the authentication request means: 
        //  hey guy you could not be authenticated with the given credentials!
        $this->log('User authentication by API failed for '.$login);
        throw new ZfExtended_NoAccessException();
    }
    
    /**
     * Sets the locale in the session
     * @param Zend_Session_Namespace $session
     * @param ZfExtended_Models_SessionUserInterface $userModel
     */
    protected function setLocale(Zend_Session_Namespace $session, ZfExtended_Models_SessionUserInterface $userModel) {
        $locale = $userModel->getLocale();
        if(!Zend_Locale::isLocale($locale)){
            $locale = Zend_Registry::get('config')->runtimeOptions->defaultLanguage;
        }
        $session->locale = $locale;
    }
    
    /**
     * Deleteing the session via session_id or internalSessionUniqId
     * (non-PHPdoc)
     * @see ZfExtended_RestController::deleteAction()
     */
    public function deleteAction() {
        $sessionId = $this->_getParam('id');
        
        $sessionTable = ZfExtended_Factory::get('ZfExtended_Models_Db_Session');
        /* @var $sessionTable ZfExtended_Models_Db_Session */
        $SessionMapInternalUniqIdTable = ZfExtended_Factory::get('ZfExtended_Models_Db_SessionMapInternalUniqId');
        /* @var $SessionMapInternalUniqIdTable ZfExtended_Models_Db_SessionMapInternalUniqId */
        
        //longer as 30 (32) means that it is the sessionMapInternalUniqId, so we have to fetch the real session_id before
        if(strlen($sessionId) > 30) {
            $result = $SessionMapInternalUniqIdTable->fetchRow(['internalSessionUniqId = ?' => $sessionId]);
            if(!$result) {
                //we dont throw any information about the success here due security reasons, we just do nothing
                return;
            }
            $sessionId = $result->session_id;
        }
        
        $sessionTable->delete(["session_id = ?" => $sessionId]);
        $SessionMapInternalUniqIdTable->delete(["session_id = ?" => $sessionId]);
    }
    
    protected function log($msg) {
        if(ZfExtended_Debug::hasLevel('core', 'apiLogin')) {
            error_log($msg);
        }
    }
}