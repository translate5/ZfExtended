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
 *
 */
class ZfExtended_WorkerController extends ZfExtended_RestController {
    
    protected $entityClass = 'ZfExtended_Models_Worker';
    
    /**
     * @var ZfExtended_Models_Worker
     */
    protected $entity;
    
    
    public function postAction() {
        error_log(__CLASS__.'->'.__FUNCTION__);
    }
    
    public function putAction() {
        error_log(__CLASS__.'->'.__FUNCTION__);
        $this->entity->load($this->_getParam('id'));
        $oldWorker = clone $this->entity;
        
        // set "default-return" = current workerModel-data from database
        $this->view->rows = $oldWorker->getDataObject();
        
        $this->decodePutData();
        $this->setDataInEntity();
        
        if( $oldWorker->getState() == ZfExtended_Models_Worker::STATE_WAITING
            && $this->entity->getState() == ZfExtended_Models_Worker::STATE_RUNNING) {
            
            $this->entity->setState(ZfExtended_Models_Worker::STATE_WAITING);
            
            $worker = ZfExtended_Worker_Abstract::instanceByModel($this->entity);
            
            if (!$worker) {
                error_log(__CLASS__.'->'.__FUNCTION__.'; worker can not be instanciated. $this->entity: '.print_r($this->entity->getDataObject(), true));
                return false;
            }
            /* @var $worker editor_Worker_TermTagger */
            if (!$worker->runQueued()) {
                // TODO what to do if worker can not be runQueued (e.g. because it can not be set to mutex-save)
                return false;
            }
            
            // set return as workerModel after runQueued() 
            // normaly should have state='done' if everything went well,
            $this->view->rows = $worker->getModelBeforeDelete()->getDataObject();
            
            //error_log(__CLASS__.'->'.__FUNCTION__.'; $this->view->rows: '.print_r($this->view->rows, true));
            return;
        }
    }
    
    
    //if using this method, it must be ensured that the caller is allowed to see the stored hash values!
    public function indexAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->'.__FUNCTION__);
    }
    
    //if using this method, it must be ensured that the caller is allowed to see the stored hash values!
    public function getAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->'.__FUNCTION__);
        //parent::getAction();
        //error_log(__CLASS__.'->'.__FUNCTION__.'; $this->view->rows: '.print_r($this->view->rows, true));
    }
    
    public function deleteAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->'.__FUNCTION__);
    }
    
    /**
     * Not a real REST-action
     * Interface-function to trigger the application-wide workerQueue-Process
     */
    public function queueAction () {
        error_log(__CLASS__.'->'.__FUNCTION__);
        $workerQueue = ZfExtended_Factory::get('ZfExtended_Worker_Queue');
        /* @var $workerQueue ZfExtended_Worker_Queue */
        $workerQueue->process();
    }
}