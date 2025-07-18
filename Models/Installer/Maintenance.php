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

class ZfExtended_Models_Installer_Maintenance
{
    protected Zend_Config $config;

    protected Zend_Db_Adapter_Abstract $db;

    /**
     * @throws Zend_Exception
     * @throws Zend_Db_Exception
     */
    public function __construct()
    {
        $this->config = Zend_Registry::get('config');
        $this->db = Zend_Db::factory($this->config->resources->db);
    }

    /**
     * returns also true if maintenance is active
     * @throws Zend_Exception
     * @throws Exception
     */
    public static function isLoginLock(?int $timeToMaintenance = null): bool
    {
        /* @var $config Zend_Config */
        $config = Zend_Registry::get('config');
        $rop = $config->runtimeOptions;
        if (isset($config->runtimeOptions->maintenance->allowedIPs)) {
            $ip = new ZfExtended_RemoteAddress();
            $ip->setProxyHeader('HTTP_X_REAL_IP');
            $ip->setTrustedProxies(['proxy']);
            $ip->setUseProxy();
            $allowedIPs = $config->runtimeOptions->maintenance->allowedIPs->toArray() ?? [];
            $allowedByIP = in_array($ip->getIpAddress() ?: null, $allowedIPs);
        } else {
            $allowedByIP = false;
        }
        if (! isset($rop->maintenance) || $allowedByIP) {
            return false;
        }

        $maintenanceStartDate = $rop->maintenance->startDate;
        //if there is no date and the start date is not in the next 24H
        if (! $maintenanceStartDate || (strtotime($maintenanceStartDate) > (time() + 86400))) {
            return false;
        }

        $timeToLoginLock = max(1, $timeToMaintenance ?? (int) $rop->maintenance->timeToLoginLock);

        $time = strtotime($maintenanceStartDate);
        $time = $time - ($timeToLoginLock * 60);
        $date = date("Y-m-d H:i:s", $time);

        return new DateTime() >= new DateTime($date);
    }

    /**
     * returns the configured start date from DB, false if no entry found
     * @return object{
     *     'message': ?string,
     *     'startDate': ?string,
     *     'timeToNotify': ?string,
     *     'timeToLoginLock': ?string,
     *     'announcementMail': ?string
     * }
     * @throws Zend_Db_Statement_Exception
     */
    protected function getConfFromDb(): object
    {
        $result = new stdClass();
        $result->message = null;
        $result->startDate = null;
        $result->timeToNotify = null;
        $result->timeToLoginLock = null;
        $result->announcementMail = null;

        $res = $this->db->query(
            'SELECT name, value FROM `Zf_configuration` WHERE `name` like \'runtimeOptions.maintenance.%\''
        );

        $conf = $res->fetchAll();
        foreach ($conf as $row) {
            $name = explode('.', $row['name']);
            $name = end($name);
            $result->$name = $row['value'];
        }

        return $result;
    }

    /**
     * @throws Zend_Db_Statement_Exception
     */
    public function isInIni(): bool
    {
        return $this->config->runtimeOptions->maintenance->startDate != $this->getConfFromDb()->startDate;
    }

    /**
     * @return object{
     *      'message': ?string,
     *      'startDate': ?string,
     *      'timeToNotify': ?string,
     *      'timeToLoginLock': ?string,
     *      'announcementMail': ?string
     * }
     * @throws Zend_Db_Statement_Exception
     */
    public function status(): object
    {
        return $this->getConfFromDb();
    }

    /**
     * @throws Zend_Db_Statement_Exception
     */
    public function isActive(int $sinceSeconds = 0): bool
    {
        $conf = $this->getConfFromDb();
        if (is_null($conf->startDate)) {
            return false;
        }
        $startTimeStamp = strtotime($conf->startDate);
        $now = time() - $sinceSeconds;

        return $startTimeStamp && $startTimeStamp < $now;
    }

    /**
     * @throws Zend_Db_Statement_Exception
     */
    public function isNotified(): bool
    {
        $conf = $this->getConfFromDb();
        $startTimeStamp = strtotime($conf->startDate ?? '');
        $now = time();

        return $startTimeStamp && $startTimeStamp - ((int) $conf->timeToNotify * 60) < $now;
    }

