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
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Zend_Exception;
use ZfExtended_Factory;
use ZfExtended_Models_Worker;

class Queue
{
    /**
     * ensures that queue is triggered only once
     */
    private LockInterface|SharedLockInterface $lock;

    public function __construct()
    {
        $factory = new LockFactory(extension_loaded('sysvsem') ? new SemaphoreStore() : new FlockStore());
        $this->lock = $factory->createLock(\ZfExtended_Utils::installationHash());
    }

    /**
     * @throws ReflectionException
     * @throws Zend_Exception
     * @return bool returns true if any ready to run workers found
     */
    public function process(): bool
    {
        $workerModel = ZfExtended_Factory::get(ZfExtended_Models_Worker::class);
        $workerListQueued = $workerModel->getListQueued();

        $result = false;
        $trigger = WorkerTriggerFactory::create();
        foreach ($workerListQueued as $workerQueue) {
            $result = true;
            $trigger->triggerWorker(
                (string) $workerQueue['id'],
                $workerQueue['hash'],
                $workerQueue['worker'],
                $workerQueue['taskGuid']
            );
        }

        return $result;
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

        if ($this->lockAcquire()) {
            WorkerTriggerFactory::create()->triggerQueue();
            $this->lockRelease();
        }
    }

    public function lockAcquire(): bool
    {
        return $this->lock->acquire();
    }

    public function lockRelease(): void
    {
        $this->lock->release();
    }
}
