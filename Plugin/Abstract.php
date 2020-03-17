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
    
    
    protected $publicFileTypes=array('js', 'resources');
    
    public function __construct($pluginName) {
        $this->pluginName = $pluginName;
        $this->eventManager = Zend_EventManager_StaticEventManager::getInstance();
        $c = Zend_Registry::get('config');
        if(empty($c->runtimeOptions->plugins)) {
            // No Plugin Configuration found!
            throw new ZfExtended_Plugin_Exception('E1235');
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
        return array_values($this->frontendControllers);
    }
    
    /**
     * reusable function to filter frontend controlles by ACL
     * This is not used by default.
     * @return array
     */
    protected function getFrontendControllersFromAcl() {
        $result = array();
        $userSession = new Zend_Session_Namespace('user');
        if(empty($userSession) || empty($userSession->data)) {
            return $result;
        }
        $acl = ZfExtended_Acl::getInstance();
        /* @var $acl ZfExtended_Acl */
        if(!$acl->has('frontend')) {
            return $result;
        }
        foreach($this->frontendControllers as $right => $controller) {
            if($acl->isInAllowedRoles($userSession->data->roles, 'frontend', $right)) {
                $result[] = $controller;
            }
        }
        return $result;
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
     * Returns the web directory for public resources
     * @param string $resource
     * @return string
     */
    public function getResourcePath(string $resource): string {
        //the parts /plugins/resources/ are defined by convention
        return APPLICATION_RUNDIR.'/'.Zend_Registry::get('module').'/plugins/resources/'.$this->pluginName.'/'.ltrim($resource,'/');
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
        //A Plugin is missing or not active
        throw new ZfExtended_Plugin_MissingDependencyException('E1236',['plugin' => $classname]);
    }
    
    /**
     * @param string $classname
     * @throws ZfExtended_Plugin_ExclusionException
     */
    protected function blocks($classname) {
        if(in_array($classname, $this->activePlugins)) {
            //Plugins are not allowed to be active simultaneously
            throw new ZfExtended_Plugin_ExclusionException('E1237', [
                'current' => get_class($this),
                'blocked' => $classname
            ]);
        }
    }
    
    /**
    * Check if the folder contains file
    * @param string $dir
    * @return boolean
    */
    protected function isFolderEmpty($dir) {
        foreach (new DirectoryIterator($dir) as $fileInfo) {
            if($fileInfo->isDot()){
                continue;
            }
            return false;
        }
        return true;
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
            //No Plugin Configuration found for plugin
            throw new ZfExtended_Plugin_Exception('E1238',[
                'plugin' => $this->pluginName
            ]);
        }
        return $this->config;
    }
    
    public function addPublicFileTypes($newType){
        array_push($this->publicFileTypes, $newType);
    }
    
    /**
     * Adds the given controller to the application
     * Give just the Controller Name, Controller directory in Plugins is by convention "Controllers" and file must end with .php 
     * @param string $controller
     */
    public function addController($controller) {
        require_once APPLICATION_PATH.'/'.$this->getPluginPath().'/Controllers/'.$controller.'.php';
    }
    
    public function getPublicFileTypes(){
        return $this->publicFileTypes;
    }
    
    public function isPublicFileType($requestedType) {
        return in_array($requestedType, $this->getPublicFileTypes());
    }
}