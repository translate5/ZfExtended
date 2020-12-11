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
 * abstract class of a log writer. Log writers are responsible to filter and write log events to their final destination
 */
abstract class ZfExtended_Logger_Writer_Abstract {
    
    /**
     * Configuration of the writer
     * @var array
     */
    protected $options;
    
    /**
     * filter instance
     * @var ZfExtended_Logger_Filter
     */
    protected $filter;
    
    /**
     * creates a Logger writer as defined in the given options array, possible values
     * @param array $options
     * @return ZfExtended_Logger_Writer_Abstract
     */
    public static function create(array $options) {
        $cls = 'ZfExtended_Logger_Writer_'.$options['type'];
        if(!class_exists($cls)) {
            $cls = $options['type'];
        }
        if(class_exists($cls)) {
            return ZfExtended_Factory::get($cls, [$options]);
        }
        throw new ZfExtended_Logger_Exception("ZfExtended_Logger writer ".$options['type']." not found!");
    }
    
    public function __construct(array $options) {
        $this->validateOptions($options);
        $this->options = $options;
        $this->filter = ZfExtended_Factory::get('ZfExtended_Logger_Filter', [$options['filter']]);
    }
    
    /**
     * Writes the given event to the log if event matches the configured filters
     * @param ZfExtended_Logger_Event $event
     */
    abstract public function write(ZfExtended_Logger_Event $event);
    
    /**
     * Returns true if is writer instance is enabled. Needed since other system configs may influence the concrete worker instance
     * @return boolean
     */
    public function isEnabled(): bool {
        return true;
    }
    
    /**
     * returns true if writer accepts (via configured filters) the given event
     * @param ZfExtended_Logger_Event $event
     * @return boolean
     */
    public function isAccepted(ZfExtended_Logger_Event $event) {
        return $this->filter->testEvent($event);
    }
    
    /**
     * returns true if the current writer accepts events for the given level and domain
     * Attention: returns also true if writer is configured for domain foo.bar.xxx and here is checked for foo only.
     * @param int $level
     * @param string $domain
     */
    public function isAcceptingBasicly(int $level, string $domain): bool {
        //attention: returns true also if given domain is foo and the filter is configured to listen to foo.bar!
        // The reason is, that this method is used in bootstraping to check if debugging is enabled for the whole given domain part
        return $this->filter->testBasic($level, $domain);
    }
    
    /**
     * Validates the given options
     */
    public function validateOptions(array &$options) {
        if(!empty($options['filter']) && !is_array($options['filter'])) {
            throw new ZfExtended_Logger_Exception(__CLASS__.': option filter is not an array!');
        }
        settype($options['filter'], 'array'); //ensure that if it was empty, that it is an array afterwards
    }
    
    /**
     * Shortct function to getDuplicateCount
     * @param ZfExtended_Logger_Event $event
     * @return int
     */
    protected function getDuplicateCount(ZfExtended_Logger_Event $event): int {
        return ZfExtended_Logger_DuplicateHandling::getInstance()->getDuplicateCount($event);
    }
}