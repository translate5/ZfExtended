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
 * @package ZfExtended
 * @version 2.0
 * @deprecated
 */
abstract class ZfExtended_Models_Installer_Modules_Abstract {
    /**
     * @var string
     */
    protected $currentWorkingDir;
    
    /**
     * @var ZfExtended_Models_Installer_Logger
     */
    protected $logger;
    
    public function __construct(){
        $this->logger = new ZfExtended_Models_Installer_Logger();
    }
    
    public function setOptions(string $cwd, array $options){
        $this->currentWorkingDir = $cwd;
        $this->options = $options;
    }
    
    abstract public function run();
    
    /**
     * returns a list of valid short options for that Module (for getopt)
     * @return string
     */
    public function getShortOptions() {
        return '';
    }
    
    /**
     * returns a list of valid long options for that Module (for getopt)
     * @return array
     */
    public function getLongOptions() {
        return [];
    }
    
    /**
     * Adds the downloaded Zend Lib to the include path
     */
    protected function addZendToIncludePath() {
        $zendDir = $this->options['zend'];
        if(!is_dir($zendDir)) {
            $this->logger->log("Could not find Zend library ".$zendDir);
            exit;
        }
        $path = get_include_path();
        set_include_path($path.PATH_SEPARATOR.$zendDir);
    }
    
    /**
     * generates a Zend Application like environment with all needed registry entries filled
     */
    protected function initApplication() {
        $_SERVER['REQUEST_URI'] = '/database/forceimportall';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['HTTP_HOST'] = 'localhost';
        define('APPLICATION_PATH', $this->currentWorkingDir.DIRECTORY_SEPARATOR.'application');
        define('APPLICATION_ENV', 'application');
        
        require_once 'Zend/Session.php';
        Zend_Session::$_unitTestEnabled = true;
        require_once 'library/ZfExtended/BaseIndex.php';
        $index = ZfExtended_BaseIndex::getInstance();
        $index->initApplication()->bootstrap();
        $index->addModuleOptions('default');
        
        //set the hostname to the configured one:
        $config = Zend_Registry::get('config');
        
//FIXME hostname should be set to null in over written initApplication in installer module, it must be null in installation module! (was isInstallation check)
        $this->hostname = $config->runtimeOptions->server->name;
        
        $version = ZfExtended_Utils::getAppVersion();
        $this->logger->log('Current translate5 version '.$version);
    }
}