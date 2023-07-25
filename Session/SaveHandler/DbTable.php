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
 *
 */
class ZfExtended_Session_SaveHandler_DbTable
    extends Zend_Session_SaveHandler_DbTable
{

    /**
     * We store the session data here for comparsion before save
     * @var string
     */
    protected string $data = '';
    
    /**
     * {@inheritDoc}
     * @see Zend_Session_SaveHandler_DbTable::read()
     */
    public function read($id) {
        //if the read gets null from DB (which may happen) we have to return an empty string here,
        // otherwise start_session will fail with a strange error
        return $this->data = parent::read($id) ?? '';
    }
    
    /**
     * Write session data.
     * Overwrite initial method for using INSERT ON DUPLICATE KEY UPDATE instead.
     *
     * @param string $id
     * @param string $data
     * @return true
     */
    public function write($id, $data)
    {
        $isModified = $data !== $this->data;
        $data = [
            $this->_modifiedColumn => time(),
            $this->_dataColumn     => (string) $data
        ];
        $userSession = new Zend_Session_Namespace('user');
        $userId = empty($userSession?->data?->id) ? null : intval($userSession->data->id);

        // check if the session user namespace exist
        if($isModified) {
            $sql = 'INSERT INTO `session` (`session_id`, `name`, `modified`, `lifetime`, `session_data`,`userId`) VALUES (?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE `modified` = ?, `session_data` = ?, `userId` = ? ';
            $bindings = [
                $id,
                $this->_sessionName,
                intval($data[$this->_modifiedColumn]),
                intval($this->_lifetime),
                $data[$this->_dataColumn],
                $userId,
                intval($data[$this->_modifiedColumn]),
                $data[$this->_dataColumn],
                $userId
            ];
        } else {
            $sql = 'INSERT INTO `session` (`session_id`, `name`, `modified`, `lifetime`, `userId`) VALUES (?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE `modified` = ?, `userId` = ? ';
            $bindings = [
                $id,
                $this->_sessionName,
                intval($data[$this->_modifiedColumn]),
                intval($this->_lifetime),
                intval($data[$this->_modifiedColumn]),
                $userId
            ];
        }
        
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        //$db->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $db->query($sql, $bindings);
        
        return true; // session_write_close(): Session callback expects true/false return value
    }
    
    /**
     * {@inheritDoc}
     * @see Zend_Session_SaveHandler_DbTable::destroy()
     */
    public function destroy($id)
    {
        $this->getAdapter()->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        return parent::destroy($id);
    }
    
    /**
     * {@inheritDoc}
     * @see Zend_Session_SaveHandler_DbTable::gc()
     */
    public function gc($maxlifetime)
    {
        $this->getAdapter()->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

        $lifetime = Zend_Registry::get('config')->resources->ZfExtended_Resource_Session->lifetime;
        $thresh = time() - $lifetime;
        // delete data from uniqueId mapping table
        $mappingTable = new ZfExtended_Models_Db_SessionMapInternalUniqId();
        $mappingTable->delete('modified < ' . $thresh . ' OR session_id = \'\'');

        // delete data from session table
        $this->delete('modified < ' . $thresh . ' OR session_id = \'\'');

        return true;
    }
}
