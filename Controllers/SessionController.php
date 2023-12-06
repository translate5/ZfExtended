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

use MittagQI\Translate5\Acl\Rights;
use MittagQI\ZfExtended\Acl\SystemResource;
use ZfExtended_Authentication as Auth;

/**
 * Session Controller to start/delete authenticated sessions
 * the POST/DELETE actions are not CSRF-protected to enable an API-driven authentication without App-Token
 */
class ZfExtended_SessionController extends ZfExtended_RestController {
    
    const STATE_AUTHENTICATED = 'authenticated';
    const STATE_NOT_AUTHENTICATED = 'not authenticated';

    protected array $dataSanitizationMap = ['passwd' => ZfExtended_Sanitizer::UNSANITIZED];

    /**
     * post & delete shall be csrf unprotected, otherwise one would need an APP-token to register via post
     * @var string[]
     */
    protected array $_unprotectedActions = [ 'post', 'delete', 'get' ];

    /**
     * inits the internal entity Object, handels given limit, filter and sort parameters
     * @see Zend_Controller_Action::init()
     */
    public function init() {
        if (ZfExtended_Utils::isDevelopment()) {
            $this->_unprotectedActions[] = 'index';
        }
        $this->acl = ZfExtended_Acl::getInstance();
        $this->initRestControllerSpecific();
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::indexAction()
     * returns a REST like login status information.
     * HTTP 200 and a JSON Representation of the user if authenticated
     * HTTP 404 (!=200) if not authenticated
     */
    public function getAction() {
        $auth = ZfExtended_Authentication::getInstance();
        if(! $auth->isAuthenticated()) {
            $this->_response->setHttpResponseCode(404);
            $this->view->state = self::STATE_NOT_AUTHENTICATED;
            $this->view->user = null;
            return;
        }
        $this->view->state = self::STATE_AUTHENTICATED;
        $this->view->user = clone $auth->getUserData();
        $this->view->user->passwd = '********';
        unset($this->view->user->openIdSubject);
        unset($this->view->user->openIdIssuer);
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::putAction()
     */
    public function putAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->put');
    }
    
    public function indexAction() {
        if(! ZfExtended_Utils::isDevelopment()) {
            throw new ZfExtended_BadMethodCallException(__CLASS__.'->index');
        }
        $this->_helper
            ->getHelper('contextSwitch')
            ->addContext('html', [
                'headers' => [
                    'Content-Type'          => 'text/html',
                ]
            ])
            ->initContext('html');
        //since we are in a rest controller we have to load and echo manually
        echo $this->view->render('session/index.phtml');
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::postAction()
     * @return bool true if login was successful, false otherwise
     * @throws Zend_Db_Table_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_NoAccessException
     */
    public function postAction() {
        $this->decodePutData();
        settype($this->data, 'object');
        settype($this->data->login, 'string');
        settype($this->data->passwd, 'string');
        //enabling passing credentials by plain form requests or given data object
        $login = $this->getParam('login', $this->data->login);
        $passwd = trim($this->getParam('passwd', $this->data->passwd));
        $errors = [];
        $t = ZfExtended_Zendoverwrites_Translate::getInstance();
        /* @var $t ZfExtended_Zendoverwrites_Translate */
        if(empty($login)) {
             $errors['login'] = $t->_('Kein Benutzername angegeben.');
        }
        
        if(empty($passwd)) {
             $errors['passwd'] = $t->_('Kein Passwort angegeben.');
        }
        $authentication = Auth::getInstance();

        if(!empty($errors)) {
            $e = new ZfExtended_ValidateException();
            $e->setErrors($errors);
            $this->handleValidateException($e);
            $this->log('User authentication by API failed with error: '.print_r($errors, 1));
            return false;
        }

        $invalidLoginCounter = ZfExtended_Factory::get('ZfExtended_Models_Invalidlogin',array($login));
        /* @var $invalidLoginCounter ZfExtended_Models_Invalidlogin */

        $oldSessId = substr(Zend_Session::getId(), 0, 10);
        session_destroy();
        session_start();
        $sessionId = Zend_Session::getId();
        if ($authentication->authenticatePasswordAndToken($login, $passwd)) {
            $userModel = $authentication->getUser();

            // check for existing valid session for the current user
            //$sessionId = ZfExtended_Session::updateSession(true, true, intval($userModel->getId()));

            $newSessId = substr($sessionId, 0, 10);

            $session = new Zend_Session_Namespace();
            $this->setLocale($session, $userModel);
            $this->view->sessionId = $sessionId;
            
            $sessionDb = new ZfExtended_Models_Db_Session();
            $this->view->sessionToken = $sessionDb->updateAuthToken($this->view->sessionId, $userModel->getId());
            
            $this->log('User authentication by API successful for '.$login);
            $invalidLoginCounter->resetCounter();
            ZfExtended_Models_LoginLog::addSuccess($authentication, 'sessionapi'.'#'.$oldSessId.'#'.$newSessId);
            return true;
        }
        ZfExtended_Models_LoginLog::addFailed($login, 'sessionapi'.'#'.$oldSessId);
        $invalidLoginCounter->increment();
        //throwing a 403 on the authentication request means:
        //  hey guy you could not be authenticated with the given credentials!
        $this->log('User authentication by API failed for '.$login);
        throw new ZfExtended_NoAccessException();
    }

    /**
     * Sets the locale in the session
     * @param Zend_Session_Namespace $session
     * @param ZfExtended_Models_User $userModel
     * @throws Zend_Exception
     */
    protected function setLocale(Zend_Session_Namespace $session, ZfExtended_Models_User $userModel) {
        $session->locale = ZfExtended_Utils::getLocale($userModel->getLocale());
    }
    
    /**
     * Deleting the session via session_id or internalSessionUniqId
     * (non-PHPdoc)
     * @see ZfExtended_RestController::deleteAction()
     */
    public function deleteAction() {
        $sessionId = $this->_getParam('id');

        $sessionTable = new ZfExtended_Models_Db_Session();
        // longer as 30 (32) means that it is the internalSessionUniqId,
        // and we delete the session by internalSessionUniqId then
        $this->acl = ZfExtended_Acl::getInstance();
        if($this->isAllowed(SystemResource::ID, SystemResource::SESSION_DELETE_BY_INTERNAL_ID)
            && strlen($sessionId) > 30) {
            $sessionTable->delete([
                'internalSessionUniqId = ?' => $sessionId
            ]);
            return;
        }
        
        $sessionTable->delete([
            'session_id = ?' => $sessionId
        ]);
    }
    
    protected function log($msg) {
        if(ZfExtended_Debug::hasLevel('core', 'apiLogin')) {
            error_log($msg);
        }
    }
}
