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
 * Class representing the session storage table
 * TODO FIXME: why is the primary-key combined session_id/name ? Also, this should icorporate a "uniqieId" colun instead of adding the 1:1 related table sessionMapInternalUniqId
 */
class ZfExtended_Models_Db_Session extends Zend_Db_Table_Abstract {

    const GET_VALID_SESSIONS_SQL = 'SELECT `internalSessionUniqId` FROM `sessionMapInternalUniqId` m, `session` s  WHERE s.modified + s.lifetime >= UNIX_TIMESTAMP() AND s.session_id = m.session_id';

    protected $_name    = 'session';
    public $_primary = 'session_id';

    public function name(): string
    {
        return $this->_name;
    }

    /**
     * returns a SQL select to get the valid internalSessionUniqId values
     * @return string
     */
    public function getValidSessionsSql(): string
    {
        $events = ZfExtended_Factory::get(ZfExtended_EventManager::class, [__CLASS__]);
        $res = $events->trigger('getStalledSessions', __CLASS__);
        if($res->isEmpty()) {
            return self::GET_VALID_SESSIONS_SQL;
        }
        $merged = [];
        foreach($res as $item) {
            $merged = array_values(array_unique(array_merge($merged, (array) $item)));
        }
        if(empty($merged)) {
            return self::GET_VALID_SESSIONS_SQL;
        }

        return self::GET_VALID_SESSIONS_SQL . $this->getAdapter()->quoteInto(' AND m.session_id NOT IN (?)', $merged);
    }

    /**
     * updates or inserts an authToken for the given Session ID in the DB and returns it
     * @param string $sessionId
     * @param int|null $userId
     * @return string
     * @throws Exception
     */
    public function updateAuthToken(string $sessionId, int $userId = null): string
    {
        $name = Zend_Session::getOptions('name');
        $authToken = bin2hex(random_bytes(16));
        $lifetime = Zend_Session::getSaveHandler()->getLifeTime();
        if(empty($userId)){
            $this->getAdapter()->query(
                'INSERT INTO `session` (`session_id`, `name`, `authToken`, `lifetime`)'
                . ' VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE authToken = ?',
                [$sessionId, $name, $authToken, $lifetime, $authToken]
            );
        } else {
            $this->getAdapter()->query(
                'INSERT INTO `session` (`session_id`, `name`, `authToken`, `lifetime`, `userId`)'
                . ' VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE authToken = ?, userId = ?',
                [$sessionId, $name, $authToken, $lifetime, $userId, $authToken, $userId]
            );
        }
        return $authToken;
    }

    /**
     * Load the session_id for the given user. If $excludeSession is provided, this session value will be ignored from the select.
     * @param int $userId
     * @param string $excludeSession
     * @return mixed
     * @throws Zend_Db_Table_Exception
     */
    public function loadSessionIdForUser(int $userId, string $excludeSession = '')
    {
        $s = $this->select()
            ->from($this->_name,'session_id')
            ->where('userId = ?', $userId);
        if(!empty($excludeSession)){
            $s->where('session_id != ?',$excludeSession);
        }
        return $this->getAdapter()->fetchRow($s);
    }

    /**
     * Tries to reuse existing rows to update or create a row
     * @param string $sessionId
     * @param string $sessionData
     * @param int $modified
     * @param int|null $userId
     * @return void
     */
    public function createOrUpdateRow(string $sessionId, string $sessionData, int $modified, ?int $userId)
    {
        $row = $this->fetchRow(['session_id = ?' => $sessionId]);
        // create new row & save
        if($row === null){
            $row = $this->createRow();
            $this->setRowData($row, $sessionId, $modified, $sessionData, $userId);
            $row->save();
            return;
        }
        // reuse row & save only if existing row differs
        if (
            $sessionId !== $row->session_id
            || $sessionData !== $row->session_data
            || $userId !== ZfExtended_Utils::parseDbInt($row->userId)
            || ZfExtended_Resource_Session::doUpdateTimestamp(ZfExtended_Utils::parseDbInt($row->modified), $modified)
        ) {
            $this->setRowData($row, $sessionId, $modified, $sessionData, $userId);
            $row->save();
        }
    }

    /**
     * Fills a session-row with the dynamic data
     * @param Zend_Db_Table_Row_Abstract $row
     * @param string $sessionId
     * @param int|null $modified
     * @param string|null $sessionData
     * @param int|null $userId
     * @return void
     */
    private function setRowData(Zend_Db_Table_Row_Abstract $row, string $sessionId, ?int $modified, ?string $sessionData, ?int $userId)
    {
        $row->session_id = $sessionId;
        $row->name = Zend_Session::getOptions('name');
        $row->modified = $modified;
        $row->lifetime = Zend_Session::getSaveHandler()->getLifeTime();
        $row->session_data = $sessionData;
        $row->userId = $userId;
    }
}

