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
class ZfExtended_Controller_Helper_Auth extends Zend_Controller_Action_Helper_Abstract {
    /**
     * authenticates a user
     *
     * @param  string                   $login 
     * @param  string                   $passwd in clear text
     * @param  string                   $authTableName as taken by Zend_Auth_Adapter_DbTable
     * @param  string                   $identityColumn as taken by Zend_Auth_Adapter_DbTable
     * @param  string                   $credentialColumn as taken by Zend_Auth_Adapter_DbTable
     * @param  string                   $credentialTreatment as taken by Zend_Auth_Adapter_DbTable
     * @return boolean
     */
    public function isValid(string $login, string $passwd, string $authTableName,string $identityColumn,
            string $credentialColumn, string $credentialTreatment) {
        $db = Zend_Registry::get('db');
        $authAdapter = new Zend_Auth_Adapter_DbTable(
            $db,
            $authTableName,
            $identityColumn,
            $credentialColumn,
            $credentialTreatment
        );
        $authAdapter->setIdentity($login)->setCredential($passwd);
        $auth = Zend_Auth::getInstance();
        $result = $auth->authenticate($authAdapter);
        return $result->isValid()?true:false;
    }
}
