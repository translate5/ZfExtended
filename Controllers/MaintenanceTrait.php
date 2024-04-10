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

trait ZfExtended_Controllers_MaintenanceTrait
{
    protected $enableMaintenanceHeader = true;

    /**
     * Flag which can be used in the controller using this trait to check if maintenance is scheduled
     * @var boolean
     */
    protected $maintenanceIsScheduled = false;

    public function displayMaintenance()
    {
        $this->maintenanceIsScheduled = false;
        if ($this->_response->isException()) {
            return;
        }
        $config = Zend_Registry::get('config');
        if (! isset($config->runtimeOptions->maintenance)) {
            return;
        }
        //FIXME proxy config must be automated by a an env setting the proxy hostname, must be configurable for
        // setups with additional proxies
        $ip = new ZfExtended_RemoteAddress();
        $ip->setProxyHeader('HTTP_X_REAL_IP');
        $ip->setTrustedProxies(['proxy']);
        $ip->setUseProxy();
        if (isset($config->runtimeOptions->maintenance->allowedIPs)) {
            $allowedByIP = in_array($ip->getIpAddress(), $config->runtimeOptions->maintenance->allowedIPs->toArray() ?? []);
        } else {
            $allowedByIP = false;
        }

        //maintenance can be enabled by setting a debug level or for just testing the layout by adding the testmaintenance=1 parameter to the URL
        $directMaintenance = ZfExtended_Debug::hasLevel('core', 'maintenance') || ! empty($_GET['testmaintenance']);
        $maintenanceStartDate = $config->runtimeOptions->maintenance->startDate;
        $maintenanceMessage = $config->runtimeOptions->maintenance->message ?? '';

        //if there is no date and the start date is not in the next 24H, then just show a message if configured
        if (! $directMaintenance && (! $maintenanceStartDate || ! (strtotime($maintenanceStartDate) <= (time() + 86400)))) {
            if (! empty($maintenanceMessage)) {
                $this->_response->setHeader('x-translate5-maintenance-message', $maintenanceMessage);
                $this->view->displayMaintenancePanel = true;
            }

            return;
        }

        //since database maintenance is also part of maintenance, its controller should be able to run
        if ($this->_request->getControllerName() === 'database') {
            return;
        }

        if ($directMaintenance || new DateTime() >= new DateTime($maintenanceStartDate)) {
            if ($allowedByIP) {
                $this->_response->setHeader('x-translate5-maintenance-message', 'Maintenance is active! But your IP is allowed to access the application.');
                $this->view->displayMaintenancePanel = true;

                return;
            }
            $this->_response->setHeader('x-translate5-shownotice', date(DATE_ISO8601, strtotime($maintenanceStartDate)));
            $this->_response->setHeader('x-translate5-maintenance-message', $maintenanceMessage);

            throw new ZfExtended_Models_MaintenanceException();
        }
        $maintenanceTimeToNotify = max(1, (int) $config->runtimeOptions->maintenance->timeToNotify);

        $maintenanceStartDate = strtotime($maintenanceStartDate);
        $time = $maintenanceStartDate - ($maintenanceTimeToNotify * 60);

        $date = new DateTime(date("Y-m-d H:i:s", $time));

        if (new DateTime() < $date) {
            return;
        }
        $this->maintenanceIsScheduled = true;

        if ($this->enableMaintenanceHeader) {
            $this->_response->setHeader('x-translate5-shownotice', date(DATE_ISO8601, $maintenanceStartDate));
            if ($allowedByIP) {
                $maintenanceMessage .= ' But your IP will still have access!';
            }
            if (! empty($maintenanceMessage)) {
                $this->_response->setHeader('x-translate5-maintenance-message', $maintenanceMessage);
            }
        }
        $this->view->displayMaintenancePanel = true;
    }

    /**
     * Locks the login (configurable minutes) before the mainteance mode
     * @param int|null $timeToMaintenance optional, provide an integer here to compare the maintenance start against, instead the configured loginLock time
     * @return boolean
     * @throws Zend_Exception
     */
    protected function isMaintenanceLoginLock(int $timeToMaintenance = null): bool
    {
        return ZfExtended_Models_Installer_Maintenance::isLoginLock($timeToMaintenance);
    }
}
