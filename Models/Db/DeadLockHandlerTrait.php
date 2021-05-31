<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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
     * Executes the given function, and just do nothing if a DB DeadLock occurs (for example if retrying the transaction makes no sense)
     * @param Callable $function
     * @return mixed returns the $function return value
     */
    public function ignoreOnDeadlock(Callable $function) {
        try {
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
     * Executes the given function, and retries the execution if a DB DeadLock occurs
     * @param Callable $function
     * @return mixed returns the $function return value
     */
    public function retryOnDeadlock(Callable $function) {
        $e = null;
        for ($i = 0; $i < $this->DEADLOCK_REPETITIONS; $i++) {
            try {
                $result = $function();
                if($i > 0 && !empty($e)) {
                    $logger = Zend_Registry::get('logger')->cloneMe(ZfExtended_Models_Db_Exceptions_DeadLockHandler::DOMAIN);
                    /* @var $logger ZfExtended_Logger */
                    $logger->debug('E1202', 'A transaction could be completed after {retries} retries after a DB deadlock.', ['deadlock' => (string) $e, 'retries' => $i]);
                }
                return $result;
            }
            catch(Zend_Db_Table_Row_Exception $e) {
                $this->throwIfNotParentMissingException($e);
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
        if(strpos($e->getMessage(), 'Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction,') === false) {
            throw $e;
        }
    }
    
    /**
     * Handles DB Exceptions: handles refresh row as parent is missing
     */
    protected function throwIfNotParentMissingException(Zend_Db_Exception $e) {
        if(strpos($e->getMessage(), 'Cannot refresh row as parent is missing') === false) {
            throw $e;
        }
    }
}
