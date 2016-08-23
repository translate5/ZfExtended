<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
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
     * @var ZfExtended_Models_Worker
     */
    protected $workerModel;
    
    /**
     * @var string taskguid
     */
    protected $taskGuid;
    
    /**
     * @var ZfExtended_Models_Worker
     */
    protected $finishedWorker;
    
    /**
     * Number of allowed parallel processes for a certain worker
     * @var integer
     */
    protected $maxParallelProcesses = 1;
    
    /**
     * Blocking-typ for this certain worker
     * @var const blocking-type BLOCK_XYZ
     */
    protected $blockingType = self::BLOCK_SLOT;
    
    /**
     * switch if the queue call of the worker will block the current thread until the worker was called
     *  per default our workers are non blocking
     *  like block and non blocking sockets
     * Has nothing to do with the above blocking type!
     * see also $blockingTimeout
     * @var string
     */
    protected $isBlocking = false;
    
    /**
     * Per default a "socket blocking like" worker is cancelled after 3600 seconds.
     * @var integer
     */
    protected $blockingTimeout = 3600;
    
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
     * resourcePool for the different TermTagger-Operations;
     * Example from TermTagger: Possible Values: $this->allowdResourcePools = array('default', 'gui', 'import');
     * @var string
     */
    protected $resourcePool = 'default';
    /**
     * Allowd values for setting resourcePool
     * @var array(strings)
     */
    protected static $allowedResourcePools = array('default');
    
    /**
     * Praefix for workers resource-name
     * @var string
    */
    protected static $praefixResourceName = 'default_';
    
    protected static $resourceName = NULL;
    /**
     *
     * @var array all scheduled workers for the worker classes listed here have to be done, before this worker will be woken up. Is serialized as JSON in DB. Should be soverridden by child
     */
    protected $workerChainDependency = array();

    /**
     * Contains the Exception thrown in the worker
     * Since one worker is designed to do one job, there should be only one exception.
     * @var array
     */
    protected $workerException = null;
    
    public function __construct() {
        $this->log = ZfExtended_Factory::get('ZfExtended_Log');
        $this->maxParallelProcesses = $this->getMaxParallelProcesses();
    }
    
    protected function getMaxParallelProcesses() {
        $config = Zend_Registry::get('config');
        $class = get_class($this);
        $workerConfig = $config->runtimeOptions->worker->$class;
        if(empty($workerConfig)) {
            throw new ZfExtended_Exception('Missing Worker config for class '.$class.'. Please update config!'); //should be seen by developers only
        }
        return $workerConfig->maxParallelWorkers;
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
        
        $this->initWorkerModel($taskGuid, $parameters);
        
        if (isset($parameters['resourcePool']) && in_array($parameters['resourcePool'], static::$allowedResourcePools)) {
            $this->resourcePool = $parameters['resourcePool'];
        }
        
        self::$resourceName = self::$praefixResourceName.$this->resourcePool;
        
        return true;
    }

    /**
     * creates the internal worker model ready for DB storage if it not already exists.
     * The latter case happens when using instanceByModels
     * 
     * @param unknown $taskGuid
     * @param unknown $parameters
     */
    private function initWorkerModel($taskGuid, $parameters) {
        if(!empty($this->workerModel)) {
            return;
        }
        $this->workerModel = ZfExtended_Factory::get('ZfExtended_Models_Worker');
        
        $this->workerModel->setState(ZfExtended_Models_Worker::STATE_SCHEDULED);
        $this->workerModel->setWorker(get_class($this));
        $this->workerModel->setTaskGuid($taskGuid);
        $this->workerModel->setParameters($parameters);
        //FIXME see TRANSLATE-337
        $this->workerModel->setHash(uniqid(NULL, true));
        
        $this->workerModel->setBlockingType($this->blockingType);
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
        
        $instance->workerModel = $model;
        
        if (!$instance->init($model->getTaskGuid(), $model->getParameters())) {
            $this->log->logError('Worker can not be instanciated from stored workerModel', __CLASS__.' -> '.__FUNCTION__.'; $model->getParameters(): '.print_r($model->getParameters(), true));
            return false;
        }
        
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
     * @param number $parentId optional, defaults to 0. Should contain the workerId of the parent worker.
     * @param state $state optional, defaults to null. Designed to queue a worker with a desired state.
     * @return integer returns the id of the newly created worker DB entry
     */
    public function queue($parentId = 0, $state = NULL) {
        $this->checkIsInitCalled();
        $tempSlot = $this->calculateQueuedSlot();
        $this->workerModel->setResource($tempSlot['resource']);
        $this->workerModel->setSlot($tempSlot['slot']);
        $this->workerModel->setParentId($parentId);
        $this->workerModel->setMaxParallelProcesses($this->maxParallelProcesses);
        if(!is_null($state)){
            $this->workerModel->setState($state);
        }
        $this->workerModel->save();
        
        $this->wakeUpAndStartNextWorkers($this->workerModel->getTaskGuid());
        
        $this->emulateBlocking();
        return $this->workerModel->getId();
    }
    
    /**
     * Sets the queue call of this worker to blocking, 
     *  that means the current process remains in an endless loop until the worker was called.
     *  Like stream set blocking
     * @param bool $blocking
     */
    public function setBlocking($blocking = true) {
        $this->isBlocking = $blocking;
    }
    
    /**
     * waits until the worker with the given ID is done
     *  defunct will trigger an exception
     * @param string $taskGuid
     * @param Closure $filter optional filter with the loaded ZfExtended_Models_Worker as parameter, must return true to wait for the model or false to not. 
     * @return boolean true if something found to wait on, false if not
     */
    public function waitFor(string $taskGuid, Closure $filter = null) {
        $wm = $this->workerModel = ZfExtended_Factory::get('ZfExtended_Models_Worker');
        /* @var $wm ZfExtended_Models_Worker */
        
        //set a default filter if nothing given
        is_null($filter) && $filter = function() {
            return true;
        };
        
        //TODO: getting the class name this way is not factory overwrite aware: 
        if($wm->loadLatestOpen(get_class($this), $taskGuid) && $filter($wm)) {
            $this->setBlocking();
            $this->emulateBlocking();
        }
    }
    
    /**
     * emulates a blocking worker queue call
     * @throws ZfExtended_Exception
     */
    protected function emulateBlocking() {
        if(!$this->isBlocking) {
            return;
        }
        $sleep = 1;
        $starttime = time();
        $wm = $this->workerModel;
        do {
            sleep($sleep);
            $runtime = time() - $starttime;
            
            // as longer we wait, as longer are getting the intervals to check for the worker.
            if($runtime > $sleep * 10 && $sleep < 60) {
                $sleep = $sleep * 2; //should result in a max of 64 seconds
            }
            
            $wm->load($wm->getId());
            $state = $wm->getState();
            switch ($state) {
                case $wm::STATE_DEFUNCT:
                    throw new ZfExtended_Exception('Worker "'.$wm.'" is defunct!');
                case $wm::STATE_DONE:
                    return;
            }
        } while($runtime < $this->blockingTimeout);
        throw new ZfExtended_Exception('Worker "'.$wm.'" was queued blocking and timed out!');
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
        Zend_Registry::set('affected_taskGuid', $this->taskGuid); //for TRANSLATE-600 only
        
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
     * @return boolean true if $this->work() runs without errors
     */
    private function _run() {
        $this->registerShutdown();
        
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
        
        return $result;
    }
    
    /**
     * sets the worker model to defunct when a fatal error happens
     */
    private function registerShutdown() {
        register_shutdown_function(function($wm) {
            $error = error_get_last();
            if(!is_null($error) && ($error['type'] & FATAL_ERRORS_TO_HANDLE)) {
                $wm->setState(ZfExtended_Models_Worker::STATE_DEFUNCT);
                $wm->save();
            }
        }, $this->workerModel);
    }
    
    protected function wakeUpAndStartNextWorkers($taskGuid) {
        $this->workerModel->wakeupScheduled($taskGuid,  self::$resourceName);
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
