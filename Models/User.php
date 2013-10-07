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
 * @method void setId() setId(integer $id)
 * @method void setUserGuid() setUserGuid(string $guid)
 * @method void setFirstName() setFirstName(string $name)
 * @method void setSurName() setSurName(string $name)
 * @method void setEmail() setEmail(string $email)
 * @method void setRoles() setRoles(string $roles)
 * @method void setPasswd() setPassword(string $passwd)
 * @method void setPasswdReset() setPasswordReset(boolean $reset)
 * @method void setGender() setGender(string $gender)
 * @method void setLogin() setLogin(string $login)
 * @method integer getId() getId()
 * @method string getUserGuid() getUserGuid()
 * @method string getFirstName() getFirstName()
 * @method string getSurName() getSurName()
 * @method string getEmail() getEmail()
 * @method string getRoles() getRoles()
 * @method string getPasswd() getPasswd()
 * @method boolean getPasswdReset() getPasswdReset()
 * @method string getGender() getGender()
 * @method string getLogin() getLogin()
 */
class ZfExtended_Models_User extends ZfExtended_Models_Entity_Abstract {
  protected $dbInstanceClass = 'ZfExtended_Models_Db_User';
  protected $validatorInstanceClass = 'ZfExtended_Models_Validator_User';
    /**
     * sets the user in Zend_Session_Namespace('user')
     *
     * @param string login
     * @return void
     */
    public function setUserSessionNamespaceWithoutPwCheck(string $login) {
        $s = $this->db->select();
        $s->where('login = ?',$login);
        $this->setUserSessionNamespace($s);
    }
    /**
     * sets the user in
     *
     * @param string login
     * @param string passwd unverschlüsseltes Passwort, wie vom Benutzer eingegeben
     * @return void
     */
    public function setUserSessionNamespaceWithPwCheck(string $login, string $passwd) {
        $s = $this->db->select();
        $s->where('login = ?',$login)
          ->where('passwd = ?',md5($passwd));
        $this->setUserSessionNamespace($s);
    }
    /**
     * Registriert die Employee-Rolle im Zend_Session_Namespace('user')
     *
     * - wird registriert in employee->role
     *
     * @param Zend_Db_Table_Select select of ZfExtended_Models_Db_User
     * @param string passwd unverschlüsseltes Passwort, wie vom Benutzer eingegeben
     * @return void
     */
    protected function setUserSessionNamespace(Zend_Db_Table_Select $s) {
        $userSession = new Zend_Session_Namespace('user');
        $this->loadRowBySelect($s);
        $userData = $this->getDataObject();
        $userData->roles = explode(',',$userData->roles);
        $userData->userName = $userData->firstName.' '.$userData->surName;
        foreach ($userData as &$value) {
            if(is_numeric($value)){
                $value = (int)$value;
            }
        }
        $userSession->data = $userData;
    }
    
    /**
     * Loads a user by userGuid
     * @param string $userGuid
     * @return ZfExtended_Models_User
     */
    public function loadByGuid(string $userGuid) {
        try {
            $s = $this->db->select()->where('userGuid = ?', $userGuid);
            $row = $this->db->fetchRow($s);
        } catch (Exception $e) {
            $this->notFound('NotFound after other Error', $e);
        }
        if (!$row) {
            $this->notFound(__CLASS__ . '#userGuid', $userGuid);
        }
        //load implies loading one Row, so use only the first row
        $this->row = $row;
        return $this;
    }
    
    /**
     * merges firstname and surname to username
     */
    public function getUserName() {
        return $this->getFirstName().' '.$this->getSurName();
    }
}
