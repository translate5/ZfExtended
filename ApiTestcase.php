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
class ZfExtended_ApiTestcase extends \ZfExtended_Testcase {
    const AUTH_COOKIE_KEY = 'zfExtended';
    /**
     * enable xdebug debugger in eclipse
     * @var boolean
     */
    protected $xdebug = false;

    /**
     * Authentication / session cookie
     * @var string
     */
    protected static $authCookie;

    /**
     * Authenticated login
     * @var string
     */
    protected static $authLogin;
    
    /**
     * @param string $url
     * @return Zend_Http_Client
     */
    protected function request($url, $method = 'GET', $parameters = array()) {
        $http = new Zend_Http_Client();
        //FIXME from config:
        //$url = $server.$rundir.$url;
        $url = 'http://translate5.localdev/'.$url;
        $http->setUri($url);
        $http->setHeaders('Accept', 'application/json');
        
        //enable xdebug debugger in eclipse
        if($this->xdebug) {
            $http->setCookie('XDEBUG_SESSION','ECLIPSE_DBGP_192.168.178.31');
            $http->setConfig(array('timeout'      => 3600));
        }
        
        if(!empty(static::$authCookie)) {
            $http->setCookie(self::AUTH_COOKIE_KEY, static::$authCookie);
        }
        
        $addParamsMethod = $method == 'POST' ? 'setParameterPost' : 'setParameterGet';
        
        if(!empty($parameters)) {
            foreach($parameters as $key => $value) {
                $http->$addParamsMethod($key, $value);
            }
        }
        
        return $http->request($method);
    }
    
    protected function login($login, $password = 'asdfasdf') {
        if(isset(static::$authLogin)){
            if(static::$authLogin == $login) {
                return;
            }
            else {
                $this->request('login/logout/'); //FIXME from config
            }
        }
        
        $response = $this->request('editor/');
        $this->assertEquals(200, $response->getStatus(), 'Server did not respond HTTP 200');
        
        $cookies = $response->getHeader('Set-Cookie');
        if(!is_array($cookies)) {
            $cookies = array($cookies);
        }
        $this->assertTrue(count($cookies) > 0, 'Server did not send a Cookie.');
        
        $sessionId = null;
        foreach($cookies as $cookie) {
            if(preg_match('/'.self::AUTH_COOKIE_KEY.'=([^;]+)/', $cookie, $matches)) {
                $sessionId = $matches[1];
            }
        }
        $this->assertNotEmpty($sessionId, 'No session ID given from server as Cookie.');
        static::$authCookie = $sessionId;
        static::$authLogin = $login;
        
        $body = $response->getBody();
        $noCsrf = null;
        if(preg_match('#<input\s+type="hidden"\s+name="noCsrf"\s+value="([^"]+)"\s+id="noCsrf"\s+/>#', $body, $matches)) {
            $noCsrf = $matches[1];
        }
        $this->assertNotEmpty($noCsrf, 'No "noCsrf" key found in server response.');
        
        $response = $this->request('login/', 'POST', array(
            'noCsrf' => $noCsrf,
            'login' => $login,
            'passwd' => $password,
        ));
        if(preg_match('#<ul class="errors">(.+)</ul>#s', $response->getBody(), $matches)) {
            $this->fail('Could not login to server, message was: '.$matches[1]);
        }
    }
    
    public function testNeededUsers() {
        $this->login('hannibal', 'foo123*t5');

        $response = $this->request('editor/user', 'GET', array(
            //'filter' => '[{"type":"string","value":"hannibal","field":"login"}]',
            'page' => 1,
            'start' => 0,
            'limit' => 20.
        ));
        error_log($response->getStatus());
        error_log($response->getBody());
    }
}