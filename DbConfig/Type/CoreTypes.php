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
 * Contains the config handler for core config types
 * These are (see table definition): string | integer | boolean | list | map | absolutepath | float | markup | json | regex | regexlist
 */
class ZfExtended_DbConfig_Type_CoreTypes extends ZfExtended_DbConfig_Type_Abstract {

    const TYPE_STRING = 'string';

    const TYPE_INTEGER = 'integer';

    const TYPE_FLOAT = 'float';

    const TYPE_BOOLEAN = 'boolean';

    const TYPE_MAP = 'map';

    const TYPE_LIST = 'list';

    const TYPE_ABSPATH = 'absolutepath';

    const TYPE_MARKUP = 'markup';

    const TYPE_JSON = 'json';

    const TYPE_REGEX = 'regex';

    const TYPE_REGEXLIST = 'regexlist';

    /**
     * Retrieves the sanititzation-type for a config-type
     * Note, that the application therefore is responsible to sanitize REGEX & REGEXLIST types (what for Config-values is achieved with the validation via ::validateValue)
     * @param string $configType
     * @return string
     */
    public static function sanitizationType(string $configType) : string
    {
        return match ($configType) {
            self::TYPE_REGEX, self::TYPE_REGEXLIST => ZfExtended_Sanitizer::UNSANITIZED,
            self::TYPE_MARKUP => ZfExtended_Sanitizer::MARKUP,
            default => ZfExtended_Sanitizer::STRING,
        };
    }

    /**
     * converts the type of the config value to the corresponding PHP type
     * @param string $type
     * @return string
     */
    public static function phpType(string $type): string
    {
        return match ($type) {
            self::TYPE_LIST, self::TYPE_MAP, self::TYPE_REGEXLIST => 'array',
            self::TYPE_ABSPATH, self::TYPE_JSON, self::TYPE_MARKUP, self::TYPE_REGEX => 'string',
            default => $type,
        };
    }

    /**
     * Sets the given PHP type for a value
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    public static function setPhpType(mixed $value, string $type): mixed
    {
        settype($value, self::phpType($type));
        return $value;
    }

    /**
     * validates and converts the given config value (basic type check and conversion ("true"|"on" to valid bool true and so on)
     * @param editor_Models_Config $type the underlying config type
     * @param string $newvalue the value to be checked
     * @param string|null $errorStr OUT the error message of the failed validation
     * @return bool false if not valid
     */
    public function validateValue(editor_Models_Config $config, string &$newvalue, ?string &$errorStr): bool {
        $type = $config->getType();
        switch($type) {
            case self::TYPE_LIST:
            case self::TYPE_MAP:
                $typeForComparsion = $type == self::TYPE_LIST ? 'array' : 'object';
                $valueDecoded = $this->jsonDecode($newvalue, $errorStr);

                if(!empty($errorStr)) {
                    $errorStr = "type $type needs a valid JSON value, error is '".$errorStr."'";
                    return false;
                }

                if($typeForComparsion != gettype($valueDecoded)) {
                    //not a valid array or object
                    $errorStr = "not a valid $typeForComparsion '$newvalue'";
                    return false;
                }
                break;

            case self::TYPE_BOOLEAN:
                $res = parse_ini_string('value = '.$newvalue, false, INI_SCANNER_TYPED);
                $newvalue = boolval($res['value'] ?? false) ? '1' : '0'; //ensure bool, then from bool we make 0 or 1 as string
                return true; // no error is possible here

            case self::TYPE_INTEGER:
            case self::TYPE_FLOAT:
                //must be is_numeric otherwise error
                if(!is_numeric($newvalue)) {
                    $errorStr = "not a valid $type '$newvalue'";
                    return false;
                }
                $newvalue = strval($type == 'float' ? doubleval($newvalue) : intval($newvalue)); //cast the value to the desired number then back to string
                break;

            case self::TYPE_REGEX:
                if(preg_match($newvalue, '') === false){
                    $errorStr = "not a valid $type '$newvalue'";
                    return false;
                }
                return true;

            case self::TYPE_REGEXLIST:
                $valueDecoded = $this->jsonDecode($newvalue, $errorStr);
                foreach($valueDecoded as $regex){
                    if(preg_match($regex, '') === false){
                        $errorStr = "not a valid $type '$newvalue'";
                        return false;
                    }
                }
                return true;

            case self::TYPE_JSON:
                if(json_decode($newvalue) === null){
                    $errorStr = "not a valid $type '$newvalue'";
                    return false;
                }
                return true;

            default:
                return true; // no error is possible here, its always a string
        }
        return true;
    }

    /**
     * returns true if there are "defaults" values and the given value is one of them
     */
    public function isValidInDefaults(editor_Models_Config $config, string $value): bool {
        $defaults = $config->getDefaults();
        if(empty($defaults)) {
            return true;
        }
        $defaults = explode(',', $defaults);
        //since list is a core type, we can include the check here
        if($config->getType() == self::TYPE_LIST || $config->getType() == self::TYPE_REGEXLIST) {
            $value = json_decode($value);
            $diff = array_diff($value, $defaults);
            return empty($diff);
        }

        //currently for all other types we just check of the new value is in the default list.
        // TODO For a map the defaults could be a json with an assoc array to compare against, but thats just not implemented yet
        return in_array(trim($value), $defaults);
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
            case self::TYPE_REGEXLIST:
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
    public function getPhpType(string $type): string
    {
        return self::phpType($type);
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
