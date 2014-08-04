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
    const TYPE_MAP = 'map';
    const TYPE_LIST = 'list';
    const TYPE_ABSPATH = 'absolutepath';
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
        $this->recursiveSetter($this->dbOptionTree, $path, $entry);
    }
    
    protected function recursiveSetter(array &$dbOptionTree, array $path, $entry) {
        $value = $entry['value'];
        $type = $entry['type'];
        $key = array_shift($path);
        if(empty($path)) {
            $dbOptionTree[$key] = $this->convertConfigValue($type, $value);
            settype($dbOptionTree[$key], $this->convertConfigToPhpType($type));
            return;
        }
        settype($dbOptionTree[$key], 'array');
        $this->recursiveSetter($dbOptionTree[$key], $path, $entry);
    }
    
    /**
     * converts the config values stored in the DB to the applicable target format
     * @param string $type
     * @param string $value
     */
    protected function convertConfigValue($type, $value) {
        switch ($type) {
            case self::TYPE_LIST:
            case self::TYPE_MAP:
                return json_decode($value);
            case self::TYPE_ABSPATH:
                return $this->convertFilepath($value);
        }
        return $value;
    }
    
    /**
     * converts the type of the config value to the corresponding PHP type
     * @param string $type
     * @return string
     */
    protected function convertConfigToPhpType($type) {
        switch ($type) {
            case self::TYPE_LIST:
            case self::TYPE_MAP:
                return 'array';
            case self::TYPE_ABSPATH:
                return 'string';
        }
        return $type;
    }
    
    /**
     * checks if the given path is notated as relative path, if yes the APPLICATION_PATH is prepended
     * @param string $path
     * @return string
     */
    protected function convertFilepath($path) {
        $firstChar = mb_substr($path, 0, 1);
        //this is a absolute path
        if($firstChar == DIRECTORY_SEPARATOR) {
            return $path;
        }
        //on windows we have to check for driveletters also
        $secondChar = mb_substr($path, 1, 1); //is on windows with driveletter == :
        if(DIRECTORY_SEPARATOR != '/' && $secondChar == ':') {
            return $path;
        }
        //convert / to \ under windows in stored / default values
        if(DIRECTORY_SEPARATOR != '/') {
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        }
        //all others are relative, so we have to append the APPLICATION_PATH
        return APPLICATION_PATH.DIRECTORY_SEPARATOR.$path;
    }
}