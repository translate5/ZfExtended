<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2022 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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

use MittagQI\ZfExtended\Cors;
/**
 * Base Request Class
 * Sanitizes all parameters but not 'data' which shall be retrieved with ::getData to properly support JSON params
 */
class ZfExtended_Sanitized_HttpRequest extends REST_Controller_Request_Http {

    public function __construct($action = null, $controller = null, $module = null, array $params = array())
    {
        // processing CORS preflight requests
        Cors::handlePreflight();
        parent::__construct($action, $controller, $module, $params);
    }

    /**
     * Retrieves all GET/POST params sanitized
     * @param string $key
     * @return mixed
     */
    public function __get($key){
        if(isset($this->_params[$key])){
            return $this->_params[$key];
        } else if($key != 'data' && isset($_GET[$key])){
            return $this->sanitizeRequestValue($_GET[$key]);
        } else if($key != 'data' && isset($_POST[$key])){
            return $this->sanitizeRequestValue($_POST[$key]);
        }
        return parent::__get($key);
    }

    /**
     * Retrieves the raw unsanitized request params
     * @param string $key
     * @return mixed
     */
    public function getRaw(string $key){
        return parent::__get($key);
    }

    /**
     * Retrieves a sanitized request parameter
     * @param mixed $key
     * @param mixed $default Default value to use if key not found
     * @return mixed
     */
    public function getParam($key, $default = null){
        $keyName = (null !== ($alias = $this->getAlias($key))) ? $alias : $key;
        $paramSources = $this->getParamSources();
        if (isset($this->_params[$keyName])) {
            return $this->_params[$keyName];
        } elseif ($keyName != 'data' && in_array('_GET', $paramSources) && isset($_GET[$keyName])) {
            return $this->sanitizeRequestValue($_GET[$keyName]);
        } elseif ($keyName != 'data' && in_array('_POST', $paramSources) && isset($_POST[$keyName])) {
            return $this->sanitizeRequestValue($_POST[$keyName]);
        }
        return $default;
    }

    /**
     * Retrieves a sanitized request param
     * The type must be one of ZfExtended_Sanitizer constants
     * @param string $key
     * @param string $type
     * @param null $default
     * @return mixed
     * @throws ZfExtended_SecurityException
     */
    public function getSanitizedParam(string $key, string $type, $default = null){
        $param = parent::getParam($key, $default);
        if(is_string($param)){
            return ZfExtended_Sanitizer::sanitize($param, $type);
        }
        return $param;
    }

    /**
     * Retrieves an unsanitized request parameter
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getRawParam(string $key, $default = null){
        return parent::getParam($key, $default);
    }

    /**
     * Retrieves a sanitized array of all parameters
     * If some param already exists in $this->_params - it won't be overwritten by $_POST or $_GET
     * @return array
     */
    public function getParams(){
        $return       = $this->_params;
        $paramSources = $this->getParamSources();
        if (in_array('_GET', $paramSources) && isset($_GET) && is_array($_GET)){
            foreach($_GET as $key => $val){
                if (!array_key_exists($key, $return)) {
                    $return[$key] = ($key === 'data') ? $val : $this->sanitizeRequestValue($val);
                }
            }
        }
        if (in_array('_POST', $paramSources) && isset($_POST) && is_array($_POST)){
            foreach($_POST as $key => $val){
                if (!array_key_exists($key, $return)) {
                    $return[$key] = ($key === 'data') ? $val : $this->sanitizeRequestValue($val);
                }
            }
        }
        return $return;
    }

    /**
     * Retrieves an unsanitized array of all parameters
     * @return array
     */
    public function getRawParams(){
        return parent::getParams();
    }

    /**
     * Sanitizes the "data" param that represents the JSON data of a PUT or POST
     * @param bool $decodeAssociative
     * @param array $typeMap
     * @return mixed
     * @throws Zend_Controller_Request_Exception
     * @throws ZfExtended_BadRequest
     * @throws ZfExtended_SecurityException
     */
    public function getData(bool $decodeAssociative, array $typeMap = []): mixed
    {
        $hasLegacyDataField = parent::has('data');
        if ($hasLegacyDataField) {
            $data = parent::getParam('data');
        } else {
            // if the request does not contain the data field, we assume JSON in raw body if content type is appropriate
            if ($this->getHeader('content-type') !== 'application/json') {
                return null;
            }
            $data = $this->getRawBody();
        }
        try {
            $data = json_decode($data, $decodeAssociative, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ZfExtended_BadRequest('E1560', [
                'error' => $e->getMessage()
            ], $e);
        }
        if (is_array($data)){
            return $this->sanitizeArray($data, $typeMap);
        } elseif(is_object($data)){
            return $this->sanitizeObject($data, $typeMap);
        } elseif(is_string($data)){
            return ZfExtended_Sanitizer::string($data);
        }
        // can only be number, bool or null here, so needs no sanitization
        return $data;
    }

    /**
     * Retrieves the raw data-request param
     * @return mixed|string|null
     */
    public function getRawData($default = null){
        return parent::getParam('data', $default);
    }

    /**
     * Sanitizes a single request value, which can only be string or array
     * @param array|string $requestValue
     * @return array|string
     */
    private function sanitizeRequestValue($requestValue){
        if(is_array($requestValue)){
            foreach($requestValue as $key => $value){
                $requestValue[$key] = $this->sanitizeRequestValue($value);
            }
            return $requestValue;
        }
        if(!empty($requestValue)){
            return ZfExtended_Sanitizer::string($requestValue);
        }
        return $requestValue;
    }

    /**
     * Helper for data-sanitization of arrays
     * @param array $data
     * @param array $typeMap
     * @return array
     * @throws ZfExtended_SecurityException
     */
    private function sanitizeArray(array $data, array $typeMap) : array {
        foreach($data as $key => $val){
            if(is_string($val)){
                $data[$key] = $this->sanitizeDataValue($key, $val, $typeMap);
            } else if(is_array($val)){
                $data[$key] = $this->sanitizeArray($val, []);
            } else if(is_object($val)){
                $data[$key] = $this->sanitizeObject($val, []);
            }
        }
        return $data;
    }

    /**
     * Helper for data-sanitization of stdClass Objects
     * @param $data
     * @param array $typeMap
     * @return mixed
     * @throws ZfExtended_SecurityException
     */
    private function sanitizeObject($data, array $typeMap){
        foreach($data as $key => $val){
            if(is_string($val)){
                $data->$key = $this->sanitizeDataValue($key, $val, $typeMap);
            } else if(is_array($val)){
                $data->$key = $this->sanitizeArray($val, []);
            } else if(is_object($val)){
                $data->$key = $this->sanitizeObject($val, []);
            }
        }
        return $data;
    }

    /**
     * Helper for data-sanitization
     * @param string $key
     * @param string $val
     * @param array $typeMap
     * @return string
     * @throws ZfExtended_SecurityException
     */
    private function sanitizeDataValue(string $key, string $val, array $typeMap) : string {
        if(array_key_exists($key, $typeMap)){
            return ZfExtended_Sanitizer::sanitize($val, $typeMap[$key]);
        }
        return ZfExtended_Sanitizer::string($val);
    }
}