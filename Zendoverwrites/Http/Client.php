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

/**
 * Overwritten HTTP Client
 * - Adds the config parameter "removeArrayIndexInUrlEncode", if true the [] in a post with multiple same named parameter are removed
 * - Adds debugging capabilities
 */
class  ZfExtended_Zendoverwrites_Http_Client extends Zend_Http_Client {
    public function request($method = null){
        try {
            //ignore the debugging if not enabled
            $url = $this->getUri(true);
            if(!$this->isRequestDebugEnabled($url)){
                return parent::request($method);
            }
            $randKey = substr(md5(rand()), 0, 7);
            $this->logRequest($randKey, $method, $url);
            $response = parent::request($method);
            $this->logResponse($randKey, $response);
            return $response;
        } catch(Zend_Http_Client_Exception | Zend_Http_Client_Adapter_Exception $httpException) {
            $this->handleException($httpException, $method, $url);
        }
    }
    
    protected function handleException(Exception $httpException, $method, $url) {
        $msg = $httpException->getMessage();
        
        //if the error is one of the following, we have a request timeout
        //ERROR Zend_Http_Client_Adapter_Exception: E9999 - Read timed out after 10 seconds
        if(strpos($msg, 'Read timed out after') === 0) {
            //Request time out in {method}ing URL {url}
            throw new ZfExtended_Zendoverwrites_Http_Exception_TimeOut('E1307', [
                'method' => ($method ?? $this->method),
                'url' => $url,
            ], $httpException);
            //if the error is one of the following, we have a connection problem
            //ERROR Zend_Http_Client_Adapter_Exception: E9999 - Unable to Connect to tcp://localhost:8080. Error #111: Connection refused
            //ERROR Zend_Http_Client_Adapter_Exception: E9999 - Unable to Connect to tcp://michgibtesdefinitivnichtalsdomain.com:8080. Error #0: php_network_getaddresses: getaddrinfo failed: Name or service not known
            //the following IP is not routed, so it trigers a timeout on connection connect, which must result in "Unable to connect" too and not in a request timeout below
            //ERROR Zend_Http_Client_Adapter_Exception: E9999 - Unable to Connect to tcp://10.255.255.1:8080. Error #111: Connection refused
        }elseif(strpos($msg, 'Unable to Connect to') === 0) {
            $url = array_intersect_key(parse_url($url), array_flip(['scheme', 'host', 'port']));
            $url['host'] = '//'.($url['host'] ?? '');
            // Requested URL is DOWN: {url}
            throw new ZfExtended_Zendoverwrites_Http_Exception_Down('E1308', [
                'url' => $url,
                'server' => join(':', $url),
            ], $httpException);
        }elseif(strpos($msg, 'Unable to read response, or response is empty') === 0) {
            //Empty response in {method}ing URL {url}
            throw new ZfExtended_Zendoverwrites_Http_Exception_NoResponse('E1309', [
                'method' => ($method ?? $this->method),
                'url' => $url,
            ], $httpException);
        }
        //FIXME what do we get here? Wrap with a general Request Exception???
        throw $httpException;
    }
    
    protected function logRequest($randKey, $method, $url) {
        if(!empty($this->paramsGet)) {
            $url .= ('?'.http_build_query($this->paramsGet));
        }
        error_log("SEND Request ($randKey): ".($method ?? $this->method).' '.$url);
        error_log("SEND Headers ".print_r($this->headers,1));
        if(!empty($this->raw_post_data)) {
            $bytes = '('.mb_strlen($this->raw_post_data).' bytes)';
            error_log("SEND Raw Data ($randKey) '.$bytes.': \n".$this->raw_post_data."\n\n");
        }
        if(!empty($this->paramsPost)) {
            error_log("SEND Post Data ($randKey): \n".print_r($this->paramsPost,1));
        }
    }
    
    protected function logResponse($randKey, $response) {
        error_log("GOT Status ($randKey): ".print_r($response->getStatus(),1));
        error_log("GOT Headers ($randKey):".($response->getHeadersAsString()));
        error_log("GOT Raw Body ($randKey):".print_r($response->getRawBody(),1));
        error_log("GOT Body ($randKey):".print_r($response->getBody(),1));
    }
    
    /**
     * Prepare the request body (for POST and PUT requests)
     *
     * @return string
     * @throws Zend_Http_Client_Exception
     */
    protected function _prepareBody()
    {
        $removeArrayIndexInUrlEncode = !empty($this->config['removearrayindexinurlencode']);
        //remove the encoded array indexes from the body
        
        $body = parent::_prepareBody();
        if($removeArrayIndexInUrlEncode && $this->enctype == self::ENC_URLENCODED){
            $body = preg_replace('/\%5B\d+\%5D/', '', $body);
            $this->setHeaders(self::CONTENT_LENGTH, strlen($body));
        }
        return $body;
    }
    
    /***
     * Check if the debug mode is enabled for the current request.
     * @param $url string the URL to be called for filtering
     * @return boolean
     */
    protected function isRequestDebugEnabled(string $url): bool{
        $config = Zend_Registry::get('config');
        $debug = $config->debug->httpclient ?? false;
        if(!$debug) {
            return false;
        }
        $debug = strtolower($debug);
        switch ($debug) {
            case '1':
            case 'on':
            case 'true':
                return true;
                
            default:
                return (stripos($url, $debug) !== false);
        }
    }
}
