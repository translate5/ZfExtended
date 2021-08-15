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
 * This class provides a garbage cleaning for the worker table.
 * Currently this is invoked by Import Process
 *  
 * Be careful: This class is not used in worker_dependencies !
 */
class ZfExtended_Worker_GarbageCleaner extends ZfExtended_Worker_Abstract {
    /**
     * (non-PHPdoc)
     * @see ZfExtended_Worker_Abstract::validateParameters()
     */
    protected function validateParameters($parameters = array()) {
        return true;
    } 
    
    /**
     * Only one garbage cleaner may run at a time
     * (non-PHPdoc)
     * @see ZfExtended_Worker_Abstract::getMaxParallelProcesses()
     */
    protected function getMaxParallelProcesses() {
        return 1;
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_Worker_Abstract::work()
     */
    public function work() {
        return $this->workerModel->cleanGarbage();
    }
}