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
abstract class ZfExtended_Plugin_Abstract {
    /**
     * Contains the Plugin Path relativ to APPLICATION_PATH or absolut if not under APPLICATION_PATH
     * @var string
     */
    protected $relativePluginPath = '';
    
    /**
     * @var Zend_EventManager_StaticEventManager
     */
    protected $eventManager;
    
    /**
     * @var string
     */
    protected $pluginName;
    
    /**
     * @var array
     */
    protected $activePlugins;
    
    /**
     * shortcut to the plugin specific config (not complete config!)
     * @var Zend_Config
     */
    protected $config;
    
    /**
     * A list of JS frontendcontrollers which has to be loaded for this plugin
     * @var array
     */
    protected $frontendControllers = array();
    
    /**
     * A folder relative to the plugin root which contains the plugin translations
     * if false there are no translations added to the translation framework
     * if used, should be by convention: "locales"
     * @var string
     */
    protected $localePath = false;
    
    public function __construct($pluginName) {
        $this->pluginName = $pluginName;
        $this->eventManager = Zend_EventManager_StaticEventManager::getInstance();
        $c = Zend_Registry::get('config');
        if(empty($c->runtimeOptions->plugins)) {
            throw new ZfExtended_Exception('No Plugin Configuration found!');
        }
        $this->config = $c->runtimeOptions->plugins->$pluginName;
        $this->activePlugins = $c->runtimeOptions->plugins->active->toArray();
        $rc = new ReflectionClass($this);
        $path = '^'.dirname($rc->getFileName());
        $this->relativePluginPath = ltrim(str_replace(rtrim('^'.APPLICATION_PATH,"/\\"), '', $path),"/\\");
        $this->init();
    }
    
    abstract public function init();
    
    //TODO when implement Plugin Management using the following methods would a standardized way for plugins to identifdy themselves
    //abstract function getName();
    //abstract function getDescription();

    /**
     * return the plugins frontend controllers
     * @return array
     */
    public function getFrontendControllers() {
        return $this->frontendControllers;
    }
    
    /**
     * return the plugins locale path
     * @return array
     */
    public function getLocalePath() {
        if(!$this->localePath) {
            return false;
        }
        return $this->getPluginPath().'/'.$this->localePath;
    }
    
    /**
     * SubClasses of $classname are recognized as fulfilled dependency!
     * @param string $classname
     * @throws ZfExtended_Plugin_MissingDependencyException
     */
    protected function dependsOn($classname) {
        if(in_array($classname, $this->activePlugins)) {
            return;
        }
        foreach($this->activePlugins as $oneActive) {
            if(is_subclass_of($oneActive, $classname)) {
                return;
            }
        }
        throw new ZfExtended_Plugin_MissingDependencyException('A Plugin is missing or not active - plugin: '.$classname);
    }
    
    /**
     * @param string $classname
     * @throws ZfExtended_Plugin_ExclusionException
     */
    protected function blocks($classname) {
        if(in_array($classname, $this->activePlugins)) {
            throw new ZfExtended_Plugin_ExclusionException('The following Plugin Bootstraps are not allowed to be active simultaneously: '.get_class($this).' and '.$classname);
        }
    }
    
    /**
     * returns the relative plugin path to APPLICATION_ROOT
     * @return string
     */
    public function getPluginPath() {
        return $this->relativePluginPath;
    }
    
    /**
     * returns a list of files from plugins public directory. List is normally used as whitelist on file inclusion.
     * @param string $subdirectory optional, subdirectory to start in
     * @param string $absolutePath optional, passed by reference to get the absolutePath from this method
     * @return multitype:string
     */
    public function getPublicFiles($subdirectory = '', & $absolutePath = null) {
        $publicDirectory = APPLICATION_PATH.'/'.$this->relativePluginPath.'/public/'.$subdirectory;
        $absolutePath = $publicDirectory;
        $objects = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($publicDirectory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST
        );
        $result = array();
        foreach($objects as $file) {
            if($file->isFile()) {
                $result[] = trim(str_replace(array($publicDirectory,'\\'), array('','/'), $file), '/');
            }
        }
        return $result;
    }
    
    /**
     * returns the plugin specific config
     * @throws ZfExtended_Exception
     * @return Zend_Config
     */
    public function getConfig() {
        if(empty($this->config)) {
            throw new ZfExtended_Exception('No Plugin Configuration found for plugin '.$this->pluginName);
        }
        return $this->config;
    }
}