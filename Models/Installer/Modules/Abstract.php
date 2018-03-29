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

/**
 * @package ZfExtended
 * @version 2.0
 */
abstract class ZfExtended_Models_Installer_Modules_Abstract {
    public function __construct(){
        $this->logger = new ZfExtended_Models_Installer_Logger();
    }
    
    public function setOptions($options){
        $this->options = $options;
    }
    
    abstract public function run();
    
    /**
     * returns a list of valid short options for that Module (for getopt)
     * @return string
     */
    public function getShortOptions() {
        return '';
    }
    
    /**
     * returns a list of valid long options for that Module (for getopt)
     * @return array
     */
    public function getLongOptions() {
        return [];
    }
}