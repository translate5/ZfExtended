<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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
 * Abstract worker class, providing base worker functionality. The work to be done is implemented / triggered in the extending classes of this one.
 * All other functionality reacting on the worker run is encapsulated in the behaviour classes
 */
abstract class ZfExtended_Worker_Abstract {
    use ZfExtended_Models_Db_DeadLockHandlerTrait;
    
    /**
     * With blocking type slot, the maximum of parallel workers is defined by the available resources
     * Historically the naming is "slot" but it should better be named "resource"
     * @var string
     */
    const BLOCK_SLOT = 'slot';

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
     * @var string blocking-type BLOCK_XYZ
     */
    protected $blockingType = self::BLOCK_SLOT;
    
    /**
     * If this flag is false, multiple workers per taskGuid can be run
     *  for example the termTagger
     *  Should be overriden by class extension
     * @var boolean
     */
    protected $onlyOncePerTask = true;
    
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
     * Contains the Exception thrown in the worker
     * Since one worker is designed to do one job, there should be only one exception.
     * @var Throwable
     */
    protected $workerException = null;

    /**
     * @var ZfExtended_EventManager
     */
    protected $events;
    
    /**
     * @var ZfExtended_Worker_Behaviour_Default
     */
    protected $behaviour;
    
    /**
     * Defines the behaviour class to be used for this worker
     * @var string
     */
    protected $behaviourClass = 'ZfExtended_Worker_Behaviour_Default';
    
    /***
     * Is worker thread flag
     *
     * @var boolean
     */
    public $isWorkerThread=true;
    
