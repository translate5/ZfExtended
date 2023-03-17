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
 * Abstract Worker Class
 *
 * @method void setId() setId(int $id)
 * @method void setParentId() setParentId(int $id)
 * @method void setState() setState(string $state)
 * @method void setWorker() setWorker(string $phpClassName)
 * @method void setResource() setResource(string $resource)
 * @method void setSlot() setSlot(string $slotName)
 * @method void setTaskGuid() setTaskGuid(string $taskGuid)
 * @method void setPid() setPid(int $pid)
 * @method void setStarttime() setStarttime(string $starttime)
 * @method void setMaxRuntime() setMaxRuntime(string $maxRuntime)
 * @method void setHash() setHash(string $hash)
 * @method void setMaxParallelProcesses() setMaxParallelProcesses(int $maxParallelProcesses)
 * @method void setBlockingType() setBlockingType(string $blockingType)
 * @method void setProgress() setProgress(float $progress)
 *
 * @method integer getId()
 * @method integer getParentId() getParentId()
 * @method string getState()
 * @method string getWorker()
 * @method string getResource()
 * @method string getSlot()
 * @method string getTaskGuid()
 * @method integer getPid()
 * @method string getStarttime()
 * @method string getMaxRuntime()
 * @method string getHash()
 * @method integer getMaxParallelProcesses()
 * @method string getBlockingType()
 * @method float getProgress()
 *
 */
class ZfExtended_Models_Worker extends ZfExtended_Models_Entity_Abstract {
    use ZfExtended_Models_Db_DeadLockHandlerTrait;
    
    /**
     * This constant values define the different worker-states
     * @var string
     */
    const STATE_PREPARE     = 'prepare';    // the worker is added to the worker table, but is not ready to run
    // (for example some other workers are still missing in the worker table)
    // call schedulePrepared to mark the prepared workers of a taskGuid or worker group to be scheduled
    const STATE_SCHEDULED   = 'scheduled';  // scheduled workers may be set to waiting by wakeupScheduled but keep the order as defined in worker dependencies
    const STATE_WAITING     = 'waiting';    // waiting workers are ready to run, and may be started (set to running) in parallel, restricted by maxRunProcesses and slot / resource blocking mechanisms
    const STATE_RUNNING     = 'running';    // the worker is running
    const STATE_DEFUNCT     = 'defunct';    // the worker (or a sub worker) crashed
    const STATE_DONE        = 'done';       // the worker has successfully finished its work
    
    const WORKER_SERVERID_HEADER = 'X-Translate5-Worker-Serverid';
    
    /**
     * @var ZfExtended_Models_Db_Worker
     */
    public $db;
    
    /**
     * @var string
     */
    protected $dbInstanceClass = 'ZfExtended_Models_Db_Worker';
    
    /**
     * Default worker-lifetime (could/should be overwritten by child-class)
     *
     * @var string
     *      MySQL INTERVAL as defined in http://dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_date-add
     */
    protected $maxLifetime = '1 HOUR';
    
    /**
     * To prevent that the serialized parameters are unserialized multiple
     *  times when calling get we have to cache them.
     * @var mixed
     */
    protected $parameters = null;

    /**
     * Loads first worker of a specific worker for a specific task
     * @param string $worker
     * @param string $taskGuid
     * @return ZfExtended_Models_Worker
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    public function loadFirstOf(string $worker, string $taskGuid): self {
        try {
            $s = $this->db->select()
            ->where('taskGuid = ?', $taskGuid)
            ->where('worker = ?', $worker)
            ->order('id ASC')
            ->limit(1);
            $row = $this->db->fetchRow($s);
        } catch (Exception $e) {
            $this->notFound('NotFound after other Error', $e);
        }
        if (!$row) {
            $this->notFound(__CLASS__ . '#worker #taskGuid', $worker.', '.$taskGuid);
        }
        //load implies loading one Row, so use only the first row
        $this->row = $row;
        return $this;
    }
    
    /***
     * Load worker rows by given $worker, $state and $taskGuid
     *
     * @param string $state
     * @param string $worker optional
     * @param string $taskGuid optional
     * @return array
     */
    public function loadByState(string $state, string $worker = null, string $taskGuid = null): array {
        try{
            $s = $this->db->select()
            ->where('state = ?', $state)
            ->order('id ASC');
            if(!empty($worker)) {
                $s->where('worker = ?', $worker);
            }
            if(!empty($taskGuid)) {
                $s->where('taskGuid = ?', $taskGuid);
            }
            $rows = $this->db->fetchAll($s);
        } catch (Exception $e) {
            $this->notFound('NotFound after other Error', $e);
            return [];
        }
        if($rows->count() > 0){
            return $rows->toArray();
        }
        return [];
    }

