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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * @method void setId() setId(int $id)
 * @method integer getId() getId()
 * @method void setCreated() setCreated(string $date)
 * @method string getCreated() getCreated()
 * @method void setLogin() setLogin(string $login)
 * @method string getLogin() getLogin()
 * @method void setUserGuid() setUserGuid(string $guid)
 * @method string getUserGuid() getUserGuid()
 * @method void setStatus() setStatus(string $status)
 * @method string getStatus() getStatus()
 * @method void setWay() setWay(string $way)
 * @method string getWay() getWay()
 */
class ZfExtended_Models_LoginLog extends ZfExtended_Models_Entity_Abstract {
    
    const LOGIN_SUCCESS = 'success';
    const LOGIN_FAILED = 'failed';
    
    protected $dbInstanceClass = 'ZfExtended_Models_Db_LoginLog';
    
    /**
     * @param string $login
     * @param string $userGuid
     * @param string $status
     * @param string $way
     */
    public static function addSuccess(ZfExtended_Models_User $user, string $way = 'plainlogin'){
        $log = self::createLog($way);
        $log->setLogin($user->getLogin());
        $log->setUserGuid($user->getUserGuid());
        $log->setStatus(self::LOGIN_SUCCESS);
        $log->save();
    }
    
    /**
     * @param string $login
     * @param string $userGuid
     * @param string $status
     * @param string $way
     */
    public static function addFailed(string $login, string $way = 'plainlogin'){
        $log = self::createLog($way);
        $log->setLogin($login);
        $log->setStatus(self::LOGIN_FAILED);
        $log->save();
    }
    
    /**
     * @param string $way
     * @return ZfExtended_Models_LoginLog
     */
    public static function createLog(string $way): ZfExtended_Models_LoginLog {
        $log = ZfExtended_Factory::get(__CLASS__);
        /* @var $log ZfExtended_Models_LoginLog */
        $log->setCreated(NOW_ISO);
        $log->setWay($way);
        return $log;
    }
}
