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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * Defines some global functions.
 * Functions should be defines as public static function, so they can be calles as:
 * 
 * ZfExtended_Utils::function()
 * 
 * from everywhere in the application.
 * 
 */
class ZfExtended_Utils {
    
    /**
     * Get an array of all class-constants-names from class $className which begin with $prefix.
     *
     * @param string $className
     * @param string $praefix
     *
     * @return array of constants-names (key) and its values (value)
     */
    public static function getConstants(string $className, string $praefix) {
        $constants = array();
        
        $reflectionClass = new ReflectionClass($className);
        $classConstants = $reflectionClass->getConstants();
        
        foreach($classConstants as $key => $value) {
            if (strpos($key, $praefix) === 0) {
                $constants[$key] = $value;
            }
        }
        
        return $constants;
    }
    
    /**
     * does a recursive copy of the given directory
     * @param string $src Source Directory
     * @param string $dst Destination Directory
     */
    public static function recursiveCopy(string $src, string $dst) {
        $dir = opendir($src);
        error_log($dst);
        if(!file_exists($dst)) {
            @mkdir($dst);
        }
        $SEP = DIRECTORY_SEPARATOR;
        while(false !== ( $file = readdir($dir)) ) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (is_dir($src.$SEP.$file)) {
                self::recursiveCopy($src.$SEP.$file, $dst.$SEP.$file);
            }
            else {
                copy($src.$SEP.$file, $dst.$SEP.$file);
            }
        }
        closedir($dir);
    }
}
