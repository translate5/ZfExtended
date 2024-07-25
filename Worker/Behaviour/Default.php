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

use MittagQI\ZfExtended\Worker\Trigger\Factory as WorkerTriggerFactory;

class ZfExtended_Worker_Behaviour_Default
{
    use ZfExtended_Controllers_MaintenanceTrait;

    protected ZfExtended_Models_Worker $workerModel;

    /**
     * Some default behaviour can be configured (instead overwriting this class for just a small configurable change)
     */
    protected array $config = [
        // false => return always false, so do not stop worker if maintenance is scheduled
        // true => call isMaintenanceLoginLock check
        'isMaintenanceScheduled' => false,
        // Multi-instance workers where several instances work on the same workload /usually processing segments)
        'isMultiInstance' => false,
    ];

    /**
     * some behaviour is configurable ($config['configName'] => value):
     * "isMaintenanceScheduled":
     *   false: worker ignores maintenance,
     *   true: isMaintenanceLoginLock check is called,
     *   integer: disallow worker run the value in minutes before scheduled maintenance
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * returns the internal configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function setWorkerModel(ZfExtended_Models_Worker $workerModel): void
    {
        $this->workerModel = $workerModel;
    }

    /**
     * Checks the parent workers if they are defunct, if yes set this worker also to defunct and return false
     * @return boolean returns true when all is OK, false when a parent worker is defunct
     * @throws Zend_Db_Statement_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     */
    public function checkParentDefunc(): bool
    {
        $summary = $this->workerModel->getParentSummary();
        $defunc = [];
        foreach ($summary as $result) {
            //when a non defunc worker was found, the whole group of same workers is considered as non defunc
            // for example multiple termtagger import calls can contain some defunc workers,
            // this should not set the whole worker group to defunc
            if (isset($defunc[$result->worker]) && $defunc[$result->worker] !== false) {
                continue;
            }
            $defunc[$result->worker] = $result->state == $this->workerModel::STATE_DEFUNCT;
        }
        $defunc = array_filter($defunc);
        //no defunc workers found
        if (empty($defunc)) {
            return true;
        }
        $this->workerModel->setState($this->workerModel::STATE_DEFUNCT);
        $this->workerModel->save();

        return false;
    }

    /**
     * sets the worker model to defunct when a fatal error happens
     */
    public function registerShutdown(): void
    {
        register_shutdown_function(function ($wm) {
            $error = error_get_last();
            if (! is_null($error) && ($error['type'] & FATAL_ERRORS_TO_HANDLE)) {
                $wm->setState(ZfExtended_Models_Worker::STATE_DEFUNCT);
                $wm->save();
            }
        }, $this->workerModel);
    }

    /**
     * wake up scheduled workers and start next waiting workers
     * @throws Zend_Exception
     */
    public function wakeUpAndStartNextWorkers(): void
    {
        WorkerTriggerFactory::create()->triggerQueue();
    }

    /**
     * By default workers do not check if maintenance is scheduled.
     * This can be overwritten by worker.
     * @throws Zend_Exception
     */
    public function isMaintenanceScheduled(): bool
    {
        $conf = $this->config['isMaintenanceScheduled'];
        if ($conf === false) {
            return false;
        }

        return $this->isMaintenanceLoginLock(is_int($conf) ? $conf : null);
    }

    /**
     * Some workers work in parallel on the same workload
     */
    public function isMultiInstance(): bool
    {
        return $this->config['isMultiInstance'];
    }
}
