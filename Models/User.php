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

use MittagQI\ZfExtended\Acl\ConfigLevelResource;
use MittagQI\ZfExtended\Acl\Roles;

/**
 * @method void setId() setId(int $id)
 * @method void setUserGuid() setUserGuid(string $guid)
 * @method void setFirstName() setFirstName(string $name)
 * @method void setSurName() setSurName(string $name)
 * @method void setGender() setGender(string $gender)
 * @method void setLogin() setLogin(string $login)
 * @method void setEmail() setEmail(string $email)
 * @method void setPasswd() setPasswd(string $hash)
 * @method void setEditable() setEditable(bool $editable)
 * @method void setLocale() setLocale(string $locale)
 * @method void setParentIds() setParentIds(string $parentIds)
 * @method void setCustomers() setCustomers(string $customers)
 * @method void setOpenIdIssuer() setOpenIdIssuer(string $openIdIssuer)
 * @method void setOpenIdSubject() setOpenIdSubject(string $openIdSubject)
 *
 * @method integer getId() getId()
 * @method string getUserGuid() getUserGuid()
 * @method string getFirstName() getFirstName()
 * @method string getSurName() getSurName()
 * @method string getGender() getGender()
 * @method string getLogin() getLogin()
 * @method string getEmail() getEmail()
 *                getRoles defined natively
 * @method string getPasswd() getPasswd()
 * @method bool getEditable() getEditable()
 * @method string getLocale() getLocale()
 * @method string getParentIds() getParentIds()
 * @method string getCustomers() getCustomers()
 * @method string getOpenIdIssuer() getOpenIdIssuer()
 * @method string getOpenIdSubject() getOpenIdSubject()
 */
class ZfExtended_Models_User extends ZfExtended_Models_Entity_Abstract {

    const SYSTEM_LOGIN = 'system';
    const SYSTEM_GUID = '{00000000-0000-0000-0000-000000000000}';
    
    const GENDER_NONE = 'n';
    const GENDER_FEMALE = 'f';
    const GENDER_MALE = 'm';

    /**
     * Turns the 2customers" string of our data-model to a proper array of integers
     * The data can be like ",1,4,7," or "4" or "," or empty
     * @param string $customers
     * @return array
     */
    public static function customersToCustomerIds(string $customers): array
    {
        return array_map('intval', explode(',', trim($customers, ',')));
    }

    /**
     * Caches the setaclrole restricted roles
     * @var array
     */
    protected static array $setaclroleCache = [];
    
    protected $dbInstanceClass = 'ZfExtended_Models_Db_User';
    protected $validatorInstanceClass = 'ZfExtended_Models_Validator_User';

    /**
     * User-lists must be filtered by role-driven restrictions
     */
    protected ?array $clientAccessRestriction = ['field' => 'customers', 'type' => 'listCommaSeparated'];

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
     * set the user roles
     * @param array $roles
     */
    public function setRoles(array $roles): void {
        //piping the method to __call, declaration is needed for interface
        // the filterRoles removes "subroles" when the main role is not set (e.g. for the client-pm)
        $this->__call('setRoles', [join(',', Roles::filterRoles($roles))]);
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
     * Loads a user by userGuid
     * @param string $userGuid
     * @return ZfExtended_Models_User
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    public function loadByGuid(string $userGuid): static
    {
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
     * Load users hierarchically, based on the current logged in user.
     * @return array
     * @throws Zend_Db_Table_Exception
     */
    public function loadAllOfHierarchy(){
        $id = ZfExtended_Authentication::getInstance()->getUserId();
        $adapter = $this->db->getAdapter();
        $s = $this->_loadAll();
        //NOTE:the where must be in one row because of the brackets
        //$s->where( parentIds like "%,4,5,6,%" OR id=2 )  sql=> WHERE (parentIds like "%,4,5,6,%" OR id=2)
        //$s->where( parentIds like)
        //$s->where( id=2 ) sql=> WHERE (parentIds like "%,4,5,6,%") OR id=2
        //this with combination of the filters will provide different result
        $s->where('parentIds like "%,'.$adapter->quote($id).',%" OR id='.$adapter->quote($id));
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
        if (empty($roles)) {
            //if there are no roles given, we may not find a user for them!
            $s->where('false');
            return;
        }
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
     * @return Zend_Db_Table_Select
     * @throws Zend_Db_Table_Exception
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
        unset($cols['passwd'], $cols['openIdSubject'], $cols['openIdIssuer']);
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
    
    /**
     * Get total count of hierarchy users
     * @return int
     */
    public function getTotalCountHierarchy(): int
    {
        $id = ZfExtended_Authentication::getInstance()->getUserId();
        $adapter = $this->db->getAdapter();
        $s = $this->db->select();
        $s->where('login != ?', self::SYSTEM_LOGIN); //filter out the system user
        $s->where('parentIds like "%,'.$adapter->quote($id).',%" OR id='.$adapter->quote($id));
        return $this->computeTotalCount($s);
    }
    
    /**
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
     * @return int[]
     */
    public function getCustomersArray(): array
    {
        return static::customersToCustomerIds($this->getCustomers());
    }
    
    /***
     * Load user by given issuer and subject (the issuer and subject are openid specific fields)
     * @param string $login
     * @return Zend_Db_Table_Row_Abstract|NULL
     */
    public function loadByIssuerAndSubject($issuer,$subject) {
        $s = $this->db->select();
        $s->where('openIdIssuer = ?', $issuer);
        $s->where('openIdSubject = ?', $subject);
        $row=$this->db->fetchRow($s);
        if(empty($row)){
            return null;
        }
        $this->row = $row;
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
        $s->where('login = ?', $login);
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
        return $acl->getRightsToRolesAndResource($this->getRoles(), ConfigLevelResource::ID);
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
            SELECT `id`, CONCAT(`firstName`, \' \', `surName`) AS `userName`
            FROM `Zf_users`
            WHERE ' . $where
        )->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * If this API returns true, all views this user accesses have to be client-filtered
     * @return bool
     */
    public function isClientRestricted(): bool
    {
        return Roles::isClientRestricted($this->getRoles());
    }

    /**
     * Retrieves the client-id's the user is limited to in managing
     * Just a shorthand and to be able to add future features
     * @return array
     */
    public function getRestrictedClientIds(): array
    {
        if($this->isClientRestricted()){
            return $this->getCustomersArray();
        }
        return [];
    }

    /**
     * Retrieves the roles a user is allowed to set for other users
     * This also defines, if another user is editable for a user
     * @return string[]
     */
    public function getSetableRoles(): array
    {
        if(!array_key_exists($this->getId(), static::$setaclroleCache)){
            static::$setaclroleCache[$this->getId()]
                = ZfExtended_Acl::getInstance()->getSetableRolesForRoles($this->getRoles());
        }
        return static::$setaclroleCache[$this->getId()];
    }

    /**
     * Retrieves, if a user can be edited by another user.
     * This will be evaluated by the "setaclrule" ACLs of the given user:
     * If the user is allowed to set all our roles, he is allowed to edit
     * @param ZfExtended_Models_User|null $user
     * @return bool
     */
    public function isEditableFor(?ZfExtended_Models_User $user): bool
    {
        $userSetableRoles = ($user === null) ? [] : $user->getSetableRoles();
        return empty(array_diff($this->getRoles(), $userSetableRoles));
    }
}
