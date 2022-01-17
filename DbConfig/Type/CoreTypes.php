<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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
 * Contains the config handler for core types
 */
class ZfExtended_DbConfig_Type_CoreTypes extends ZfExtended_DbConfig_Type_Abstract {
    const TYPE_MAP = 'map';
    const TYPE_LIST = 'list';
    const TYPE_ABSPATH = 'absolutepath';


    /**
     * validates and converts the given config value (basic type check and conversion ("true"|"on" to valid bool true and so on)
     * @param string $type the underlying config type
     * @param string $value the value to be checked
     * @param string|null $errorStr OUT the error message of the failed validation
     * @return bool false if not valid
     */
    public function validateValue(string $type, string &$value, ?string &$errorStr): bool {
        switch($type) {
            case self::TYPE_LIST:
            case self::TYPE_MAP:
                $typeForComparsion = $type == self::TYPE_LIST ? 'array' : 'object';
                $valueDecoded = $this->jsonDecode($value, $errorStr);

                if(!empty($errorStr)) {
                    $errorStr = "type $type needs a valid JSON value, error is '".$errorStr."'";
                    return false;
                }

                if($typeForComparsion != gettype($valueDecoded)) {
                    //not a valid array or object
                    $errorStr = "not a valid $typeForComparsion '$value'";
                    return false;
                }
                break;

            case 'boolean':
                $res = parse_ini_string('value = '.$value, false, INI_SCANNER_TYPED);
                $value = boolval($res['value'] ?? false) ? '1' : '0'; //ensure bool, then from bool we make 0 or 1 as string
                return true; // no error is possible here

            case 'integer':
            case 'float':
                //must be is_numeric otherwise error
                if(!is_numeric($value)) {
                    $errorStr = "not a valid $type '$value'";
                    return false;
                }
                $value = strval($type == 'float' ? doubleval($value) : intval($value)); //cast the value to the desired number then back to string
                break;

            case 'string':
            case self::TYPE_ABSPATH:
            default:
                return true; // no error is possible here, its always a string
        }
        return true;
    }

    /**
     * returns the GUI view class to be used or null for default handling
     * @return string|null
     */
    public function getGuiViewCls(): ?string {
        return null;
    }

    /**
     * converts the config values stored in the DB to the applicable target format
     * @param string $type
     * @param string|null $value
     * @return mixed|string|null
     */
    public function convertValue(string $type, ?string $value) {
        $error = '';
        switch ($type) {
            case self::TYPE_LIST:
            case self::TYPE_MAP:
                return $this->jsonDecode($value, $error);
            case self::TYPE_ABSPATH:
                return $this->convertFilepath($value);
        }
        return $value;
    }

    protected function jsonDecode($value, ?string &$error) {
        if ($value === '') {
            return null;
        }
        $result = json_decode($value);
        if(json_last_error() != JSON_ERROR_NONE) {
            //ZfExtended_Log is not possible here, so log it manually, see TRANSLATE-354 decouple Log from Mail
            $error = json_last_error_msg();
        }
        return $result;
    }

    /**
     * converts the type of the config value to the corresponding PHP type
     * @param string $type
     * @return string
     */
    public function getPhpType(string $type): string {
        switch ($type) {
            case self::TYPE_LIST:
            case self::TYPE_MAP:
                return 'array';
            case self::TYPE_ABSPATH:
                return 'string';
        }
        return $type;
    }

    /**
     * checks if the given path is notated as relative path, if yes the APPLICATION_PATH is prepended
     * @param string $path
     * @return string
     */
    protected function convertFilepath(string $path): string
    {
        $firstChar = mb_substr($path, 0, 1);
        //this is a absolute path
        if($firstChar == DIRECTORY_SEPARATOR) {
            return $path;
        }
        //on windows we have to check for driveletters also
        $secondChar = mb_substr($path, 1, 1); //is on windows with driveletter == :
        if(DIRECTORY_SEPARATOR != '/' && $secondChar == ':') {
            return $path;
        }
        //convert / to \ under windows in stored / default values
        if(DIRECTORY_SEPARATOR != '/') {
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        }
        //all others are relative, so we have to append the APPLICATION_PATH
        return APPLICATION_PATH.DIRECTORY_SEPARATOR.$path;
    }
}