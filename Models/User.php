<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
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
 * @method void setId() setId(integer $id)
 * @method void setUserGuid() setUserGuid(string $guid)
 * @method void setFirstName() setFirstName(string $name)
 * @method void setSurName() setSurName(string $name)
 * @method void setEmail() setEmail(string $email)
 * @method void setRoles() setRoles(string $roles)
 * @method void setPasswd() setPassword(string $passwd)
 * @method void setGender() setGender(string $gender)
 * @method void setLogin() setLogin(string $login)
 * @method integer getId() getId()
 * @method string getUserGuid() getUserGuid()
 * @method string getFirstName() getFirstName()
 * @method string getSurName() getSurName()
 * @method string getEmail() getEmail()
 * @method string getRoles() getRoles()
 * @method string getPasswd() getPasswd()
 * @method string getGender() getGender()
 * @method string getLogin() getLogin()
 * @method string getLocale() getLocale()
 */
class ZfExtended_Models_User extends ZfExtended_Models_Entity_Abstract implements ZfExtended_Models_SessionUserInterface {
    const SYSTEM_LOGIN = 'system';
    const SYSTEM_GUID = '{00000000-0000-0000-0000-000000000000}';
    
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
        $userData->loginTimeStamp = $_SERVER['REQUEST_TIME'];
        $userData->passwd = '********'; // We don't need and don't want the PW hash in the session
        foreach ($userData as &$value) {
            if(is_numeric($value)){
                $value = (int)$value;
            }
        }
        $userSession->data = $userData;
    }
    
    /**
     * {@inheritDoc}
     * @see ZfExtended_Models_SessionUserInterface::setLocale()
     */
    public function setLocale(string $locale) {
        //piping the method to __call, declaration is needed for interface
        $this->__call('setLocale', [$arguments]);
    }
    
    /**
     * removes the logged in user from the session
     */
    public function removeFromSession() {
        $userSession = new Zend_Session_Namespace('user');
        $userSession->data= null;
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
     * loads all users without the passwd field
     * (non-PHPdoc)
     * @see ZfExtended_Models_Entity_Abstract::loadAll()
     * @return array
     */
    public function loadAll() {
        $s = $this->_loadAll();
        return $this->loadFilterdCustom($s);
    }
    
    /**
     * loads all users without the passwd field
     * with role $role
     * @return array
     */
    public function loadAllByRole($role) {
        $s = $this->_loadAll();
        
        $adapter = $this->db->getAdapter();
        $sLike = sprintf('roles like %s OR roles like %s OR roles like %s OR roles=%s',
            $adapter->quote($role.',%'),
            $adapter->quote('%,'.$role.',%'),
            $adapter->quote('%,'.$role),
            $adapter->quote($role)
        );
        $s->where($sLike);
        
        return $this->loadFilterdCustom($s);
    }
    
    /**
     * 
     * @return sql-string
     */
    private function _loadAll() {
        $db = $this->db;
        $cols = array_flip($db->info($db::COLS));
        unset($cols['passwd']);
        $s = $db->select()->from($db->info($db::NAME), array_flip($cols));
        $s->where('login != ?', self::SYSTEM_LOGIN); //filter out the system user
        if(!$this->filter->hasSort()){
            $this->filter->addSort('login');
        }
        return $s;
    }
    
    /**
     * returns the total (without LIMIT) count of rows
     */
    public function getTotalCount(){
        $s = $this->db->select();
        $s->where('login != ?', self::SYSTEM_LOGIN); //filter out the system user
        return $this->computeTotalCount($s);
    }
    
    /**
     * @param mixed $newPasswd string or null
     * @param boolean $save
     */
    public function setNewPasswd($newPasswd, $save = true) {
        if(!is_null($newPasswd))
            $newPasswd = md5($newPasswd);
        $this->setPasswd($newPasswd);
        if($save) {
            $this->validate();
            $this->save();
        }
    }
    
    /**
     * merges firstname and surname to username
     */
    public function getUserName() {
        return $this->getFirstName().' '.$this->getSurName();
    }
    
    /**
     * returns the username as: "Lastname, Firstname (login)"
     * @return string
     */
    public function getUsernameLong() {
        return $this->getSurName().', '.$this->getFirstName().' ('.$this->getLogin().')';
    }
}
