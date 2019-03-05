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

require APPLICATION_PATH.'/../library/OpenID-Connect-PHP/vendor/autoload.php';

use Jumbojett\OpenIDConnectClient;
use Jumbojett\OpenIDConnectClientException;

class ZfExtended_OpenIDConnectClient{
    
    /***
     * 
     * @var Zend_Controller_Request_Http
     */
    protected $request;
    
    /***
     * Current customer used in the request domain
     * @var editor_Models_Customer
     */
    protected $customer;
    
    /***
     * Open id client instance
     * @var OpenIDConnectClient
     */
    protected $openIdClient;
    
    /***
     * @var 
     */
    protected $config;
    
    public function __construct(Zend_Controller_Request_Abstract $request) {
        $this->openIdClient=new OpenIDConnectClient();
        $this->config=Zend_Registry::get('config');
        $this->setRequest($request);
        $this->initOpenIdData();
    }
    
    

    public function setRequest(Zend_Controller_Request_Abstract $request){
       $this->request=$request;
    }
    
    /***
     * Init openid required data from the request and session.
     */
    protected function initOpenIdData(){
        $this->initCustomerFromDomain();
        //if the openidfields for the customer are not set, stop the init
        if(!$this->isOpenIdCustomerSet()){
            return;
        }
        $this->openIdClient->setClientID($this->customer->getOpenIdClientId());
        $this->openIdClient->setClientSecret($this->customer->getOpenIdClientSecret());
        
        $this->openIdClient->setProviderURL($this->customer->getOpenIdServer());
        $this->openIdClient->setIssuer($this->customer->getOpenIdAuth2Url());
        $this->openIdClient->setRedirectURL($this->getRedirectDomainUrl());
    }
    
    public function authenticate(){
        
        //if the openidfields for the customer are not set, ignore the auth call
        if(!$this->isOpenIdCustomerSet()){
            return false;
        }

        $isAuthRequest=!empty($this->request->getParam('code')) || !empty($this->request->getParam('id_token'));
        $isLoginRequest=!empty($this->request->getParam('login')) && !empty($this->request->getParam('passwd'));
        $isRedirectRequest=$this->request->getParam('redirect')!=null;
        $isShowLoginScreen=$this->customer->getOpenIdRedirectCheckbox();
        if(!$isAuthRequest && !$isRedirectRequest && !$isLoginRequest && !$isShowLoginScreen){
            return false;
        }

        $this->openIdClient->setVerifyHost(true);
        $this->openIdClient->setVerifyPeer(true);
        $this->openIdClient->addScope(array('openid','profile','email'));
        $this->openIdClient->setAllowImplicitFlow(true);
        $this->openIdClient->addAuthParam(array('response_mode' => 'form_post'));
        $this->openIdClient->setResponseTypes('id_token');
        $this->openIdClient->setResponseTypes('code');
        try {
            return $this->openIdClient->authenticate();
        } catch (OpenIDConnectClientException $e) {
            throw $e;
        }
    }
    
    /***
     * Create user from the OAuth verified user claims
     * @return NULL|ZfExtended_Models_User
     */
    public function createUser(){
        
        $user=ZfExtended_Factory::get('ZfExtended_Models_User');
        /* @var $user ZfExtended_Models_User */
        
        $issuer=$this->openIdClient->getVerifiedClaims('iss');
        $subject=$this->openIdClient->getVerifiedClaims('sub');
        
        //check if the user exist
        if(!$user->loadByIssuerAndSubject($issuer,$subject)){
            //it is new user, create guid
            $guidHelper = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
                'Guid'
                );
            $userGuid=$guidHelper->create(true);
            
            $user->setUserGuid($userGuid);
            $user->setOpenIdIssuer($issuer);
            $user->setOpenIdSubject($subject);
            $user->setLogin($userGuid);
            
            //save the user so we get userid
            $userId=$user->save();
            
            //update the login with the openid as prefix
            $user->setLogin('OID-'.$userId);
        }
        
