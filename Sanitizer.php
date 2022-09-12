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

    /**
     * Leads to stripping of all tags
     */
    const STRING = 'string';
    /**
     * Leads to stripping of script-tags & on** handlers
     */
    const MARKUP = 'markup';
    /**
     * leads to NO sanitization and thus the application logic must ensure XSS prevention
     */
    const UNSANITIZED = 'unsanitized';

    /**
     * Sanitizes a request value that represents the given type
     * The type must be one of our constants
     * @param string $val
     * @param string $type
     * @return string
     */
    public static function sanitize(string $val, string $type) : string {
        switch($type){

            case self::MARKUP:
                return self::markup($val);

            case self::UNSANITIZED:
                return $val;
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
        error_log('ZfExtended_Sanitizer::markup: '.$markup); // TODO REMOVE
        // TODO IMPLEMENT
        return $markup;
    }
}