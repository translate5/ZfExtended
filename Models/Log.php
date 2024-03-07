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
 * @method void setId(int $id)
 * @method void setCreated(string $created)
 * @method void setLast(string $last)
 * @method void setCount(int $count)
 * @method void setLevel(int $level)
 * @method void setDomain(string $domain)
 * @method void setWorker(string $worker)
 * @method void setEventCode(string $ecode)
 * @method void setMessage(string $message)
 * @method void setAppVersion(string $version)
 * @method void setFile(string $file)
 * @method void setLine(string $line)
 * @method void setTrace(string $trace)
 * @method void setExtra(string $extra)
 * @method void setHttpHost(string $host)
 * @method void setUrl(string $url)
 * @method void setMethod(string $method)
 * @method void setUserLogin(string $login)
 * @method void setUserGuid(string $userGuid)
 *
 * @method string getId()
 * @method string getCreated()
 * @method string getLast()
 * @method string getCount()
 * @method string getLevel()
 * @method string getDomain()
 * @method string getWorker()
 * @method string getEventCode()
 * @method string getMessage()
 * @method string getAppVersion()
 * @method string getFile()
 * @method string getLine()
 * @method string getTrace()
 * @method string getExtra()
 * @method string getHttpHost()
 * @method string getUrl()
 * @method string getMethod()
 * @method string getUserLogin()
 * @method string getUserGuid()
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
     * Get all events created after the one having `id` equal to given $eventId, (optionally) having given $eventCode
     *
     * @param int $recordId
     * @param int $limit
     * @param string|null $eventCode
     * @return array
     * @throws Zend_Db_Statement_Exception
     */
    public function getAllAfter(int $recordId, int $limit = 0, ?string $eventCode = null): array
    {
        // Start preparing sql and args
        $sql []= 'SELECT * FROM `Zf_errorlog` WHERE `id` > ?';
        $arg []= $recordId;

        // Respect $eventCode arg, if given
        if ($eventCode) {
            $sql []= 'AND `eventCode` = ?';
            $arg []= $eventCode;
        }

        // Add ORDER BY clause
        $sql []= 'ORDER BY `id`';

        // Respect $limit arg, if given
        if ($limit) {
            $sql []= "LIMIT $limit";
        }

        // Run query and return results
        return $this->db->getAdapter()->query(join(' ', $sql), $arg)->fetchAll();
    }
}
