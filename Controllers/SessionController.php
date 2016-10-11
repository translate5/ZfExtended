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
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::postAction()
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
            return;
        }
        
        //$this->_userModel = ZfExtended_Factory::get('ZfExtended_Models_User');
        if($this->_helper->auth->isValid($login, $passwd)) {
            $userModel = ZfExtended_Factory::get(Zend_Registry::get('config')->authentication->userEntityClass);
            /* @var $userModel ZfExtended_Models_SessionUserInterface */
            $userModel->setUserSessionNamespaceWithPwCheck($login, $passwd);
            $session = new Zend_Session_Namespace();
            $this->setLocale($session, $userModel);
            $this->view->sessionId = session_id();
            $this->view->sessionToken = $session->internalSessionUniqId;
            $this->log('User authentication by API successful for '.$login);
            return;
        }
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
     * (non-PHPdoc)
     * @see ZfExtended_RestController::deleteAction()
     */
    public function deleteAction() {
        $sessionId = $this->_getParam('id');
        
        $sessionTable = ZfExtended_Factory::get('ZfExtended_Models_Db_Session');
        /* @var $sessionTable ZfExtended_Models_Db_Session */
        $sessionTable->delete(array("session_id = ?" => $sessionId));
        
        $SessionMapInternalUniqIdTable = ZfExtended_Factory::get('ZfExtended_Models_Db_SessionMapInternalUniqId');
        /* @var $SessionMapInternalUniqIdTable ZfExtended_Models_Db_SessionMapInternalUniqId */
        $SessionMapInternalUniqIdTable->delete(array("session_id = ?" => $sessionId));
    }
    
    protected function log($msg) {
        if(ZfExtended_Debug::hasLevel('core', 'apiLogin')) {
            error_log($msg);
        }
    }
}