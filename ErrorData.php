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
 * Container class that is intended to have a unified model to hold data for exceptions and logging without having to mess around with parameter lists
 */
class ZfExtended_ErrorData {

    private string $code;

    private string $message;

    private array $extra;

    private string $domain;

    private int $level;

    /**
     * @param string $code
     * @param string $msg
     * @param array $extraData
     * @param string $domain
     * @param int $level
     */
    public function __construct(string $code, string $msg='', array $extraData=[], string $domain='', int $level=-1){
        if(empty($code) || !preg_match('/^E[0-9]{4}$/', $code)){
            error_log('ZfExtended_ErrorData: INVALID ERROR CODE: '.$code);
            $this->code = ZfExtended_Logger::ECODE_LEGACY_ERRORS;
        } else {
            $this->code = $code;
        }
        $this->message = $msg;
        $this->extra = $extraData;
        $this->domain = $domain;
        $this->level = $level;
    }

    /**
     * @return string
     */
    public function getCode() : string {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getMessage() : string {
        return $this->message;
    }

    /**
     * @return array
     */
    public function getExtra() : array {
        return $this->extra;
    }

    /**
     * @return string
     */
    public function getDomain() : string {
        if(empty($this->domain)){
            return ZfExtended_Logger::CORE_DOMAIN;
        }
        return $this->domain;
    }

    /**
     * @return int
     */
    public function getlevel() : int {
        if($this->level < 1){
            return ZfExtended_Logger::LEVEL_ERROR;
        }
        return $this->level;
    }

    /**
     * @return string
     */
    public function debug() : string {
        $text = 'ERROR '.$this->getCode().': '.$this->getMessage();
        foreach($this->getExtra() as $key => $val){
            $text .= "\n   ".$key.': ';
            if(is_bool($val)){
                $text .= $val ? 'true' : 'false';
            } else if(is_scalar($val) || (is_object($val) && $val instanceof Stringable)){
                $text .= $val;
            } else {
                $text .= gettype($val);
            }
        }
        return $text . "\n";
    }
}
