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

/**
 * code to check access of the authenticated user to the requested URL per request
 */
class ZfExtended_Controller_Helper_Access extends Zend_Controller_Action_Helper_Abstract {
    /**
     * @var Zend_Controller_Router_Route_Interface
     */
    protected Zend_Controller_Router_Route_Interface $_route;

    /**
     * @var Zend_Controller_Request_Abstract|null
     */
    protected ?Zend_Controller_Request_Abstract $_request = null;

    /**
     * @var array
     */
    protected array $_roles = ['noRights'];

    public function __construct() {
        $front = $this->getFrontController();
        $this->_request = $front->getRequest();
        $this->_route = $front->getRouter()->getCurrentRoute();
    }

    /**
     * authenticates the user by session
     * fill up the roles, check ACL and redirect the user to the desired page
     * @throws Zend_Exception
     */
    public function isAuthenticated(){
        $acl = ZfExtended_Acl::getInstance();
        $auth = ZfExtended_Authentication::getInstance();
        $isAuthenticated = false;
        if($auth->isAuthenticated()) {
            $isAuthenticated = true;
            $locale = (string) Zend_Registry::get('Zend_Locale');
            $this->_roles = $auth->getUserRoles();
            $user = $auth->getUser();
            if($locale !== $user->getLocale()) {
                $user->setLocale($locale);
                $user->save();
            }
        }

        $resource = $this->getResource();
        $action = $this->getAction();

        $userDeleted = $auth->getAuthStatus() == $auth::AUTH_DENY_USER_NOT_FOUND;
        if($userDeleted) {
            Zend_Session::destroy();
        }

        if($userDeleted || !$acl->isInAllowedRoles($this->_roles, $resource, $action)) {
            $this->accessDenied($isAuthenticated, $resource, $action);
        }
        else {
            $this->cleanRedirectTo($resource);
        }
    }
    
    /**
     * Checks if the request is dispatchable (a controller and action exists)
     * if not a 404 is produced
     */
    public function isDispatchable() {
        $front = $this->getFrontController();
        if(! $front->getDispatcher()->isDispatchable($front->getRequest())) {
            $e = new ZfExtended_NotFoundException();
            $e->setMessage('Seite nicht gefunden!',true);
            throw $e;
        }
    }
    
    /**
     * handles access denied depending on route type and authentication
     * @throws ZfExtended_NoAccessException if access via route restDefault is forbidden
     * @throws ZfExtended_NotAuthenticatedException|Zend_Exception if no user is authenticated at all
     */
    private function accessDenied(bool $authenticated, string $resource, string $action) {
        if($this->isRestRoute()){
            //setting the message to empty here, since a textual message would break the JSON decoding
            // Exceptions in this early stage of the application would not be converted correctly to JSON
            // since RestContext is done in shutdown of the dispatching
            if($authenticated) {
                $e = new ZfExtended_NoAccessException();
                $e->setLogging(false);
                //having no access here is mostly because of missing ACL rules
                // or wrong implementation in the GUI (missing is allowed there, showing not allowed functionality then)
                // so we log it as error
                Zend_Registry::get('logger')->exception($e, [
                    'eventCode' => 'E1352',
                    'message' => 'No access to requested URL - check ACL configuration or usage',
                    'extra' => [
                        'resource' => $resource,
                        'action' => $action,
                        'roles' => $this->_roles,
                    ]
                ]);
            }
            else {
                $e = new ZfExtended_NotAuthenticatedException();
            }
            $e->setMessage("");
            throw $e;
        }

        $redirector = ZfExtended_Factory::get('Zend_Controller_Action_Helper_Redirector');
        /* @var $redirector Zend_Controller_Action_Helper_Redirector */

        if (in_array('noRights', $this->_roles) && count($this->_roles)>=1){
            $this->updateStoredRedirectTo();
            $redirector->gotoSimpleAndExit('index', 'login','default');
        }
        else{
            $redirector->gotoSimpleAndExit('index', 'index','default');
        }
    }

    /**
     * returns true when the used router is a REST Router and when a path was given.
     * When path is empty, thats the default controller which is surly not REST like
     * @return boolean
     */
    protected function isRestRoute() {
        $routeInst = $this->_route;
        $route = get_class($this->_route);
        $restRoutes = ['Zend_Rest_Route', 'ZfExtended_Controller_RestLikeRoute', 'ZfExtended_Controller_RestFakeRoute'];

        $path = $this->_request->getPathInfo();
        $path = trim($path, $routeInst::URI_DELIMITER);
        $emptyPath = empty($path);
        return !$emptyPath && in_array($route, $restRoutes);
    }

    /**
     * Stores the given redirecthash from the login process into
     */
    public function addHashToOrigin(string $hash) {
        if(!empty($hash)){
            $s = new Zend_Session_Namespace();
            $redTo = explode('#', $this->_session->redirectTo ?? '');
            $s->redirectTo = $redTo[0] .'#'. trim($hash, '#');
        }
    }

    /**
     * Redirects and exits to the originating request before calling the login
     */
    public function redirectToOrigin() {
        $s = new Zend_Session_Namespace();
        if(!empty($s->redirectTo)) {
            //$this->redirect($redTo, ['code' => 302, 'exit' => true]);
            $this->_actionController->redirect($s->redirectTo, ['code' => 302, 'exit' => true]);
        }
    }

    /**
     * Updates on denied access the originally requested URL for redirection after login
     */
    private function updateStoredRedirectTo() {
        $target = $this->getRequest()->getRequestUri();
        if(Zend_Session::isDestroyed()) {
            return;
        }
        $s = new Zend_Session_Namespace();

        //if we should redirect to the same location, this is a loop and we should break it, for default processing after login redirect.
        if($target == $s->redirectTo) {
            unset($s->redirectTo);
        }
        else {
            $s->redirectTo = $target;
        }
    }

    /**
     * Return the redirectTo session data value. If the there is no session, empty value will be returned
     * @return string
     */
    public function getRedirectTo(): string
    {
        if(Zend_Session::isDestroyed()) {
            return '';
        }
        $s = new Zend_Session_Namespace();
        return $s->redirectTo ?? '';
    }


    /**
     * Cleans the stored redirect to variable after successful page access (unless it is not the login page itself!)
     * @param string $resource
     */
    private function cleanRedirectTo(string $resource)
    {
        // UGLY, but convienent: do not unset the redirect to after successfully opening the login page,
        // we need the value on the next request
        if($resource === 'login') {
            return;
        }
        $s = new Zend_Session_Namespace();
        if(!empty($s->redirectTo)) {
            unset($s->redirectTo);
        }
    }

    /**
     * @return string
     * @throws Zend_Exception
     */
    private function getResource(): string
    {
        $module = Zend_Registry::get('module') . '_';
        if ($module === 'default_') {
            $module = '';
        }
        $resource = $module . $this->_request->getControllerName();
        return $resource;
    }

    /**
     * @return string
     */
    private function getAction(): string
    {
        $action = $this->_request->getActionName();
        //set the real operation action
        if ($action == 'operation') {
            $action = $this->_request->getParam('operation') . 'Operation';
        }
        return $action;
    }
}
