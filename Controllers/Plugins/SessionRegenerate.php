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
 * Plugin zur Regnerierung der Session-ID (Vermeidung von XSS-Attacken)
 *
 * - nutzt rememberMe, denn nur so funktioniert es bei Nutzung von Zend_Auth
 * - rememberMe ruft regenerateId auf
 * -  Regeneriert die Session nur, wenn das Layout aktiviert ist, denn bei vielen
 *    Aufrufen in wenigen Millisekunden Abstand kommen sich die Aufrufe gegenseitig 
 *    in die Quere mit dem Effekt, dass der User ausgeloggt wird.
 * - legt die Variable $session->internalSessionUniqId im allgemeinen
 *   Session-Namespace fest. Sie wird nur intern in der Programmierung und für
 *   Flag-Files verwendet und darf aus Sicherheitsgründen nicht nicht in Cookies
 *   gesetzt werden. 
 */
class ZfExtended_Controllers_Plugins_SessionRegenerate extends Zend_Controller_Plugin_Abstract {

    /**
     * Wird vor dem Start des Dispatcher Laufes ausgeführt
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function dispatchLoopShutdown() {
        $layouthelper = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'layout'
        );
        $user = new Zend_Session_Namespace('user');
        $isAuthenticated = !empty($user->data->userGuid);
        $this->updateSession($layouthelper->isEnabled() && !$isAuthenticated && !Zend_Session::isDestroyed());
    }

    /**
     * Updates the sessionMapInternalUniqId table modified stamp and regenerates the session id if needed 
     * @param bool $regenerate if true, the session id is regenerated!
     */
    public function updateSession($regenerate = false) {
        $db = ZfExtended_Factory::get('ZfExtended_Models_Db_SessionMapInternalUniqId');
        /* @var $db ZfExtended_Models_Db_SessionMapInternalUniqId */
        $newSessionId = $oldSessionId = Zend_Session::getId();
        
        if($regenerate){
            $config = Zend_Registry::get('config');
            Zend_Session::rememberMe($config->resources->ZfExtended_Resource_Session->remember_me_seconds);
            $newSessionId = Zend_Session::getId();
        }
        $db->update([
            'session_id' => $newSessionId,
            'modified' => time(),
        ], ['session_id = ?' => $oldSessionId]);
    }
    
        
}