    /***
     * Find the first worker required for context calculation. This is specific method and it is used only
     * for import progress calculation.
     * This will return the oldest worker for given taskGuid with state running.
     * If no worker with state running is found return worker with state prepare,scheduled or done
     * @param string $taskGuid
     * @return array
     */
    public function findWorkerContext(string $taskGuid) {
            $s=$this->db->select()
            ->where('state IN (?)',[self::STATE_RUNNING,self::STATE_PREPARE,self::STATE_SCHEDULED,self::STATE_DONE])
            ->where('taskGuid = ?',$taskGuid)
            ->order([
                new Zend_Db_Expr('state="'.self::STATE_RUNNING.'" desc'),
                new Zend_Db_Expr('state="'.self::STATE_PREPARE.'" desc'),
                new Zend_Db_Expr('state="'.self::STATE_SCHEDULED.'" desc'),
                new Zend_Db_Expr('state="'.self::STATE_DONE.'" desc')
            ])->limit(1);
        $result = $this->db->fetchAll($s)->toArray();
        return reset($result);
    }
    
    /***
     * Load all workers from Zf_worker table for the given taskGuid and given context.
     * The context represents set of workers connected with same parentId.
     * ex: on task import, all queued workers are with same parentId (the id of the import worker : editor_Models_Import_Worker)
     * @param string $taskGuid
     * @param int $parrentId
     * @return array
     */
    public function loadByTaskAndContext(string $taskGuid,int $parentId = 0) : array{
        $s = $this->db->select()
        ->from($this->db->info($this->db::NAME))
        ->where('taskGuid = ?',$taskGuid);
        if(!empty($parentId)){
            $s->where('id = ?',$parentId)->orWhere('parentId = ?',$parentId);
        }
        return $this->db->fetchAll($s)->toArray();
    }
    
