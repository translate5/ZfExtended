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
 * handles passwd reset
 *
 * @method void setId() setId(integer $id)
 * @method void setUserId() setUserId(integer $userId)
 * @method void setExpiration() setExpiration(integer $expiration)
 * @method void setResetHash() setResetHash(string $resetHash)
 * @method integer getId() getId()
 * @method integer getUserId() getUserId()
 * @method integer getExpiration() getExpiration()
 * @method string getResetHash() getResetHash()
 * 
 */
class ZfExtended_Models_Passwdreset extends ZfExtended_Models_Entity_Abstract {
  protected $dbInstanceClass = 'ZfExtended_Models_Db_Passwdreset';
  protected $validatorInstanceClass = 'ZfExtended_Models_Validator_Passwdreset';
  public function deleteOldHashes() {
      $all = $this->loadAll();
      foreach ($all as $key => $row) {
          if($row['expiration'] < time()){
              $this->load($row['id']);
              $this->delete();
          }
      }
  }
  /**
   * 
   * @param string $hash
   * @return type
   */
  public function hashMatches($hash) {
      try {
          $session = new Zend_Session_Namespace();
          $s = $this->db->select();
          $s->where('resetHash = ?', $hash)
                    ->where('internalSessionUniqId = ?', $session->internalSessionUniqId);
            $this->loadRowBySelect($s);
        } catch (ZfExtended_Models_Entity_NotFoundException $exc) {
            return false;
        }
        return true;
  }
}