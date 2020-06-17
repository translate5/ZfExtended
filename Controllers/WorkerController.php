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
 * Important: putAction and queueAction are deleting their session so that no new session entry is created
 * On futural usage of the postAction (to be used from frontend for direct calls) this indeed should not be the case!
 */
class ZfExtended_WorkerController extends ZfExtended_RestController {
    
    protected $entityClass = 'ZfExtended_Models_Worker';
    
    /**
     * @var ZfExtended_Models_Worker
     */
    protected $entity;
    
    
    protected $cleanupSessionAfterRun;
    
    
    public function __destruct() {
        if($this->cleanupSessionAfterRun){
            $session = new Zend_Session_Namespace();
            $SessionMapInternalUniqIdTable = new ZfExtended_Models_Db_SessionMapInternalUniqId();
            $SessionMapInternalUniqIdTable->update(array('modified'=>0),'`internalSessionUniqId` = \''.$session->internalSessionUniqId.'\'');
            $sessionTable = new ZfExtended_Models_Db_Session();
            $sessionTable->update(array('modified'=>0),'`session_id` = \''.Zend_Session::getId().'\'');
        }
    }
    
    public function init() {
        parent::init();
        $this->cleanupSessionAfterRun = (bool) $this->_request->getHeader(ZfExtended_Worker_TriggerByHttp::WORKER_HEADER);
    }
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::postAction()
     * For session handling see class head comment
     */
    public function postAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->'.__FUNCTION__);
    }
    
    /**
     * Destroys the session for some worker actions, see class head comment
     * (non-PHPdoc)
     * @see ZfExtended_RestController::postDispatch()
     */
    //SEE TRANSLATE-349
    //public function postDispatch() {
        //parent::postDispatch();
        //$action = $this->_request->getActionName();
        //if($action == 'queue' || $action == 'put') {
            //Zend_Session::destroy(true);
        //}
    //}
    
    /**
     * (non-PHPdoc)
     * @see ZfExtended_RestController::putAction()
     */
    public function putAction() {
        //if maintenance is scheduled we disallow starting workers
        if($this->maintenanceIsScheduled) {
            throw new ZfExtended_Models_MaintenanceException();
        }
        try {
            $this->entity->load($this->getParam('id'));
        }
        catch (ZfExtended_Models_Entity_NotFoundException $workerLoad) {
            //we catch the not found here, since it can just happen in normal handling that a worker is triggered which is already deleted
            // to prevent unwanted 404s here we just catch that not found messages and do nothing instead
            // to prevent false positives when having multiple translate5 installations (and one has wrong server.name) it can happen,
            // that the worker requests go to the wrong translate5 installation. Since no 404 is logged, we don't find out that easily.
            // as soplution we send additionally a server id which must match in order to run workers.
            $this->decodePutData();
            $this->testServerId($this->data->serverId);
            return false;
        }
        
        $oldWorker = clone $this->entity;
        
        // set "default-return" = current workerModel-data from database
        $this->view->rows = $oldWorker->getDataObject();
        
        $this->decodePutData();
        $this->testServerId($this->data->serverId);
        $this->setDataInEntity();
        
        if( $oldWorker->getState() == ZfExtended_Models_Worker::STATE_WAITING
            && $this->entity->getState() == ZfExtended_Models_Worker::STATE_RUNNING) {
            
            $this->entity->setState(ZfExtended_Models_Worker::STATE_WAITING);
            
            $worker = ZfExtended_Worker_Abstract::instanceByModel($this->entity);
            
            if (!$worker) {
                return false;
            }
            /* @var $worker ZfExtended_Worker_Abstract */
            if (!$worker->runQueued()) {
                // TODO what to do if worker can not be runQueued (e.g. because it can not be set to mutex-save)
                return false;
            }
            
            // set return as workerModel after runQueued()
            // normaly should have state='done' if everything went well,
            $this->view->rows = $worker->getModelBeforeDelete()->getDataObject();
        }
    }

    /**
     * tests if the given serverId is the one from the current installation
     * @param string $givenId
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    protected function testServerId($givenId) {
        $localId = ZfExtended_Utils::installationHash('ZfExtended_Worker_Abstract');
        $this->_response->setHeader(ZfExtended_Models_Worker::WORKER_SERVERID_HEADER, $localId);
        if($givenId !== $localId) {
            throw new ZfExtended_Models_Entity_NotFoundException('Server ID does not match, called worker on wrong server.');
        }
    }
    
    //if using this method, it must be ensured that the caller is allowed to see the stored hash values!
    public function indexAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->'.__FUNCTION__);
    }
    
    //if using this method, it must be ensured that the caller is allowed to see the stored hash values!
    public function getAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->'.__FUNCTION__);
    }
    
    public function deleteAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->'.__FUNCTION__);
    }
    
    /**
     * Not a real REST-action
     * Interface-function to trigger the application-wide workerQueue-Process
     */
    public function queueAction () {
        $this->_response->setHeader(ZfExtended_Models_Worker::WORKER_SERVERID_HEADER, ZfExtended_Utils::installationHash('ZfExtended_Worker_Abstract'));
        $this->_response->sendHeaders();
        $this->_helper->viewRenderer->setNoRender(false);
        $workerQueue = ZfExtended_Factory::get('ZfExtended_Worker_Queue');
        /* @var $workerQueue ZfExtended_Worker_Queue */
        $workerQueue->process();
        //$this->postDispatch(); needed for TRANSLATE-249
        exit;//since we have no output here, we will exit immediatelly
    }
}
