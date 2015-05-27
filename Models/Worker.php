<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**
 * Abstract Worker Class
 * 
 * @method void setId() setId(integer $id)
 * @method void setState() setState(string $state)
 * @method void setWorker() setWorker(string $phpClassName)
 * @method void setResource() setResource(string $resource)
 * @method void setSlot() setSlot(string $slotName)
 * @method void setTaskGuid() setTaskGuid(string $taskGuid)
 * @method void setPid() setPid(integer $pid)
 * @method void setStarttime() setStarttime(string $starttime)
 * @method void setMaxRuntime() setMaxRuntime(string $maxRuntime)
 * @method void setHash() setHash(string $hash)
 * @method void setMaxParallelProcesses() setMaxParallelProcesses(integer $maxParallelProcesses)
 * @method void setBlockingType() setBlockingType(string $blockingType)
 * 
 * @method integer getId()
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
 *  
 */
class ZfExtended_Models_Worker extends ZfExtended_Models_Entity_Abstract {
    /**
     * @var ZfExtended_Models_Db_Worker
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
     * This constant values define the different worker-states
     * @var string
     */
    const STATE_SCHEDULED = 'scheduled';
    const STATE_WAITING = 'waiting';
    const STATE_RUNNING = 'running';
    const STATE_DEFUNCT = 'defunct';
    const STATE_DONE    = 'done';
    
    
    /**
     * Wake up a scheduled worker (set state from scheduled to waiting)
     * if there are no other worker waiting or running with the given taskGuid
     * TODO implement for waking up general workers without a $taskGuid
     * @param string $taskGuid
     */
    public function wakeupScheduled($taskGuid) {
        // check if there are any worker waiting or running with this taskGuid
        $db = $this->db;
        $adapter = $db->getAdapter();

        //SQL Explanation:
        // set the next (limit 1 and order by) worker of the given task to waiting
        // if no other worker are running or waiting (EXISTS)
        // this is regardless of taskGuid, so scheduled workers of different tasks 
        //    has to wait for a whole tasks work to be finished
        $sql = function($withTaskGuid = false) {
            $taskGuid = $withTaskGuid ? 'taskGuid = ? AND ' : '';
            return 'UPDATE Zf_worker u, (
                    SELECT id from Zf_worker 
                    WHERE '.$taskGuid.' state = ? 
                    AND NOT EXISTS (SELECT * 
                                    FROM Zf_worker 
                                    WHERE STATE IN (?, ?))
                    ORDER BY id ASC
                    LIMIT 1
                ) s
                SET u.STATE = ?
                WHERE u.id = s.id;'; 
        };
        //first try to wake up scheduleds of the given task
        $bindings = array($taskGuid, self::STATE_SCHEDULED, self::STATE_WAITING, self::STATE_RUNNING, self::STATE_WAITING);
        $res = $adapter->query($sql(true), $bindings);
        //if waked up one, we dont need to look for others
        if($res->rowCount() > 0) {
            return;
        }
        
