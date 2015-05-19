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

abstract class ZfExtended_Worker_Abstract {
    
    /**
     * @var ZfExtended_Models_Worker
     */
    protected $workerModel = false;
    
    /**
     * @var string taskguid
     */
    protected $taskGuid;
    
    /**
     * @var ZfExtended_Models_Worker
     */
    protected $finishedWorker = false;
    
    /**
     * Number of allowed parallel processes for a certain worker
     * @var integer
     */
    protected $maxParallelProcesses = 1;
    
    /**
     * With blocking type slot, the maximum of parallel workers is defined by the available slots for this resource.
     * @var string
     */
    const BLOCK_SLOT = 'slot';
    
    /**
     * If a worker with blocking type "resource" is running, no other queued worker with same resource may be started at the same time.
     * @var string
     */
    const BLOCK_RESOURCE = 'resource';
    
    /**
     * If a worker with blocking global is running, no other queued worker may be started
     * No other queued worker means, regardless of maxParallelProcesses and regardless of resource.
     * @var string
     */
    const BLOCK_GLOBAL = 'global';
    
    /**
     * Blocking-typ for this certain worker
     * @var const blocking-type BLOCK_XYZ
     */
    protected $blockingType = self::BLOCK_SLOT;
    
    
    /**
     * holds the result-values of processing method $this->work()
     *  
     * @var mixed
     */
    protected $result;
    
    /**
     * @var ZfExtended_Log
     */
    protected $log;
    
    /**
     * Contains the Exception thrown in the worker
     * Since one worker is designed to do one job, there should be only one exception.
     * @var array
     */
    protected $workerException = null;
    
    public function __construct() {
        $this->log = ZfExtended_Factory::get('ZfExtended_Log');
    }
    
    /**
     * Initialize a worker and a internal worker-model 
     * 
     * @param string $taskGuid
     * @param array $parameters stored in the worker-model
     * 
     * @return boolean true if worker can be initialized.
     */
    public function init($taskGuid = NULL, $parameters = array()) {
        if (!$this->validateParameters($parameters)) {
            return false;
        }
        
        $this->taskGuid = $taskGuid;
        
        $this->workerModel = ZfExtended_Factory::get('ZfExtended_Models_Worker');
        
        $this->workerModel->setState(ZfExtended_Models_Worker::STATE_SCHEDULED);
        $this->workerModel->setWorker(get_class($this));
        $this->workerModel->setTaskGuid($taskGuid);
        $this->workerModel->setParameters($parameters);
        //FIXME see TRANSLATE-337
        $this->workerModel->setHash(uniqid(NULL, true));
        
        $this->workerModel->setBlockingType($this->blockingType);
        
        return true;
    }
    
    
    /**
     * Returns the internal worker-model of this worker
     * 
     * @return ZfExtended_Models_Worker
     */
    public function getModel(){
        return $this->workerModel;
    }
    
    /**
     * Returns the internal worker-model in the state before it was deleted
     * 
     * @return ZfExtended_Models_Worker
     */
    public function getModelBeforeDelete() {
        return $this->finishedWorker;
    }
    
    /**
     * Get a worker-instance from a worker-model
     * 
     * @param ZfExtended_Models_Worker $model
     * @return mixed a concrete worker corresponding to the submittied worker-model; false if instance could not be initialized;
     */
    static public function instanceByModel(ZfExtended_Models_Worker $model) {
        $instance = ZfExtended_Factory::get($model->getWorker());
        /* @var $instance ZfExtended_Worker_Abstract */
        if (!$instance->init($model->getTaskGuid(), $model->getParameters())) {
            $this->log->logError('Worker can not be instanciated from stored workerModel', __CLASS__.' -> '.__FUNCTION__.'; $model->getParameters(): '.print_r($model->getParameters(), true));
            return false;
        }
        
        $instance->workerModel = $model;
        $instance->taskGuid = $model->getTaskGuid();
        return $instance;
    }
    
    
    /**
     * Checks the parameters given to init().
     * Need to be defined in concrete worker.
     * 
     * @param array $parameters
     * @return boolean true if everything is OK
     */
    abstract protected function validateParameters($parameters = array());
    
