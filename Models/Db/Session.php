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
 * Klasse zum Zugriff auf die Tabelle mit Namen des Klassennamens (in lower case)
 * 
 * - Eintrag für Portalbetreiber wird mit der GUIE {00000000-0000-0000-0000-000000000000} vorausgesetzt
 */
class ZfExtended_Models_Db_Session extends Zend_Db_Table_Abstract {
    const GET_VALID_SESSIONS_SQL = 'select internalSessionUniqId from sessionMapInternalUniqId m, session s  where s.modified + lifetime >= UNIX_TIMESTAMP() and s.session_id = m.session_id';
    protected $_name    = 'session';
    public $_primary = 'session_id';

    /**
     * returns a SQL select to get the valid internalSessionUniqId values
     * @return string
     */
    public function getValidSessionsSql(): string {
        /** @var $events ZfExtended_EventManager */
        $events = ZfExtended_Factory::get('ZfExtended_EventManager', [__CLASS__]);
        $res = $events->trigger('getStalledSessions', __CLASS__);
        if($res->isEmpty()) {
            return self::GET_VALID_SESSIONS_SQL;
        }
        $merged = []; foreach($res as $item) { $merged = array_merge($merged, (array) $item);}
        if(empty($merged)) {
            return self::GET_VALID_SESSIONS_SQL;
        }

        return self::GET_VALID_SESSIONS_SQL.$this->getAdapter()->quoteInto('AND m.session_id NOT IN (?)', $merged);
    }

    /**
     * updates the authToken for the given Session ID in the DB and returns it
     * @return string
     */
    public function updateAuthToken($sessionId) {
        $token = bin2hex(random_bytes(16));
        $sql = 'INSERT INTO '.$this->_name.' (authToken, session_id, name, lifetime) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE authToken = VALUES(authToken)';
        $lifetime = Zend_Session::getSaveHandler()->getLifeTime();
        $this->getAdapter()->query($sql, [$token, $sessionId, Zend_Session::getOptions('name'), $lifetime]);
        return $token;
    }

    /***
     * Load the session_id for the given user. If $excludeSession is provided, this session value will be ignored from the select.
     * @param int $userId
     * @param string $excludeSession
     * @return mixed
     * @throws Zend_Db_Table_Exception
     */
    public function loadSessionIdForUser(int $userId,string $excludeSession = ''){
        $s = $this->select()->from($this->info($this::NAME),'session_id')->where('userId = ?',$userId);
        if(!empty($excludeSession)){
            $s->where('session_id != ?',$excludeSession);
        }
        return $this->getAdapter()->fetchRow($s);
    }
}

