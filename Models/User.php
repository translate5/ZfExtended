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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * @method void setId() setId(int $id)
 * @method void setUserGuid() setUserGuid(string $guid)
 * @method void setFirstName() setFirstName(string $name)
 * @method void setSurName() setSurName(string $name)
 * @method void setEmail() setEmail(string $email)
 * @method void setPasswd() setPassword(string $passwd)
 * @method void setGender() setGender(string $gender)
 * @method void setParentIds() setParentIds(string $parentIds)
 * @method void setCustomers() setCustomers(string $customers)
 * @method void setOpenIdIssuer() setOpenIdIssuer(string $openIdIssuer)
 * @method void setOpenIdSubject() setOpenIdSubject(string $openIdsubject)
 *
 * @method void setLogin() setLogin(string $login)
 * @method integer getId() getId()
 * @method string getUserGuid() getUserGuid()
 * @method string getFirstName() getFirstName()
 * @method string getSurName() getSurName()
 * @method string getEmail() getEmail()
 * @method string getPasswd() getPasswd()
 * @method string getGender() getGender()
 * @method string getLogin() getLogin()
 * @method void getParentIds() getParentIds()
 * @method void getCustomers() getCustomers()
 * @method void getOpenIdIssuer() getOpenIdIssuer()
 * @method void getOpenIdSubject() getOpenIdSubject()
 */
class ZfExtended_Models_User extends ZfExtended_Models_Entity_Abstract implements ZfExtended_Models_SessionUserInterface {
    
    /***
     * resource key for the acl level record
     * @var string
     */
    const APPLICATION_CONFIG_LEVEL='applicationconfigLevel';
    
    const SYSTEM_LOGIN = 'system';
    const SYSTEM_GUID = '{00000000-0000-0000-0000-000000000000}';
    
    const GENDER_NONE = 'n';
    const GENDER_FEMALE = 'f';
    const GENDER_MALE = 'm';
    
