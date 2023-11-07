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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * @method void setId() setId(int $id)
 * @method void setCreated() setCreated(string $created)
 * @method void setLast() setLast(string $last)
 * @method void setCount() setCount(int $count)
 * @method void setLevel() setLevel(int $level)
 * @method void setDomain() setDomain(string $domain)
 * @method void setWorker() setWorker(string $worker)
 * @method void setEventCode() setEventCode(string $ecode)
 * @method void setMessage() setMessage(string $message)
 * @method void setAppVersion() setAppVersion(string $version)
 * @method void setFile() setFile(string $file)
 * @method void setLine() setLine(string $line)
 * @method void setTrace() setTrace(string $trace)
 * @method void setExtra() setExtra(string $extra)
 * @method void setHttpHost() setHttpHost(string $host)
 * @method void setUrl() setUrl(string $url)
 * @method void setMethod() setMethod(string $method)
 * @method void setUserLogin() setUserLogin(string $login)
 * @method void setUserGuid() setUserGuid(string $userGuid)
 *
 * @method integer getId() getId()
 * @method string getCreated() getCreated()
 * @method string getLast() getLast()
 * @method integer getCount() getCount()
 * @method integer getLevel() getLevel()
 * @method string getDomain() getDomain()
 * @method string getWorker() getWorker()
 * @method string getEventCode() getEventCode()
 * @method string getMessage() getMessage()
 * @method string getAppVersion() getAppVersion()
 * @method string getFile() getFile()
 * @method string getLine() getLine()
 * @method string getTrace() getTrace()
 * @method string getExtra() getExtra()
 * @method string getHttpHost() getHttpHost()
 * @method string getUrl() getUrl()
 * @method string getMethod() getMethod()
 * @method string getUserLogin() getUserLogin()
 * @method string getUserGuid() getUserGuid()
 */
class ZfExtended_Models_Log extends ZfExtended_Models_Entity_Abstract {
    protected $dbInstanceClass = 'ZfExtended_Models_Db_ErrorLog';
  
    /**
     * loads all tasks of the given tasktype that are associated to a specific user as PM
     * @param string $pmGuid
     * @param string $tasktype
     * @return array
     */
    public function loadListByNamePart(string $name) {
        $s = $this->db->select()
          ->where('name like ?', '%'.$name.'%')
          ->order('name ASC');
        return parent::loadFilterdCustom($s);
    }

    /**
     * Deletes the log entries older as the given amount of weeks.
     * @param int $weeks
     * @return int The number of rows deleted.
     */
    public function purgeOlderAs(int $weeks): int
    {
        return $this->db->delete([
            'created < NOW() - INTERVAL ? WEEK' => $weeks
        ]);
    }

    /**
     * Get all events created after the one having `id` equal to given $eventId
     *
     * @param int $recordId
     * @param int $limit
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public function getAllAfter(int $recordId, int $limit = 0): array
    {
        return $this->db->getAdapter()
            ->query('SELECT * FROM `Zf_errorlog` WHERE `id` > ? ORDER BY `id`'
                . ($limit ? " LIMIT $limit" : ''), $recordId)
            ->fetchAll();
    }
}
