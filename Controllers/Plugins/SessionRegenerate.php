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

    /*
     * @var Zend_Session_Namespace
     */
    protected $_session = NULL;
    /**
     * Wird vor dem Start des Dispatcher Laufes ausgeführt
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function dispatchLoopShutdown()
    {
        $layouthelper = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'layout'
        );
        if($layouthelper->isEnabled() and !Zend_Session::isDestroyed()){
            $row = ZfExtended_Factory::get('ZfExtended_Models_Entity',
                        array('ZfExtended_Models_Db_SessionMapInternalUniqId',
                            array()));
            $row->loadRow('session_id = ?',Zend_Session::getId());
            $config = Zend_Registry::get('config');
            Zend_Session::rememberMe($config->resources->ZfExtended_Resource_Session->remember_me_seconds);
            $row->setSession_id(Zend_Session::getId());
            $row->setModified(time());
            $row->save();
        }
    }
}