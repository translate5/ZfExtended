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
 */
trait ZfExtended_Models_Db_DeadLockHandlerTrait {
    /**
     * how often should the function be retried on a deadlock
     * @var integer
     */
    protected $DEADLOCK_REPETITIONS = 3;
    
    /**
     * how long should we wait between the retries, in seconds
     * @var integer
     */
    protected $DEADLOCK_SLEEP = 1;

    /**
     * Executes the given function, and just do nothing if a DB DeadLock occurs
     *  (for example if retrying the transaction makes no sense)
     * @param Callable $function
     * @param bool $reduceDeadlocks Usable when trait is used on Model! Otherwise, call reduceDeadlocks manually!
     * @return mixed returns the $function return value
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     */
    public function ignoreOnDeadlock(Callable $function, bool $reduceDeadlocks = false) {
        try {
            if($reduceDeadlocks) {
                $this->reduceDeadlocks();
            }
            return $function();
        }
        catch(Zend_Db_Statement_Exception $e) {
            $this->throwIfNotDeadLockException($e);
            //just do nothing if it was a deadlock
            
            $logger = Zend_Registry::get('logger')->cloneMe(ZfExtended_Models_Db_Exceptions_DeadLockHandler::DOMAIN);
            /* @var $logger ZfExtended_Logger */
            $logger->debug('E1203', 'A transaction was rejected after a DB deadlock', ['deadlock' => (string) $e]);
        }
        return null;
    }

    /**
     * By prepending this function call to update/delete queries, dead locks may be reduced there.
     * According to https://dev.mysql.com/doc/refman/8.0/en/innodb-transaction-isolation-levels.html
     * the usage of READ COMMITTED reduces the risk of dead locks for update and delete statements,
     * since record locks are released after evaluating the WHERE condition of the statement with that level.
     */
    public function reduceDeadlocks(Zend_Db_Table_Abstract $db = null) {
        $db = $db ?? $this->db ?? null;
        if (! $db instanceof Zend_Db_Table_Abstract) {
            throw new LogicException('db is not given or no instance of Zend_Db_Table_Abstract!');
        }
        $db->getAdapter()->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }

    /**
     * Executes the given function, and retries the execution if a DB DeadLock occurs
     * @param Callable $function
     * @param bool $reduceDeadlocks Only usable when trait is used on Model!
     * @return mixed returns the $function return value
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_Models_Db_Exceptions_DeadLockHandler
     */
    public function retryOnDeadlock(callable $function, bool $reduceDeadlocks = false) {
        $e = null;
        for ($i = 0; $i < $this->DEADLOCK_REPETITIONS; $i++) {
            try {
                if($reduceDeadlocks) {
                    $this->reduceDeadlocks();
                }
                $result = $function();
                if($i > 0 && !empty($e)) {
                    $logger = Zend_Registry::get('logger')->cloneMe(ZfExtended_Models_Db_Exceptions_DeadLockHandler::DOMAIN);
                    /* @var $logger ZfExtended_Logger */
                    $logger->debug('E1202', 'A transaction could be completed after {retries} retries after a DB deadlock.', ['deadlock' => (string) $e, 'retries' => $i]);
                }
                return $result;
            }
            catch(Zend_Db_Statement_Exception $e) {
                //hier schleife
                $this->throwIfNotDeadLockException($e);
                sleep($this->DEADLOCK_SLEEP);
            }
        }
        
        throw new ZfExtended_Models_Db_Exceptions_DeadLockHandler('E1201', [
            'retries' => $i
        ], $e); //if we reach here $e is set!
    }
    
    /**
     * Handles DB Exceptions: encapsulates Deadlock found exception
     */
    protected function throwIfNotDeadLockException(Zend_Db_Exception $e) {
        $message = $e->getMessage();
        if(strpos($message, 'Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction,') !== false) {
            return;
        }
        if(strpos($message, 'General error: 1205 Lock wait timeout exceeded; try restarting transaction') !== false) {
            return;
        }
        throw $e;
    }
}
