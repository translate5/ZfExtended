<?php
 /*
 START LICENSE AND COPYRIGHT
 
 This file is part of Translate5 Editor PHP Serverside and build on Zend Framework
 
 Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

 This file may be used under the terms of the GNU General Public License version 3.0
 as published by the Free Software Foundation and appearing in the file gpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU General Public License version 3.0 requirements will be met:
 http://www.gnu.org/copyleft/gpl.html.

 For this file you are allowed to make use of the same FLOSS exceptions to the GNU 
 General Public License version 3.0 as specified by Sencha for Ext Js. 
 Please be aware, that Marc Mittag / MittagQI take no warranty  for any legal issue, 
 that may arise, if you use these FLOSS exceptions and recommend  to stick to GPL 3. 
 For further information regarding this topic please see the attached license.txt
 of this software package.
 
 MittagQI would be open to release translate5 under EPL or LGPL also, if this could be
 brought in accordance with the ExtJs license scheme. You are welcome to support us
 with legal support, if you are interested in this.
 
 
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU General Public License version 3.0 http://www.gnu.org/copyleft/gpl.html
             with FLOSS exceptions (see floss-exception.txt and ux-exception.txt at the root level)
 
 END LICENSE AND COPYRIGHT 
 */
/**
 * Abstract Worker Class
 * 
 * @method void setId() setId(integer $id)
 * @method void setWorker() setWorker(string $phpClassName)
 * @method void setSlot() setSlot(string $slotName)
 * @method void setTaskGuid() setTaskGuid(string $taskGuid)
 * @method void setParameters() setParameters(string $serializedParameters)
 * @method void setPid() setPid(integer $pid)
 * @method void setStarttime() setStarttime(string $starttime)
 * @method void setHash() setHash(string $hash)
 * 
 * @method void getId()
 * @method void getWorker()
 * @method void getSlot()
 * @method void getTaskGuid()
 * @method void getParameters()
 * @method void getPid()
 * @method void getStarttime()
 * @method void getHash()
 * 
 */
class ZfExtended_Models_Worker extends ZfExtended_Models_Entity_Abstract {
    /**
     * @var ZfExtended_Models_Db_Worker
     */
    protected $dbInstanceClass = 'ZfExtended_Models_Db_Worker';
    
    /**
     * Default worker-lifetime (could/should be overwritten by child-class)
     * 
     * @var string
     *      MySQL INTERVAL as defined in http://dev.mysql.com/doc/refman/5.5/en/date-and-time-functions.html#function_date-add
     */
    protected $maxLifetime = '1 HOUR';
    
    /**
     * This constant values define the different worker-states
     * @var string
     */
    const STATE_WAITING = 'waiting';
    const STATE_RUNNING = 'running';
    
    /*
    public function run() {
        error_log(__CLASS__.' -> '.__FUNCTION__);
        //error_log(print_r($this->row, true));
        //$this->setStarttime('Lifetime: '.$this->maxLifetime);
        $this->setWorker(get_class($this));
        $this->setStarttime(new Zend_Db_Expr('NOW()'));
        $this->setHash(uniqid(NULL, true));
        //$this->save();
    }
    */
    
    
    public function setRunningMutex() {
        //error_log(__CLASS__.' -> '.__FUNCTION__);
        
        // workerModel can not be set to mutex if it is new 
        if (!$this->getId() || !$this->getHash())
        {
            return false;
        }
        
        $sql = $this->db->select()
                    ->where('id = ?', $this->getId())
                    ->where('hash = ?', $this->getHash())
                    ->where('state = ?', self::STATE_WAITING);
        //error_log('sql: '.$sql);
        $row = $this->db->fetchRow($sql);
        
        // workerModel can not be set to mutex because no entry with this id an hash can be found in database
        if (!$row)
        {
            return false;
        }
        
        // is mutex-save: set new hash and save it to the DB
        $this->setHash(uniqid(NULL, true));
        $this->save();
        
        return true;
    }
    
    
    public function cleanGarbage() {
        error_log(__CLASS__.' -> '.__FUNCTION__);
        $sql = $this->db->select()->where('starttime < NOW() - INTERVAL '.$this->maxLifetime);
        //error_log('SQL: '.$sql);
        $rows = $this->db->fetchAll($sql);
        //error_log('Result: '.print_r($rows, true));
        
        foreach ($rows as $row) {
            $row->delete();
        }
    }
    
    
}