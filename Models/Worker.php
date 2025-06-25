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

use MittagQI\ZfExtended\Worker\Logger;

/**
 * Abstract Worker Class
 *
 * @method void setId(int $id)
 * @method void setParentId(int $id)
 * @method void setState(string $state)
 * @method void setWorker(string $phpClassName)
 * @method void setResource(string $resource)
 * @method void setSlot(string $slotName)
 * @method void setTaskGuid(string|null $taskGuid)
 * @method void setPid(int $pid)
 * @method void setStarttime(string|null $starttime)
 * @method void setEndtime(string|null $endtime)
 * @method void setMaxRuntime(string $maxRuntime)
 * @method void setHash(string $hash)
 * @method void setMaxParallelProcesses(int $maxParallelProcesses)
 * @method void setBlockingType(string $blockingType)
 * @method void setProgress(float $progress)
 * @method void setDelayedUntil(int $until)
 * @method void setDelays(int $numDelays)
 *
 * @method string getId()
 * @method string getParentId()
 * @method string getState()
 * @method string getWorker()
 * @method string getResource()
 * @method string getSlot()
 * @method null|string getTaskGuid()
 * @method integer getPid()
 * @method null|string getStarttime()
 * @method null|string getEndtime()
 * @method string getMaxRuntime()
 * @method string getHash()
 * @method string getMaxParallelProcesses()
 * @method string getBlockingType()
 * @method string getProgress()
 * @method string getDelayedUntil()
 * @method string getDelays()
 *
 * @property ZfExtended_Models_Db_Worker $db
 */
final class ZfExtended_Models_Worker extends ZfExtended_Models_Entity_Abstract
{
    use ZfExtended_Models_Db_DeadLockHandlerTrait;

    /**
     * the worker is added to the worker table, but is not ready to run (for example some other workers are
     * still missing in the worker table) call schedulePrepared to mark the prepared workers of a taskGuid
     * or worker group to be scheduled
     * @var string
     */
    public const STATE_PREPARE = 'prepare';

    /**
     * scheduled workers may be set to waiting by wakeupScheduled but keep the order as defined in worker dependencies
     * @var string
     */
    public const STATE_SCHEDULED = 'scheduled';

    /**
     * waiting workers are ready to run, and may be started (set to running) in parallel,
     * restricted by maxRunProcesses and slot / resource blocking mechanisms
     * @var string
     */
    public const STATE_WAITING = 'waiting';

    /**
     * The worker is waiting for a (external) service to be available again. In this state, the worker-process is
     * set to scheduled by a cron service after the given delay-time.
     * The delay-time is set by the worker itself and after a max number of retries an exception is thrown
     * (leading to an erroneus task)
     * @var string
     */
    public const STATE_DELAYED = 'delayed';

    /**
     * the worker is running
     * @var string
     */
    public const STATE_RUNNING = 'running';

    /**
     * the worker (or a sub worker) crashed
     * @var string
     */
    public const STATE_DEFUNCT = 'defunct';

    /**
     * the worker has successfully finished its work
     * @var string
     */
    public const STATE_DONE = 'done';

    public const WORKER_SERVERID_HEADER = 'X-Translate5-Worker-Serverid';

    /**
     * @var string
     */
    protected $dbInstanceClass = ZfExtended_Models_Db_Worker::class;

    /**
     * Default worker-lifetime (could/should be overwritten by child-class)
     * MySQL INTERVAL as defined in http://dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_date-add
     */
    protected string $maxLifetime = '1 HOUR';

    /**
     * To prevent that the serialized parameters are unserialized multiple
     *  times when calling get we have to cache them.
     */
    protected ?array $parameters = null;