  protected $dbInstanceClass = 'ZfExtended_Models_Db_User';
  protected $validatorInstanceClass = 'ZfExtended_Models_Validator_User';
  
  
      /**
       * Loads user by a given list of userGuids
       * @param array $guids
       * @return array
       */
    public function loadByGuids(array $guids){
         $s=$this->db->select()
         ->where('userGuid IN (?)', array_unique($guids));
         return $this->loadFilterdCustom($s);
     }
  
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
        $userData->roles = $this->getRoles();
        $userData->roles[] = 'basic';
        $userData->roles[] = 'noRights'; //the user always has this roles
        $userData->roles = array_unique($userData->roles);
        $userData->userName = $userData->firstName.' '.$userData->surName;
        $userData->loginTimeStamp = $_SERVER['REQUEST_TIME'];
        $userData->passwd = '********'; // We don't need and don't want the PW hash in the session
        $userData->openIdIssuer='';
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
        $this->__call('setLocale', [$locale]);
    }
    
    /**
     * {@inheritDoc}
     * @see ZfExtended_Models_SessionUserInterface::getLocale()
     */
    public function getLocale() {
        //piping the method to __call, declaration is needed for interface
        return $this->__call('getLocale', []);
    }
    
    /**
     * set the user roles
     * @param array $roles
     */
    public function setRoles(array $roles): void {
        //piping the method to __call, declaration is needed for interface
        $this->__call('setRoles', [join(',',$roles)]);
    }
    
    /**
     * get the user roles
     */
    public function getRoles(): array {
        //piping the method to __call, declaration is needed for interface
        $roles = trim($this->__call('getRoles', []), ', ');
        if(empty($roles)) {
            return [];
        }
        return explode(',', $roles);
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
    
    /***
     * Load users hierarchically, based on the current logged in user.
     * @return array
     */
    public function loadAllOfHierarchy(){
        $userSession = new Zend_Session_Namespace('user');
        $userData=$userSession->data;
        $adapter=$this->db->getAdapter();
        $s=$this->_loadAll();
        //NOTE:the where must be in one row because of the brackets
        //$s->where( parentIds like "%,4,5,6,%" OR id=2 )  sql=> WHERE (parentIds like "%,4,5,6,%" OR id=2)
        //$s->where( parentIds like)
        //$s->where( id=2 ) sql=> WHERE (parentIds like "%,4,5,6,%") OR id=2
        //this with combination of the filters will provide different result
        $s->where('parentIds like "%,'.$adapter->quote($userData->id).',%" OR id='.$adapter->quote($userData->id));
        return $this->loadFilterdCustom($s);
    }
    
    /**
     * loads all users without the passwd field
     * with role $role
     * @param array $roles - acl roles
     * @param integer $parentIdFilter - the parent id which the select should check
     * @return array
     */
    public function loadAllByRole(array $roles, $parentIdFilter = false) {
        $s = $this->_loadAll();
        $this->addByRoleSql($s, $roles, $parentIdFilter);
        return $this->loadFilterdCustom($s);
    }
    
    /**
     * loads all users without the passwd field
     * with role $role
     * @param array $roles - acl roles
     * @param integer $parentIdFilter - the parent id which the select should check
     * @return integer
     */
    public function getTotalByRole(array $roles, $parentIdFilter = false) {
        $s = $this->_loadAll();
        $s->reset($s::COLUMNS);
        $s->reset($s::FROM);
        $this->addByRoleSql($s, $roles, $parentIdFilter);
        return $this->computeTotalCount($s);
    }

    /**
     * Adds a role selector and parent id filter to an exising user SELECT query
     * @param Zend_Db_Select $s
     * @param array $roles
     * @param integer $parentIdFilter or false if no filter to be used
     */
    protected function addByRoleSql(&$s, array $roles, $parentIdFilter) {
        $adapter = $this->db->getAdapter();
        $first = true;
        foreach ($roles as $role) {
            $sLike = sprintf('roles like %s OR roles like %s OR roles like %s OR roles=%s',
                $adapter->quote($role.',%'),
                $adapter->quote('%,'.$role.',%'),
                $adapter->quote('%,'.$role),
                $adapter->quote($role)
                );
            $first ? $s->where($sLike) : $s->orWhere($sLike);
            $first = false;
        }
        if($parentIdFilter !== false){
            $s->where('parentIds like "%,'.$parentIdFilter.',%" OR id='.$adapter->quote($parentIdFilter));
        }
    }
    
    /**
     *
     * @return Zend_Db_Table_Select
     */
    private function _loadAll() {
        $db = $this->db;
        $s = $db->select()->from($db->info($db::NAME), $this->getPublicColumns());
        $s->where('login != ?', self::SYSTEM_LOGIN); //filter out the system user
        if($this->filter && !$this->filter->hasSort()){
            $this->filter->addSort('login');
        }
        return $s;
    }
    
    /**
     * returns a list of user columns containing non sensitive data
     * @return array
     */
    public function getPublicColumns(): array {
        $cols = array_flip($this->db->info($this->db::COLS));
        unset($cols['passwd']);
        unset($cols['openIdSubject']);
        unset($cols['openIdIssuer']);
        return array_flip($cols);
    }
    
    /**
     * returns the total (without LIMIT) count of rows
     */
    public function getTotalCount(): int {
        $s = $this->db->select();
        $s->where('login != ?', self::SYSTEM_LOGIN); //filter out the system user
        return $this->computeTotalCount($s);
    }
    
    /***
     * Get total count of hierarchy users
     * @return number
     */
    public function getTotalCountHierarchy(){
        $userSession = new Zend_Session_Namespace('user');
        $userData=$userSession->data;
        $adapter=$this->db->getAdapter();
        $s = $this->db->select();
        $s->where('login != ?', self::SYSTEM_LOGIN); //filter out the system user
        $s->where('parentIds like "%,'.$adapter->quote($userData->id).',%" OR id='.$adapter->quote($userData->id));
        return $this->computeTotalCount($s);
    }
    
    /***
     * Check if the current user has parent user with the given id
     * @param string $parentId -> the parent userid to be checked if it is a parent of the current one
     * @param string $parentIds optional, if empty take the parentIds of the current user instance. A custom comma separated string can be given here
     * @return boolean
     */
    public function hasParent($parentId, $parentIds = null){
        if(empty($parentIds)){
            $parentIds = $this->getParentIds();
        }
        $parentIds = explode(',', trim($parentIds, ' ,'));
        return in_array($parentId, $parentIds);
    }
    
    /**
     * @todo This method is a working draft and is not tested yet!
     * Return true if the given user is a child of the currently loaded one
     *
     * @param ZfExtended_Models_User $user
     * @return boolean
     */
    public function isChildOf(ZfExtended_Models_User $user) {
        return $user->isParentOf($this);
    }
    
    /**
     * @todo This method is a working draft and is not tested yet!
     * Return true if the given user is a parent of the currently loaded one
     *
     * @param ZfExtended_Models_User $user
     * @return boolean
     */
    public function isParentOf(ZfExtended_Models_User $user) {
        $parentIds = explode(',', trim($user->getParentIds(), ' ,'));
        $toCheck = $this->getRoles();
        $toCheck[] = $this->getId();
        $parentFound = array_intersect($toCheck, $parentIds);
        return !empty($parentFound);
    }
    
    /**
     * @param mixed $newPasswd string or null
     * @param bool $save
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
     * Check if currently logged in user is allowed to access the given ressource and right
     *
     * @param string $resource
     * @param string $right
     *
     * @return boolean
     */
    public function isAllowed($resource,$right) {
        $userRoles=null;
        $aclInstance = ZfExtended_Acl::getInstance();
        $userSession = new Zend_Session_Namespace('user');
        $userRoles=$userSession->data->roles;
        return $aclInstance->isInAllowedRoles($userRoles,$resource,$right);
    }
    
    
    /**
     * Check if currently logged in user has the given role
     *
     * @param string $role
     * @return boolean
     */
    public function hasRole(string $role) {
        $userSession = new Zend_Session_Namespace('user');
        return in_array($role, $userSession->data->roles);
    }
    
    
    /**
     * Get assigned customers to the currently logged user
     *
     * @param bool $termportal If given as true - termPM_allClients role will be respected
     * @return array
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Session_Exception
     */
    public function getUserCustomersFromSession(bool $termportal = false): array
    {
        $sessionUser = new Zend_Session_Namespace('user');
        
        $sessionUser=$sessionUser->data;
        
        $userModel=ZfExtended_Factory::get('ZfExtended_Models_User');
        /* @var $userModel ZfExtended_Models_User */
        
        $userModel->load($sessionUser->id);

        // If current user has role 'termPM_allClients' - return all clients ids
        //FIXME not allowed that way!
        if ($termportal && in_array('termPM_allClients', $userModel->getRoles()))
            return Zend_Db_Table_Abstract::getDefaultAdapter()->query('SELECT `id` FROM `LEK_customer`')->fetchAll(PDO::FETCH_COLUMN);

        if(empty($userModel->getCustomers())){
            return array();
        }
        
        $customers=trim($userModel->getCustomers(),",");
        return explode(',', $customers);
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
    
    /***
     * Return the user customers as array
     * @return array
     */
    public function getCustomersArray() {
        return explode(',', trim($this->getCustomers(),","));
    }
    
    /***
     * Load user by given issuer and subject (the issuer and subject are openid specific fields)
     * @param string $login
     * @return Zend_Db_Table_Row_Abstract|NULL
     */
    public function loadByIssuerAndSubject($issuer,$subject) {
        $s = $this->db->select();
        $s->where('openIdIssuer=?',$issuer);
        $s->where('openIdSubject=?',$subject);
        $row=$this->db->fetchRow($s);
        if(empty($row)){
            return null;
        }
        $this->row =$row;
        return $row;
    }
    
    /**
     * Load user by user login
     *
     * @param string $login
     * @throws ZfExtended_Models_Entity_NotFoundException
     * @return Zend_Db_Table_Row_Abstract
     */
    public function loadByLogin($login) {
        $s = $this->db->select();
        $s->where('login=?',$login);
        $row=$this->db->fetchRow($s);
        if(empty($row)){
            $this->notFound(__CLASS__ . '#login', $login);
        }
        $this->row =$row;
        return $row;
    }
    
    /**
     * returns a list of users matching the given parameter in login (mysql wildcards permitted) or a direct in the e-mail field
     * @param string $search
     * @return array
     */
    public function loadAllByLoginPartOrEMail(string $search): array {
        $s = $this->_loadAll();
        $s->where('login like ?', $search)
        ->orWhere('email = ?', $search);
        return $this->loadFilterdCustom($s);
    }
    
    /***
     * Check if the domain exist for one of the customers of the user
     * @param string $userGuid
     * @param string $domain
     * @return NULL|int
     */
    public function isValidCustomerDomain(string $userGuid,string $domain){
        if(empty($userGuid) || empty($domain)){
            return null;
        }
        $customer=ZfExtended_Factory::get('editor_Models_Customer_Customer');
        /* @var $customer editor_Models_Customer_Customer */
        $customer->loadByDomain($domain);
        if($customer->getId()==null){
            return null;
        }
        $this->loadByGuid($userGuid);
        $customers=trim($this->getCustomers(),",");
        $customers=explode(',', $customers);
        if(in_array($customer->getId(),$customers)){
            return $customer->getId();
        }
        return null;
    }
    
    /***
     * Get user application config level
     * @return array
     */
    public function getApplicationConfigLevel(){
        $acl = ZfExtended_Acl::getInstance();
        return $acl->getRightsToRolesAndResource($this->getRoles(), self::APPLICATION_CONFIG_LEVEL);
    }

    /**
     * @return array
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Session_Exception
     */
    public function getAccessibleCollectionIds() {
        return Zend_Db_Table_Abstract::getDefaultAdapter()->query('
            SELECT DISTINCT `lr`.`id`
            FROM
              `LEK_languageresources` `lr`,
              `LEK_languageresources_customerassoc` `lr2c`,
              `Zf_users` `u`
            WHERE TRUE
              AND `lr`.`resourceType` = "termcollection"
              AND `lr`.`id` = `lr2c`.`languageResourceId`
              AND `u`.`customers` REGEXP CONCAT(",", `lr2c`.`customerId`,",")
              AND `u`.`id` = ?
        ', (new Zend_Session_Namespace('user'))->data->id)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Return id => userName pairs, fetched by given user ids
     *
     * @param array $ids
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public function getNamesByIds(array $ids): array {

        // Prepare WHERE clause
        $where = $this->db->getAdapter()->quoteInto('`id` IN (?)', $ids ?: [0]);

        // Fetch and return id => userName pairs
        return $this->db->getAdapter()->query('
            SELECT `id`, CONCAT(`firstName`, " ", `surName`) AS `userName`
            FROM `Zf_users`
            WHERE ' . $where
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}