    /**
     * @throws Zend_Exception
     */
    public function disable(): void
    {
        $conf = $this->getConfFromDb();
        if (! empty($conf->startDate) || ! empty($conf->message)) {
            Zend_Registry::get('logger')
                ->cloneMe('system.maintenance')
                ->info('E1549', 'End maintenance mode');
        }

        $this->db->query(
            "UPDATE `Zf_configuration` SET `value` = null WHERE `name` = 'runtimeOptions.maintenance.startDate'"
        );
        $this->db->query(
            "UPDATE `Zf_configuration` SET `value` = null WHERE `name` = 'runtimeOptions.maintenance.message'"
        );
        //on leaving maintenance mode we clear the allowed IPs to not keep them accidentally for the next maintenance
        $this->db->query(
            "UPDATE `Zf_configuration` SET `value` = '[]' WHERE `name` = 'runtimeOptions.maintenance.allowedIPs'"
        );
        //when  we disable the maintenance mode, we trigger the worker queue
        $wq = new Queue();
        $wq->trigger();
    }

    /**
     * sets the maintenance mode, returns false if timestamp can not be parsed
     * @throws Zend_Exception
     */
    public function set(string $time, string $msg = ''): bool
    {
        $timeStamp = strtotime($time);
        if (! $timeStamp) {
            return false;
        }
        $timeStamp = date('Y-m-d H:i', $timeStamp);
        Zend_Registry::get('logger')
            ->cloneMe('system.maintenance')
            ->info('E1548', 'Set maintenance at {time} with message "{msg}"', [
                'time' => $timeStamp,
                'msg' => $msg,
            ]);
        $this->db->query(
            "UPDATE `Zf_configuration` SET `value` = ? WHERE `name` = 'runtimeOptions.maintenance.startDate'",
            $timeStamp
        );
        $this->db->query(
            "UPDATE `Zf_configuration` SET `value` = ? WHERE `name` = 'runtimeOptions.maintenance.message'",
            $msg
        );

        return true;
    }

    /**
     * sets a GUI message, provide empty string to clear
     */
    public function message(string $msg): void
    {
        $this->db->query(
            "UPDATE `Zf_configuration` SET `value` = ? WHERE `name` = 'runtimeOptions.maintenance.message'",
            $msg
        );
    }

    /**
     * @throws ReflectionException
     */
    public function announce($time, $msg = ''): array
    {
        $result = [
            'error' => [],
            'warning' => [],
            'sent' => [],
        ];

        $startTimeStamp = strtotime($time);
        if (! $startTimeStamp) {
            $result['error'][] = 'The given time can not parsed to a valid timestamp!';

            return $result;
        }

        $receiver = $this->config->runtimeOptions->maintenance->announcementMail ?? '';
        //fastest way to prevent that we spam our support mailbox TODO better solution
        $preventDuplicates = ['support@translate5.net'];
        if (empty($receiver)) {
            $result['error'][]
                = 'No receiver groups/users set in runtimeOptions.maintenance.announcementMail, so no email sent!';

            return $result;
        }
        $receiver = explode(',', $receiver);
        $plainUsers = [];
        $receiverGroups = array_filter($receiver, function ($item) use (&$plainUsers) {
            $item = explode(':', $item);
            if (count($item) == 1) {
                return true;
            }
            $plainUsers[] = end($item);

            return false;
        });

        Zend_Registry::set('module', 'editor'); // fix to load correct mail paths
        Zend_Registry::set('Zend_Locale', new Zend_Locale('en')); // fix to prevent error message
        $mailer = ZfExtended_Factory::get('ZfExtended_TemplateBasedMail');
        /* @var $mailer ZfExtended_TemplateBasedMail */
        $mailer->setParameters([
            'appName' => $this->config->runtimeOptions->appName,
            'maintenanceDate' => date('Y-m-d H:i (O)', $startTimeStamp),
            'message' => $msg,
        ]);
        $mailer->setTemplate('announceMaintenance.phtml');

        $user = ZfExtended_Factory::get('ZfExtended_Models_User');
        /* @var $user ZfExtended_Models_User */
        $receivers = $user->loadAllByRole($receiverGroups);

        foreach ($plainUsers as $login) {
            try {
                $receivers[] = $user->loadByLogin($login)->toArray();
            } catch (ZfExtended_Models_Entity_NotFoundException) {
                $result['warning'][] = "There is a non existent user '" . $login
                    . "' in the runtimeOptions.maintenance.announcementMail configuration!";
            }
        }

        foreach ($receivers as $userData) {
            $user->init($userData);
            if (in_array($user->getEmail(), $preventDuplicates)) {
                continue;
            }
            $preventDuplicates[] = $user->getEmail();
            $result['sent'][] = "  " . $user->getUsernameLong() . ' ' . $user->getEmail();
            $mailer->sendToUser($user);
        }

        return $result;
    }
}
