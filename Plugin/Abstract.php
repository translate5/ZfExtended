<?php
 /*
   START LICENSE AND COPYRIGHT

  This file is part of the ZfExtended library and build on Zend Framework

  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

  This file is multi-licensed under the EPL, LGPL and GPL licenses. 

  It may be used under the terms of the Eclipse Public License - v 1.0
  as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
  included in the packaging of this file.  Please review the following information 
  to ensure the Eclipse Public License - v 1.0 requirements will be met:
  http://www.eclipse.org/legal/epl-v10.html.

  Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
  or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
  Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
  included in the packaging of this file.  Please review the following information 
  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
  GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
  http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
  
  @copyright  Marc Mittag, MittagQI - Quality Informatics
  @author     MittagQI - Quality Informatics
  @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html

  END LICENSE AND COPYRIGHT 
 */

/**
 * provides basic functionality for plugins
 */
abstract class ZfExtended_Plugin_Abstract {
    
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
        $this->init();
    }
    
    abstract public function init();
    
    //TODO when implement Plugin Management using the following methods would a standardized way for plugins to identifdy themselves
    //abstract function getName();
    //abstract function getDescription();
    
    /**
     * @param string $classname
     * @throws ZfExtended_Plugin_MissingDependencyException
     */
    protected function dependsOn($classname) {
        $active = $this->config->active->toArray();
        if(!in_array($classname, $active)) {
            throw new ZfExtended_Plugin_MissingDependencyException('A Plugin is missing or not active - plugin: '.$classname);
        }
    }
}