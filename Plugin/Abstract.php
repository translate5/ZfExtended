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
    
    public function __construct() {
        $this->eventManager = Zend_EventManager_StaticEventManager::getInstance();
        $c = Zend_Registry::get('config');
        $this->config = $c->runtimeOptions->plugins;
        if(empty($this->config)) {
            throw new ZfExtended_Exception('No Plugin Configuration found!');
        }
        $rc = new ReflectionClass($this);
        $path = '^'.dirname($rc->getFileName());
        $this->relativePluginPath = str_replace(rtrim('^'.APPLICATION_PATH,"/\\").'/', '', $path);
        $this->init();
    }
    
    abstract public function init();
    
    //TODO when implement Plugin Management using the following methods would a standardized way for plugins to identifdy themselves
    //abstract function getName();
    //abstract function getDescription();
    
    /**
     * SubClasses of $classname are recognized as fulfilled dependency!
     * @param string $classname
     * @throws ZfExtended_Plugin_MissingDependencyException
     */
    protected function dependsOn($classname) {
        $active = $this->config->active->toArray();
        if(in_array($classname, $active)) {
            return;
        }
        foreach($active as $oneActive) {
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
        $active = $this->config->active->toArray();
        if(in_array($classname, $active)) {
            throw new ZfExtended_Plugin_ExclusionException('The following Plugin Bootstraps are not allowed to be active simultaneously: '.get_class($this).' and '.$classname);
        }
    }
    
    /**
     * @return string
     */
    public function getPluginPath() {
        return $this->relativePluginPath;
    }
}