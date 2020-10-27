<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt
 included in the packaging of this file.  Please review the following information
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3 requirements will be met:
 http://www.gnu.org/licenses/agpl.html
  
 There is a plugin exception available for use with this release of translate5 for
 translate5: Please see http://www.translate5.net/plugin-exception.txt or
 plugin-exception.txt in the root folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execption
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

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
    abstract function validate(): ZfExtended_Models_SystemRequirement_Result;
}