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
 * Handler for event duplicates
 */
class ZfExtended_Logger_DuplicateHandling {
    /**
     * default interval in which errors are considered as duplicate
     * @var integer
     */
    const DEFAULT_INTERVAL = 300;
    
    /**
     * recognizes event duplications by formatted message ({variables} replaced with content)
     * @var string
     */
    const DUPLICATION_BY_MESSAGE = 'message';
    
    /**
     * recognizes event duplications just by ecode, ignoring content of {variables}
     * @var string
     */
    const DUPLICATION_BY_ECODE = 'ecode';
    
    static protected $duplicateConfiguration = [];
    
    static protected $singletonInstance = null;
    
    /**
     * @var Zend_Cache_Core
     */
    protected $memcache;
    
    protected function __construct() {
        $this->memcache = Zend_Cache::factory('Core', new ZfExtended_Cache_MySQLMemoryBackend(), ['automatic_serialization' => false]);
    }
    
    static public function getInstance() {
        return self::$singletonInstance ?? new self();
    }
    
    /**
     * Add ecodes where the duplication should be checked as given as $mode
     * @param array $ecodes
     * @param string $mode
     */
    static public function addDuplicates(array $ecodes, string $mode) {
        self::$duplicateConfiguration = array_merge(self::$duplicateConfiguration, array_fill_keys($ecodes, $mode));
    }
    
    /**
     * return the duplication count, 0 for no duplications
     * @param ZfExtended_Logger_Event $event
     * @param ZfExtended_Logger_Writer_Abstract $writer
     * @return int
     */
    public function getDuplicateCount(ZfExtended_Logger_Event $event): int {
        if(empty($event->duplicationHash)) {
            return 0;
        }
        // look in mem cache if an entry exists for that hash, if yes return value
        return (int) $this->memcache->load($this->getCacheKey($event->duplicationHash));
    }
    
    /**
     * sets the duplicate hash in the event and increments the counter if configured
     * @param ZfExtended_Logger_Event $event
     * @return int
     */
    public function incrementCount(ZfExtended_Logger_Event $event): int {
        $duplicationType = self::$duplicateConfiguration[$event->eventCode] ?? null;
        
        switch ($duplicationType) {
            case self::DUPLICATION_BY_MESSAGE:
                $key = $event->eventCode.$event->message;
                break;
            
            case self::DUPLICATION_BY_ECODE:
                $key = $event->eventCode;
                break;
            
            default:
                // without config returning 0 so that it is never seen as duplicate
                return 0;
        }
        
        // generate duplication hash to identify events
        $event->duplicationHash = md5($key);
        
        $key = $this->getCacheKey($event->duplicationHash);

        // look in mem cache if an entry exists for that hash, if yes increment value
        $count = $this->memcache->load($key);
        
        if($count === false) {
            $count = 0;
        } else {
            settype($count, 'int');
            $count++;
        }
        
        $this->memcache->save((string) $count, $key, [], self::DEFAULT_INTERVAL);
        return $count;
    }
    
    protected function getCacheKey($duplicationHash) {
        return __CLASS__.'_'.$duplicationHash;
    }
}