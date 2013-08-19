<?php
 /*
 START LICENSE AND COPYRIGHT
 
 This file is part of Translate5 Editor PHP Serverside and build on Zend Framework
 
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
 * @package translate5
 * @version 1.0
 *
 */
/* 
 * Helper, der bei jedem http-request prüft, ob der Benutzer noch authentifiziert ist
 */
class ZfExtended_Controller_Helper_Access extends  Zend_Controller_Action_Helper_Abstract {
    /**
     * Zend_Session_Namespace
     */
    protected $_session = NULL;
    /**
     * Zend_Controller_Front
     */
    protected $_front = NULL;
    /**
     * Zend_Controller_Router_Route_Interface
     */
    protected $_route = NULL;
    /**
     * Zend_Controller_Request_Abstract
     */
    protected $_request = NULL;
    /**
     * array
     */
    protected $_roles = array('noRights');
    /**
     * @var string
     */
    protected $_role;

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
        $this->_route = get_class($this->_front->getRouter()->getCurrentRoute());
        $acl = ZfExtended_Acl::getInstance();

        // Lade aktuelle Rolle aus der Identität falls vorhaben
        $this->_roles = Zend_Auth::getInstance()->hasIdentity() ? array('user') : $this->_roles;
        //falls user, lade rolle aus der Session
        if(in_array('user', $this->_roles)){
            $user = new Zend_Session_Namespace('user');
            $this->_roles = explode(',', $user->roles);
        }
        $module = Zend_Registry::get('module').'_';
        if($module === 'default_'){
            $module = '';
        }
        // Prüfe Rechte
        try {
            $allowed = false;
            foreach ($this->_roles as $role) {
                if($acl->isAllowed(
                    $role, $module.$this->_request->getControllerName(),
                    $this->_request->getActionName())){
                        $allowed = true;
                        $this->_role = $role;
                    }
            }
            if(!$allowed){
                $this->notAuthenticated();
            }
        } catch (Exception $exc) {
            if($this->_route === 'Zend_Rest_Route'){
                $this->notAuthenticated();
            }
            #throw new ZfExtended_NotFoundException('Seite nicht gefunden!');
        }
    }
    
    /**
     * Returns the role which did allow the user to enter application
     * @return string
     */
    public function getAuthenticaedRole() {
      return $this->_role;
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
     * @throws ZfExtended_NoAccessException wenn Zugriff via route restDefault nicht (mehr) erlaubt
     * @return false
     */
    private function notAuthenticated(){
        if($this->_route === 'Zend_Rest_Route'){
            throw new ZfExtended_NoAccessException('Keine Zugriffsrechte!');
            return false;
        }
        $redirector = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'Redirector'
        );
        if ($this->_role === 'noRights'){
            $redirector->gotoSimpleAndExit('index', 'login','default');
        }
        else{
            $redirector->gotoSimpleAndExit('index', 'index','default');
        }
        return false;
    }
}