    public function __construct() {
        $this->log = ZfExtended_Factory::get('ZfExtended_Log');
        $this->maxParallelProcesses = $this->getMaxParallelProcesses();
        $this->events = ZfExtended_Factory::get('ZfExtended_EventManager', array(get_class($this)));
        $this->behaviour = ZfExtended_Factory::get($this->behaviourClass);
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
     * @param string $taskGuid
     * @param array $parameters
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
        $this->workerModel->setHash(bin2hex(random_bytes(32)));
        
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
     * @return ZfExtended_Worker_Abstract mixed a concrete worker corresponding to the submittied worker-model; false if instance could not be initialized;
     */
    static public function instanceByModel(ZfExtended_Models_Worker $model) {
        $instance = ZfExtended_Factory::get($model->getWorker());
        /* @var $instance ZfExtended_Worker_Abstract */
        
        $instance->workerModel = $model;
        
        if (!$instance->init($model->getTaskGuid(), $model->getParameters())) {
            $log = Zend_Registry::get('logger')->cloneMe('core.worker');
            $log->debug('E1219', 'Worker "{worker}" failed on initialisation.', [
                'worker' => get_class($instance),
                'parameters' => $model->getParameters(),
            ]);
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
     * @param string $state optional, defaults to null. Designed to queue a worker with a desired state.
     * @param bool $startNext defaults to true, if true starts directly the queued worker. False to prevent this.
     * @return integer returns the id of the newly created worker DB entry
     */
    public function queue($parentId = 0, $state = NULL, $startNext = true): int {
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
        $this->logit('queued with state '.$this->workerModel->getState());
        
        if($startNext) {
            $this->wakeUpAndStartNextWorkers();
            $this->emulateBlocking();
        }
        
        return (int) $this->workerModel->getId();
    }
    
    /**
     * Schedules prepared workers of same taskGuid and workergroup as the current
     */
    public function schedulePrepared() {
        $this->workerModel->schedulePrepared();
        $this->wakeUpAndStartNextWorkers();
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
        $this->logit('is blocking!');
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
     * Direct run of a worker, if a worker should be runnable directly, define this function public in the in the concrete worker
     * and call this by parent::run();
     * direct calls per run are not mutex-save!
     *
     * @throws Exception
     * @return boolean true if $this->work() runs without errors
     */
    protected function run() {
        $this->isWorkerThread = false;
        $this->checkIsInitCalled();
        Zend_Registry::set('affected_taskGuid', $this->taskGuid); //for TRANSLATE-600 only
        
        $tempSlot = $this->calculateDirectSlot();
        $this->workerModel->setResource($tempSlot['resource']);
        $this->workerModel->setSlot($tempSlot['slot']);
        
        $result = $this->_run();
        
        if(!empty($this->workerException)) {
            throw $this->workerException;
        }
        
        $this->wakeUpAndStartNextWorkers();
        return $result;
    }
    
    /**
     * Mutex save worker-run. Before this function can be called, the worker must be queued with $this->queue();
     *
     * @return boolean true if $this->work() runs without errors
     */
    public function runQueued() {
        $this->checkIsInitCalled();
        if (!$this->workerModel->isMutexAccess()){
            return false;
        }
        
        $result = $this->_run();
        
        $this->wakeUpAndStartNextWorkers();
        
        if(!empty($this->workerException)) {
            if($this->workerException instanceof  Zend_Exception) {
                //when a worker runs into an exception we want to have always a log
                $this->workerException->setLogging(true);
            }
            throw $this->workerException;
        }
        
        return $result;
    }
    
    
    /**
     * inner run function used by run and runQueued
     * @return boolean true if $this->work() runs without errors
     */
    private function _run() {
        //code in the worker can check now if we are in a worker thread or not
        if(!defined('ZFEXTENDED_IS_WORKER_THREAD') && $this->isWorkerThread){
            define('ZFEXTENDED_IS_WORKER_THREAD', true);
        }
        $this->behaviour->setWorkerModel($this->workerModel);
        $this->behaviour->registerShutdown();
        //prefilling the finishedWorker for the following return false step outs
        $this->finishedWorker = clone $this->workerModel;
        
        // checks before parent workers before running
        if(! $this->behaviour->checkParentDefunc()) {
            $this->logit(' set to defunct by parent!');
            return false;
        }
        
        //FIXME diese set calls und save durch eine Update ersetzen, welches task bezogen auf andere runnings dieser resource prüft
        //Dazu: checke im Model ob von außerhalb ein Hash mitgegeben wurde, wenn nein, setze ihn auf 0, damit der checkMutex (der implizit den Hash checkt) kracht.

        if($this->behaviour->isMaintenanceScheduled()) {
            return false;
        }
        
        if(!$this->workerModel->setRunning($this->onlyOncePerTask)){
            //the worker can not set to state run, so don't perform the work
            return false; //FIXME what is this result used for?
        }
        //reload, to get running state and timestamps
        $this->workerModel->load($this->workerModel->getId());
        
        $this->logit('set to running!');
        try {
            $this->events->trigger('beforeWork', $this, [
                'worker' => $this,
                'taskGuid' => $this->taskGuid,
            ]);
            $result = $this->work();
            $this->workerModel->setState(ZfExtended_Models_Worker::STATE_DONE);
            $this->workerModel->setEndtime(new Zend_Db_Expr('NOW()'));
            $this->finishedWorker = clone $this->workerModel;
            $this->retryOnDeadlock(function(){
                $this->workerModel->save();
            });
        } catch(Throwable $workException) {
            $this->reconnectDb();
            $result = false;
            $this->workerModel->setState(ZfExtended_Models_Worker::STATE_DEFUNCT);
            $this->retryOnDeadlock(function(){
                $this->workerModel->save();
            });
            $this->finishedWorker = clone $this->workerModel;
            $this->handleWorkerException($workException);
        }
        $this->updateProgress(1);//update the worker progress to 1, when the worker status is set to done
        return $result;
    }
    
    /**
     * Handles the exception occured while working, by default just store it internally
     * @param Throwable $workException
     */
    protected function handleWorkerException(Throwable $workException) {
        $this->workerException = $workException;
    }
    
    /**
     * if worker exception was destroying DB connection on DB side
     * (for example violating max_allowed_packet or so), each next DB connection would trigger a mysql gone away
     * to prevent that we force a reconnect in case of error
     * Sadly this solves the problems only partly, since the reconnect reconnects the connection here,
     * but not for later connections in this request, why ever...
     */
    protected function reconnectDb() {
        $db = $this->workerModel->db->getAdapter();
        $db->closeConnection();
        $db->getConnection();
    }
    
    protected function wakeUpAndStartNextWorkers() {
        $this->behaviour->wakeUpAndStartNextWorkers($this->workerModel);
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
    
    protected function logit($msg) {
        if(ZfExtended_Debug::hasLevel('core', 'worker')){
            if(!empty($this->workerModel)){
                $msg = 'Worker '.$this->workerModel->getWorker().' ('.$this->workerModel->getId().'): '.$msg;
            }
            if(ZfExtended_Debug::hasLevel('core', 'worker',2)){
                $msg .= "\n".'    by '.$_SERVER['REQUEST_URI'];
            }
            error_log($msg);
        }
    }
    
    /***
     * Update the progres for the current worker model. The progress value needs to be calculated in the worker class.
     * 
     * @param float $progress
     */
    public function updateProgress(float $progress = 1){
        $parentId = $this->workerModel->getParentId() ? $this->workerModel->getParentId() : $this->workerModel->getId();
        $this->workerModel->updateProgress($progress,$parentId);
    }
    
    /***
     * Worker weight/percent of the total import proccess.
     * @return number
     */
    public function getWeight() {
        return 1;
    }
}