        //if no worker of given task could be waked up at all, take the next of all workers:
        $bindings = array(self::STATE_SCHEDULED, self::STATE_WAITING, self::STATE_RUNNING, self::STATE_WAITING);
        $adapter->query($sql(), $bindings);
    }
    
    /**
     * Try to set worker into mutex-save mode
     * 
     * @return boolean true if workerModel is set to mutex-save
     */
    public function setRunningMutex() {
        // workerModel can not be set to mutex if it is new 
        if (!$this->getId() || !$this->getHash() || $this->getState() != self::STATE_WAITING)
        {
            error_log(__CLASS__.' -> '.__FUNCTION__.' workerModel can not be set to mutex (id-Error, state-Error, hash-Error)');
            return false;
        }
        $data = array('hash' => uniqid(NULL, true));
        
        $whereStatements = array();
        $whereStatements[] ='id = "'.$this->getId().'"';
        $whereStatements[] = 'hash = "'.$this->getHash().'"';
        $whereStatements[] = 'state = "'.self::STATE_WAITING.'"';
        
        $countRows = $this->db->update($data, $whereStatements);
        
        // workerModel can not be set to mutex because no entry with same id and hash can be found in database
        // nothing to log since this can happen often
        return $countRows > 0;
    }
    
    /**
     * returns a list of queued workers (optional of a given taskguid)
     * @param string $taskGuid
     * @return array: list of queued entries in table Zf_worker which are "ready to run"
     */
    public function getListQueued($taskGuid = NULL) {
        $listQueued = array();
        $db = $this->db;
        $db->getAdapter()->beginTransaction();
        $listWaiting = $this->getListWaiting($taskGuid);
        $listRunning = $this->getListRunning($taskGuid);
        $db->getAdapter()->commit();
        
        $listRunningResources = array();
        $listRunningResourceSlotSerialized = array();
        foreach ($listRunning as $running) {
            // stop if one running worker is of blocking-type 'GLOBAL'
            if ($running['blockingType'] == ZfExtended_Worker_Abstract::BLOCK_GLOBAL) {
                return array();
            }
            $listRunningResources[] = $running['resource'];
            $listRunningResourceSlotSerialized[] = serialize(array($running['resource'], $running['slot']));
        }
        
        foreach($listWaiting as $waiting) {
            // check if blocking-type 'RESOURCE' blocks this waiting worker
            if ($waiting['blockingType'] == ZfExtended_Worker_Abstract::BLOCK_RESOURCE
                && in_array($waiting['resource'], $listRunningResources)) {
                continue;
            }
            
            // check if blocking-type is 'SLOT' and number of parallel processes for this resource/slot is not reached
            $tempResourceSlot = array($waiting['resource'], $waiting['slot']);
            $tempResourceSlotSerialized = serialize($tempResourceSlot);
            
            $countedWorkers = array_count_values($listRunningResourceSlotSerialized);
            $countRunningSlotProcesses = 0;
            if (array_key_exists($tempResourceSlotSerialized, $countedWorkers)) {
                $countRunningSlotProcesses = $countedWorkers[$tempResourceSlotSerialized];
            }
            
            if ($waiting['blockingType'] == ZfExtended_Worker_Abstract::BLOCK_SLOT
                && $countRunningSlotProcesses >= $waiting['maxParallelProcesses']) {
                
                continue;
            }
            
            $listQueued[] = $waiting;
            $listRunning[] = $tempResourceSlot;
            $listRunningResources[] = $waiting['resource'];
            $listRunningResourceSlotSerialized[] = $tempResourceSlotSerialized;
        }
        //error_log("QUEUED: ".print_r($listQueued,1));
        return $listQueued;
    }
    
    private function getListWaiting($taskGuid) {
        $sql = $this->db->select()->where('state = ?', self::STATE_WAITING)->order('id ASC');
        
        if ($taskGuid) {
            $sql->where('taskGuid = ?', $taskGuid);
        }
        
        $rows = $this->db->fetchAll($sql)->toArray();
        
        return $rows;
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
    
        $rows = $db->fetchAll($sql)->toArray();
        
        return $rows;
    }
    
    /**
     * Get a counted list of all "hot" slots (all states (waiting, running))
     * for the given resource $resourceName
     * 
     * @param string $resourceName
     * @param string $validSlots optional array with valid Solts. All slots in the result which are not in this array of valid solts will be removed
     * 
     * @return array: list of array(slot, count) for the given resource
     */
    public function getListSlotsCount($resourceName = '', $validSlots = array()) {
        $db = $this->db;
        $sql = $db->select()
                    //->columns(array('resource', 'slot')) // this does not work :-((((
                    ->from($db->info($db::NAME), array('slot', 'COUNT(*) AS count'))
                    ->where('resource = ?', $resourceName)
                    ->where('state IN (?)', array(self::STATE_WAITING, self::STATE_RUNNING))
                    ->group(array('resource', 'slot'))
                    ->order('count ASC');
        
        $rows = $db->fetchAll($sql)->toArray();
        
        if (!empty($validSlots)) {
            foreach ($rows as $key => $row) {
                if(!in_array($row['slot'], $validSlots)) {
                    unset($rows[$key]);
                }
            }
        }
        
        return $rows;
    }
    
    
    public function cleanGarbage() {
        // first clean all 'archived' worker (state=done and endtim older than 1 HOUR)
        $where = array();
        $where[] = $this->db->getAdapter()->quoteInto('state = ?', self::STATE_DONE);
        $where[] = $this->db->getAdapter()->quoteInto('endtime < ?', new Zend_Db_Expr('NOW() - INTERVAL 1 HOUR'));
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
     * returns the deserialized parameters of the worker
     */
    public function getParameters() {
        return unserialize($this->get('parameters'));
    }
}
