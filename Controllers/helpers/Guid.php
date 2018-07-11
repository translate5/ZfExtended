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
     * @param boolean addBrackets to add {}-brackets around the GUID, defaults to false
     * @return string $guid 
     */
    public function create($addBrackets = false) {
        $validator = new ZfExtended_Validate_Guid();
        switch (true) {
            //some intallations are using md5 formatted UUIDs
            case $validator->isValid('ca473dc489b0b126b3769cd8921b66b5'): 
                return md5(uniqid(rand()));
            //the default GUID format:
            case $validator->isValid('{C1D11C25-45D2-11D0-B0E2-201801180001}'):
            default:
                return $this->guid($addBrackets);
        };
    }
    
    /**
     * creates a GUID
     * @param boolean $addBrackets
     * @return string
     */
    protected function guid($addBrackets = false) {
        $guid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        if ($addBrackets){
            return '{' . $guid . '}';
        }
        return $guid;
    }

}
