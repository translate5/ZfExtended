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
        if(!is_null($this->cleanupSessionAfterRun)){
            $session = new Zend_Session_Namespace();
            $SessionMapInternalUniqIdTable = new ZfExtended_Models_Db_SessionMapInternalUniqId();
            $SessionMapInternalUniqIdTable->update(array('modified'=>0),'`internalSessionUniqId` = \''.$session->internalSessionUniqId.'\'');
            $sessionTable = new ZfExtended_Models_Db_Session();
            $sessionTable->update(array('modified'=>0),'`session_id` = \''.Zend_Session::getId().'\'');
        }
    }
    
    public function init() {
        parent::init();
        $this->cleanupSessionAfterRun = $this->_getParam('cleanupSessionAfterRun');
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
        try {
            $this->entity->load($this->_getParam('id'));
        }
        catch (ZfExtended_Models_Entity_NotFoundException $workerLoad) {
            return false;
        }
        
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
    }
    
    public function deleteAction() {
        throw new ZfExtended_BadMethodCallException(__CLASS__.'->'.__FUNCTION__);
    }
    
    /**
     * Not a real REST-action
     * Interface-function to trigger the application-wide workerQueue-Process
     */
    public function queueAction () {
        $this->_helper->viewRenderer->setNoRender(false);
        $workerQueue = ZfExtended_Factory::get('ZfExtended_Worker_Queue');
        /* @var $workerQueue ZfExtended_Worker_Queue */
        $workerQueue->process();
        //$this->postDispatch(); needed for TRANSLATE-249
        exit;//since we have no output here, we will exit immediatelly
    }
}