    /**
     * Wake up a scheduled worker (set state from scheduled to waiting)
     * if there are no other worker waiting or running with the same taskGuid
     */
    public function wakeupScheduled() {
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
        $intermediateTable =
        
                   "SELECT w.id AS id, @num := if(@wworker = w.worker, @num:= @num + 1, 1) AS count, @wworker := w.worker as worker, w.maxParallelProcesses AS max, w.state AS state
                    FROM Zf_worker w, (SELECT @wworker := _utf8mb4 '' COLLATE utf8mb4_unicode_ci, @num := 0) r
                    WHERE w.state = ? /* BINDING 0 */
                    OR (
                        w.state = ? /* BINDING 1 */
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
                                AND ws.state IN (?, ?, ?, ?) /* BINDING 2 - 5 */
                                AND ws2.id = w.id)
                        )
                    )
                    ORDER BY w.worker ASC, w.state ".$stateOrder.", w.id ASC
                    FOR UPDATE";
        
        // we now update the worker state for the evaluated workers hold in the intermediate table but only up to the number of maxParalellWorkers per worker
        $sql = 'UPDATE Zf_worker u, ( '.$intermediateTable.' ) s
                SET u.state = ? /* BINDING 6 */
                WHERE u.id = s.id
                AND s.state = ? /* BINDING 7 */
                AND s.count <= s.max;';
        
        $bindings = [self::STATE_RUNNING, self::STATE_SCHEDULED, self::STATE_WAITING, self::STATE_RUNNING, self::STATE_SCHEDULED, self::STATE_PREPARE, self::STATE_WAITING, self::STATE_SCHEDULED];
        
        //it may happen that a worker is not set to waiting if the deadlock was ignored, at least at the next worker queue call it is triggered again
        $this->ignoreOnDeadlock(function() use ($sql, $bindings){
            $this->db->reduceDeadlocks();
            $this->db->getAdapter()->query($sql, $bindings);
        });
    }
    
    /**
     * sets the prepared workers of the same workergroup and taskGuid as the current one
     */
    public function schedulePrepared() {
        // check if there are any worker waiting or running with this taskGuid
        $taskGuid = $this->getTaskGuid();
        $parentId = $this->getParentId();
        if(empty($parentId)) {
            $parentId = $this->getId();
        }
        $bindings = [self::STATE_SCHEDULED, self::STATE_PREPARE, $parentId, $parentId];
        $sql = 'UPDATE `Zf_worker` SET `state` = ? ';
        $sql .= 'WHERE `state` = ? ';
        $sql .= 'AND (`id` = ? OR `parentId` = ?) ';
        if(!empty($taskGuid)) {
            $sql .= 'AND taskGuid = ? ';
            $bindings[] = $taskGuid;
        }

        $this->retryOnDeadlock(function() use ($sql, $bindings){
            $this->db->reduceDeadlocks();
            return $this->db->getAdapter()->query($sql, $bindings);
        });
    }
    
    /**
     * Try to set worker into mutex-save mode
     *
     * @return boolean true if workerModel is set to mutex-save
     */
    public function isMutexAccess() {
        // workerModel can not be set to mutex if it is new
        if (!$this->getId() || !$this->getHash() || $this->getState() != self::STATE_WAITING) {
            return false;
        }
        $data = array('hash' => bin2hex(random_bytes(32)));
        
        $whereStatements = array();
        $whereStatements[] = 'id = "'.$this->getId().'"';
        $whereStatements[] = 'hash = "'.$this->getHash().'"';
        $whereStatements[] = 'state = "'.self::STATE_WAITING.'"';

        $countRows = $this->retryOnDeadlock(function() use ($data, $whereStatements){
            $this->db->reduceDeadlocks();
            return $this->db->update($data, $whereStatements);
        });
            
        // workerModel can not be set to mutex because no entry with same id and hash can be found in database
        // nothing to log since this can happen often
        return $countRows > 0;
    }
    
    /**
     * Sets this workers state to running - if possible
     * If it is a direct run (empty ID) the worker will be started always,
     *  regardless of already existing workers with the same taskGuid
     *
     * @var boolean $oncePerTaskGuid default true
     * @return boolean true if task was set to running
     */
    public function setRunning($oncePerTaskGuid = true) {
        $data = [
            'state' => self::STATE_RUNNING,
            'starttime' => new Zend_Db_Expr('NOW()'),
            'maxRuntime' => new Zend_Db_Expr('NOW() + INTERVAL '.$this->getMaxLifetime()),
            'pid' => getmypid(),
        ];
        
        $id = $this->getId();
        //if there is no id, that means we are in a direct run and the worker should be started in any case
        if(empty($id)) {
            foreach($data as $k => $v) {
                $this->set($k, $v);
            }
            $this->save();
            return true;
        }
        
        $sets = function($prefix) use ($data) {
            foreach($data as $k => $v) {
                $this->set($k, $v);
                $sets[] = $prefix.'`'.$k.'` = '.$this->db->getAdapter()->quote($v);
            }
            return ' SET '.join(', ', $sets);
        };
        
        if($oncePerTaskGuid) {
            $sql = 'UPDATE `Zf_worker` w1 LEFT OUTER JOIN `Zf_worker` w2';
            $sql .= ' ON w1.`taskGuid` = w2.`taskGuid` AND w1.`worker` = w2.`worker` AND w2.`state` = "'.self::STATE_RUNNING.'" AND w1.`id` != w2.`id`';
            $sql .= $sets('`w1`.');
            $sql .= ' WHERE w2.id IS NULL AND w1.id = ? AND w1.state != "'.self::STATE_RUNNING.'"';
        }
        else {
            $sql = 'UPDATE `Zf_worker`';
            $sql .= $sets('');
            $sql .= ' WHERE id = ? AND state != "'.self::STATE_RUNNING.'"';
        }
        
        $values = [$this->getId()];
        
        $stmt = $this->retryOnDeadlock(function() use ($sql, $values){
            $this->db->reduceDeadlocks();
            return $this->db->getAdapter()->query($sql, $values);
        });
        
        $result = $stmt->rowCount();
        return $result > 0;
    }

    /**
     * Set all Workers not yet finished as done for the current task & worker without the worker calling this API
     */
    public function setRemainingToDone() {
        // set unfinished workers to done
        $bindings = [ self::STATE_DONE, $this->getTaskGuid(), $this->getWorker(), $this->getId(), self::STATE_PREPARE, self::STATE_SCHEDULED, self::STATE_WAITING, self::STATE_RUNNING ];
        $sql = 'UPDATE `Zf_worker` SET `state` = ?  WHERE `taskGuid` = ? AND `worker` = ? AND `id` != ? AND `state` IN (?, ?, ?, ?)';
        $this->retryOnDeadlock(function() use ($sql, $bindings){
            $this->db->reduceDeadlocks();
            return $this->db->getAdapter()->query($sql, $bindings);
        });
    }
    
    /**
     * returns a list of waiting workers to trigger (optional of a given taskguid)
     * @param string $taskGuid
     * @return array: list of queued entries in table Zf_worker which are "ready to run"
     */
    public function getListQueued(string $taskGuid = null) : array
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
            if(!array_key_exists($key, $blockedSlots)){
                $blockedSlots[$key] = 0;
            }
            $blockedSlots[$key]++;
        }

        foreach ($waiting as $worker) {
            // check if blocking-type is 'SLOT' and number of parallel processes for this resource/slot is not reached
            $key = $worker['resource'] . '-' . $worker['slot'];
            if(!array_key_exists($key, $blockedSlots)){
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
    
    private function getListWaiting($taskGuid) {
        $sql = $this->db->select()->where('state = ?', self::STATE_WAITING)->order('id ASC');
        
        if ($taskGuid) {
            $sql->where('taskGuid = ?', $taskGuid);
        }
        
        return $this->db->fetchAll($sql)->toArray();
    }

    private function getListRunning($taskGuid) {
        $db = $this->db;
        $sql = $db->select()
        //->columns(array('resource', 'slot')) // this does not work :-((((
        ->from($db->info($db::NAME), array('resource', 'slot', 'blockingType'))
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
     * @param string $resourceName
     * @param array $validSlots:  optional array with valid Slots. All slots in the result which are not in this array of valid slots will be removed
     * @return array: list of array(slot, count) for the given resource
     */
    public function getListSlotsCount(string $resourceName = '', array $validSlots=NULL) {
        $db = $this->db;
        $sql = $db->select()
            //->columns(array('resource', 'slot')) // this does not work :-((((
            ->from($db->info($db::NAME), array('slot', 'COUNT(*) AS count'))
            ->where('resource = ?', $resourceName)
            ->where('worker = ?', $this->getWorker())
            ->where('state IN (?)', array(self::STATE_WAITING, self::STATE_RUNNING))
            ->group(array('resource', 'slot'))
            ->order('count ASC');
        
        $rows = $db->fetchAll($sql)->toArray();

        if(is_array($validSlots)) {
            $validatedRows = [];
            foreach ($rows as $row) {
                if(in_array($row['slot'], $validSlots)) {
                    $validatedRows[] = $row;
                }
            }
            return $validatedRows;
        }
        return $rows;
    }

    /**
     * Clean the worker table by given state list and remove the matching worker entries immediatelly
     * @param array $states
     */
    public function clean(array $states) {
        $this->db->reduceDeadlocks();
        $this->db->delete(['state in (?)' => $states]);
    }

    public function cleanGarbage() {
        // first clean all 'archived' worker (state=done and endtim older than 1 HOUR)
        $where = array();
        $where[] = $this->db->getAdapter()->quoteInto('state = ?', self::STATE_DONE);
        $where[] = $this->db->getAdapter()->quoteInto('endtime < ?', new Zend_Db_Expr('NOW() - INTERVAL 2 HOUR'));
        $this->db->reduceDeadlocks();
        $this->db->delete($where);
        
        // TODO: do something with all crashed worker (maxRuntime expired)
        // TODO: do something with all worker marked with 'defunct'
        return;
        //$sql = $this->db->select()->where('maxRuntime < NOW()');
        //$rows = $this->db->fetchAll($sql);
        
        //foreach ($rows as $row) {
        //    $row->delete();
        //}
    }
    
    /**
     * returns a summary of how many workers are in DB, grouped by state
     */
    public function getSummary(array $groupBy = ['state']) {
        $s = $this->db->select()
        ->from($this->db, array_merge(['cnt' => 'count(*)'], $groupBy))
        ->group($groupBy);
        $res = $this->db->fetchAll($s);
        
        if(count($groupBy) > 1) {
            return $res;
        }
        $result = array(
            self::STATE_SCHEDULED => 0,
            self::STATE_WAITING => 0,
            self::STATE_RUNNING => 0,
            self::STATE_DEFUNCT => 0,
            self::STATE_DONE => 0,
        );
        foreach($res as $row) {
            $result[$row[$groupBy[0]]] = (int) $row['cnt'];
        }
        return $result;
    }
    
    /**
     * returns a summary of the workers states of the current workers group.
     *  grouped by state and worker
     *  Worker group means: same taskGuid, same parent worker.
     */
    public function getParentSummary() {
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
     * Sets all remaining scheduled and waiting workers of that worker group (same parent (or the parent itself) and same taskGuid) to defunct
     * @param array $exludedWorkers optional, contains the worker classes which should be ignored in setting them to defunc
     * @param bool $includeRunning includes also the running state to be defunct
     * @param bool $taskguidOnly TODO move taskGuid out of ZfExtended!
     * @throws ZfExtended_Models_Db_Exceptions_DeadLockHandler
     */
    public function defuncRemainingOfGroup(array $exludedWorkers = [], bool $includeRunning = false, bool $taskguidOnly = false) {
        $this->retryOnDeadlock(function() use ($exludedWorkers, $includeRunning, $taskguidOnly) {
            $byGroup = $workerExclude = '';
            $params = [$this->getTaskGuid()];
            if(!empty($exludedWorkers)) {
                //adapter->query can not handle arrays directlym so use plain string concat
                $workerExclude = $this->db->getAdapter()->quoteInto(' AND worker NOT IN (?)', $exludedWorkers);
            }
            $this->db->reduceDeadlocks();
            $affectedStates = [self::STATE_WAITING, self::STATE_SCHEDULED, self::STATE_PREPARE];
            if($includeRunning) {
                $affectedStates[] = self::STATE_RUNNING;
            }
            if(! $taskguidOnly) {
                $byGroup = ' AND ((parentId = 0 AND id IN (?,?)) OR (parentId != 0 AND parentId = ?))';
                $params[] = $this->getId();
                $params[] = $this->getParentId();
                $params[] = $this->getParentId();
            }
            $stateInclude = $this->db->getAdapter()->quoteInto(' AND state IN (?)', $affectedStates);
            $this->db->getAdapter()->query('UPDATE Zf_worker SET state = \''.self::STATE_DEFUNCT.'\'
                WHERE taskGuid = ? '.$byGroup.$stateInclude.$workerExclude, $params);
        });
    }
    
    public function getMaxLifetime() {
        return $this->maxLifetime;
    }
    
    /**
     * sets the serialized parameters of the worker
     */
    public function setParameters($parameters) {
        $this->set('parameters', serialize($parameters));
    }
    
    /**
     * returns the unserialized parameters of the worker
     * stores the unserialized values internally to prevent multiple unserialization (and multiple __wakeup calls)
     */
    public function getParameters() {
        if(is_null($this->parameters)) {
            $this->parameters = unserialize($this->get('parameters'));
        }
        return $this->parameters;
    }

    /**
     * Update the worker model progress field with given $progress value.
     * This will trigger updateProgress event on each call.
     *
     * @param float $progress
     * @return boolean
     * @throws ZfExtended_Models_Db_Exceptions_DeadLockHandler
     */
    public function updateProgress(float $progress = 1): bool {
        $id = $this->getId();
        $isUpdated = $this->retryOnDeadlock(function() use ($progress, $id){
            $this->db->reduceDeadlocks();
            return $this->db->update([
                'progress'=>$progress
            ], [
                'id = ?'=>$id
            ]) > 0;
            
        });
        return $isUpdated;
    }
    
    /**
     * Returns this worker as string: Worker, id, state.
     */
    public function __toString() {
        return sprintf('%s (id: %s, state: %s, slot: %s)', $this->getWorker(), $this->getId(), $this->getState(), $this->getSlot());
    }
}