    /**
     * 
     * @param string $state; default NULL
     */
    public function queue($state = NULL) {
        $this->checkIsInitCalled();
        $tempSlot = $this->calculateQueuedSlot();
        $this->workerModel->setResource($tempSlot['resource']);
        $this->workerModel->setSlot($tempSlot['slot']);
        $this->workerModel->setMaxParallelProcesses($this->maxParallelProcesses);
        if(!is_null($state)){
            $this->workerModel->setState($state);
        }
        $this->workerModel->save();
        
        $this->wakeUpAndStartNextWorkers($this->workerModel->getTaskGuid());
    }
    
    
    /**
     * @return array('resource' => ResurceName, 'slot' => SlotName);
     */
    protected function calculateDirectSlot() {
        return $this->calculateQueuedSlot();
    }
    /**
     * @return array('resource' => ResurceName, 'slot' => SlotName);
     */
    protected function calculateQueuedSlot() {
        return array('resource' => $this->workerModel->getWorker(), 'slot' => 'default');
    }
    
    
    /**
     * if function is needed public, you have to define a public function in the concrete worker
     * and call this by parent::run();
     * 
     * direct calls per run are not mutex-save!
     * 
     * @return boolean true if $this->work() runs without errors
     */
    protected function run($slot = false) {
        $this->checkIsInitCalled();
        if(!$slot){
            $tempSlot = $this->calculateDirectSlot();
            $this->workerModel->setResource($tempSlot['resource']);
            $this->workerModel->setSlot($tempSlot['slot']);
        }
        if ($slot) {
            $this->workerModel->setResource($slot['resource']);
            $this->workerModel->setSlot($slot['slot']);
        }
        
        $result = $this->_run();
        
        if(!empty($this->workerException)) {
            $this->log->logError('Exception logged in direct run of '.get_class($this).'::work');
            $this->log->logException($this->workerException);
        }
        
        $this->wakeUpAndStartNextWorkers($this->finishedWorker->getTaskGuid());
        return $result;
    }
    
    /**
     * Mutex save worker-run. Before this function can be called, the worker must be queued with $this->queue();
     * 
     * @return boolean true if $this->work() runs without errors
     */
    public function runQueued() {
        $this->checkIsInitCalled();
        if (!$this->workerModel->setRunningMutex())
        {
            return false;
        }
        
        $result = $this->_run();
        
        if(!empty($this->workerException)) {
            throw $this->workerException;
        }
        
        $this->wakeUpAndStartNextWorkers($this->finishedWorker->getTaskGuid());
        return $result;
    }
    
    
    /**
     * inner run function used by run and runQueued
     * @param boolean $directRun optional, is false per default
     * @return boolean true if $this->work() runs without errors
     */
    private function _run($directRun = false) {
        $this->workerModel->setState(ZfExtended_Models_Worker::STATE_RUNNING);
        $this->workerModel->setStarttime(new Zend_Db_Expr('NOW()'));
        $this->workerModel->setMaxRuntime(new Zend_Db_Expr('NOW() + INTERVAL '.$this->workerModel->getMaxLifetime()));
        $this->workerModel->setPid(getmypid());
        
        //error_log($this->workerModel->getId().' '.get_class($this).' # '.$this->workerModel->getTaskGuid().' # '.str_replace("\n",'; ',print_r($this->workerModel->getParameters(),1)));
        $this->workerModel->save();
        try {
            $result = $this->work();
            $this->workerModel->setState(ZfExtended_Models_Worker::STATE_DONE);
            $this->workerModel->setEndtime(new Zend_Db_Expr('NOW()'));
            $this->finishedWorker = clone $this->workerModel;
            $this->workerModel->save();
        } catch(Exception $workException) {
            $result = false;
            $this->workerModel->setState(ZfExtended_Models_Worker::STATE_DEFUNCT);
            $this->workerModel->save();
            $this->finishedWorker = clone $this->workerModel;
            $this->workerException = $workException;
        }
        $this->workerModel->cleanGarbage();
        
        return $result;
    }
    
    protected function wakeUpAndStartNextWorkers($taskGuid) {
        $this->workerModel->wakeupScheduled($taskGuid);
        $this->startWorkerQueue();
    }
    
    /**
     * trigger application-wide worker-queue
     */
    private function startWorkerQueue() {
        $trigger = ZfExtended_Factory::get('ZfExtended_Worker_TriggerByHttp');
        /* @var $trigger ZfExtended_Worker_TriggerByHttp */
        $trigger->triggerQueue();
    }
    
    
    /**
     * need to be defined in concrete worker.
     * Results (if any) shold be written in $this->result so they can be read-out later by $this->getResults()
     * 
     * @return boolean true if everything is OK
     */
    abstract protected function work();
    
    
    /**
     * Get the result-values of prrocessing $this->work();
     * @return mixed
     */
    public function getResult() {
        return $this->result;
    }
    
    /**
     * internal method to check if method init was called
     * @throws ZfExtended_Exception
     */
    protected function checkIsInitCalled() {
        if(empty($this->workerModel)) {
            throw new ZfExtended_Exception('Please call $worker->init() method before!');
        }
    }
}