        $user->setFirstName($this->openIdClient->getVerifiedClaims('given_name'));
        $user->setSurName($this->openIdClient->getVerifiedClaims('family_name'));
        
        //the gender is required in translate5, and in the response can be empty or larger than 1 character
        $gender=!empty($this->openIdClient->requestUserInfo('gender')) ? substr($this->openIdClient->requestUserInfo('gender'),0,1) : 'f';
        $user->setGender($gender);
        
        $user->setEmail($this->openIdClient->getVerifiedClaims('email'));
        
        $user->setEditable(0);
        
        //find the default locale from the config
        $localeConfig = $this->config->runtimeOptions->translation;
        $appLocale=!empty($localeConfig->applicationLocale) ? $localeConfig->applicationLocale : null;
        $fallbackLocale=!empty($localeConfig->fallbackLocale) ? $localeConfig->fallbackLocale : null;
        
        $defaultLocale=empty($appLocale) ? (empty($fallbackLocale) ? 'en' : $fallbackLocale) : $appLocale;
        
        //use the parrent language if the locale is not one
        $claimLocale=$this->openIdClient->getVerifiedClaims('locale');
        $claimLocale=explode('-', $claimLocale);
        $claimLocale=!empty($claimLocale) ? $claimLocale[0] : $defaultLocale;
        $user->setLocale($claimLocale);
        
        $user->setCustomers(','.$this->customer->getId().',');
        
        $user->setRoles($this->mergeUserRoles($this->openIdClient->getVerifiedClaims('roles')));
        
        return $user->save()>0? $user : null;
    }
    
    /***
     * Merge the verified role claims from the openid client server and from the customer for the user.
     * @param array|string $claimsRoles
     * @return string
     */
    protected function mergeUserRoles($claimsRoles) {
        $customerRoles=$this->customer->getOpenIdServerRoles();
        //no customer roles, the user should be saved without roles
        if(empty($customerRoles)){
            return '';
        }
        
        //the roles are not defined in the openid client server for the user, use the customer defined roles
        if(empty($claimsRoles)){
            return $customerRoles;
        }
        
        $customerRoles=explode(',',$customerRoles);
        
        if(is_string($claimsRoles)){
            $claimsRoles=explode(',', $claimsRoles);
        }
        
        $acl = ZfExtended_Acl::getInstance();
        /* @var $acl ZfExtended_Acl */
        
        $allRoles = $acl->getAllRoles();
        $roles = array();
        foreach($allRoles as $role) {
            if($role == 'noRights' || $role == 'basic') {
                continue;
            }
            //the role exist in the translate5 and the role is valid for the customer
            if(in_array($role, $claimsRoles) && in_array($role, $customerRoles)){
                $roles[]=$role;
            }
        }
        return implode(',',$roles);
    }
    
    /***
     * @return string
     */
    protected function getRedirectDomainUrl() {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ?
            "https" : "http") . "://" . $_SERVER['HTTP_HOST'] .
            $_SERVER['REQUEST_URI'];
    }
    
    /***
     * @return string
     */
    protected function getBaseUrl() {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ?
            "https" : "http") . "://" . $_SERVER['HTTP_HOST'] .
            $this->request->getBaseUrl().'/';
    }
    
    /***
     * Get the customer from the current used domain.
     * @return editor_Models_Customer
     */
    protected function initCustomerFromDomain(){
        $customer=ZfExtended_Factory::get('editor_Models_Customer');
        $customer->loadByDomain($this->getBaseUrl());
        //the customer for the domain does not exist, load the default customer
        if($customer->getId()==null){
            $customer->loadByDefaultCustomer();
        }
        $this->customer=$customer;
        return $this->customer;
    }
    
    /***
     * Check if the openid fields are set in the customer
     * @return boolean
     */
    public function isOpenIdCustomerSet() {
        if($this->customer->getId()==null){
            return false;
        }
        if($this->customer->getOpenIdServer()==null || $this->customer->getOpenIdServer()==''){
            return false;
        }
        return true;
    }
    
    public function getCustomer() {
        return $this->customer;
    }
}