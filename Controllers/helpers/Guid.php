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

/* * #@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */

/**
 * methods around Guids
 *
 * 
 */
class ZfExtended_Controller_Helper_Guid extends Zend_Controller_Action_Helper_Abstract {

    /**
     * creates a guid as defined in the config
     * @param bool addBrackets to add {}-brackets around the GUID, defaults to false
     * @return string $guid 
     */
    public function create($addBrackets = false) {
        $validator = new ZfExtended_Validate_Guid();
        switch (true) {
            //some intallations are using md5 formatted UUIDs
            case $validator->isValid('ca473dc489b0b126b3769cd8921b66b5'): 
                return md5(random_bytes(32));
            //the default GUID format:
            case $validator->isValid('{C1D11C25-45D2-11D0-B0E2-201801180001}'):
            default:
                return $this->guid($addBrackets);
        };
    }
    
    /**
     * creates a GUID (v4)
     * @param bool $addBrackets
     * @return string
     */
    protected function guid($addBrackets = false) {
        $rand = random_bytes(16);
        
        //see https://stackoverflow.com/a/15875555/1749200
        $rand[6] = chr(ord($rand[6]) & 0x0f | 0x40); // set version to 0100
        $rand[8] = chr(ord($rand[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        
        $guid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($rand), 4));
        
        if ($addBrackets){
            return '{' . $guid . '}';
        }
        return $guid;
    }

}
