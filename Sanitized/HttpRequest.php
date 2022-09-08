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

/**
 * Base Request Class
 * Sanitizes all parameters but not 'data' which shall be retrieved with ::getData to properly support JSON params
 */
class ZfExtended_Sanitized_HttpRequest extends REST_Controller_Request_Http {

    /**
     * Retrieves all GET/POST params sanitized
     * @param string $key
     * @return mixed
     */
    public function __get($key){
        if(isset($this->_params[$key])){
            return $this->_params[$key];
        } else if($key != 'data' && isset($_GET[$key])){
            return ZfExtended_Sanitizer::string($_GET[$key]);
        } else if($key != 'data' && isset($_POST[$key])){
            return ZfExtended_Sanitizer::string($_POST[$key]);
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
        } elseif ($keyName != 'data' && in_array('_GET', $paramSources) && (isset($_GET[$keyName]))) {
            return ZfExtended_Sanitizer::string($_GET[$keyName]);
        } elseif ($keyName != 'data' && in_array('_POST', $paramSources) && (isset($_POST[$keyName]))) {
            return ZfExtended_Sanitizer::string($_POST[$keyName]);
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
     */
    public function getSanitizedParam(string $key, string $type, $default = null){
        $param = param::getParam($key, $default);
        if($param !== null && is_string($param)){
            return ZfExtended_Sanitizer::sanitize($param, $type);
        }
        return $param;
    }

    /**
     * Retrieves an unsanitized request parameter
     * @param $key
     * @param mixed $default
     * @return mixed
     */
    public function getRawParam(string $key, $default = null){
        return parent::getParam($key, $default);
    }

    /**
     * Retrieves an sanitized array of all parameters
     * @return array
     */
    public function getParams(){
        $return       = $this->_params;
        $paramSources = $this->getParamSources();
        if (in_array('_GET', $paramSources) && isset($_GET) && is_array($_GET)){
            foreach($_GET as $key => $val){
                $return[$key] = ($key === 'data') ? $val : ZfExtended_Sanitizer::string($val);
            }
        }
        if (in_array('_POST', $paramSources) && isset($_POST) && is_array($_POST)){
            foreach($_POST as $key => $val){
                $return[$key] = ($key === 'data') ? $val : ZfExtended_Sanitizer::string($val);
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
     * @param array $typeMap: a map of param-names and types as defined in ZfExtended_Sanitizer as constants. The default sanitization is always "string"
     * @return stdClass|array
     */
    public function getData(bool $decodeAssociative, array $typeMap = []){
        $data = json_decode(parent::getParam('data'), $decodeAssociative);
        if($decodeAssociative){
            return self::sanitizeArray($data, $typeMap);
        }
        return self::sanitizeObject($data, $typeMap);
    }

    /**
     * Retrieves the raw data-request param
     * @return mixed|string|null
     */
    public static function getRawData($default = null){
        return parent::getParam('data', $default);
    }

    /**
     * Helper for data-sanitization
     * @param array|stdClass $data
     * @param array $typeMap
     * @return mixed
     */
    private function sanitizeArray($data, array $typeMap){
        foreach($data as $key => $val){
            if(is_string($val)){
                $data[$key] = self::sanitizeDataValue($key, $val, $typeMap);
            } else if(is_array($val)){
                $data[$key] = self::sanitizeArray($val, []);
            } else if(is_object($val)){
                $data[$key] = self::sanitizeObject($val, []);
            }
        }
        return $data;
    }

    private function sanitizeObject($data, array $typeMap){
        foreach($data as $key => $val){
            if(is_string($val)){
                $data->$key = self::sanitizeDataValue($key, $val, $typeMap);
            } else if(is_array($val)){
                $data->$key = self::sanitizeArray($val, []);
            } else if(is_object($val)){
                $data->$key = self::sanitizeObject($val, []);
            }
        }
        return $data;
    }

    /**
     * Helper for data-sanitization
     * @param string $key
     * @param string $val
     * @param array $typeMap
     * @return mixed
     */
    private function sanitizeDataValue(string $key, string $val, array $typeMap){
        if(array_key_exists($key, $typeMap)){
            return ZfExtended_Sanitizer::sanitize($val, $typeMap[$key]);
        }
        return ZfExtended_Sanitizer::string($val);
    }
}