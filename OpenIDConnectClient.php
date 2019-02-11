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
        /*
         * 
        $issuer = 'aleksandar.auth0.com';
        
        $cid = 'qmT6Ndh6bukLtLohN07Z7na5eR1xwoEF';
        $secret = '4KgWXuXBdFJ51moTdk-1QvJdH4x_POpT4-0VClPKXtMiwLkfmEz7kNhIOWkzvN7Q';

        $oidc = new ZfExtended_OpenIDConnectClient($this->openIdServer, $cid, $secret);
        */
        //parent::__construct('https://accounts.google.com',$this->cid,$this->secret,$this->openIdServer);
        
        //parent::__construct($this->getProviderURL(),$this->getClientID(),$this->getClientSecret(),$this->getIssuer());
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
        /*
         * google config params
            $this->setProviderURL('https://accounts.google.com');
            $this->setClientID('791386982319-bbia7jar0fvroku3li5j202rnje1g92c.apps.googleusercontent.com');
            $this->setClientSecret('dRKMToH7felCiUL3hn9RNVK9');
            $this->setIssuer('https://accounts.google.com/o/oauth2/auth');
    
            $this->setRedirectURL('http://translate5-openid.com/login');
            
            $this->setVerifyHost(false);
            $this->setVerifyPeer(false);
            $this->addScope(array('openid','profile','mail'));
            $this->setAllowImplicitFlow(true);
            $this->addAuthParam(array('response_mode' => 'form_post'));
        
        */
        $this->initCustomerFromDomain();
        //if the openidfields for the customer are not set, stop the init
        if(!$this->isOpenIdCustomerSet()){
            return;
        }
        $this->setClientIdRequest();
        $this->setClientSecretRequest();
        
        $this->setSetProviderAndIssuer();
        $this->setRedirectDomainURL();
    }
    
    public function authenticate(){
        //authenticate when is login request with username and password or when oauth response callback with code parametar
        if(empty($this->request->getParam('login')) && empty($this->request->getParam('passwd')) && empty($this->request->getParam('code'))){
            return false;
        }

        //if the openidfields for the customer are not set, ignore the auth call
        if(!$this->isOpenIdCustomerSet()){
            return false;
        }
        
        $this->setVerifyHost(false);
        $this->setVerifyPeer(false);
        $this->addScope(array('openid','profile','email'));
        $this->setAllowImplicitFlow(true);
        $this->addAuthParam(array('response_mode' => 'form_post'));
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
        
        $session = new Zend_Session_Namespace('openid');
        
        $user=ZfExtended_Factory::get('ZfExtended_Models_User');
        /* @var $user ZfExtended_Models_User */
        $user->setFirstName($this->getVerifiedClaims('given_name'));
        $user->setSurName($this->getVerifiedClaims('family_name'));
        $user->setGender($this->requestUserInfo('gender'));
        $user->setLogin($this->getVerifiedClaims('aud'));
        $user->setEmail($this->getVerifiedClaims('email'));
        $user->setPasswd(md5($session->passwd));
        $user->setEditable(1);
        
        $user->setLocale($this->getVerifiedClaims('locale'));
        
        $user->setCustomers(','.$this->customer->getId().',');
        $user->setRoles($this->customer->getOpenIdServerRoles());
        
        $guidHelper = ZfExtended_Zendoverwrites_Controller_Action_HelperBroker::getStaticHelper(
            'Guid'
            );
        $user->setUserGuid($guidHelper->create(true));
        
        return $user->save()>0? $user : null;
    }
    
    /***
     * Set hte client id from the request, when no request parametar is provided the client id will be set from the sessin
     */
    protected function setClientIdRequest() {
        $session = new Zend_Session_Namespace('openid');
        $login=$this->request->getParam('login');
        if(empty($login)){
            $login=$session->login;
        }else{
            $session->login=$login;
        }
        $this->setClientID($login);
    }
    
    /***
     * * Set hte client secret from the request, when no request parametar is provided the client secret will be set from the sessin
     */
    protected  function setClientSecretRequest() {
        $session = new Zend_Session_Namespace('openid');
        $passwd=$this->request->getParam('passwd');
        if(empty($passwd)){
            $passwd=$session->passwd;
        }else{
            $session->passwd=$passwd;
        }
        $this->setClientSecret($passwd);
    }
    
    /***
     * Set the provider and the issuer from the customer domain.
     * When no customer domain is found the default customer defined parametars will be used.
     */
    protected  function setSetProviderAndIssuer() {
        $this->setProviderURL($this->customer->getOpenIdServer());
        $this->setIssuer($this->customer->getOpenIdAuth2Url());
    }
    
    /***
     * Set the redirect url from the current domain url.
     */
    protected function setRedirectDomainURL() {
        $this->setRedirectURL($this->getRedirectDomainUrl());
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
        Zend_Session::namespaceUnset('openid');
        $this->unsetState();
        $this->unsetNonce();
    }
    
    /***
     * Check if the openid fields are set in the customer
     * @return boolean
     */
    protected function isOpenIdCustomerSet() {
        if($this->customer->getId()==null){
            return false;
        }
        if($this->customer->getOpenIdServer()==null || $this->customer->getOpenIdServer()==''){
            return false;
        }
        return true;
    }
}