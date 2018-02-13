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

class ZfExtended_Worker_TriggerByHttp {
    
    private $host = 'localhost';
    private $port = 80; //attention the port alone does not define if SSL is used or no by fsockopent! 
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
        return $this->triggerUrl(APPLICATION_RUNDIR.'/editor/worker/'.$id, array('state' => 'running', 'hash' => $hash), 'PUT');
    }
    
    public function triggerQueue() {
        return $this->triggerUrl(APPLICATION_RUNDIR.'/editor/worker/queue');
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
        
        $host = $this->triggerInit($url, $postParameters, $method);
        
        $fsock = fsockopen($host, $this->port, $errno, $errstr, 30);
        if ($fsock === false) {
            $this->log->logError(__CLASS__.'->'.__FUNCTION__.'; Can not trigger url:  '.$host.':'.$this->port.' Error: '.$errstr.' ('.$errno.')');
            return false;
        }
        
        $out = $this->createHeader($postParameters);
        fwrite($fsock, $out);
        
        stream_set_timeout($fsock, 5); // max readtime = 5 sec.
        
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
        
        $info = stream_get_meta_data($fsock);

        // $header will be empty if fsock-connection runs into stream_set_timeout() 
        //  or if the configured Worker URL is not accessible (due HTTPS / gateway / proxy reasons) 
        // a timeout normally indicates that everything is OK, since worker are intended to have a long execution time
        if (empty($header) || $info['timed_out']) {
            fclose($fsock);
            if($info['timed_out']) {
                return true; //a real timeout is mostly OK. 
                //TODO can we identify timeouts because of target does not exist?
            }
            $this->log->logError(__CLASS__.'->'.__FUNCTION__.'; Worker URL result is no HTTP answer!: '.$host.':'.$this->port);
            // if not (URL responds immediately with an empty result) this means the called URL is not properly configured!
            // → make a dedicated log entry, since the log below would be bogus for this situation
            return false; 
        }
        
        fclose($fsock);
        
        if ($state < 200 || $state >= 300) {
            $msg = __CLASS__.'->'.__FUNCTION__.'; Worker HTTP response state was not 2XX but '.$state.'; called URL:  '.$host.':'.$this->port;
            $longMsg = 'Method: '.$method.'; Post-Parameter: '.print_r($postParameters, true);
            $this->log->logError($msg, $longMsg);
            return false;
        }
        
        return true;
    }
    
    /**
     * Initializes the worker URL parts
     * @param string $path
     * @param array $postParameters
     * @param string $method
     * @return string the host which should be used by fsockopen
     */
    private function triggerInit(string $path, $postParameters = array(), $method = 'GET') {
        $config = Zend_Registry::get('config');
        $rop = $config->runtimeOptions;
        
        $workerServer = $rop->worker->server;
        if(empty($workerServer)) {
            $workerServer = $rop->server->protocol.$rop->server->name;
        }
            
        $urlParts = parse_url($workerServer.$path);
        
        if (!empty($urlParts['host'])) {
            $this->host = $urlParts['host'];
        }
        $host = $this->host;
    
        if (!empty($urlParts['scheme'])) {
            switch ($urlParts['scheme']) {
                case 'https':
                    //fsockopen needs ssl:// scheme when using https
                    $host = 'ssl://'.$host;
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
        
        //return the hostname dedicated for fsockopen
        return $host;
    }
    
    
    private function createHeader() {
        $out = $this->method.' '.$this->path.$this->getParameters.' HTTP/1.1'."\r\n";
        $out .= 'Host: '.$this->host."\r\n";
        $out .= 'Accept: application/json'."\r\n"; // this is translate5-specific !!!
        if(ZfExtended_Debug::hasLevel('core', 'worker')){
            $out .= 'Cookie: XDEBUG_SESSION=ECLIPSE'."\r\n";
        }
            $out .= 'Cookie: XDEBUG_SESSION=ECLIPSE'."\r\n";
        
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