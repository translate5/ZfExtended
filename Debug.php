<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 */
/**
 * provides easy access to the debug configuration
 * Example - If you plan to execute some code only for debug reasons then implement:
 * if(ZfExtended_Debug::hasLevel('core', 'YourSection', 1))
 * → the code in the if block is executed only if one of the following lines is given in config: 
 * runtimeOptions.debug = 1
 * runtimeOptions.debug.core = 1
 * runtimeOptions.debug.core.YourSection = 1
 */
class ZfExtended_Debug {
    /**
     * @var array
     */
    protected static $profiler = array();
    protected static $lap = array();
    
    /**
     * @var Zend_Config_Ini
     */
    protected static $config = null;
    
    /**
     * compares the given debug level binary with the configured one for the given category and section
     * @param string $category
     * @param string $section
     * @param integer $level default is level 1
     * @return boolean
     */
    public static function hasLevel($category, $section, $level = 1) {
        return (self::getLevel($category, $section) & $level) !== 0;
    }
    
    /**
     * returns the configured debug level for given category and key
     * 
     * In Configuration debug can be enabled by setting:
     * runtimeOptions.debug = 1 → this enables debug overall
     * or
     * runtimeOptions.debug.category = 1 → this enables debug in given category
     * or
     * runtimeOptions.debug.category.key = 1 → this enables debug in given category and section
     * The height of the integer can represent the verbosity. This should be documented at the concrete section.
     * In general: as higher is the integer, as higher is the verbosity
     * 
     * @param string $category
     * @param string $section
     * @return integer
     */
    public static function getLevel($category, $section) {
        if(is_null(self::$config)) {
            self::$config = Zend_Registry::get('config');
        }
        $rop = self::$config->runtimeOptions;
        $keys = array('debug', $category, $section);
        
        $walker = function($conf, $keys) use (&$walker) {
            $key = array_shift($keys);
            $conf = $conf->$key;
            if(empty($conf)) {
                return 0;
            }
            if(! $conf instanceof Zend_Config) {
                return (int) $conf;
            }
            return $walker($conf, $keys);
        };
        return $walker($rop, $keys);
    }
    
    /**
     * starts the profiler for given key, default is profiler
     * @param string $key
     */
    public static function start($key = 'profiler'){
        self::$lap[$key] = 0;
        self::$profiler[$key] = microtime(true);
    }
    
    /**
     * logs the laptime of the profiler to given key to the log
     * @param string $key
     */
    public static function laptime($key = null) {
        $stop = microtime(true);
        if(empty($key)) {
            $keys = array_keys(self::$profiler);
            $key = end($keys);
        }
        $start = self::$profiler[$key];
        error_log(__CLASS__.' #'.$key.' laptime(#'.(self::$lap[$key]++).') (in s): '.($stop - $start));
    }
    
    /**
     * stops the profiler for given key, or if empty for the key given on last start call
     * Key and elapsed time is written to error_log
     * @param string $key
     */
    public static function stop($key = null){
        $stop = microtime(true);
        if(empty($key)) {
            $keys = array_keys(self::$profiler);
            $key = end($keys);
        }
        $start = self::$profiler[$key];
        error_log(__CLASS__.' #'.$key.' duration (in s): '.($stop - $start));
        unset(self::$profiler[$key]);
    }
}