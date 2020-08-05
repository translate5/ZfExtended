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
 * provides basic functionality for plugins
 */
class ZfExtended_Plugin_Manager {
    /**
     * Container for the Plguin Instances
     * @var array
     */
    protected $pluginInstances = array();
    protected $pluginNames = array();
    
    protected $allLocalePaths = array();
    protected $allFrontendControllers = array();
    
    public function bootstrap() {
        $config = Zend_Registry::get('config');
        if (! isset($config->runtimeOptions->plugins)) {
            return;
        }
        $pluginClasses = array_unique($config->runtimeOptions->plugins->active->toArray());

        //TRANSLATE-569: ensure that only the plugin config for the affected module is loaded.
        foreach ($pluginClasses as $pluginClass) {
            try {
                $name = $this->classToName($pluginClass);
                $plugin = ZfExtended_Factory::get($pluginClass, array($name));
                /* @var $plugin ZfExtended_Plugin_Abstract */
                if($plugin->getModuleName()!==Zend_Registry::get('module')){
                    continue;
                }
                $this->pluginNames[$pluginClass] = $name ;
                $this->pluginInstances[$pluginClass] = $plugin;
                /* @var $plugin ZfExtended_Plugin_Abstract */
                $localePath = $plugin->getLocalePath();
                if($localePath) {
                    $this->allLocalePaths[$name] = $localePath;
                }
            }
            catch (ReflectionException $exception) {
                $logger = Zend_Registry::get('logger');
                /* @var $logger ZfExtended_Logger */
                $logger->warn('E1218', 'The PHP class for the activated plug-in "{plugin}" does not exist.', [
                    'plugin' => $pluginClass,
                    'originalExceptionMsg' => $exception->getMessage()
                ]);
            }
        }
        
        //due to ACL restrictions we have first load all plugins, then we can call getFrontendControllers 
        foreach($this->pluginInstances as $plugin) {
            $this->allFrontendControllers = array_merge($this->allFrontendControllers, $plugin->getFrontendControllers());
        }
    }
    
    /**
     * return frontend controllers for all active plugins
     * @return array
     */
    public function getActiveFrontendControllers() {
        return $this->allFrontendControllers;
    }
    
    /**
     * return locale paths for all active plugins
     * @return array
     */
    public function getActiveLocalePaths() {
        return $this->allLocalePaths;
    }
    
    /**
     * Intelligent Method to return the stored plugin instance by 
     *   the direct plugin name, 
     *   the plugin classname
     *   or either the classname of a class belonging to the plugin (for example a worker belonging to the plugin)
     *   @param string $key the plugin key/class to look for
     *   @return ZfExtended_Plugin_Abstract|null
     */
    public function get($key){
        if(isset($this->pluginInstances[$key])) {
            return $this->pluginInstances[$key];
        }
        $name = $this->classToName($key);
        $classes = array_keys($this->pluginNames, $name, true);
        $cnt = count($classes);
        if($cnt === 0) {
            return null;
        }
        if($cnt === 1) {
            $key = $classes[0];
            return empty($this->pluginInstances[$key]) ? null : $this->pluginInstances[$key];
        }
        //if some one ever traps here: search key ordered by "_" in the plugin class list (or implement something like a search tree)
        // Multiple Plugin Classes found to key
        throw new ZfExtended_Plugin_Exception('E1234', ['key' => $key, 'foundClasses' => $classes]);
    }
    
    /**
     * returns a list of loaded plugins
     * @return multitype:
     */
    public function getActive() {
        return array_keys($this->pluginInstances);
    }
    
    /**
     * returns the Plugin Name distilled from class name
     * @param string $class
     * @return string
     */
    public function classToName($class) {
        $parts = $this->classExplode($class);
        reset($parts);
        do {
            if(strtolower(current($parts)) === 'plugins') {
                return next($parts);
            } 
        }while(next($parts) !== false);
        return $class;
    }
    
    /**
     * explodes the given class name
     * @param string $class
     * @return array
     */
    protected function classExplode($class) {
        return explode('_', $class);
    }
}