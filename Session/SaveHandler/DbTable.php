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

use MittagQI\ZfExtended\Session\SessionInternalUniqueId;

/**
 *
 */
class ZfExtended_Session_SaveHandler_DbTable extends Zend_Session_SaveHandler_DbTable
{

    /**
     * We store the session data here for comparison before save
     * @var string
     */
    protected string $data = '';

    /**
     * The column name for the user id
     * @var string
     */
    protected string $_userColumn = 'userId';

    /**
     * The column name for the internal session uniq id
     * @var string
     */
    protected string $_internalSessionUniqIdColumn = 'internalSessionUniqId';


    /**
     * {@inheritDoc}
     * @see Zend_Session_SaveHandler_DbTable::read()
     */
    public function read($id): string
    {
        $return = '';
        $rows = call_user_func_array([&$this, 'find'], $this->_getPrimary($id));

        if (count($rows)) {
            $row = $rows->current();
            $dbtime = $this->_getExpirationTime($row);
            $time = time();
            if ($dbtime > $time) {
                $return = $row->{$this->_dataColumn};
                SessionInternalUniqueId::getInstance()->set(
                    $row->{$this->_internalSessionUniqIdColumn}
                );
            } else {
                $this->destroy($id);
            }
        }

        //if the read gets null from DB (which may happen) we have to return an empty string here,
        // otherwise start_session will fail with a strange error
        return $this->data = $return ?? '';
    }

    /**
     * Inserts or updates a session row
     * @throws Zend_Db_Statement_Exception
     * @throws Exception
     */
    public function write($id, $data): bool
    {
        $internalId = SessionInternalUniqueId::getInstance()->get();

        $userId = ZfExtended_Authentication::getInstance()->getUserId() ?: null;
        $data = [
            $this->_dataColumn => (string)$data,
            $this->_userColumn => $userId,
            $this->_internalSessionUniqIdColumn => $internalId,
            $this->_lifetimeColumn => $this->_lifetime
        ];

        $db = $this->getAdapter();
        $primary = $this->_getPrimary($id, self::PRIMARY_TYPE_ASSOC);

        $data = array_merge($primary, $data);

        $placeholders = array_fill(0, count($data), '?');
        $columns = array_keys($data);
        $values = array_values($data);

        $updateColumns = array_map(function($column) {
            return "$column = VALUES($column)";
        }, $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s",
            $this->_name,
            implode(', ', $columns),
            implode(', ', $placeholders),
            implode(', ', $updateColumns)
        );

        $stmt = $db->query($sql, $values);

        return $stmt->rowCount() >= 0;
    }

    /**
     * {@inheritDoc}
     * @see Zend_Session_SaveHandler_DbTable::destroy()
     */
    public function destroy($id): bool
    {
        $this->getAdapter()->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        return parent::destroy($id);
    }

    /**
     * Garbage collection for session data.
     *
     * This method calculates a threshold for session expiration,
     * and deletes any sessions from the database that are older than this threshold or have an empty session id.
     *
     * @param int $maxlifetime The maximum lifetime of a session in seconds.
     * @return bool Returns true on success.
     * @throws Zend_Exception
     */
    public function gc($maxlifetime): bool
    {
        $this->getAdapter()->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

        $lifetime = Zend_Registry::get('config')->resources->ZfExtended_Resource_Session->lifetime;
        $thresh = time() - $lifetime;

        // Convert Unix timestamp to MySQL datetime format
        $threshDateTime = date('Y-m-d H:i:s', $thresh);

        // delete data from session table
        $this->delete('modified < ' . $this->getAdapter()->quote($threshDateTime) . ' OR session_id = \'\'');

        return true;
    }

    /**
     * Retrieve session expiration time
     *
     * @param Zend_Db_Table_Row_Abstract $row
     * @return int
     */
    protected function _getExpirationTime(Zend_Db_Table_Row_Abstract $row): int
    {
        // the modified date field is datetime but the expiry date is calculated using unix timestamp
        return (int)strtotime($row->{$this->_modifiedColumn}) + $this->_getLifetime($row);
    }
}
