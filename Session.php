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

class ZfExtended_Session {

    /**
     * @var ZfExtended_EventManager
     */
    protected $events = false;

    public function __construct(){
        $this->events = ZfExtended_Factory::get('ZfExtended_EventManager', array(get_class($this)));
    }
    /***
     * Updates the sessionMapInternalUniqId table modified stamp and regenerates the session id if needed
     * @param bool $regenerate if true, the session id is regenerated!
     * @return string : return the generated sessionid
     *
     */

    /***
     * Updates the sessionMapInternalUniqId table modified stamp and regenerates the session id if needed.
     * In addition, if $findExisting is set to true, this function will try to find an existing valid session for the
     * currently authenticated user, and use this session for the current request
     * @param bool $regenerate
     * @param bool $findExisting
     * @return mixed|string
     * @throws Zend_Db_Table_Exception
     * @throws Zend_Exception
     */
    public static function updateSession(bool $regenerate = false, bool $findExisting = false, int $userId = null) {

        $newSessionId = $oldSessionId = null;

        // if the user session is set, try to find for the current user existing valid session in the database
        if($findExisting && !is_null($userId)){

            $sessionDb = new ZfExtended_Models_Db_Session();
            $userSessionDb = $sessionDb->loadSessionIdForUser($userId, session_id());
            $userSessionDb = $userSessionDb['session_id'] ?? null;
            // the user has valid session in database ?
            if(!is_null($userSessionDb) && !empty($userSessionDb)){
                // remove the current temp session, and replace it with the database session.
                session_destroy();
                $newSessionId = $oldSessionId = $userSessionDb;
                session_id($newSessionId);
                session_start();
                // when replacing the database session, disable the regenerate
                $regenerate = false;
            }
        }
        // if no session id exist, generate new
        if(is_null($newSessionId) || empty($newSessionId)){
            $newSessionId = $oldSessionId = Zend_Session::getId();
        }

        if($regenerate){
            $config = Zend_Registry::get('config');
            Zend_Session::rememberMe($config->resources->ZfExtended_Resource_Session->lifetime);
            $newSessionId = Zend_Session::getId();
        }
        $sessionMapDb = new ZfExtended_Models_Db_SessionMapInternalUniqId();
        $sessionMapDb->update([
            'session_id' => $newSessionId,
            'modified' => time()
        ],[
            'session_id = ?' => $oldSessionId
        ]);
        return $newSessionId;
    }

    /***
     * Remove all sessions for given user, and run the garbage collector after this. <br/>
     * <b>NOTE: this will NOT check if the session is expired, it will just remove all session entries for given userId</b>
     * @param int $userId
     */
    public function cleanForUser(int $userId){
        $sessionDb = new ZfExtended_Models_Db_Session();
        $sessionDb->delete([
            'userId = ?' => $userId,
            'session_id != ?' => session_id()
        ]);
        $this->events->trigger('afterSessionCleanForUser', $this, []);
    }
}
