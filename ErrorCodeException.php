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
 * Intermediate Exception class.
 * TODO The plan is to merge ZfExtended_ErrorCodeException and ZfExtended_Exception one time.
 * Before we can do that, all direct and old (without E0000 errorcodes) usage of ZfExtended_Exception must be eliminated.
 * After that the two classes can be merged, and all occurences of ZfExtended_ErrorCodeException replaced with ZfExtended_Exception
 */
class ZfExtended_ErrorCodeException extends ZfExtended_Exception {
    /**
     * default HTTP return code
     * @var integer
     */
    protected $httpReturnCode = 500;
    
    /**
     * Into this field all errorcodes of the class hierarchy is merged
     * @var array
     */
    protected $allErrorCodes = [
    ];
    
    protected static $errorCodeDomainOverride = [];
    
    /**
     * @param string $errorCode
     * @param array $extra
     * @param Exception $previous
     */
    public function __construct($errorCode, array $extra = [], Exception $previous = null) {
        $this->allErrorCodes = $this->mergeErrorCodes();
        parent::__construct($this->getErrorMessage($errorCode), substr($errorCode, 1), $previous);
        $this->setErrors($extra);
    }
    
    /**
     * return the internally stored domain
     * @return string
     */
    public function getDomain() {
        $code = $this->getErrorCode();
        if(empty(self::$errorCodeDomainOverride[$code])) {
            return $this->domain;
        }
        return self::$errorCodeDomainOverride[$code];
    }
    
    /**
     * Its not always making sense to create a separate Exception class - therefore via that function the needed error codes can be set dynamically
     * @param array $codes
     * @param string $domain optional, defines a different domain for the added codes
     */
    public static function addCodes(array $codes, $domain = null) {
        static::$localErrorCodes = array_merge(static::$localErrorCodes, $codes);
        if(!empty($domain)) {
            $codeKeys = array_keys($codes);
            self::$errorCodeDomainOverride = array_merge(self::$errorCodeDomainOverride, array_fill_keys($codeKeys, $domain));
        }
    }
    
    /**
     * Add additonal extra data to an existing exception instance
     * @param array $extraData
     */
    public function addExtraData(array $extraData) {
        $origData = $this->getErrors();
        $this->setErrors(array_merge($origData, $extraData));
    }
    
    /**
     * returns the internally used error code of that exception instance
     * @return string
     */
    public function getErrorCode() {
        //since the original exception can store only an integer we have to add the e here.
        $code = parent::getCode();
        return 'E'.str_pad($code, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * returns the desired HTTP return code for that Execption
     * @return string
     */
    public function getHttpReturnCode() {
        return $this->httpReturnCode;
    }
    
    protected function getErrorMessage($errorCode) {
        if(empty($this->allErrorCodes[$errorCode])) {
            return $errorCode.': Unknown Error!';
        }
        return $this->allErrorCodes[$errorCode];
    }
    
    /**
     * Merges the static errorcodes from this class and all its parents into one array
     * @return array
     */
    protected function mergeErrorCodes() {
        $ret = [];
        $c = get_called_class();
        do {
            if($c == 'ZfExtended_ErrorCodeException') {
                break;
            }
            $ret = array_merge($c::$localErrorCodes, $ret);
        } while(($c = get_parent_class($c)) !== false);
        return $ret;
    }
}