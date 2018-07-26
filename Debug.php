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
    
    /**
     * Creates a summary about the current application and returns it.
     * @return stdClass
     */
    public static function applicationState() {
        $result = new stdClass();
        $downloader = ZfExtended_Factory::get('ZfExtended_Models_Installer_Downloader', array(APPLICATION_PATH.'/..'));
        /* @var $downloader ZfExtended_Models_Installer_Downloader */
        try {
            $result->isUptodate = $downloader->applicationIsUptodate();
        } catch (Exception $e) {
            $result->isUptodate = -1;
        }
        $versionFile = APPLICATION_PATH.'../version';
        if(file_exists($versionFile)) {
            $result->version = file_get_contents($versionFile);
        }
        else {
            $result->version = 'development';
            $result->branch = exec('cd '.APPLICATION_PATH.'; git status -bs | head -1');
        }
        
        $worker = ZfExtended_Factory::get('ZfExtended_Models_Worker');
        /* @var $worker ZfExtended_Models_Worker */
        $result->worker = $worker->getSummary();
        
        $pm = Zend_Registry::get('PluginManager');
        /* @var $pm ZfExtended_Plugin_Manager */
        $result->pluginsLoaded = $pm->getActive();
        
        $events = ZfExtended_Factory::get('ZfExtended_EventManager', array(__CLASS__));
        /* @var $events ZfExtended_EventManager */
        $events->trigger('applicationState', __CLASS__, array('applicationState' => $result));
        
        self::addLanguageResources($result);
        
        return $result;
    }
    
    /***
     * Add the available resources to the application state
     * @param stdClass $applicationState
     */
    public static  function addLanguageResources(&$applicationState) {
        
        $serviceManager = ZfExtended_Factory::get('editor_Services_Manager');
        /* @var $serviceManager editor_Services_Manager */
        $applicationState->languagesource = [];
        $resources = $serviceManager->getAllResources();
        foreach($resources as $resource) {
            /* @var $resource editor_Models_Resource */
            $obj = new stdClass();
            $obj->id = $resource->getId();
            $obj->name = $resource->getName();
            $obj->serviceType = $resource->getServiceType();
            $obj->serviceName = $resource->getService();
            
            //FIXME implement a "ping" method in the reosurce class to ping the connection to the resource
            
            $obj->url = $resource->getUrl();
            $applicationState->languagesource[] = $obj;
        }
    }
}