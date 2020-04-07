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
 * @package translate5
 * @version 1.0
 *
 * Helper, der bei jedem http-request prüft, ob der Benutzer noch authentifiziert ist
 */
class ZfExtended_Controller_Helper_Access extends Zend_Controller_Action_Helper_Abstract implements ZfExtended_Controllers_helpers_IAccess {
    /**
     * @var Zend_Controller_Front
     */
    protected $_front = NULL;
    /**
     * @var Zend_Controller_Router_Route_Abstract
     */
    protected $_route = NULL;
    /**
     * @var Zend_Controller_Request_Abstract
     */
    protected $_request = NULL;
    /**
     * @var array
     */
    protected $_roles = array('noRights');
    /**
     * @var ZfExtended_Acl
     */
    protected $_acl;

    /**
     * Authentifiziert den Benutzer
     *
     * - leitet den Nutzer bei fehlender Berechtigung zur Loginseite weiter (falls
     *   nicht eingeloggt) oder zur index-Seite (falls eingeloggt)
     * - prüft in diesem Zuge auf erfolgte Berechtigung und vorhandene Rechte für
     *   die angefragte Resource
     * - existiert die Seite nicht, wird ein 404-Fehler via ErrorController geworfen
     *
     * @throws Zend_Exception falls Benutzer keine Rechte zum Login hat
     * @return boolean
     */
    public function isAuthenticated(){
        $this->_front = $this->getFrontController();
        $this->_request = $this->_front->getRequest();
        $this->_route = $this->_front->getRouter()->getCurrentRoute();
        $this->_acl = ZfExtended_Acl::getInstance();
        
        $this->setRoles();
        $this->checkRights();
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
     * checks the rights of the user and redirects if no access is allowed
     */
    protected function checkRights() {
        $module = Zend_Registry::get('module').'_';
        if($module === 'default_'){
            $module = '';
        }
        $action = $this->_request->getActionName();
        $ressource = $module.$this->_request->getControllerName();
        //set the real operation action
        if($action=='operation'){
            $action = $this->_request->getParam('operation').'Operation';
        }
        if($this->_acl->isInAllowedRoles($this->_roles, $ressource, $action)){
            return;
        }
        $this->notAuthenticated();
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
     * Sets the roles 
     * 
     * -adds the passed roles to Zend_Session_Namespace('user')->roles
     * - sets Zend_Session_Namespace('user')->roles as array
     * - ensures, that they are not added if already existent
     * 
     */
    protected function setRoles() {
        //normally basic and noRights are already set by login, but we keep this code here 
        $roles2add = Zend_Auth::getInstance()->hasIdentity() ? array('basic','noRights') : $this->_roles;
        $user = new Zend_Session_Namespace('user');
        settype($user->data, 'object');
        settype($user->data->roles, 'array');
        foreach ($roles2add as $role) {
            if(!in_array($role, $user->data->roles)) {
                $user->data->roles[] = $role;
            }
        }
        $this->_roles = $user->data->roles;
    }
    /**
     * Returns the roles of the user
     * @return array
     */
    public function getRoles() {
      return $this->_roles;
    }
    
    /**
     * führt für Rest-Zugriffe und normale Zugriffe unterschiedliches Verhalten
     * bei nicht authentifizierten Zugriffen durch
     * - Rest: Exception
     * - setzen von default/login/index bei Rolle noRights
     * - Ansonsten: setzen von default/index/index bei allen anderen Rollen
     *
     * @throws ZfExtended_NotAuthenticatedException wenn Zugriff via route restDefault nicht (mehr) erlaubt
     * @return false
     */
    private function notAuthenticated(){
        if($this->isRestRoute()){
            //setting the message to empty here, since a textual message would break the JSON decoding
            // Exceptions in this early stage of the application would not be converted correctly to JSON
            // since RestContext is done in shutdown of the dispatching
            $e = new ZfExtended_NotAuthenticatedException();
            $e->setMessage("");
            throw $e;
        }
        $redirector = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'Redirector'
        );
        if (in_array('noRights', $this->_roles) && count($this->_roles)===1){
            $redirector->gotoSimpleAndExit('index', 'login','default');
        }
        else{
            $redirector->gotoSimpleAndExit('index', 'index','default');
        }
        return false;
    }
}
