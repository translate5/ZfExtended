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
    
    /***
     * User verified openid claims
     * 
     * @var stdClass
     */
    protected $openIdUserClaims;
    
    
    /***
     * Additional user information from the openid enpoind
     * @var stdClass
     */
    protected $openIdUserInfo;
    
    /**
     * @var ZfExtended_Logger
     */
    protected $log;
    
    public function __construct(Zend_Controller_Request_Abstract $request) {
        $this->openIdClient=new OpenIDConnectClient();
        $this->config=Zend_Registry::get('config');
        $this->setRequest($request);
        $this->initOpenIdData();
        $this->log = Zend_Registry::get('logger')->cloneMe('core.openidconnect');
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
        $this->openIdClient->setIssuer($this->customer->getOpenIdIssuer());
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
            $this->throwOpenIdConnectException($e->getMessage().PHP_EOL. $e->getTraceAsString());
        }
    }
    
    /**
     * It calls the end-session endpoint of the OpenID Connect provider to notify the OpenID
     * Connect provider that the end-user has logged out of the relying party site
     * (the client application).
     *
     * @param string $accessToken ID token (obtained at login)
     * @param string $redirect URL to which the RP is requesting that the End-User's User Agent
     * be redirected after a logout has been performed. The value MUST have been previously
     * registered with the OP. Value can be null.
     *
     */
    public function signOut($accessToken, $redirect) {
        $this->openIdClient->signOut($accessToken, $redirect);
    }
    
    /***
     * Create user from the OAuth verified user claims
     * FIXME should be renamed to createOrMergeUser
     * @return NULL|ZfExtended_Models_User
     */
    public function createUser(){ 
        $emailClaims = $this->getEmailClaim();
        $user = $this->initOrLoadUser($emailClaims);
        
        //down here update user with data from SSO
        $user->setEmail($emailClaims);
        $user->setFirstName($this->getOpenIdUserData('given_name'));
        $user->setSurName($this->getOpenIdUserData('family_name'));
        
        //the gender is required in translate5, and in the response can be empty or larger than 1 character
        $gender=!empty($this->getOpenIdUserData('gender')) ? substr($this->getOpenIdUserData('gender'),0,1) : 'n';
        $user->setGender($gender);
        
        $user->setEditable(1);
        
        //find the default locale from the config
        $localeConfig = $this->config->runtimeOptions->translation;
        $appLocale=!empty($localeConfig->applicationLocale) ? $localeConfig->applicationLocale : null;
        $fallbackLocale=!empty($localeConfig->fallbackLocale) ? $localeConfig->fallbackLocale : null;
        
        $defaultLocale=empty($appLocale) ? (empty($fallbackLocale) ? 'en' : $fallbackLocale) : $appLocale;
        
        
        $claimLocale=$this->getOpenIdUserData('locale');
        
        //if the claim locale is empty, use the default user locale
        if(empty($claimLocale)){
            $claimLocale=$defaultLocale;
        }else{
            $claimLocale=explode('-', $claimLocale);
            $claimLocale=$claimLocale[0];
        }
        $user->setLocale($claimLocale);
        
        $user->setCustomers(','.$this->customer->getId().',');
        
        //find and set the roles, depending of the openid server config, this can be defined as roles or role
        //and it can exist either in the verified claims or in the user info
        $roles=$this->getOpenIdUserData('roles');
        if(empty($roles)){
            $roles=$this->getOpenIdUserData('role');
        }
        $user->setRoles($this->mergeUserRoles($roles));
        return $user->save()>0? $user : null;
    }
    
    /**
     * Inits either an empty user with SSO data, or loads an existing one to be updated with the SSO data
     * @param string $emailClaims
     */
    protected function initOrLoadUser($emailClaims) {
        $user = ZfExtended_Factory::get('ZfExtended_Models_User');
        /* @var $user ZfExtended_Models_User */
        
        $issuer = $this->getOpenIdUserData('iss');
        $subject = $this->getOpenIdUserData('sub');
        
        //check if the sso user already exist for the issuer and subject, if yes use it and update other data outside
        if($user->loadByIssuerAndSubject($issuer,$subject)){
            return $user;
        }

        $guidHelper = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper('Guid');
        $userGuid = $guidHelper->create(true);
        
        //check if the user with email as login exist
        try {
            $user->loadByLogin($emailClaims);
        } 
        catch(ZfExtended_Models_Entity_NotFoundException $e) {
            //the user with email as login does not exist, this is a new user, so set the login as email and set the sso info
            $user->setOpenIdIssuer($issuer);
            $user->setOpenIdSubject($subject);
            $user->setUserGuid($userGuid);
            $user->setLogin($emailClaims);
            return $user;
        }
        
        //the user with same email exist, now try to check if it is another sso user
        //another sso user is when the user has values in issuer and subject fields
        //this can only happen if 2 different sso user has same email address
        if(!empty($user->getOpenIdIssuer()) && !empty($user->getOpenIdSubject())){
            // the loaded $user is already an SSO user with same email.
            // we want to create a new one then (init to throw away the above loaded data)
            $user->init();
            
            $user->setUserGuid($userGuid);
            $user->setLogin($userGuid);
            $userId=$user->save();
            //update the login with the openid as prefix
            $user->setLogin('OID-'.$userId);
        }
        
        $user->setOpenIdIssuer($issuer);
        $user->setOpenIdSubject($subject);
        return $user;
    }
    
    /**
     * return the emailClaim from email or upn or preferred_username, null if nothing was a valid email
     * @return string|NULL
     */
    protected function getEmailClaim() {
        $emailClaims = $this->getOpenIdUserData('email');
        
        if(!empty($emailClaims)){
            return $emailClaims;
        }
        //if the email is not found from the standard claims, try to get it from 'upn'
        $emailClaims=$this->getOpenIdUserData('upn');
        //the upn is defined, chech if it is valid email
        if(!empty($emailClaims) && filter_var($emailClaims, FILTER_VALIDATE_EMAIL) !== false){
            return $emailClaims;
        }
        
        //if the email is empty again, try to find if it is defined as preferred_username claim
        $emailClaims=$this->getOpenIdUserData('preferred_username');
        //the preferred_username is defined, chech if it is valid email
        if(!empty($emailClaims) && filter_var($emailClaims, FILTER_VALIDATE_EMAIL) !== false){
            return $emailClaims;
        }
        //FIXME throw an exception here???
        return null;
    }
    
    /***
     * Merge the verified role claims from the openid client server and from the customer for the user.
     * @param array|string $claimsRoles
     * @return array
     */
    protected function mergeUserRoles($claimsRoles): array {
        $customerRoles=$this->customer->getOpenIdServerRoles();
        $openIdDefaultServerRoles=$this->customer->getOpenIdDefaultServerRoles();
        
        if(empty($openIdDefaultServerRoles)){
            $this->throwOpenIdConnectException('The default server roles are not defined.');
        }
        
        //no claim roles, use the default roles
        if(empty($claimsRoles)){
            return explode(',', $openIdDefaultServerRoles);
        }
        
        //if there is not customer roles, log the info message and trhrow an exception
        if(empty($customerRoles)){
            $this->throwOpenIdConnectException('The customer server roles are empty but there are roles from the provider.');
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
        //check if the claims roles are allowed by the server customer roles
        if(empty($roles)){
            $this->throwOpenIdConnectException('Invalid claims roles for the allowed server customer roles');
        }
        return $roles;
    }
    
    /***
     * @return string
     */
    public function getRedirectDomainUrl() {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ?
            "https" : "http") . "://" . $_SERVER['HTTP_HOST'] .
            $_SERVER['REQUEST_URI'];
    }
    
    /***
     * @return string
     */
    protected function getBaseUrl() {
        return $_SERVER['HTTP_HOST'].$this->request->getBaseUrl().'/';
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
    
    
    /***
     * Get the user info from the openid provider.
     * 
     * @param string $attribute
     * @return NULL|mixed
     */
    public function getOpenIdUserData(string $attribute) {
        
        //load openid claims from the sso provider
        if(!isset($this->openIdUserClaims)){
            $this->openIdUserClaims=$this->openIdClient->getVerifiedClaims();
        }
        
        //load the openid user info from the defined userinfo endpoint
        if(!isset($this->openIdUserInfo)){
            $this->openIdUserInfo=$this->openIdClient->requestUserInfo();
        }
        
        //check if the attribute exist in the claims
        if(array_key_exists($attribute, $this->openIdUserClaims)) {
            return $this->openIdUserClaims->$attribute;
        }
        
        //check if the attribute exist in the user info
        if (array_key_exists($attribute, $this->openIdUserInfo)) {
            return $this->openIdUserInfo->$attribute;
        }
        
        //no attribute was found
        return null;
    }
    
    /***
     * Throw open id client exception with additional debug data and given message
     * @param string $message
     * @throws ZfExtended_OpenIDConnectClientException
     */
    protected function throwOpenIdConnectException(string $message) {
        $openidInfo=[
            'customer'=>$this->getCustomer()->getDataObject(),
            'userInfo'=>$this->openIdUserInfo,
            'userClaims'=>$this->openIdUserClaims
        ];
        $data=[
            'message' => $message,
            'request' => print_r($this->request->getParams(),1),
            'session' => print_r($_SESSION,1),
            'openid' => $openidInfo
        ];
        $this->log->info('E1165', $message,$data);
        throw new ZfExtended_OpenIDConnectClientException('E1165',[
            'message' => $message,
            'request' => print_r($this->request->getParams(),1),
            'session' => print_r($_SESSION,1),
            'openid' => $openidInfo
        ]);
    }
}