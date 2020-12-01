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
        
        //ignore the debugginf if not enabled
        if(!$this->isRequestDebugEnabled()){
            return parent::request($method);
        }
        //TODO: add posibility this to be filtered per url
        $randKey = substr(md5(rand()), 0, 7);
        
        error_log("Method ($randKey): ".(empty($method) ? $this->method : $method));
        error_log("URL ($randKey):".$this->getUri(true));
        error_log("\n\nDATA ($randKey): \n".$this->raw_post_data."\n\n");
        error_log("Bytes ($randKey):".mb_strlen($this->raw_post_data));
        error_log("Headers ".print_r($this->headers,1));
        $response = parent::request($method);
        error_log("Status ($randKey): ".print_r($response->getStatus(),1));
        error_log("Raw Body ($randKey):".print_r($response->getRawBody(),1));
        error_log("Body ($randKey):".print_r($response->getBody(),1));
        error_log("Headers ($randKey):".($response->getHeadersAsString()));
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
     * @return boolean
     */
    protected function isRequestDebugEnabled(){
        $config = Zend_Registry::get('config');
        return $config->runtimeOptions->debug->httpclient ?? false;
    }
}