    /**
     * Loads first worker of a specific worker for a specific task
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    public function loadFirstOf(string $worker, string $taskGuid, array $states = []): self
    {
        try {
            $s = $this->db->select()
                ->where('taskGuid = ?', $taskGuid)
                ->where('worker = ?', $worker)
                ->order('id ASC')
                ->limit(1);
            if (count($states) === 1) {
                $s->where('state = ?', $states[0]);
            } elseif (count($states) > 1) {
                $s->where('state IN (?)', $states);
            }
            $row = $this->db->fetchRow($s);
        } catch (Exception $e) {
            $this->notFound('NotFound after other Error', $e);
        }
        if (! $row) {
            $this->notFound(__CLASS__ . '#worker #taskGuid', $worker . ', ' . $taskGuid);
        }
        //load implies loading one Row, so use only the first row
        $this->row = $row;

        return $this;
    }

    /**
     * Load worker rows by given $worker, $state and $taskGuid
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    public function loadByState(string $state, string $worker = null, string $taskGuid = null): array
    {
        $rows = null;

        try {
            $s = $this->db->select()
                ->where('state = ?', $state)
                ->order('id ASC');
            if (! empty($worker)) {
                $s->where('worker = ?', $worker);
            }
            if (! empty($taskGuid)) {
                $s->where('taskGuid = ?', $taskGuid);
            }
            $rows = $this->db->fetchAll($s);
        } catch (Exception $e) {
            $this->notFound('NotFound after other Error', $e);
        }
        if ($rows !== null && $rows->count() > 0) {
            return $rows->toArray();
        }

        return [];
    }

    /***
     * Find the first worker required for context calculation. This is specific method and it is used only
     * for import progress calculation.
     * This will return the oldest worker for given taskGuid
     */
    public function findWorkerContext(string $taskGuid): array|false
    {
        // we search all workers for the task. This may includes finished operations !!
        $s = $this->db->select()
            ->where('state IN (?)', [
                self::STATE_RUNNING,
                self::STATE_PREPARE,
                self::STATE_SCHEDULED,
                self::STATE_DELAYED,
                self::STATE_DONE,
            ])
            ->where('taskGuid = ?', $taskGuid)
            ->order([
                new Zend_Db_Expr('state="' . self::STATE_RUNNING . '" desc'),
                new Zend_Db_Expr('state="' . self::STATE_PREPARE . '" desc'),
                new Zend_Db_Expr('state="' . self::STATE_SCHEDULED . '" desc'),
                new Zend_Db_Expr('state="' . self::STATE_DELAYED . '" desc'),
                new Zend_Db_Expr('state="' . self::STATE_DONE . '" desc'),
            ]);
        // the first worker of the result will represent the one that is currently running or is prepared/waiting
        // since this worker might be in a nested hierarchy (currently the parentId of a worker not neccessarily
        // point to the outer operation worker, we must search upwards for the parent having "0" as parent
        $list = $this->db->fetchAll($s)->toArray();
        if (count($list) > 0) {
            $first = $list[0];
            $parentId = (int) $first['parentId'];
            $topmost = ($parentId === 0) ? $first : $this->findTopmostWorker($list, $parentId);
            // this may happens when workers become defunct ...
            if ($topmost === null) {
                error_log('ERROR: Worker::findWorkerContext("' . $taskGuid .
                    '") can not find the topmost worker:' . print_r($list, true));
            }

            // as a fallback, we return the $first as context
            return ($topmost === null) ? $first : $topmost;
        }

        return false;
    }

    /**
     * Finds the Topmost Worker in a List of workers
     */
    private function findTopmostWorker(array $workerList, int $parentId, int $iterations = 0): ?array
    {
        // we need to limit the iterations, invalid worker-models theoretically could cause deadloops
        if ($iterations < 25) {
            foreach ($workerList as $worker) {
                if ((int) $worker['id'] === $parentId) {
                    return ((int) $worker['parentId'] === 0) ?
                        $worker : $this->findTopmostWorker($workerList, (int) $worker['parentId'], ++$iterations);
                }
            }
        }

        return null;
    }

    /***
     * Load all workers from Zf_worker table for the given taskGuid and given context.
     * The context represents set of workers connected with same parentId.
     * e.g.: on task import, all queued workers are with same parentId (the id of the import worker : editor_Models_Import_Worker)
     */
    public function loadByTaskAndContext(string $taskGuid, int $parentId = 0): array
    {
        $s = $this->db->select()
            ->from($this->db->info($this->db::NAME))
            ->where('taskGuid = ?', $taskGuid);
        if (! empty($parentId)) {
            $s->where('id = ?', $parentId)->orWhere('parentId = ?', $parentId);
        }

        return $this->db->fetchAll($s)->toArray();
    }

