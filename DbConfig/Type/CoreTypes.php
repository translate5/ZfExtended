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
     * validates the given config value (basic type check)
     * @param string $type the underlying config type
     * @param mixed $value the value to be checked
     * @param string|null $errorStr OUT the error message of the failed validation
     * @return bool false if not valid
     */
    public function validateValue(string $type, &$value, ?string &$errorStr): bool {
        $originalValue = $value;
        switch($type) {
            case self::TYPE_LIST:
                $typeForComparsion = 'array';
                $value = $this->jsonDecode($value, $errorStr);
                break;

            case self::TYPE_MAP:
                $typeForComparsion = 'object';
                $value = $this->jsonDecode($value, $errorStr);
                break;

            case self::TYPE_ABSPATH:
                $typeForComparsion = 'string';
                break;

            case 'float':
                $typeForComparsion = 'double'; //see gettype documentation why is this
                break;

            default:
                $typeForComparsion = $type;
                break;

            //all others are basic php types
        }

        if(!empty($errorStr)) {
            $errorStr = "type $type needs a valid JSON value, error is '".$errorStr."'";
            return false;
        }

        if($typeForComparsion != gettype($value)) {
            //not a valid $type
            $errorStr = "not a valid $type '$originalValue'";
            return false;
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
