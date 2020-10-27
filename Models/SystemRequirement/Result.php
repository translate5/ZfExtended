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
class ZfExtended_Models_SystemRequirement_Result {
    public $id;
    public $name;
    /**
     * Summary and infos shown if the result has had errors or warnings.
     * @var array
     */
    public $badSummary = [];
    public $info = [];
    public $warning = [];
    public $error = [];
    
    /**
     * Returns true if the result has errors
     * @return bool
     */
    public function hasError(): bool {
        return !empty($this->error);
    }
    
    /**
     * Returns true if the result has warnings
     * @return bool
     */
    public function hasWarning(): bool {
        return !empty($this->warning);
    }
    
    /**
     * Returns true if the result has errors
     * @return bool
     */
    public function hasInfo(): bool {
        return !empty($this->info);
    }
}