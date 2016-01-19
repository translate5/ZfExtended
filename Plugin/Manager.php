<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
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
    
    protected $allFrontendControllers = array();
    
    public function bootstrap() {
        $config = Zend_Registry::get('config');
        if (! isset($config->runtimeOptions->plugins)) {
            return;
        }
        $pluginClasses = array_unique($config->runtimeOptions->plugins->active->toArray());
        
        //TRANSLATE-569: ensure that only the plugin config for the affected module is loaded.
        foreach ($pluginClasses as $pluginClass) {
            // error_log("Plugin-Class ".$pluginClass." initialized.");
            try {
                $this->pluginNames[$pluginClass] = $name = $this->classToName($pluginClass);
                $this->pluginInstances[$pluginClass] = $plugin = ZfExtended_Factory::get($pluginClass, array($name));
                /* @var $plugin ZfExtended_Plugin_Abstract */
                $this->allFrontendControllers = array_merge($this->allFrontendControllers, $plugin->getFrontendControllers());
            }
            catch (ReflectionException $exception) {
                /* @var $log ZfExtended_Log */
                error_log(__CLASS__.' -> '.__FUNCTION__.'; $exception: '. print_r($exception->getMessage(), true));
            }
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
        throw new Exception('More than Plugin Classes found to key '.$key.' found: '.print_r($classes,1));
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