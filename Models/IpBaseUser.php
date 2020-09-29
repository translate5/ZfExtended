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

/*
 */
class ZfExtended_Models_IpBaseUser extends ZfExtended_Models_User {
    
    
    public function handleIpBasedUser(string $ip) {
        
        $session = new Zend_Session_Namespace('user');
        if(isset($session) && isset($session->data->userGuid)){
            try {
                $this->loadByGuid($session->data->userGuid);
            } catch (Exception $e) {
                
            }
        }
        $config = Zend_Registry::get('config');
        $rop = $config->runtimeOptions;
        $customersMap = $rop->authentication->ipbased->IpCustomerMap->toArray();
        
        $customer = ZfExtended_Factory::get('editor_Models_Customer');
        /* @var $customer editor_Models_Customer */
        
        if(isset($customersMap[$ip])){
            try {
                $customer->loadByNumber($customersMap[$ip]);
            } catch (Exception $e) {
                $logger = Zend_Registry::get('logger')->cloneMe('authentication.ipbased');
                $logger->warn('E1289', str_replace("{number}", $customersMap[$ip],"Ip based authentication: Customer with number ({number}) does't exist.") , [
                    'number' => $customersMap[$ip]
                ]);
            }
        }
        if($customer->getId() == null){
            $customer->loadByDefaultCustomer();
        }
        
        $this->setCustomers(','.$customer->getId().',');
        $roles = $rop->authentication->ipbased->userRoles->toArray();
        
        $acl = ZfExtended_Acl::getInstance();
        /* @var $acl ZfExtended_Acl */
        $allowedRoles = [];
        foreach ($roles as $role){
            if($acl->isAllowed($role,'frontend', 'ipBasedAuthentication')){
                $allowedRoles[]=$role;
            }
        }
        
        if(empty($allowedRoles)){
            //TODO: error code for missing roles in ip based auth
            throw new ZfExtended_ErrorCodeException("");
        }
        
        $this->setRoles($allowedRoles);
        //update only the configurable properties if the user exist
        if($this->getId()!=null){
            return $this;
        }
        
        $this->setEmail($config->resources->mail->defaultFrom->email);
        $this->setUserGuid(ZfExtended_Utils::guid(true));
        $this->setFirstName("Ip");
        $this->setSurName("Based User");
        
        //TODO: the user needs to be unique. (ip adress + session id (if the session id is to much, make it shorter))
        $this->setLogin($ip);
        //the gender is required in translate5, and in the response can be empty or larger than 1 character
        $this->setGender('n');
        
        $this->setEditable(1);
        
        //find the default locale from the config
        $localeConfig = $rop->translation ?? null;
        $appLocale=$localeConfig->applicationLocale ?? null;
        $fallbackLocale=$localeConfig->fallbackLocale ?? 'en';
        $locale=$appLocale ?? $fallbackLocale;

        $this->setLocale($locale);
        
        $this->save();
        return $this;
    }
    
    /***
     * @param string $userGuid
     * @return boolean
     */
    public function isIpBasedUser(string $userGuid) {
        try {
            $this->loadByGuid($userGuid);
        } catch (ZfExtended_Models_Entity_NotFoundException $e) {
            //the user does not exist
            return false;
        }
        if($this->getId()==null){
            return false;
        }
        $sessionId = Zend_Session::getId();
        
        $remoteAdress = ZfExtended_Factory::get('ZfExtended_RemoteAddress');
        /* @var $remoteAdress ZfExtended_RemoteAddress */
        
        $login = $this->getLogin();
        $sessionIpLogin = $remoteAdress->getIpAddress().$sessionId;
        
        return $this->getLogin() == ($remoteAdress->getIpAddress().$sessionId);
    }
    
    public function delete() {
        try {
            parent::delete();
        } catch (Exception $e) {
            //TODO: if this throws an error, find where ghe foregin key conflickt is and remove it first
        }
    }
    
}
