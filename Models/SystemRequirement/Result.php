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

    /**
     * Returns true if the result has a summary hinting how to fix the problems
     * @return bool
     */
    public function hasBadSummary(): bool {
        return !empty($this->badSummary);
    }
}