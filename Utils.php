<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

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
     * Get an array of all class-constants-names from class $className which begin with $praefix.
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
    
    
}
