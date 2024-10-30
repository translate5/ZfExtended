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

use MittagQI\ZfExtended\Worker\Queue;
use MittagQI\ZfExtended\Worker\Trigger\Http;

/**
 * Important: putAction and queueAction are deleting their session so that no new session entry is created
 * Therefore, these actions are not CSRF-protected
 * On futural usage of the postAction (to be used from frontend for direct calls) this indeed should not be the case!
 *
 * @property ZfExtended_Models_Worker $entity
 */
class ZfExtended_WorkerController extends ZfExtended_RestController
{
    protected $entityClass = ZfExtended_Models_Worker::class;

    /**
     * Generally the worker-endpoints are CSRF unprotected, a protection is achieved via the worker-hash
     * @var string[]
     */
    protected array $_unprotectedActions = ['put', 'queue'];

    protected bool $cleanupSessionAfterRun = false;

    public function __destruct()
    {
        if ($this->cleanupSessionAfterRun) {
            $sessionTable = new ZfExtended_Models_Db_Session();
            $sessionTable->update([
                'modified' => 0,
            ], '`session_id` = \'' . Zend_Session::getId() . '\'');
        }
    }

    public function init()
    {
        parent::init();
        $this->cleanupSessionAfterRun = (bool) $this->_request->getHeader(Http::WORKER_HEADER);
    }

    /**
     * (non-PHPdoc)
     * @throws ZfExtended_BadMethodCallException
     * @see ZfExtended_RestController::postAction()
     * For session handling see class head comment
     */
    public function postAction()
    {
        throw new ZfExtended_BadMethodCallException(__CLASS__ . '->' . __FUNCTION__);
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
     * @throws Throwable
     * @throws Zend_Exception
     * @throws ZfExtended_Exception
     * @throws ZfExtended_Models_Entity_NotFoundException
     * @throws ZfExtended_Models_MaintenanceException
     * @see ZfExtended_RestController::putAction()
     */
    public function putAction()
    {
        try {
            $this->entity->load($this->getParam('id'));
        } catch (ZfExtended_Models_Entity_NotFoundException) {
            //we catch the not found here, since it can just happen in normal handling that a worker is triggered
            // which is already deleted to prevent unwanted 404s here we just catch that not found messages and do
            // nothing instead to prevent false positives when having multiple translate5 installations (and one has
            // wrong server.name) it can happen, that the worker requests go to the wrong translate5 installation.
            // Since no 404 is logged, we don't find out that easily.
            // As solution we send additionally a server id which must match in order to run workers.
            $this->decodePutData();
            $this->testServerId($this->data->serverId);

            return;
        }
        //if maintenance is near, we disallow starting workers
        if ($this->isMaintenanceLoginLock()) {
            throw new ZfExtended_Models_MaintenanceException();
        }

        $oldWorker = clone $this->entity;

        // set "default-return" = current workerModel-data from database
        $this->view->rows = $oldWorker->getDataObject();

        $this->decodePutData();
        $this->testServerId($this->data->serverId);
        $this->setDataInEntity();

        if ($oldWorker->getState() == ZfExtended_Models_Worker::STATE_WAITING
            && $this->entity->getState() == ZfExtended_Models_Worker::STATE_RUNNING) {
            $this->entity->setState(ZfExtended_Models_Worker::STATE_WAITING);
            /* @var ZfExtended_Worker_Abstract $worker */
            $worker = ZfExtended_Worker_Abstract::instanceByModel($this->entity);

            if (! $worker) {
                return;
            }
            if (! $worker->runQueued()) {
                return;
            }

            // set return as workerModel after runQueued()
            // normaly should have state='done' if everything went well,
            $this->view->rows = $worker->getModelBeforeDelete()->getDataObject();
        }
    }

    /**
     * tests if the given serverId is the one from the current installation
     * @throws ZfExtended_Models_Entity_NotFoundException
     */
    protected function testServerId(?string $givenId): void
    {
        if ($givenId == Http::WORKER_CHECK_IGNORE) {
            $localId = $givenId;
        } else {
            $localId = ZfExtended_Utils::installationHash('ZfExtended_Worker_Abstract');
        }

        $this->_response->setHeader(ZfExtended_Models_Worker::WORKER_SERVERID_HEADER, $localId);
        if ($givenId !== $localId) {
            throw new ZfExtended_Models_Entity_NotFoundException(
                'Server ID does not match, called worker on wrong server.'
            );
        }
    }

    //if using this method, it must be ensured that the caller is allowed to see the stored hash values!

    /**
     * @throws ZfExtended_BadMethodCallException
     */
    public function indexAction()
    {
        throw new ZfExtended_BadMethodCallException(__CLASS__ . '->' . __FUNCTION__);
    }

    //if using this method, it must be ensured that the caller is allowed to see the stored hash values!

    /**
     * @throws ZfExtended_BadMethodCallException
     */
    public function getAction()
    {
        throw new ZfExtended_BadMethodCallException(__CLASS__ . '->' . __FUNCTION__);
    }

    /**
     * @throws ZfExtended_BadMethodCallException
     */
    public function deleteAction()
    {
        throw new ZfExtended_BadMethodCallException(__CLASS__ . '->' . __FUNCTION__);
    }

    /**
     * Not a real REST-action
     * Interface-function to trigger the application-wide workerQueue-Process
     * @throws ReflectionException
     * @throws Zend_Exception
     */
    public function queueAction(): void
    {
        $this->_response->setHeader(
            ZfExtended_Models_Worker::WORKER_SERVERID_HEADER,
            ZfExtended_Utils::installationHash('ZfExtended_Worker_Abstract')
        );
        $this->_response->sendHeaders();
        $this->_helper->viewRenderer->setNoRender(false);

        // trigger the queue if possible
        Queue::processQueueMutexed();

        //$this->postDispatch(); needed for TRANSLATE-249
        exit; //since we have no output here, we will exit immediatelly
    }
}
