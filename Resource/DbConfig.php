<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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
     * Pointer to the current data path used
     * @var array
     */
    protected $currentPath = null;
    
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
        $options = $app->mergeOptions($this->dbOptionTree, $app->getOptions());
        $bootstrap->setOptions($options);
        
        //update the registered config object
        Zend_Registry::Set('config', new Zend_Config($options));
    }
    
    public function initDbOptionsTree(array $dbConfigs) {
        foreach ($dbConfigs as $cnf){
            $this->addOneEntry($cnf);
        }
    }
    
    /**
     * adds a given db config entry to the internal config tree
     * @param array $entry
     */
    protected function addOneEntry(array $entry){
        $path = explode('.', $entry['name']);
        $this->currentPath = $entry['name'];
        $this->recursiveSetter($this->dbOptionTree, $path, $entry);
    }
    
    protected function recursiveSetter(array &$dbOptionTree, array $path, $entry) {
        $value = $entry['value'];
        $type = $entry['type'] ?? false;
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
                return $this->jsonDecode($value);
            case self::TYPE_ABSPATH:
                return $this->convertFilepath($value);
        }
        return $value;
    }
    
    protected function jsonDecode($value) {
        if ($value === '') {
            return null;
        }
        $result = json_decode($value);
        if(json_last_error() != JSON_ERROR_NONE) {
            $message = __CLASS__.'::'.__FUNCTION__.' given JSON from config '.$this->currentPath.' could not be decoded, error was: ';
            error_log($message.json_last_error_msg()); //ZfExtended_Log is not possible here, so log it manually, see TRANSLATE-354 decouple Log from Mail
        }
        return $result;
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
    
    public function getDbOptionTree(){
        return $this->dbOptionTree;
    }
}