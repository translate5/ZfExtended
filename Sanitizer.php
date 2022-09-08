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
 * general sanitizer that sanitizes ALL request params
 */
final class ZfExtended_Sanitizer {

    const STRING = 'string';

    const MARKUP = 'markup';

    const REGEX = 'regex';

    const INTEGER = 'integer';

    const FLOAT = 'float';

    const EMAIL = 'email';

    const URL = 'url';

    const PATH = 'path';

    /**
     * Sanitizes a request value that represents the given type
     * The type must be one of our constants
     * @param string $val
     * @param string $type
     * @return float|int|string|null
     */
    public static function sanitize(string $val, string $type){
        switch($type){

            case self::MARKUP:
                return ZfExtended_Sanitized_Markup::get($val);

            case self::REGEX:
                return ZfExtended_Sanitized_Regex::get($val);

            case self::INTEGER:
                return self::integer($val);

            case self::FLOAT:
                return self::float($val);

            case self::EMAIL:
                return self::email($val);

            case self::URL:
                return self::url($val);

            case self::PATH:
                return self::path($val);
        }
        return self::string($val);
    }

    /**
     * @param string $string
     * @return string
     */
    public static function string(string $string) : string {
        return strip_tags($string);
    }

    /**
     * @param string $markup
     * @return string
     */
    public static function markup(string $markup) : string {
        return $markup; // TODO XSS
    }

    /**
     * @param string $regex
     * @return string
     */
    public static function regex(string $regex) : string {
        return $regex; // TODO XSS
    }

    /**
     * @param string $email
     * @return string
     */
    public static function email(string $email) : string {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    /**
     * @param string $val
     * @return int|null
     */
    public static function integer(string $val) : ?int {
        $val = filter_var($val, FILTER_SANITIZE_NUMBER_INT);
        if($val === ''){
            return NULL;
        }
        return intval($val);
    }

    /**
     * @param string $val
     * @return float|null
     */
    public static function float(string $val) : ?float {
        $val = filter_var($val, FILTER_SANITIZE_NUMBER_FLOAT);
        if($val === ''){
            return NULL;
        }
        return floatval($val);
    }

    /**
     * @param string $url
     * @return string
     */
    public static function url(string $url) : string {
        return preg_replace('~[^A-Za-z0-9\-\._\~:\/\?#\[\]@!\$&\(\)\*\+,;=]~', '', $url);
    }

    /**
     * @param string $path
     * @return string
     */
    public static function path(string $path) : string {
        return preg_replace('~[^A-Za-z0-9\-_\/\.]~', '', $path);
    }
}