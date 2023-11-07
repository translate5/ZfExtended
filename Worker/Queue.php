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
declare(strict_types=1);

namespace MittagQI\ZfExtended\Worker;

use MittagQI\ZfExtended\Worker\Trigger\Factory as WorkerTriggerFactory;
use ReflectionException;
use Zend_Exception;
use ZfExtended_Factory;
use ZfExtended_Models_Worker;

class Queue
{

    /**
     * @throws ReflectionException
     * @throws Zend_Exception
     */
    public function process(): void
    {
        $workerModel = ZfExtended_Factory::get(ZfExtended_Models_Worker::class);
        $workerListQueued = $workerModel->getListQueued();

        $trigger = WorkerTriggerFactory::create();
        foreach ($workerListQueued as $workerQueue) {
            $trigger->triggerWorker(
                (string)$workerQueue['id'],
                $workerQueue['hash'],
                $workerQueue['worker'],
                $workerQueue['taskGuid']
            );
        }
    }

    /**
     * trigger application-wide worker-queue
     * @throws ReflectionException
     * @throws Zend_Exception
     */
    public function trigger(): void
    {
        $worker = ZfExtended_Factory::get(ZfExtended_Models_Worker::class);
        $worker->wakeupScheduled();

        WorkerTriggerFactory::create()->triggerQueue();
    }
}
