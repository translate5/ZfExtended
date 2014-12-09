<?php
 /*
 START LICENSE AND COPYRIGHT
 
 This file is part of Translate5 Editor PHP Serverside and build on Zend Framework
 
 Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

 This file may be used under the terms of the GNU General Public License version 3.0
 as published by the Free Software Foundation and appearing in the file gpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU General Public License version 3.0 requirements will be met:
 http://www.gnu.org/copyleft/gpl.html.

 For this file you are allowed to make use of the same FLOSS exceptions to the GNU 
 General Public License version 3.0 as specified by Sencha for Ext Js. 
 Please be aware, that Marc Mittag / MittagQI take no warranty  for any legal issue, 
 that may arise, if you use these FLOSS exceptions and recommend  to stick to GPL 3. 
 For further information regarding this topic please see the attached license.txt
 of this software package.
 
 MittagQI would be open to release translate5 under EPL or LGPL also, if this could be
 brought in accordance with the ExtJs license scheme. You are welcome to support us
 with legal support, if you are interested in this.
 
 
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU General Public License version 3.0 http://www.gnu.org/copyleft/gpl.html
             with FLOSS exceptions (see floss-exception.txt and ux-exception.txt at the root level)
 
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
     * 
     * @param string $taskGuid
     */
    public function wakeupScheduled($taskGuid = NULL) {
        // check if there are any worker waiting or running with this taskGuid
        $db = $this->db;
        $db->getAdapter()->beginTransaction();
        $sql = $db->select()
                ->from($db->info($db::NAME), array('COUNT(*) AS count'))
                ->where('taskGuid = ?', $taskGuid)
                ->where('state IN(?)', array(self::STATE_RUNNING, self::STATE_WAITING));
        $count = $db->fetchRow($sql)->count;
        
        // if no waiting/running worker was found, wake up the next scheduled worker with this taskGuid
        if ($count != 0) {
            $db->getAdapter()->commit();
            return;
        }
        
        $sql = $db->select()
                ->from($db->info($db::NAME), array('id'))
                ->where('taskGuid = ?', $taskGuid)
                ->where('state = ?', self::STATE_SCHEDULED)
                ->order('id ASC')
                ->limit(1);
        $row = $db->fetchRow($sql);
        
        // there is no scheduled worker with this taskGuid
        if (empty($row)) {
            $db->getAdapter()->commit();
            return;
        }
        
        $id = $row->id;
        $data = array('state' => self::STATE_WAITING);
        $whereStatements = array();
        $whereStatements[] ='id = "'.$id.'"';
        
        $countRows = $db->update($data, $whereStatements);
        $db->getAdapter()->commit();
        
        if ($countRows < 1)
        {
            error_log(__CLASS__.' -> '.__FUNCTION__.' workerModel can not wake up next scheduled worker with $id='.$id);
            return false;
        }
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
     * 
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
        
        return $listQueued;
    }
    
    private function getListWaiting($taskGuid = NULL) {
        $sql = $this->db->select()->where('state = ?', self::STATE_WAITING)->order('id ASC');
        
        if ($taskGuid) {
            $sql->where('taskGuid = ?', $taskGuid);
        }
        
        $rows = $this->db->fetchAll($sql)->toArray();
        
        return $rows;
    }
    private function getListRunning($taskGuid = NULL) {
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
     * Get a counted list of all slots (no matter what state (running or waiting) the entry has)
     * for the given resource $resourceName
     * 
     * @param string $resourceName
     * @return array: list of array(slot, count) for the given resource
     */
    public function getListSlotsCount($resourceName = '') {
        $db = $this->db;
        $sql = $db->select()
                    //->columns(array('resource', 'slot')) // this does not work :-((((
                    ->from($db->info($db::NAME), array('slot', 'COUNT(*) AS count'))
                    ->where('resource = ?', $resourceName)
                    ->group(array('resource', 'slot'))
                    ->order('count ASC');
        
        $rows = $db->fetchAll($sql)->toArray();
        
        return $rows;
    }
    
    
    public function cleanGarbage() {
        error_log(__CLASS__.' -> '.__FUNCTION__);
        $sql = $this->db->select()->where('maxRuntime < NOW()');
        $rows = $this->db->fetchAll($sql);
        
        foreach ($rows as $row) {
            $row->delete();
        }
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