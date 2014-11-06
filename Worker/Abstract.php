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

abstract class ZfExtended_Worker_Abstract { // extends ZfExtended_Models_Worker {
    
    /**
     * @var ZfExtended_Models_Worker
     */
    protected $workerModel = false;
    
    /**
     * Number of allowed parallel processes for a certain worker-type
     * @var integer
     */
    protected $maxParallelProcesses = 1;
    
    /**
     * This constant values define the different blocking-types
     * @var integer
     */
    const BLOCK_GLOBAL = 0;
    const BLOCK_TYPE = 0;
    const BLOCK_TYPEANDSLOT = 0;
    
    /**
     * Number of allowed parallel processes for a certain worker-type
     * @var const blocking-type BLOCK_XYZ
     */
    protected $blockingType = self::BLOCK_TYPEANDSLOT;
    
    
    
    /**
     * Initialize a worker and a internal worker-model 
     * 
     * @param string $taskGuid
     * @param array $parameters stored in the worker-model
     */
    public function init($taskGuid = NULL, $parameters = array()) {
        //error_log(__CLASS__.' -> '.__FUNCTION__);
        $this->workerModel = ZfExtended_Factory::get('ZfExtended_Models_Worker');
        
        $this->workerModel->setState(ZfExtended_Models_Worker::STATE_WAITING);
        $this->workerModel->setWorker(get_class($this));
        $this->workerModel->setTaskGuid($taskGuid);
        $this->workerModel->setParameters(serialize($parameters));
        //error_log('Startzeit: '.$this->workerModel->getStarttime());
        
        //$id = $this->workerModel->save();
        //$this->workerModel->load($id);
        //error_log(print_r($this->workerModel, true));
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
     * @return mixed a concrete worker corresponding to the submittied worker-model
     */
    static public function instanceByModel(ZfExtended_Models_Worker $model) {
        //error_log(__CLASS__.' -> '.__FUNCTION__.'; worker: '.$model->workerModel->getWorker());
        //return;
        
        // !!! this only works if all __construct() has same function-parameters
        $instance = ZfExtended_Factory::get($model->getWorker());
        $instance->init($model->getTaskGuid(), array($model->getParameters()));
        $instance->workerModel = $model;
        return $instance;
    }
    
    
    public function queue($taskGuid = NULL) {
        
        // SBE: why this ??
        $this->workerModel->setTaskGuid($taskGuid);
        
        $this->workerModel->setSlot($this->calculateQueuedSlot());
    }
    
    protected function calculateQueuedSlot() {
        return 'default';
    }
    
    protected function calculateDirectSlot() {
        return $this->calculateQueuedSlot();
    }
    
    
    
    
    protected function run($taskGuid = NULL) {
        error_log(__CLASS__.' -> '.__FUNCTION__);
        $this->workerModel->setStarttime(new Zend_Db_Expr('NOW()'));
        $this->workerModel->setHash(uniqid(NULL, true));
        // alternative try restore serialized class
        $this->workerModel->setClassDump(serialize($this));
        
        $this->workerModel->save();
    }
    
    
    public function runQueued() {
        error_log(__CLASS__.' -> '.__FUNCTION__);
        if (!$this->workerModel->setRunningMutex())
        {
            return false;
        }
    }
    
}