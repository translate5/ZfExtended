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

class ZfExtended_Worker_TriggerByHttp {
    
    private $host = 'localhost';
    private $port = 80;
    private $path = '';
    private $postParameters = array();
    private $method = 'GET';
    private $getParameters = '?cleanupSessionAfterRun=1';
    
   /**
    * @var ZfExtended_Log
    */
    protected $log;
    
    
    public function __construct() {
        $this->log = ZfExtended_Factory::get('ZfExtended_Log');
    }
    
    /**
     * Trigger worker with id = $id.
     * To run mutex-save, the current hash is needed
     * 
     * @param integer $id
     * @param string $hash
     */
    public function triggerWorker($id, string $hash) {
        $config = Zend_Registry::get('config');
        $serverName = $config->runtimeOptions->server->name;
        $serverProtocol =  $config->runtimeOptions->server->protocol;
        //$this->triggerUrl('http://test.local/editor/worker/'.$id, array('state' => 'running', 'hash' => $hash), 'PUT');
        return $this->triggerUrl($serverProtocol.$serverName.APPLICATION_RUNDIR.'/editor/worker/'.$id, array('state' => 'running', 'hash' => $hash), 'PUT');
    }
    
    public function triggerQueue() {
        $config = Zend_Registry::get('config');
        $serverName = $config->runtimeOptions->server->name;
        $serverProtocol =  $config->runtimeOptions->server->protocol;
        return $this->triggerUrl($serverProtocol.$serverName.APPLICATION_RUNDIR.'/editor/worker/queue');
    }
    
    /**
     * Trigger an url without waiting for response.
     * $url must be a valid URL.
     * If GET-Paramters should be send, than append it to the url direct.
     * If POST-Parameters should be send, use the second parameter $postParameters
     * 
     * @param string $url: a valid URL
     * @param array $postParameters: named array with values to be send to the url with method POST
     * @param string $method: defautl: 'GET', can be 'PUT' if postParamteters should by send by PUT instead of POST
     * 
     * @return boolean true if everything is OK
     */
    public function triggerUrl(string $url, $postParameters = array(), $method = 'GET') {
        
        $this->triggerInit($url, $postParameters, $method);
        
        $fsock = fsockopen(gethostbyname($this->host), $this->port, $errno, $errstr, 30);
        if ($fsock === false) {
            $this->log->logError(__CLASS__.'->'.__FUNCTION__.'; Can not trigger url:  '.$url.' Error: '.$errstr.' ('.$errno.')');
            return false;
        }
        
        $out = $this->createHeader($postParameters);
        fwrite($fsock, $out);
        
        stream_set_timeout($fsock, 5); // max readtime = 5 sec.
        $timeout = false;
        
        $header = '';
        $state = 0;
        while ($line = fgets($fsock)) {
            if ($line == "\r\n") {
                break;
            }
            $header .= $line;
            if (strpos($line, "HTTP") !== false) {
                $infos = explode(' ', $line);
                $state = $infos[1];
            }
        }
        
        // $header will be empty if fsock-connection runs into stream_set_timeout()
        // longtime-execution normaly indicates that everything is OK.
        if (empty($header)) {
            $timeout = true;
            fclose($fsock);
            return true; 
        }
        
        fclose($fsock);
        
        if ($state < 200 || $state >= 300) {
            $msg = __CLASS__.'->'.__FUNCTION__.'; Worker HTTP response state was not 2XX but '.$state.'; called URL:  '.$url;
            $longMsg = 'Method: '.$method.'; Post-Parameter: '.print_r($postParameters, true);
            $this->log->logError($msg, $longMsg);
            return false;
        }
        
        return true;
    }
    
    
    private function triggerInit(string $url, $postParameters = array(), $method = 'GET') {
        
        $urlParts = parse_url($url);
        
        if (!empty($urlParts['host'])) {
            $this->host = $urlParts['host'];
        }
    
        if (!empty($urlParts['scheme'])) {
            switch ($urlParts['scheme']) {
                case 'https':
                    $this->port = 443;
                    break;
            }
        }
        if (!empty($urlParts['port'])) {
            $this->port = $urlParts['port'];
        }
        
        if (!empty($urlParts['path'])) {
            $this->path = $urlParts['path'];
        }
        
        
        if (!empty($postParameters)) {
            $this->postParameters = $postParameters;
        }
        
        if (!empty($postParameters) && $method != 'PUT') {
            $this->method = 'POST';
        }
        
        if (!empty($method)) {
            $this->method = $method;
        }
        
        if (!empty($urlParts['query'])) {
            $this->getParameters = '&'.$urlParts['query'];
        }
    }
    
    
    private function createHeader() {
        $out = $this->method.' '.$this->path.$this->getParameters.' HTTP/1.1'."\r\n";
        $out .= 'Host: '.$this->host."\r\n";
        $out .= 'Accept: application/json'."\r\n"; // this is translate5-specific !!!
        //$out .= 'Cookie: XDEBUG_SESSION=ECLIPSE_DBGP_192.168.178.31'."\r\n";
        
        if ($this->method == 'GET') {
            $out .= 'Connection: Close'."\r\n";
            $out .= "\r\n";
        }
        
        // !!! if there are POST/PUT-data then add data here
        if ($this->method == 'POST' || $this->method == 'PUT') {
            $postData = http_build_query($this->postParameters);
            $postData = 'data='.json_encode($this->postParameters);
            $length = strlen($postData);
            
            $out .= 'Content-Type: application/x-www-form-urlencoded'."\r\n";
            //$out .= 'Content-Type: multipart/form-data'."\r\n";
            $out .= 'Content-Length: '.$length."\r\n";
            $out .= 'Connection: Close'."\r\n";
            $out .= "\r\n";
            $out .= $postData;
        }
        
        return $out;
    }
}