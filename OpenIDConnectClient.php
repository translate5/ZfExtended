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

class ZfExtended_OpenIDConnectClient extends OpenIDConnectClient{
    
    /***
     * Current customer used in the request domain
     * @var editor_Models_Customer
     */
    protected $customer;
    
    public function __construct(Zend_Controller_Request_Abstract $request) {
        $this->setRequest($request);
        $this->initOpenIdData();
    }
    
    /***
     * 
     * @var Zend_Controller_Request_Abstract
     */
    protected $request;
    

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
        $this->setClientID($this->customer->getOpenIdClientId());
        $this->setClientSecret($this->customer->getOpenIdClientSecret());
        
        $this->setProviderURL($this->customer->getOpenIdServer());
        $this->setIssuer($this->customer->getOpenIdAuth2Url());
        $this->setRedirectURL($this->getRedirectDomainUrl());
    }
    
    public function authenticate(){
        //authenticate when is login request with username and password or when oauth response callback with code parametar
        //if(empty($this->request->getParam('login')) && empty($this->request->getParam('passwd')) && empty($this->request->getParam('code')) && empty($this->request->getParam('id_token'))){
        
        
        //if the openidfields for the customer are not set, ignore the auth call
        if(!$this->isOpenIdCustomerSet()){
            return false;
        }
        
        $isAuthRequest=!empty($this->request->getParam('code')) || !empty($this->request->getParam('id_token'));
        $isLoginRequest=!empty($this->request->getParam('login')) && !empty($this->request->getParam('passwd'));
        if(!$isAuthRequest && !$this->customer->getOpenIdRedirectCheckbox() && !$isLoginRequest){
            return false;
        }

        $this->setVerifyHost(false);
        $this->setVerifyPeer(false);
        $this->addScope(array('openid','profile','email'));
        $this->setAllowImplicitFlow(true);
        $this->addAuthParam(array('response_mode' => 'form_post'));
        $this->setResponseTypes('id_token');
        $this->setResponseTypes('code');
        try {
            return parent::authenticate();
        } catch (OpenIDConnectClientException $e) {
            $this->unsetSession();
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
        
        //check if the user exist, so new guid is not created
        if(!$user->findByLogin($this->getVerifiedClaims('email'))){
            //it is new user, create guid
            $guidHelper = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
                'Guid'
                );
            $user->setUserGuid($guidHelper->create(true));
        }
        
        $user->setFirstName($this->getVerifiedClaims('given_name'));
        $user->setSurName($this->getVerifiedClaims('family_name'));
        
        //the gender is required in translate5, and in the response can be empty or larger than 1 character
        $gender=!empty($this->requestUserInfo('gender')) ? substr($this->requestUserInfo('gender'),0,1) : 'f';
        $user->setGender($gender);
        
        $user->setLogin($this->getVerifiedClaims('email'));
        $user->setEmail($this->getVerifiedClaims('email'));
        
        $user->setEditable(1);
        
        $user->setLocale($this->getVerifiedClaims('locale'));
        
        $user->setCustomers(','.$this->customer->getId().',');
        
        $user->setRoles($this->mergeUserRoles($this->getVerifiedClaims('roles')));
        
        return $user->save()>0? $user : null;
    }
    
    /***
     * Merge the verified role claims from the openid client server for the user.
     * @param array|string $claimsRoles
     * @return string
     */
    protected function mergeUserRoles($claimsRoles) {
        //the roles are not defined in the openid client server for the user, use the customer defined roles
        if(empty($claimsRoles)){
            return $this->customer->getOpenIdServerRoles();
        }
        
        if(is_string($claimsRoles)){
            $claimsRoles=explode(',', $claimsRoles);
        }
        
        $claimsRoles=array_filter($claimsRoles, 'strlen');
        
        if(empty($claimsRoles)){
            return '';
        }
        
        $acl = ZfExtended_Acl::getInstance();
        /* @var $acl ZfExtended_Acl */
        
        $allRoles = $acl->getAllRoles();
        $roles = array();
        foreach($allRoles as $role) {
            if($role == 'noRights' || $role == 'basic') {
                continue;
            }
            if(in_array($role, $claimsRoles)){
                $roles[]=$role;
            }
        }
        return implode(',',$roles);
    }
    
    /***
     * Get the redirect url from the current domain url.
     * @return string
     */
    protected function getRedirectDomainUrl() {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ?
            "https" : "http") . "://" . $_SERVER['HTTP_HOST'] .
            $_SERVER['REQUEST_URI'];
    }
    
    /***
     * Get the customer from the current used domain.
     * Ex: if thranslate5 is called from mittagqi.translate5.com, the customer will be mittagqi
     * @return editor_Models_Customer
     */
    protected function initCustomerFromDomain(){
        $tmp = explode('.',  $_SERVER['HTTP_HOST']); // split into parts
        $domain = current($tmp);
        $customer=ZfExtended_Factory::get('editor_Models_Customer');
        /* @var $customer editor_Models_Customer */
        if($domain!='www'){
            $customer->findByDomain($domain);
        }
        //the customer for the domain does not exist, load the default customer
        if($customer->getId()==null){
            $customer->loadByDefaultCustomer();
        }
        $this->customer=$customer;
        return $this->customer;
    }
    
    /***
     * Unset the openid session
     */
    protected function unsetSession(){
        $this->unsetState();
        $this->unsetNonce();
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