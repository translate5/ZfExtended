<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
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
  /**
    * reset password
    * @param string $login
    * @return boolean
    */
    public function reset(string $login) {
        $session = new Zend_Session_Namespace();
        $user = ZfExtended_Factory::get('ZfExtended_Models_User');
        /* @var $user ZfExtended_Models_User */
        try {
            $user->loadRow('login = ?', $login);
        } catch (ZfExtended_Models_Entity_NotFoundException $exc) {//catch the 404 thrown, if no user found
            return false;
        }
        $guid = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'Guid'
        );
        $session->resetHash = md5($guid->create());
        
        
        $this->setUserId($user->getId());
        $this->setResetHash($session->resetHash);
        $this->setExpiration(time()+1800);
        $this->setInternalSessionUniqId($session->internalSessionUniqId);
        
        $this->validate();
        $this->save();
        $general = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'general'
        );
        $translate = ZfExtended_Zendoverwrites_Translate::getInstance();
        /* @var $translate ZfExtended_Zendoverwrites_Translate */;
        $general->mail(
                $user->getEmail(),
                '',
                $translate->_('Passwort neu setzen'),
                array(
                    'gender' =>$user->getGender(),
                    'surname' =>$user->getSurName(),
                    'resetHash' =>$session->resetHash
                )
        );
        return true;
    }
}