    /**
     * Wake up a scheduled worker (set state from scheduled to waiting)
     * or a worker that is delayed and the delay is overdue
     * if there are no other worker waiting or running with the same taskGuid
     */
    public function wakeupScheduledAndDelayed(): void
    {
        // check if there are any worker waiting or running with this taskGuid

        // set the next workers of the given task to waiting
        // if no other worker are running or waiting or scheduled, which
        //      - is from the same taskGuid as the worker which should be started
        //      - AND which is in dependency to the worker to be set to waiting.
        //
        // This way it is achieved, that task-independent the next workers in the queue
        // are started and that task-dependent only workers are started which have
        // no dependency to any other workers in the queue which are not set to "done"

        // SQL Explanation

        // This creates an intermediate Table consisting of the fields id, count, worker and state
        // the idea is to have a list of all running and all scheduled workers that are ordered (running first) and count them up to check if we can start some more up to maxParalellProcesses
        // To properly count the worker-types it must be ordered by worker obviously and assigning the worker to the wworker variable must be done AFTER evaluating the count
        // also, the running workers must be the first workers to be counted for a worker typewhat is achieved by the second ordering
        // the table summarizes all scheduled workers that either have no dependant workers or just have dependant workers in the states defined by bindings 2-5
        // It seems MySQL ignores the collation default settings when creating variables what is really annoying as it is prone to cause problems, so the collation has to be set explicitly for the variable
        // TODO FIXME: Collation behaviour for variables may can be fixed by setting "character_set_connection"
        $stateOrder = (self::STATE_RUNNING < self::STATE_SCHEDULED) ? 'ASC' : 'DESC'; // just for robustness: evaluate the needed ordering to make running workers appear first
        $time = time();
        $intermediateTable =

            "SELECT w.id AS id, @num := if(@wworker = w.worker, @num:= @num + 1, 1) AS count,
                @wworker := w.worker as worker,
                w.maxParallelProcesses AS max,
                w.state AS state,
                w.delayedUntil AS delayedUntil
                FROM Zf_worker w, (SELECT @wworker := _utf8mb4 '' COLLATE utf8mb4_unicode_ci, @num := 0) r
                WHERE w.state = '" . self::STATE_RUNNING . "'
                OR (
                    (w.state = '" . self::STATE_SCHEDULED . "' OR (w.state = '" . self::STATE_DELAYED . "' AND w.delayedUntil < " . $time . "))
                    AND (
                        (NOT EXISTS (SELECT *
                            FROM Zf_worker_dependencies d1
                            WHERE w.worker = d1.worker)
                            )
                        OR
                        NOT EXISTS (SELECT *
                            FROM Zf_worker ws, Zf_worker ws2, Zf_worker_dependencies d2
                            WHERE d2.dependency = ws.worker
                            AND ws2.worker = d2.worker
                            AND ws.taskGuid = ws2.taskGuid
                            AND ws.state IN (
                                '" . self::STATE_WAITING . "',
                                '" . self::STATE_RUNNING . "',
                                '" . self::STATE_SCHEDULED . "',
                                '" . self::STATE_PREPARE . "',
                                '" . self::STATE_DELAYED . "'
                            )
                            AND ws2.id = w.id)
                    )
                )
                ORDER BY w.worker ASC, w.state " . $stateOrder . ", w.id ASC
                FOR UPDATE";

        // we now update the worker state for the evaluated workers hold in the intermediate table but only up to the number of maxParalellWorkers per worker
        $sql = 'UPDATE Zf_worker u, ( ' . $intermediateTable . ' ) s
            SET u.state = \'' . self::STATE_WAITING . '\'
            WHERE u.id = s.id
            AND (s.state = \'' . self::STATE_SCHEDULED . '\' OR (s.state = \'' . self::STATE_DELAYED . '\' AND s.delayedUntil < ' . $time . '))
            AND s.count <= s.max;';

        //it may happen that a worker is not set to waiting if the deadlock was ignored, at least at the next worker queue call it is triggered again
        $this->ignoreOnDeadlock(function () use ($sql) {
            $this->db->getAdapter()->query($sql);
        }, true);
    }

    /**
     * sets the prepared workers of the same workergroup and taskGuid as the current one
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Db_Exceptions_DeadLockHandler
     */
    public function schedulePrepared(): void
    {
        // check if there are any worker waiting or running with this taskGuid
        $taskGuid = $this->getTaskGuid();
        $parentId = $this->getParentId();
        if (empty($parentId)) {
            $parentId = $this->getId();
        }
        $bindings = [$parentId, $parentId];
        $taskClause = '';
        if (! empty($taskGuid)) {
            $taskClause = ' AND taskGuid = ?';
            $bindings[] = $taskGuid;
        }
        // schedule any prepared workers for the parent-id
        // or any delayed workers that can be re-scheduled
        $sql =
            'UPDATE `Zf_worker` SET `state` = \'' . self::STATE_SCHEDULED . '\''
            . ' WHERE ('
            . '`state` = \'' . self::STATE_PREPARE . '\''
            . ' AND (`id` = ? OR `parentId` = ?)'
            . $taskClause
            . ') OR ('
            . '`state` = \'' . self::STATE_DELAYED . '\''
            . ' AND `delayedUntil` < ' . time()
            . ')';

        $result = $this->retryOnDeadlock(function () use ($sql, $bindings) {
            return $this->db->getAdapter()->query($sql, $bindings);
        }, true);
        Logger::getInstance()->logRaw('schedulePrepared ' . $result->rowCount() . ' workers; task: ' . $taskGuid);
    }

    public function scheduleDanglingPreparedChildren(int $parentWorkerId): void
    {
        // schedule any dangling prepared child workers for a parent-id
        // where the parent is not in prepare anymore
        $sql =
            'UPDATE `Zf_worker` as children, `Zf_worker` as parent
             SET children.`state` = ?
             WHERE children.`parentId` = parent.`id`
               AND  children.`state` = ?
               AND parent.id = ?
               AND parent.state != ?';

        $result = $this->retryOnDeadlock(function () use ($sql, $parentWorkerId) {
            return $this->db->getAdapter()->query($sql, [
                self::STATE_SCHEDULED, //set children
                self::STATE_PREPARE,   // children are in state
                $parentWorkerId,       //children of
                self::STATE_PREPARE,   // parent is not in state
            ]);
        }, true);

        $rowCount = $result->rowCount();

        if ($rowCount > 0) {
            Logger::getInstance()->logRaw(
                'scheduleDanglingPrepared ' . $rowCount . ' workers; parentId: ' . $parentWorkerId
            );
        }
    }

    /**
     * Reschedules any delayed Workers where the delay is exceeded
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Db_Exceptions_DeadLockHandler
     */
    public function rescheduleDelayed(): void
    {
        $sql =
            'UPDATE `Zf_worker` SET `state` = \'' . self::STATE_SCHEDULED . '\''
            . ' WHERE `state` = \'' . self::STATE_DELAYED . '\' '
            . ' AND `delayedUntil` < ' . time();
        $stmt = $this->retryOnDeadlock(function () use ($sql) {
            return $this->db->getAdapter()->query($sql);
        }, true);
        /* @var Zend_Db_Statement_Interface $stmt */
        if (ZfExtended_Debug::hasLevel('core', 'Workers') && $stmt->rowCount() > 0) {
            error_log("WORKER RESCHEDULE: Rescheduled " . $stmt->rowCount() . ' delayed workers.');
        }
    }

    /**
     * Try to set worker into mutex-save mode
     * @return bool true if workerModel is set to mutex-save
     */
    public function isMutexAccess(): bool
    {
        // workerModel can not be set to mutex if it is new
        if (! $this->getId() || ! $this->getHash() || $this->getState() != self::STATE_WAITING) {
            return false;
        }
        $data = [
            'hash' => bin2hex(random_bytes(32)),
        ];

        $whereStatements = [];
        $whereStatements[] = 'id = "' . $this->getId() . '"';
        $whereStatements[] = 'hash = "' . $this->getHash() . '"';
        $whereStatements[] = 'state = "' . self::STATE_WAITING . '"';

        $countRows = $this->retryOnDeadlock(function () use ($data, $whereStatements) {
            return $this->db->update($data, $whereStatements);
        }, true);

        // workerModel can not be set to mutex because no entry with same id and hash can be found in database
        // nothing to log since this can happen often
        return $countRows > 0;
    }

    /**
     * Sets this workers state to running - if possible
     * If it is a direct run (empty ID) the worker will be started always,
     *  regardless of already existing workers with the same taskGuid
     *
     * @return boolean true if task was set to running
     * @throws Zend_Db_Exception
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Db_Exceptions_DeadLockHandler
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     */
    public function setRunning(bool $oncePerTaskGuid = true): bool
    {
        $data = [
            'state' => self::STATE_RUNNING,
            'starttime' => new Zend_Db_Expr('NOW()'),
            'maxRuntime' => new Zend_Db_Expr('NOW() + INTERVAL ' . $this->getMaxLifetime()),
            'pid' => getmypid(),
        ];
        // in case of a delayed worker we need to keep the startime as it was initially set
        // delayed workers shall represent their full lifetime incl. delays and this is needed for calculations
        if ($this->getState() === self::STATE_DELAYED && ! empty($this->getStarttime())) {
            unset($data['starttime']);
        }
        $id = $this->getId();
        //if there is no id, that means we are in a direct run and the worker should be started in any case
        if (empty($id)) {
            foreach ($data as $k => $v) {
                $this->set($k, $v);
            }
            $this->save();

            return true;
        }
        $sets = function ($prefix) use ($data) {
            foreach ($data as $k => $v) {
                $this->set($k, $v);
                $sets[] = $prefix . '`' . $k . '` = ' . $this->db->getAdapter()->quote($v);
            }

            return ' SET ' . join(', ', $sets);
        };
        if ($oncePerTaskGuid) {
            $sql = 'UPDATE `Zf_worker` w1 LEFT OUTER JOIN `Zf_worker` w2';
            $sql .= ' ON w1.`taskGuid` = w2.`taskGuid` AND w1.`worker` = w2.`worker` AND w2.`state` = "' . self::STATE_RUNNING . '" AND w1.`id` != w2.`id`';
            $sql .= $sets('`w1`.');
            $sql .= ' WHERE w2.id IS NULL AND w1.id = ? AND w1.state = "' . self::STATE_WAITING . '"';
        } else {
            $sql = 'UPDATE `Zf_worker`';
            $sql .= $sets('');
            $sql .= ' WHERE id = ? AND state = "' . self::STATE_WAITING . '"';
        }
        $values = [$this->getId()];
        $stmt = $this->retryOnDeadlock(function () use ($sql, $values) {
            return $this->db->getAdapter()->query($sql, $values);
        }, true);
        $result = $stmt->rowCount();

        return $result > 0;
    }

    /**
     * returns a list of waiting workers to trigger (optional of a given taskguid)
     * @return array: list of queued entries in table Zf_worker which are "ready to run"
     */
    public function getListQueued(string $taskGuid = null): array
    {
        // blocking by taskGuid (means only one worker of same type per taskGuid) is not possible here with less effort
        // so we move this check into the worker startup

        $toQueue = [];
        $db = $this->db;
        $db->getAdapter()->beginTransaction();
        $waiting = $this->getListWaiting($taskGuid);
        $running = $this->getListRunning($taskGuid);
        $db->getAdapter()->commit();

        $blockedSlots = []; // list of blocked slots

        foreach ($running as $worker) {
            // stop triggering any workers if one running worker is of blocking-type 'GLOBAL'
            if ($worker['blockingType'] == ZfExtended_Worker_Abstract::BLOCK_GLOBAL) {
                return [];
            }
            // HINT/QUIRK: This might be questionable: If two different workers use the same slot, this will make them unidentical.
            // Currently this cannot be fixed as we use "default" as the default string. May we switch to "resource" if "slot" is "default" and otherwise "slot" ?
            $key = $worker['resource'] . '-' . $worker['slot'];
            if (! array_key_exists($key, $blockedSlots)) {
                $blockedSlots[$key] = 0;
            }
            $blockedSlots[$key]++;
        }

        foreach ($waiting as $worker) {
            // check if blocking-type is 'SLOT' and number of parallel processes for this resource/slot is not reached
            $key = $worker['resource'] . '-' . $worker['slot'];
            if (! array_key_exists($key, $blockedSlots)) {
                $blockedSlots[$key] = 0;
            }
            if ($worker['blockingType'] !== ZfExtended_Worker_Abstract::BLOCK_SLOT || $worker['maxParallelProcesses'] > $blockedSlots[$key]) {
                $toQueue[] = $worker;
                $blockedSlots[$key]++;
            }
        }

        // if(count($toQueue)) { error_log('WORKERS TO QUEUE: '.json_encode(array_combine(array_column($toQueue, 'id'), array_column($toQueue, 'worker')))); }
        return $toQueue;
    }

    private function getListWaiting($taskGuid): array
    {
        $sql = $this->db->select()->where('state = ?', self::STATE_WAITING)->order('id ASC');

        if ($taskGuid) {
            $sql->where('taskGuid = ?', $taskGuid);
        }

        return $this->db->fetchAll($sql)->toArray();
    }

    private function getListRunning(string $taskGuid = null): array
    {
        $db = $this->db;
        $sql = $db->select()
        //->columns(array('resource', 'slot')) // this does not work :-((((
            ->from($db->info($db::NAME), ['resource', 'slot', 'blockingType', 'worker', 'taskGuid'])
            ->where('state = ?', self::STATE_RUNNING)
            ->order('resource ASC')->order('slot ASC');

        if ($taskGuid) {
            $sql->where('taskGuid = ?', $taskGuid);
        }

        return $db->fetchAll($sql)->toArray();
    }

    /**
     * Get a counted list of all "hot" slots (all states (waiting, running))
     * for the given resource $resourceName
     *
     * @return array: list of array(slot, count) for the given resource
     */
    public function getListSlotsCount(string $resourceName = '', array $validSlots = null): array
    {
        $db = $this->db;
        $sql = $db->select()
            //->columns(array('resource', 'slot')) // this does not work :-((((
            ->from($db->info($db::NAME), ['slot', 'COUNT(*) AS count'])
            ->where('resource = ?', $resourceName)
            ->where('worker = ?', $this->getWorker())
            ->where('state IN (?)', [self::STATE_WAITING, self::STATE_RUNNING, self::STATE_DELAYED])
            ->group(['resource', 'slot'])
            ->order('count ASC');

        $rows = $db->fetchAll($sql)->toArray();

        if (is_array($validSlots)) {
            $validatedRows = [];
            foreach ($rows as $row) {
                if (in_array($row['slot'], $validSlots)) {
                    $validatedRows[] = $row;
                }
            }

            return $validatedRows;
        }

        return $rows;
    }

    /**
     * Clean the worker table by given state list and remove the matching worker entries immediatelly
     */
    public function clean(array $states): void
    {
        $this->reduceDeadlocks();
        $this->db->delete([
            'state in (?)' => $states,
        ]);
    }

    /**
     * Removes all workers for the given task
     */
    public function cleanForTask(string $taskGuid): void
    {
        $this->db->delete($this->db->getAdapter()->quoteInto('taskGuid = ?', $taskGuid));
    }

    /**
     * returns a summary of how many workers are in DB, grouped by state
     */
    public function getSummary(array $groupBy = ['state']): array
    {
        $s = $this->db->select()
            ->from($this->db, array_merge([
                'cnt' => 'count(*)',
            ], $groupBy))
            ->group($groupBy);
        $res = $this->db->fetchAll($s);

        if (count($groupBy) > 1) {
            return $res->toArray();
        }
        $result = [
            self::STATE_SCHEDULED => 0,
            self::STATE_WAITING => 0,
            self::STATE_RUNNING => 0,
            self::STATE_DELAYED => 0,
            self::STATE_PREPARE => 0,
            self::STATE_DEFUNCT => 0,
            self::STATE_DONE => 0,
        ];
        foreach ($res as $row) {
            $result[$row[$groupBy[0]]] = (int) $row['cnt'];
        }

        return $result;
    }

    public function hasRemaininWorkers(): bool
    {
        $s = $this->db->select()
            ->from($this->db, [
                'cnt' => 'count(*)',
            ])
            ->where('state IN (?)', [
                self::STATE_SCHEDULED,
                self::STATE_WAITING,
                self::STATE_RUNNING,
                self::STATE_DELAYED,
            ]);
        $row = $this->db->fetchRow($s);

        return $row['cnt'] > 0;
    }

    /**
     * Retrieves a unique list of all workers not in the state "done"
     */
    public function getRemainingWorkerInfo(): array
    {
        $states = [
            self::STATE_SCHEDULED,
            self::STATE_WAITING,
            self::STATE_RUNNING,
            self::STATE_DELAYED,
            self::STATE_DEFUNCT,
        ];
        $select = $this->db->select()->where('state IN (?)', $states);
        $workers = [];
        foreach ($this->db->fetchAll($select)->toArray() as $row) {
            $workers[] = $row['worker'] . ' (' . $row['state'] . ') ' . ' (' . $row['taskGuid'] . ')';
        }

        return array_unique($workers);
    }

    /**
     * Retrieves infos about operations for a task
     * @return array<int, array{ id: int, starttime: int, state: 'defunct'|'done'|'waiting' }>
     */
    public function getOperationInfo(string $taskGuid, string $startWorker, int $startWorkerId = -1): array
    {
        $operations = [];
        $select = $this->db->select()
            ->where('taskGuid = ?', $taskGuid)
            ->where('worker = ?', $startWorker);
        if ($startWorkerId > 0) {
            $select->where('id = ?', $startWorkerId);
        }
        foreach ($this->db->fetchAll($select)->toArray() as $row) {
            $operation = [];
            $operation['id'] = (int) $row['id'];
            $operation['starttime'] = empty($row['starttime']) ? time() : strtotime($row['starttime']);
            if ($row['state'] === self::STATE_DEFUNCT) {
                $operation['state'] = self::STATE_DEFUNCT;
            } elseif ($row['state'] !== self::STATE_DONE) {
                $operation['state'] = self::STATE_WAITING;
            } else {
                $operation['state'] = self::STATE_DONE;
                $select = $this->db->select()
                    ->where('taskGuid = ?', $taskGuid)
                    ->where('parentId = ?', $row['id']);
                foreach ($this->db->fetchAll($select)->toArray() as $childRow) {
                    if ($childRow['state'] === self::STATE_DEFUNCT) {
                        $operation['state'] = self::STATE_DEFUNCT;
                    } elseif ($childRow['state'] !== self::STATE_DONE && $operation['state'] !== self::STATE_DEFUNCT) {
                        $operation['state'] = self::STATE_WAITING;
                    }
                }
            }
            $operations[] = $operation;
        }

        return $operations;
    }

    /**
     * returns a summary of the workers states of the current workers group.
     *  grouped by state and worker
     *  Worker group means: same taskGuid, same parent worker.
     */
    public function getParentSummary(): array
    {
        $res = $this->db->getAdapter()->query('SELECT w.state, w.worker, count(w.worker) cnt
            FROM Zf_worker w, Zf_worker me, Zf_worker_dependencies d
            WHERE
            me.id = ?
            AND (me.parentId = 0 AND w.parentId = me.id OR me.parentId != 0 AND (w.parentId = me.parentId or w.id = me.parentId))
            AND d.worker = me.worker
            AND d.dependency = w.worker
            AND w.taskGuid = ?
            AND me.taskGuid = ?
            GROUP BY w.worker, w.state', [$this->getId(), $this->getTaskGuid(), $this->getTaskGuid()]);

        return $res->fetchAll(Zend_Db::FETCH_OBJ);
    }

    /**
     * Sets all remaining scheduled and waiting workers of that worker group
     * (same parent (or the parent itself) and same taskGuid) to defunct
     * @param array $exludedWorkers contains the worker classes which should be ignored in setting them to defunct
     * @param bool $includeRunning includes also the running state to be defunct
     * @param bool $taskguidOnly TODO move taskGuid out of ZfExtended!
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Db_Exceptions_DeadLockHandler
     */
    public function defuncRemainingOfGroup(
        array $exludedWorkers = [],
        bool $includeRunning = false,
        bool $taskguidOnly = false,
    ): void {
        $this->retryOnDeadlock(function () use ($exludedWorkers, $includeRunning, $taskguidOnly) {
            $byGroup = $workerExclude = '';

            if (is_null($this->getTaskGuid())) {
                $params = [];
                $taskGuidSql = 'taskGuid is null';
            } else {
                $params = [$this->getTaskGuid()];
                $taskGuidSql = 'taskGuid = ?';
            }

            if (! empty($exludedWorkers)) {
                //adapter->query can not handle arrays directlym so use plain string concat
                $workerExclude = $this->db->getAdapter()->quoteInto(' AND worker NOT IN (?)', $exludedWorkers);
            }

            $this->reduceDeadlocks();
            $affectedStates = [self::STATE_WAITING, self::STATE_SCHEDULED, self::STATE_PREPARE, self::STATE_DELAYED];
            if ($includeRunning) {
                $affectedStates[] = self::STATE_RUNNING;
            }
            if (! $taskguidOnly) {
                $byGroup = ' AND ((parentId = 0 AND id IN (?,?)) OR (parentId != 0 AND parentId = ?))';
                $params[] = $this->getId();
                $params[] = $this->getParentId();
                $params[] = $this->getParentId();
            }
            $stateInclude = $this->db->getAdapter()->quoteInto(' AND state IN (?)', $affectedStates);
            $this->db->getAdapter()->query(
                'UPDATE Zf_worker SET state = \'' . self::STATE_DEFUNCT . '\' ' .
                'WHERE ' . $taskGuidSql . $byGroup . $stateInclude . $workerExclude,
                $params
            );
        });
    }

    /**
     * Removes all other workers of the same type & task, that are not running, defunct or done.
     * This is useful for finish parallel processing on the same workload.
     * We delete them to have a proper progress-calculation
     */
    public function removeOtherMultiWorkers(): void
    {
        // set unfinished workers to done
        $sql =
            'DELETE FROM `Zf_worker` ' .
            'WHERE `taskGuid` = ? ' .
            'AND `worker` = ? ' .
            'AND `id` != ? ' .
            'AND `state` NOT IN (' .
                '"' . self::STATE_DONE . '",' .
                '"' . self::STATE_DEFUNCT . '",' .
                '"' . self::STATE_RUNNING . '"' .
            ')';
        $bindings = [$this->getTaskGuid(), $this->getWorker(), $this->getId()];
        $this->retryOnDeadlock(function () use ($sql, $bindings) {
            return $this->db->getAdapter()->query($sql, $bindings);
        }, true);
    }

    /**
     * Check if export is running for given task and given export class
     */
    public function isExportRunning(string $taskGuid, string $exportClass): bool
    {
        $s = $this->db->select()
            ->where('state IN(?)', [
                self::STATE_RUNNING,
                self::STATE_WAITING,
                self::STATE_SCHEDULED,
                self::STATE_DELAYED,
                self::STATE_PREPARE,
            ])
            ->where('taskGuid = ?', $taskGuid)
            ->where('worker = ?', $exportClass);
        $result = $this->db->fetchAll($s)->toArray();

        return empty($result) === false;
    }

    public function getMaxLifetime(): string
    {
        return $this->maxLifetime;
    }

    /**
     * sets the serialized parameters of the worker
     * TODO FIXME: we should use JSON as DB-format
     */
    public function setParameters(array $parameters): void
    {
        $this->set('parameters', serialize($parameters));
    }

    /**
     * returns the unserialized parameters of the worker
     * stores the unserialized values internally to prevent multiple unserialization (and multiple __wakeup calls)
     */
    public function getParameters(): array
    {
        if (is_null($this->parameters)) {
            $unserialized = unserialize($this->get('parameters'));
            $this->parameters = empty($unserialized) ? [] : (array) $unserialized;
        }

        return $this->parameters;
    }

    /**
     * Retrieves the serialized parameters-string fromm the DB
     */
    public function getDbParameters(): ?string
    {
        return $this->get('parameters');
    }

    /**
     * Update the worker model progress field with given $progress value.
     * This will trigger updateProgress event on each call.
     * @throws ZfExtended_Models_Db_Exceptions_DeadLockHandler
     */
    public function updateProgress(float $progress = 1): bool
    {
        $id = $this->getId();
        $isUpdated = $this->retryOnDeadlock(function () use ($progress, $id) {
            return $this->db->update([
                'progress' => $progress,
            ], [
                'id = ?' => $id,
            ]) > 0;
        }, true);

        return $isUpdated;
    }

    public function isRunning(): bool
    {
        return $this->getState() === self::STATE_RUNNING;
    }

    public function isDefunct(): bool
    {
        return $this->getState() === self::STATE_DEFUNCT;
    }

    public function isDone(): bool
    {
        return $this->getState() === self::STATE_DONE;
    }

    /**
     * Searches a worker that was not yet running with the given params
     * These params must exist in the worker model and are expected to identify the worker uniquely
     * UGLY FIXME: parameters is a serialized object, work with JSON instead. the binary structure is like
     * a:1:{s:13:"operationType";s:13:"matchanalysis";}
     * a:1:{s:8:"taskGuid";s:38:"{b8e6ac2a-2e08-4754-9a69-8d88c85cc5e0}";}
     * a:1:{s:14:"pretranslateMt";b:1;s:21:"pretranslateMatchrate";i:100;}
     * @throws ZfExtended_Exception
     */
    public function isDuplicateByParams(array $params, string $taskGuid = null): bool
    {
        if (empty($params)) {
            throw new ZfExtended_Exception('Worker:isDuplicateByParam: At least one param must be given.');
        }
        $select = $this->db->select();
        $select
            ->where('state IN (?)', [self::STATE_PREPARE, self::STATE_WAITING, self::STATE_SCHEDULED])
            ->where('worker = ?', $this->getWorker());
        if (! empty($taskGuid)) {
            $select->where('taskGuid = ?', $taskGuid);
        }
        foreach ($params as $name => $value) {
            $like = '%' . serialize($name) . serialize($value) . '%';
            $select->where('parameters LIKE ?', $like);
        }

        return $this->db->fetchRow($select) !== null;
    }

    /**
     * Returns this worker as string: Worker, id, state.
     */
    public function __toString(): string
    {
        return sprintf('%s (id: %s, state: %s, slot: %s)', $this->getWorker(), $this->getId(), $this->getState(), $this->getSlot());
    }
}
