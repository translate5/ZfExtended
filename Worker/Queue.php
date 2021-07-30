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

class ZfExtended_Worker_Queue {
    
    public function process() {
        $workerModel = ZfExtended_Factory::get('ZfExtended_Models_Worker');
        /* @var $workerModel ZfExtended_Models_Worker */
        $workerListQueued = $workerModel->getListQueued();
        
        $trigger = ZfExtended_Factory::get('ZfExtended_Worker_TriggerByHttp');
        /* @var $trigger ZfExtended_Worker_TriggerByHttp */
        foreach ($workerListQueued as $workerQueue) {
            $trigger->triggerWorker($workerQueue['id'], $workerQueue['hash']);
        }
    }
    
    /**
     * trigger application-wide worker-queue
     */
    public function trigger() {
        $worker = ZfExtended_Factory::get('ZfExtended_Models_Worker');
        /* @var $worker ZfExtended_Models_Worker */
        $worker = $worker->wakeupScheduled();
        
        $trigger = ZfExtended_Factory::get('ZfExtended_Worker_TriggerByHttp');
        /* @var $trigger ZfExtended_Worker_TriggerByHttp */
        $trigger->triggerQueue();
    }
}