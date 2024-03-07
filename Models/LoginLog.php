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
 * @method void setId(int $id)
 * @method string getId()
 * @method void setCreated(string $date)
 * @method string getCreated()
 * @method void setLogin(string $login)
 * @method string getLogin()
 * @method void setUserGuid(string $guid)
 * @method string getUserGuid()
 * @method void setStatus(string $status)
 * @method string getStatus()
 * @method void setWay(string $way)
 * @method string getWay()
 */
class ZfExtended_Models_LoginLog extends ZfExtended_Models_Entity_Abstract
{

    public const LOGIN_SUCCESS = 'success';
    public const LOGIN_FAILED = 'failed';
    public const LOGIN_OPENID = 'openid';
    public const GROUP_COUNT = 1000;

    protected $dbInstanceClass = 'ZfExtended_Models_Db_LoginLog';

    /**
     * @param ZfExtended_Authentication $auth
     * @param string $way
     * @throws Zend_Db_Statement_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     * @throws ReflectionException
     */
    public static function addSuccess(ZfExtended_Authentication $auth, string $way): void
    {
        $user = $auth->getUser();
        if ($auth::isAppTokenAuthenticated()) {
            $way .= ' - APP-TOKEN';
        }
        $log = self::createLog($way);
        $log->setLogin($user->getLogin());
        $log->setUserGuid($user->getUserGuid());
        $log->setStatus(self::LOGIN_SUCCESS);
        $log->save();
    }

    /**
     * @param string $way
     * @return ZfExtended_Models_LoginLog
     * @throws ReflectionException
     */
    public static function createLog(string $way): ZfExtended_Models_LoginLog
    {
        $log = ZfExtended_Factory::get(__CLASS__);
        /* @var $log ZfExtended_Models_LoginLog */
        $log->setCreated(NOW_ISO);
        $log->setWay($way);
        return $log;
    }

    /**
     * @param string $login
     * @param string $way
     * @throws Zend_Db_Statement_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     * @throws ReflectionException
     */
    public static function addFailed(string $login, string $way): void
    {
        $log = self::createLog($way);
        $log->setLogin($login);
        $log->setStatus(self::LOGIN_FAILED);
        $log->save();
    }

    /**
     * loads the login log from latest to oldest, amount limited to the limit parameter
     * @param string $userGuid
     * @param int $limit
     * @return array
     */
    public function loadByUserGuid(string $userGuid, int $limit): array
    {
        $s = $this->db->select()
            ->where('userGuid = ?', $userGuid)
            ->order('id DESC')
            ->limit($limit);
        return $this->db->fetchAll($s)->toArray();
    }

    /**
     * Loads the last 100 log entries, and group them by day.
     * Consider the last 100 log entries only for performance reasons, so that PK can be used
     * @return array
     * @throws Zend_Db_Table_Exception
     */
    public function loadLastGrouped(): array
    {
        $s = $this->db->select()
            ->from($this->db->info($this->db::NAME), ['day' => 'date(created)'])
            ->order('id DESC')
            ->limit(self::GROUP_COUNT);

        $result = [];
        $data = $this->db->fetchAll($s)->toArray();
        foreach ($data as $row) {
            if (!array_key_exists($row['day'], $result)) {
                $result[$row['day']] = 0;
            }
            $result[$row['day']]++;
        }
        ksort($result);

        return array_reverse($result);
    }
}
