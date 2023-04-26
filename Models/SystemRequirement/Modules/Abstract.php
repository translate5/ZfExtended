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

/**#@+
 * @author Marc Mittag
 * @package portal
 * @version 2.0
 *
 */
/**
 */
abstract class ZfExtended_Models_SystemRequirement_Modules_Abstract {
    
    /**
     * Flag if the check must be called in installation before Dependency pull
     * @var boolean
     */
    protected $installationBootstrap = false;
    
    /**
     * @var ZfExtended_Models_SystemRequirement_Result
     */
    protected $result;
    
    public function __construct() {
        $this->result = new ZfExtended_Models_SystemRequirement_Result();
    }
    
    /**
     * returns true if the check should also run on installation bootstrap
     * @return boolean
     */
    public function isInstallationBootstrap() {
        return $this->installationBootstrap;
    }
    
    /**
     * Runs the module validation and returns the results
     * @return ZfExtended_Models_SystemRequirement_Result
     */
    abstract public function validate(): ZfExtended_Models_SystemRequirement_Result;
}