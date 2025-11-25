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

use MittagQI\ZfExtended\Worker\Queue\EmptyPipeException;
use MittagQI\ZfExtended\Worker\Trigger\Factory as WorkerTriggerFactory;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Zend_Exception;
use Zend_Registry;
use ZfExtended_Debug;
use ZfExtended_Models_Worker;
use ZfExtended_Utils;

class Queue
{
    private Queue\FifoPipe $fifoPipe;

    private array $workerLocking = [];

    /**
     * Processes the worker-queue if the processing is not locked
     * (meaning, a different php-process is not already triggering)
     * @throws Zend_Exception
     */
    public static function processQueueMutexed(bool $asDameon = false): void
    {
        $workerQueue = new self(
            $asDameon,
            ZfExtended_Debug::hasLevel('core', 'Workers')
        );
        $sleep = (int) Zend_Registry::get('config')->runtimeOptions->worker->processSleep;

        if ($workerQueue->lockAcquire()) {
            $workerQueue->process(new ZfExtended_Models_Worker(), $sleep);
            $workerQueue->lockRelease();
        } elseif ($asDameon === false) {
            WorkerTriggerFactory::create()->triggerQueue();
        }
    }

    /**
     * ensures that queue is triggered only once
     */
    private LockInterface|SharedLockInterface $lock;

    public function __construct(
        private readonly bool $asDameon = false,
        private readonly bool $doDebug = false
    ) {
        $factory = new LockFactory(new FlockStore());
        $this->lock = $factory->createLock(ZfExtended_Utils::installationHash());
        $this->fifoPipe = new Queue\FifoPipe();
    }

    /**
     * @return bool returns true if any ready to run workers found
     */
    private function runWorkers(ZfExtended_Models_Worker $workerModel): bool
    {
        $workerModel->wakeupScheduledAndDelayed();
        $workerListQueued = $workerModel->getListQueued();

        if ($this->doDebug) {
            error_log("WORKER QUEUE: wakeupScheduledAndDelayed and found "
                . count($workerListQueued) . ' workers to trigger.');
        }

        $result = false;
        $trigger = WorkerTriggerFactory::create();

        foreach ($workerListQueued as $workerQueue) {
            $id = (string) $workerQueue['id'];
            $result = true;
            $now = time();
            if (array_key_exists($id, $this->workerLocking)) {
                $this->cleanBlockedWorkers($id, $now);

                continue;
            }
            $this->workerLocking[$id] = $now;
            Logger::getInstance()
                ->logRaw('dispatcher run ' . $id . ' ' . $workerQueue['worker'] . ' ' . $workerQueue['taskGuid']);
            $trigger->triggerWorker(
                $id,
                $workerQueue['hash'],
            );
        }

        //clean up duplicate run protection
        $now = time();
        foreach (array_keys($this->workerLocking) as $id) {
            $this->cleanBlockedWorkers($id, $now);
        }

        return $result;
    }

    /**
     * trigger application-wide worker-queue
     */
    public function trigger(): void
    {
        if (! $this->notifyRunning()) {
            WorkerTriggerFactory::create()->triggerQueue();
        }
    }

    private function lockAcquire(): bool
    {
        return $this->lock->acquire();
    }

    /**
     * returns false if no queue is running or could not be notified
     */
    public function notifyRunning(bool $asDaemon = false): bool
    {
        if ($asDaemon) {
            $this->fifoPipe::notifyRunning();
        }

        if ($this->lock->acquire()) {
            $this->lock->release(); //if we got the lock it was not locked before so we remove it

            return false;
        }

        return true;
    }

    private function lockRelease(): void
    {
        $this->lock->release();
    }

    private function process(ZfExtended_Models_Worker $workerModel, int $sleep): void
    {
        $this->fifoPipe->initReader($this->asDameon);

        Logger::getInstance()->logRaw('dispatcher start ' . getmypid());

        $runWorkers = true;
        while (true) {
            if ($runWorkers) {
                //if we started workers, we keep running by assuming that there might come more
                $runWorkers = $this->runWorkers($workerModel);
                if ($runWorkers) {
                    usleep($sleep);
                }
            }

            if ($this->doDebug) {
                error_log('WORKER QUEUE: runWorkers ' . $runWorkers);
            }

            if (! $this->asDameon) {
                if ($runWorkers) {
                    continue;
                }

                Logger::getInstance()->logRaw('dispatcher stop');

                break;
            }

            try {
                //check if more queue calls are requested
                $runWorkers = $this->fifoPipe->checkPipe() || $runWorkers;
            } catch (EmptyPipeException) {
                try {
                    if ($workerModel->rescheduleDelayed() > 0) {
                        continue;
                    }
                } catch (Zend_Exception) {
                    // in case of an error retry to schedule the workers
                    continue;
                }
                if ($this->stopDispatching($workerModel)) {
                    break;
                }
            }
        }

        $this->fifoPipe->close();
    }

    private function cleanBlockedWorkers(int|string $id, mixed $now): void
    {
        if (($this->workerLocking[$id] + 5) <= $now) {
            unset($this->workerLocking[$id]);
        }
    }

    private function stopDispatching(ZfExtended_Models_Worker $workerModel): bool
    {
        //if there are still running workers we keep the loop
        if (! $workerModel->hasRemaininWorkers()) {
            Logger::getInstance()->logRaw('dispatcher stop with empty pipe');

            return true;
        }
        Logger::getInstance()->logRaw('dispatcher waiting blocked mode');
        if (! $this->fifoPipe->waitForPipe()) {
            Logger::getInstance()->logRaw('dispatcher stop after pipe timeout');

            return true;
        }
        Logger::getInstance()->logRaw('dispatcher keep alive');

        return false;
    }
}
