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

    /***
     * When set to true, in post request with encoding method ENC_URLENCODED,
     * the array indexes will be removed from multivalue fields
     * @var boolean
     */
    protected $removeArrayIndexInUrlEncode = false;
    
    
    public function request($method = null){
        //ignore the debugging if not enabled
        $url = $this->getUri(true);
        if(!$this->isRequestDebugEnabled($url)){
        //if(!$this->isRequestDebugEnabled()){
            return parent::request($method);
        }
        $randKey = substr(md5(rand()), 0, 7);
        
        if(!empty($this->paramsGet)) {
            $url .= ('?'.http_build_query($this->paramsGet));
        }
        error_log("Request ($randKey): ".(empty($method) ? $this->method : $method).' '.$url);
        error_log("Headers ".print_r($this->headers,1));
        if(!empty($this->raw_post_data)) {
            $bytes = '('.mb_strlen($this->raw_post_data).' bytes)';
            error_log("Raw Data ($randKey) '.$bytes.': \n".$this->raw_post_data."\n\n");
        }
        if(!empty($this->paramsPost)) {
            error_log("Post Data ($randKey): \n".print_r($this->paramsPost,1));
        }
        $response = parent::request($method);
        error_log("Status ($randKey): ".print_r($response->getStatus(),1));
        error_log("Headers ($randKey):".($response->getHeadersAsString()));
        error_log("Raw Body ($randKey):".print_r($response->getRawBody(),1));
        error_log("Body ($randKey):".print_r($response->getBody(),1));
        return $response;
    }
    
    /**
     * Prepare the request body (for POST and PUT requests)
     *
     * @return string
     * @throws Zend_Http_Client_Exception
     */
    protected function _prepareBody()
    {
        $removeArrayIndexInUrlEncode = !empty($this->config['removeArrayIndexInUrlEncode']);
        //remove the encoded array indexes from the body
        if($removeArrayIndexInUrlEncode && $this->enctype == self::ENC_URLENCODED){
            return preg_replace('/\%5B\d+\%5D/', '', parent::_prepareBody());
        }
        return parent::_prepareBody();
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
