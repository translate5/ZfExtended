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

abstract class ZfExtended_Worker_Abstract {
    
    /**
     * @var ZfExtended_Models_Worker
     */
    protected $workerModel = false;
    
    /**
     * Number of allowed parallel processes for a certain worker
     * @var integer
     */
    protected $maxParallelProcesses = 1;
    
    
    /**
     * This constant values define the different blocking-types
     * @var string
     */
    const BLOCK_SLOT = 'slot';
    const BLOCK_RESOURCE = 'resource';
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
     * Initialize a worker and a internal worker-model 
     * 
     * @param string $taskGuid
     * @param array $parameters stored in the worker-model
     * 
     * @return boolean true if worker cann be initialized.
     */
    public function init($taskGuid = NULL, $parameters = array()) {
        //error_log(__CLASS__.' -> '.__FUNCTION__);
        
        if (!$this->validateParameters($parameters)) {
            //error_log(__CLASS__.' -> '.__FUNCTION__.' Parameters can not be validated');
            return false;
        }
        
        $this->workerModel = ZfExtended_Factory::get('ZfExtended_Models_Worker');
        
        $this->workerModel->setState(ZfExtended_Models_Worker::STATE_WAITING);
        $this->workerModel->setWorker(get_class($this));
        $this->workerModel->setTaskGuid($taskGuid);
        $this->workerModel->setParameters(serialize($parameters));
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
     * Get a worker-instance from a worker-model
     * 
     * @param ZfExtended_Models_Worker $model
     * @return mixed a concrete worker corresponding to the submittied worker-model; false if instance could not be initialized;
     */
    static public function instanceByModel(ZfExtended_Models_Worker $model) {
        //error_log(__CLASS__.' -> '.__FUNCTION__.'; worker: '.$model->getWorker());
        $instance = ZfExtended_Factory::get($model->getWorker());
        if (!$instance->init($model->getTaskGuid(), unserialize($model->getParameters()))) {
            return false;
        }
        
        $instance->workerModel = $model;
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
    
    
    public function queue() {
        //error_log(__CLASS__.' -> '.__FUNCTION__);
        $tempSlot = $this->calculateQueuedSlot();
        $this->workerModel->setResource($tempSlot['resource']);
        $this->workerModel->setSlot($tempSlot['slot']);
        $this->workerModel->save();
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
    protected function run() {
        //error_log(__CLASS__.' -> '.__FUNCTION__);
        return $this->_run($this->calculateDirectSlot());
    }
    
    /**
     * Mutex save worker-run. Before this function can be called, the worker must be queued with $this->queue();
     * 
     * @return boolean true if $this->work() runs without errors
     */
    public function runQueued() {
        //error_log(__CLASS__.' -> '.__FUNCTION__);
        if (!$this->workerModel->setRunningMutex())
        {
            return false;
        }
        $result = $this->_run($this->calculateQueuedSlot());
        
        return $result;
    }
    
    
    /**
     * inner run function used by run and runQueued
     * 
     * @param array $resource = array('resource' => resourceName, 'slot' => slotName)
     * @return boolean true if $this->work() runs without errors
     */
    private function _run($resource = array()) {
        //error_log(__CLASS__.' -> '.__FUNCTION__);
        $this->workerModel->setState(ZfExtended_Models_Worker::STATE_RUNNING);
        $this->workerModel->setResource($resource['resource']);
        $this->workerModel->setSlot($resource['slot']);
        $this->workerModel->setStarttime(new Zend_Db_Expr('NOW()'));
        $this->workerModel->setMaxRuntime(new Zend_Db_Expr('NOW() + INTERVAL '.$this->workerModel->getMaxLifetime()));
        $this->workerModel->setPid(getmypid());
        
        $this->workerModel->save();
        $result = $this->work();
        $this->workerModel->delete();
        
        $this->startWorkerQueue();
        
        return $result;
    }
    
    /**
     * trigger application-wide worker-queue
     */
    private function startWorkerQueue() {
        // Stephan:
        // done by calling ZfExtended_Worker_Queue -> process();
        
        // VS.
        
        // Thomas:
        // start workerQueue by calling the REST-Controller
        // something like:
        // $ch = curl_init('/url/to/restcontroller');
        // curl_exec($ch);
        // curl_close($ch);
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
    
}