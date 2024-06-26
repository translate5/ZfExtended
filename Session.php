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

class ZfExtended_Session
{
    /**
     * @var ZfExtended_EventManager
     */
    protected $events = false;

    public function __construct()
    {
        $this->events = ZfExtended_Factory::get('ZfExtended_EventManager', [get_class($this)]);
    }

    public static function getInternalSessionUniqId(): string
    {
        return SessionInternalUniqueId::getInstance()->get();
    }

    /**
     * Take over existing session for the current user if existed.
     * In addition, if $findExisting is set to true, this function will try to find an existing valid session for the
     * currently authenticated user, and use this session for the current request
     * @return mixed|string
     * @throws Zend_Db_Table_Exception
     * @throws Zend_Exception
     */
    public static function updateSession(
        bool $regenerate = false,
        bool $findExisting = false,
        int $userId = null
    ): mixed {
        $newSessionId = null;

        // if the user session is set, try to find for the current user existing valid session in the database
        if ($findExisting && ! is_null($userId)) {
            $sessionDb = new ZfExtended_Models_Db_Session();
            $userSessionDb = $sessionDb->loadSessionIdForUser($userId, session_id());
            $userSessionDb = $userSessionDb['session_id'] ?? null;

            // the user has valid session in database ?
            if (! empty($userSessionDb)) {
                // remove the current temp session, and replace it with the database session.
                session_destroy();
                $newSessionId = $userSessionDb;
                session_id($newSessionId);
                session_start();
                // when replacing the database session, disable the regenerate
                $regenerate = false;
            }
        }

        if ($regenerate) {
            Zend_Session::rememberMe(Zend_Registry::get('config')->resources->ZfExtended_Resource_Session->lifetime);
        }

        // if no session id exist, generate new
        if (empty($newSessionId) || $regenerate) {
            $newSessionId = Zend_Session::getId();
        }

        return $newSessionId;
    }

    /**
     * Remove all sessions for given user, and run the garbage collector after this. <br/>
     * <b>NOTE: this will NOT check if the session is expired, it will just remove all session entries for given userId</b>
     */
    public function cleanForUser(int $userId): void
    {
        $sessionDb = new ZfExtended_Models_Db_Session();
        $sessionDb->delete([
            'userId = ?' => $userId,
            'session_id != ?' => session_id(),
        ]);
        $this->events->trigger('afterSessionCleanForUser', $this, []);
    }
}
