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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 */
class ZfExtended_Resource_DbConfig extends Zend_Application_Resource_ResourceAbstract {
    /**
     * internal options tree
     * @var array
     */
    protected $dbOptionTree = array();
    
    /**
     * (non-PHPdoc)
     * @see Zend_Application_Resource_Resource::init()
     */
    public function init() {
        //init some needed objects
        $bootstrap = $this->getBootstrap();
        /* $bootstrap Bootstrap */
        $app = $bootstrap->getApplication();
        
        //fetch all config from DB
        $dbConfig = ZfExtended_Factory::get('ZfExtended_Models_Config');
        /* @var $dbConfig ZfExtended_Models_Config */
        $entries = $dbConfig->loadAll();
        
        if(empty($entries)) {
            return;
        }
        
        foreach($entries as $entry) {
            $this->addOneEntry($entry);
        }
        
        //update existing stored options
        $options = $this->dbOptionTree;
        $options = $app->mergeOptions($this->dbOptionTree, $app->getOptions());
        $bootstrap->setOptions($options);
        
        //update the registered config object
        Zend_Registry::Set('config', new Zend_Config($options));
    }
    
    /**
     * adds a given db config entry to the internal config tree
     * @param array $entry
     */
    protected function addOneEntry(array $entry){
        $recursiveSetter = null;
        $path = explode('.', $entry['name']);
        
        $value = $entry['value'];
        $type = $entry['type'];
        $recursiveSetter = function(array &$dbOptionTree, array $path) use ($value, $type, &$recursiveSetter) {
            $key = array_shift($path);
            if(empty($path)) {
                $dbOptionTree[$key] = $value;
                settype($dbOptionTree[$key], $type);
                return;
            }
            settype($dbOptionTree[$key], 'array');
            $recursiveSetter($dbOptionTree[$key], $path, $value, $type);
        };
        
        $recursiveSetter($this->dbOptionTree, $path);
    }
}