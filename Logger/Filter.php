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
 * Logger filter instance 
 */
class ZfExtended_Logger_Filter {
    /**
     * The or connected filter rules, each row (evaluates to one row in the ini) contains an array again, with the and connected rules.
     * @var array
     */
    protected $rules;
    
    /**
     * Cache for the current filter instance
     * @var array
     */
    protected $filterCache = [];
    
    /**
     * @param array $filterRules
     */
    public function __construct(array $filterRules) {
        foreach($filterRules as $rule) {
            $this->rules[] = $this->parseRule($rule);
        }
    }
    
    /**
     * Parses the configure rule and add internally test functions to be evaluated on each filter match test
     * @param string $rule
     * @throws ZfExtended_Logger_Exception
     * @return array
     */
    protected function parseRule($rule){
        $expressions = explode(';', $rule);
        $andConnected = ['level' => [], 'origin' => [], 'exception' => []];
        foreach($expressions as $expression) {
            $matches = null;
            if(!preg_match('/^([a-zA-Z0-9]+)[\s]*(<=|=|>=|\\*=|\\^=|\\$=)[\s]*([^\s]+)$/', $expression, $matches)) {
                throw new ZfExtended_Logger_Exception('ZfExtended_Logger_Filter invalid expression: "'.$expression.'"');
            }
            $keyword = $matches[1];
            $addKeyword = 'add_'.$keyword;
            $operator = $matches[2];
            $value = trim($matches[3]);
            if(!method_exists($this, $addKeyword)) {
                throw new ZfExtended_Logger_Exception('ZfExtended_Logger_Filter invalid keyword in expression: "'.$expression.'"');
            }
            try {
                $andConnected[$keyword][] = call_user_func([$this, $addKeyword], $operator, $value);
            }
            catch (ZfExtended_Logger_Exception $e) {
                throw new ZfExtended_Logger_Exception("ZfExtended_Logger_Filter invalid ".$e->getMessage().' operator in expression: "'.$expression.'"');
            }
        }
        return $andConnected;
    }
    
    /**
     * Adds a level filter function
     * @param string $operator
     * @param integer $configValue
     * @return boolean
     */
    protected function add_level($operator, $configValue) {
        $configValue = $this->levelStringToInt($configValue);
        switch ($operator) {
            case '<=': 
                return function($givenValue) use ($configValue) {
                    return (int) $givenValue <= (int) $configValue;
                };
            case '>=': 
                return function($givenValue) use ($configValue) {
                    return (int) $givenValue >= (int) $configValue;
                };
            case '=': 
                return function($givenValue) use ($configValue) {
                    return (int) $givenValue == (int) $configValue;
                };
        }
        //is catched and another exception with meaningful message is thrown there
        throw new ZfExtended_Logger_Exception("level");
    }
    
    /**
     * Adds an exception class filter
     * @param string $operator
     * @param string $configValue
     */
    protected function add_exception($operator, $configValue) {
        switch ($operator) {
            case '=':
                return function($givenValue) use ($configValue) {
                    return $givenValue == $configValue;
                };
            case '*=':
                return function($givenValue) use ($configValue) {
                    return is_a($givenValue, $configValue);
                };
        }
        //is catched and another exception with meaningful message is thrown there
        throw new ZfExtended_Logger_Exception("exception");
    }
    
    /**
     * Adds a origin filter function
     * @param string $operator
     * @param string $configValue
     * @return boolean
     */
    protected function add_origin($operator, $configValue) {
        switch ($operator) {
            case '=': 
                return function($givenValue) use ($configValue) {
                    return (string) $givenValue == (string) $configValue;
                };
            case '^=': 
                return function($givenValue) use ($configValue) {
                    return mb_strpos($configValue, $givenValue) === 0;
                };
            case '$=': 
                return function($givenValue) use ($configValue) {
                    return mb_strpos(strrev($configValue), strrev($givenValue)) === 0;
                };
            case '*=': 
                return function($givenValue) use ($configValue) {
                    return mb_strpos($configValue, $givenValue) !== false;
                };
        }
        //is caught and another exception with meaningful message is thrown there
        throw new ZfExtended_Logger_Exception("origin");
    }
    
    /**
     * returns true if the given event matches the configured filter criteria
     * @param ZfExtended_Logger_Event $event
     */
    public function testEvent(ZfExtended_Logger_Event $event) {
        if(is_object($event->exception)) {
            $cls = get_class($event->exception);
        }
        else {
            $cls = null;
        }
        return $this->test($event->level, $event->domain, $cls);
    }

    /**
     * returns true if the given level and origin matches the configured filters
     * @param integer $level
     * @param string $origin
     * @return boolean
     */
    public function test($level, $origin) {
        return $this->testLevelOrigin($level, $origin);
    }
    
    /**
     * test the level and origin filter
     * @param integer $level
     * @param string $origin
     * @return boolean
     */
    protected function testLevelOrigin($level, $origin, $exception = null) {
        $cacheKey = $this->cacheFilterKey($level, $origin, $exception);
        if(array_key_exists($cacheKey, $this->filterCache)) {
            return $this->filterCache[$cacheKey];
        }
        if(is_string($level) && !is_numeric($level)) {
            $level = $this->levelStringToInt($level);
        }
        foreach($this->rules as $rule) {
            //mostly there will be level filters, so we check them first
            foreach($rule['level'] as $levelTest) {
                if(!$levelTest($level)) {
                    continue 2; //since the tests are and connected we step over the whole role if one test fails
                }
                
            }
            foreach($rule['origin'] as $originTest) {
                if(!$originTest($origin)) {
                    continue 2; //since the tests are and connected we step over the whole role if one test fails
                }
            }
            if(!empty($exception)) {
                foreach($rule['exception'] as $excTest) {
                    if(!$excTest($exception)) {
                        continue 2; //since the tests are and connected we step over the whole role if one test fails
                    }
                }
            }
            //if we could successfully loop through all tests of one rule, then we can return true, since the rules are or connected
            return $this->filterCache[$cacheKey] = true;
        }
        // if no rule is valid, we return false
        return $this->filterCache[$cacheKey] = false;
    }
    
    /**
     * returns the level integer to a given level string
     * @param string $level
     * @throws ZfExtended_Logger_Exception
     * @return number
     */
    protected function levelStringToInt($level) {
        $level = 'ZfExtended_Logger::LEVEL_'.strtoupper($level);
        if(!defined($level)) {
            throw new ZfExtended_Logger_Exception('ZfExtended_Logger_Filter invalid level given: "'.$level.'"');
        }
        return (int) constant($level);
    }
    
    /**
     * returns the key for the filter cache
     * @return string
     */
    protected static function cacheFilterKey(... $filterParams) {
        return join('-', $filterParams);
    }
}