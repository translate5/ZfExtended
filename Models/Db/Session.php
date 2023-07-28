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
     * Updates or creates the session data
     * @param string $sessionId
     * @param string $sessionData
     * @param int $modified
     * @param int|null $userId
     * @return void
     */
    public function updateSessionData(string $sessionId, string $sessionData, int $modified, ?int $userId)
    {
        $row = $this->fetchRow(['session_id = ?' => $sessionId]);
        $authToken = ($row === null) ? null : $row->authToken;
        $this->createOrUpdateRow($sessionId, $authToken, $modified, $sessionData, $userId, $row);
    }

    /**
     * updates the authToken for the given Session ID in the DB and returns it
     * @param string $sessionId
     * @param int|null $userId
     * @param Zend_Db_Table_Row_Abstract|null $row : if given, an already fetched row
     * @return string
     * @throws Exception
     */
    public function updateAuthToken(string $sessionId, int $userId = null, Zend_Db_Table_Row_Abstract $row = null)
    {
        $authToken = bin2hex(random_bytes(16));
        $modified = ($row === null) ? null : ZfExtended_Utils::parseDbInt($row->modified);
        $this->createOrUpdateRow($sessionId, $authToken, $modified, null, $userId, $row);
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
     * @param string|null $authToken
     * @param int|null $modified
     * @param string|null $sessionData
     * @param int|null $userId
     * @param Zend_Db_Table_Row_Abstract|null $row
     * @return void
     */
    public function createOrUpdateRow(string $sessionId, ?string $authToken, ?int $modified, ?string $sessionData = null, ?int $userId, Zend_Db_Table_Row_Abstract $row = null)
    {
        if($row === null){
            // try to reuse an existing row to avoid duplicates
            $row = $this->fetchRow(['session_id = ?' => $sessionId]);
        }
        // create new row & save
        if($row === null){
            $row = $this->createRow();
            $this->setRowData($row, $sessionId, $authToken, $modified, $sessionData, $userId);
            $row->save();
            return;
        }
        // reuse row & save only if existing row differs
        if (
            $sessionId !== $row->session_id
            || $authToken !== $row->authToken
            || $sessionData !== $row->session_data
            || $userId !== ZfExtended_Utils::parseDbInt($row->userId)
            || ZfExtended_Resource_Session::doUpdateTimestamp(ZfExtended_Utils::parseDbInt($row->modified), $modified)
        ) {
            $this->setRowData($row, $sessionId, $authToken, $modified, $sessionData, $userId);
            $row->save();
        }
    }

    /**
     * Fills a session-row with the dynamic data
     * @param Zend_Db_Table_Row_Abstract $row
     * @param string $sessionId
     * @param string|null $authToken
     * @param int|null $modified
     * @param string|null $sessionData
     * @param int|null $userId
     * @return void
     */
    private function setRowData(Zend_Db_Table_Row_Abstract $row, string $sessionId, ?string $authToken, ?int $modified, ?string $sessionData, ?int $userId)
    {
        $row->session_id = $sessionId;
        $row->name = Zend_Session::getOptions('name');
        $row->authToken = $authToken;
        $row->modified = $modified;
        $row->lifetime = Zend_Session::getSaveHandler()->getLifeTime();
        $row->session_data = $sessionData;
        $row->userId = $userId;
    }
}

