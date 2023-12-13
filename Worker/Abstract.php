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
     * @var string taskGuid
     */
    protected $taskGuid;
    
    /**
     * @var ZfExtended_Models_Worker
     */
    protected $finishedWorker;

    /**
     * Blocking-type for this worker
     * Default is the Resource-based Blocking
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
     * @var ZfExtended_Logger
     */
    protected ZfExtended_Logger $log;

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

    public function __construct() {
        $this->log = Zend_Registry::get('logger');
        $this->events = ZfExtended_Factory::get('ZfExtended_EventManager', array(get_class($this)));
        $this->behaviour = ZfExtended_Factory::get($this->behaviourClass);
    }

    /**
     * Retrieves the max workers that may run in parallel (optional config)
     * @return int
     * @throws Zend_Exception
     */
    protected function getMaxParallelProcesses(): int {
        $config = Zend_Registry::get('config');
        $class = get_class($this);
        $workerConfig = $config->runtimeOptions->worker->$class;
        // the max parallel config is only optional
        if(empty($workerConfig) || empty($workerConfig->maxParallelWorkers)) {
            return 1;
        }
        return (int) $workerConfig->maxParallelWorkers;
    }
    
    /**
     * Initialize a worker and a internal worker-model
     *
     * @param string $taskGuid
     * @param array $parameters stored in the worker-model
     *
     * @return boolean true if worker can be initialized.
     */
    public function init($taskGuid = NULL, $parameters = []) {
        if (!$this->validateParameters($parameters)) {
            return false;
        }
        $this->taskGuid = $taskGuid;
        $this->initWorkerModel($taskGuid, $parameters);
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
     * @return ZfExtended_Worker_Abstract|false mixed a concrete worker corresponding to the submittied worker-model; false if instance could not be initialized;
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
        $slot = $this->calculateSlot();
        $this->saveWorkerModel($slot['resource'], $slot['slot'], $parentId, $this->getMaxParallelProcesses(), $state);

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
     * @param int $timeOut
     */
    public function setBlocking(bool $blocking = true, int $timeOut = 3600) {
        $this->isBlocking = $blocking;
        $this->blockingTimeout = $timeOut;
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
    protected function calculateSlot(): array {
        return array('resource' => $this->workerModel->getWorker(), 'slot' => 'default');
    }

    /**
     * Mutex save worker-run. Before this function can be called, the worker must be queued with $this->queue();
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
            if($this->workerException instanceof ZfExtended_Exception) {
                //when a worker runs into an exception we want to have always a log
                $this->workerException->setLogging(true);
            }
            throw $this->workerException;
        }
        
        return $result;
    }
    
    /**
     * inner run function used by runQueued
     * @return boolean true if $this->work() runs without errors
     */
    private function _run() {
        //code in the worker can check if it is running in the context of a worker
        if(!defined('ZFEXTENDED_IS_WORKER_THREAD')){
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
             // do the actual work
            $result = $this->work();
            
            $progressDone = $this->calculateProgressDone();
            $this->workerModel->setState(ZfExtended_Models_Worker::STATE_DONE);
            $this->workerModel->setProgress($progressDone);
            $this->workerModel->setEndtime(new Zend_Db_Expr('NOW()'));
            $this->finishedWorker = clone $this->workerModel;
            if (! $this->saveWorkerDeadlockProof()) {
                return false;
            }
            $this->onProgressUpdated($progressDone);

        } catch (Throwable $workException) {

            $this->reconnectDb();
            $result = false;
            $this->workerModel->setState(ZfExtended_Models_Worker::STATE_DEFUNCT);
            $this->saveWorkerDeadlockProof();
            $this->finishedWorker = clone $this->workerModel;
            $this->handleWorkerException($workException);
        }
        $this->workerModel->load($this->workerModel->getId());
        $this->logWorkerUsage();

        return $result;
    }

    /**
     * saves the current worker, returns false if it could not be saved due missing worker in DB
     * @return bool
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Db_Exceptions_DeadLockHandler
     */
    private function saveWorkerDeadlockProof(): bool
    {
        try {
            $this->retryOnDeadlock(function () {
                $this->workerModel->save();
            });
            return true;
        } catch (Zend_Db_Table_Row_Exception $e) {
            if (str_contains($e->getMessage(), 'Cannot refresh row as parent is missing')) {
                return false;
            }
            throw $e;
        }
    }


    /**
     * Update the progress for the current worker model with the given value (float between 0 ad 1)
     *
     * @param float $progress
     * @throws ZfExtended_Models_Db_Exceptions_DeadLockHandler
     */
    public function updateProgress(float $progress){
        if($this->workerModel->updateProgress($progress)){
            $this->onProgressUpdated($progress);
        }
    }

    /**
     * Calculates the progress when we're done
     * @return float
     */
    protected function calculateProgressDone() : float {
        return 1;
    }

    /**
     * trigger function to add logic needed to be done after progress was updated
     * @param float $progress
     */
    protected function onProgressUpdated(float $progress){

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
     * Get the result-values of processing $this->work();
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

    /**
     * Saves our worker model on queuing
     * @param string $resource
     * @param string $slot
     * @param int $parentId
     * @param int $maxParallel
     * @param string|null $state
     * @throws Zend_Db_Statement_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     */
    private function saveWorkerModel(string $resource, string $slot, int $parentId, int $maxParallel, string $state = null){
        $this->workerModel->setResource($resource);
        $this->workerModel->setSlot($slot);
        $this->workerModel->setParentId($parentId);
        $this->workerModel->setMaxParallelProcesses($maxParallel);
        if(!is_null($state)){
            $this->workerModel->setState($state);
        }
        $this->workerModel->save();
        $this->logit('queued with state '.$this->workerModel->getState());
    }

    /**
     * creates the internal worker model ready for DB storage if it not already exists.
     * The latter case happens when using instanceByModels
     *
     * @param string|null $taskGuid
     * @param array $parameters
     */
    private function initWorkerModel(?string $taskGuid, array $parameters) {
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
     * @param $msg
     */
    protected function logit($msg) {
        if(ZfExtended_Debug::hasLevel('core', 'worker')){
            if(!empty($this->workerModel)){
                $msg = 'Worker '.$this->workerModel->getWorker().' ('.$this->workerModel->getId().'): '.$msg;
            }
            if(ZfExtended_Debug::hasLevel('core', 'worker', 2)){
                $msg .= "\n".'    by '.$_SERVER['REQUEST_URI'];
            }
            error_log($msg);
        }
    }

    /**
     * @return void
     */
    private function logWorkerUsage(): void
    {
        $duration = 0;
        $start = strtotime($this->workerModel->getStarttime() ?? '');

        $end = $this->workerModel->getEndtime();
        if (! is_null($end)) {
            $end = strtotime($this->workerModel->getEndtime());
        }

        if (is_null($end) || $end === false) {
           $end = time();
        }

        if ($start) {
            $duration = $end - $start;
        }

        $this->log->info(
            'E1547',
            'Worker {worker} ({id}) needed {duration}s and is now {state}',
            [
                'task' => $this->workerModel->getTaskGuid(),
                'worker' => $this->workerModel->getWorker(),
                'id' => $this->workerModel->getId(),
                'start' => $this->workerModel->getStarttime(),
                'end' => $this->workerModel->getEndtime(),
                'duration' => $duration,
                'state' => $this->workerModel->getState(),
            ]
        );
    }
}
