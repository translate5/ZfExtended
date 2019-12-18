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

/**#@+
 * @author Marc Mittag
 * @package portal
 * @version 2.0
 *
 */

/**
 * This fatal errors should be handled in our custom shutdown functions
 * @var Integer
 */
define('FATAL_ERRORS_TO_HANDLE', E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);

/**
 * Standard Inhalt der index.php gekapselt
 */
class ZfExtended_BaseIndex{
    protected $moduleDirs;
    protected $currentModule;
    public $application_path;
    /**
     * Singleton Instanzen
     *
     * @var array _instances enthalten ACL Objekte
     */
    protected static $_instance = null;
    /**
     *
     * @var array 
     */
    public $applicationInis = array();

    /**
     * Konstruktor enthält alles, was normaler Weise die index.php enthält
     *
     * - Definition des Pfades zur Portal-Applikation
     * - Initialiserung des Application-Objekts, bootstrap und run
     *
     * sowie den Algorithmus zur Einbindung der application.ini-Dateien auf
     * verschiedenen Ebenen, wie dies im Kopf der application.ini selbst
     * dokumentiert ist
     *
     *
     */
    protected function  __construct($indexpath) {
        if (version_compare(PHP_VERSION, '7.3', '<') || version_compare(PHP_VERSION, '7.4', '>=')) {
            $msg = array('Please use PHP version >= 7.3 and < 7.4!');
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $msg[] = 'Please update your xampp package manually or reinstall Translate5 with the latest windows installer from http://www.translate5.net';
                $msg[] = 'Warning: Reinstallation can lead to data loss! Please contact support@translate5.net when you need assistance in data conversion!';
            }
            die(join("<br>\n", $msg));
        }
        
        if(!mb_internal_encoding("UTF-8")){
            throw new Exception('mb_internal_encoding("UTF-8") could not be set!');
        }
        $this->application_path = realpath(dirname($indexpath) . '/../application');
        defined('APPLICATION_PATH') || define('APPLICATION_PATH',$this->application_path);
        // Define application environment
        defined('APPLICATION_ENV') || define('APPLICATION_ENV', ( getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'application'));
        defined('APPLICATION_AGENCY') || define('APPLICATION_AGENCY', ( getenv('APPLICATION_AGENCY') ? getenv('APPLICATION_AGENCY') : $this->getAgency()));
        defined('APPLICATION_RUNDIR') || define('APPLICATION_RUNDIR', ( getenv('APPLICATION_RUNDIR') ? getenv('APPLICATION_RUNDIR') : ''));
        $this->applicationInis = $this->getApplicationInis();
    }
    /**
     *@param $indexpath filesystem-path to the index.php of the application; 
     *      gets set to $_SERVER['SCRIPT_FILENAME'], if not set (only relevant on first invocation)
     * @return ZfExtended_BaseIndex
     */
    public static function getInstance($indexpath=NULL)
    {
        if (null === self::$_instance) {
            if(is_null($indexpath)){
                $indexpath = $_SERVER['SCRIPT_FILENAME'];
            }
            self::$_instance = new self($indexpath);
        }
        return self::$_instance;
    }
    /**
     * Singleton Instanz auf NULL setzen, um sie neu initialiseren zu können
     *
     * @return void
     */
    public static function reset() {
        self::$_instance = NULL;
    }
    /**
     * (re-)initializes important registry values
     *
     * @param Zend_Application_Bootstrap_Bootstrap bootstrap
     * @return void
     */
    public function initRegistry(Zend_Application_Bootstrap_Bootstrap $bootstrap) {
        
        Zend_Registry::set('bootstrap', $bootstrap);
        $bootstrap->bootstrap('frontController');
        
        $front = $bootstrap->getResource('frontController');
        Zend_Registry::set('frontController',$front );
        
        $config = new Zend_config($bootstrap->getOptions());
        Zend_Registry::set('config', $config);

        $bootstrap->bootstrap('db');
        Zend_Registry::set('db',$bootstrap->getResource('db'));

        $bootstrap->bootstrap('cachemanager');
        $cache = $bootstrap->getResource('cachemanager')->getCache('zfExtended');
        Zend_Registry::set('cache', $cache);
        Zend_Registry::set('module',$this->currentModule );
    }

    /**
     * Singleton Instanz auf NULL setzen, um sie neu initialiseren zu können
     *
     * @return void
     */
    public function startApplication() {
        $this->initApplication()->bootstrap()->run();
    }
    
    /**
     * @throws Zend_Exception
     * @return Zend_Application
     */
    public function initApplication() {
        if(class_exists('Zend_Registry')){
            throw new Zend_Exception('application already started - Zend_Registry exists!');
        }
        require_once 'Zend/Loader/Autoloader.php';
        Zend_Loader_Autoloader::getInstance()->setFallbackAutoloader(true);
        /** Zend_Application */
        require_once dirname(__FILE__).'/Application.php';
        $application=new ZfExtended_Application( APPLICATION_ENV,[ 'config' => $this->applicationInis]);
        $this->initAdditionalConstants();
        return $application;
    }

    /**
     * Fallback Methode wenn keine Agency per ENV gesetzt ist,
     * dann wird die Rückgabe dieser Methode verwendet. Sollte in Nicht-Translate5-
     * Anwendungen überschrieben werden.
     * @return string
     */
    public function getAgency(){
        $sName = explode('.', $_SERVER['SERVER_NAME']);
        $tld = array_pop($sName);
        $domain = array_pop($sName); 
        $sub = array_pop($sName);
        $isLive = ($domain === 'translate5' && $tld === 'net');
        $isMainT5 = (empty($sub) || $sub === 'www'); 
        if($isLive && !$isMainT5) {
            return $sub;
        }
        return 'translate5';
    }
    /**
     * gets paths to all libs. Later ones should overwrite previous ones  (therefore reverse order than in application.ini)
     * @return array
     */
    public function getModulePaths() {
        $modules = $this->getModules();
        $paths = array();
        foreach ($modules as $module) {
            $paths[] = realpath(APPLICATION_PATH .DIRECTORY_SEPARATOR.'modules'.
                    DIRECTORY_SEPARATOR.$module);
        }
        return $paths;
    }
    /**
     * gets paths to all libs. Later ones should overwrite previous ones  (therefore reverse order than in application.ini)
     * @return array
     */
    public function getLibPaths() {
        $config = Zend_Registry::get('config');
        $paths = array();
        $libs = array_reverse($config->runtimeOptions->libraries->order->toArray());
        foreach ($libs as $lib) {
            $paths[] = realpath(APPLICATION_PATH .DIRECTORY_SEPARATOR.'..'.
                    DIRECTORY_SEPARATOR.'library'.DIRECTORY_SEPARATOR.$lib);
        }
        return $paths;
    }
    
    /**
     * Changes the module of the ZF-Application, and returns the old module which was set before
     * 
     * - sets $this->currentModule
     * - refreshes the loaded application.inis in relation to the new module
     * - starts the application, if not done already, else refreshes the config
     * - overwrites options, which already exist with the options of the newly 
     *   set module, but keeps those of the old module, which are not present in
     *   the new one
     * 
     *
     * @param string module
     * @param bool $withAcl default true, enables resetting the ACLs, false to prevent this 
     * @return string the old module
     */
    public function setModule($module, $withAcl = true){
        if(!is_dir(APPLICATION_PATH.'/modules/'.  $module)){
            throw new Zend_Exception('The module-directory '.APPLICATION_PATH.
                    '/modules/'.  $module.' does not exist.');
        }
        if(!class_exists('Zend_Registry')){
            throw new Zend_Exception('application not started yet - Zend_Registry does not exist!');
        }
        $oldModule = $this->currentModule;
        $this->currentModule = $module;
        $this->applicationInis = $this->getApplicationInis();
        $bootstrap = Zend_Registry::get('bootstrap');
        $bootstrap->getApplication()->setOptions(array('config'=> $this->applicationInis));
        $bootstrap->setOptions($bootstrap->getApplication()->getOptions());
        $this->initRegistry($bootstrap);
        //update the loaded ACLs:
        $withAcl && ZfExtended_Acl::getInstance(true);
        return $oldModule;
    }
    /**
     * adds the options of the passed module-name
     * 
     * - options already set stay as they are and do not get overridden
     * 
     *
     * @param string module
     */
    public function addModuleOptions($module){
        $bootstrap = Zend_Registry::get('bootstrap');
        $oldOptions = $bootstrap->getApplication()->getOptions();
        $this->setModule($module, false);
        $newOptions = $bootstrap->getApplication()->getOptions();
        $options = $bootstrap->getApplication()->mergeOptions($newOptions,$oldOptions);
        $bootstrap->getApplication()->setOptions($options);
        $bootstrap->setOptions($bootstrap->getApplication()->getOptions());
        $this->initRegistry($bootstrap);
    }
    /**
     * Definiert APPLICATION_MODULE und gibt aktuelles Modul zurück
     *
     * @return string module
     */
    private function getCurrentModule(){
        if(is_null($this->moduleDirs)){
            $this->moduleDirs = $this->getModuleDirs();
        }
        $runDirParts = explode('/',APPLICATION_RUNDIR);
        $uriParts = explode('/', $_SERVER['REQUEST_URI']);
        $i=1;
        while(isset($runDirParts[$i]) and $uriParts[$i] === $runDirParts[$i]){
            $i++;
        }
        //FIXME some one reached here to produce an 1 not defined. 
        //URL was:     [REQUEST_URI] => proxytest.zmap.io:80, I think someone tried to proxy direct to /editor or so.
        if(in_array($uriParts[$i], $this->moduleDirs)){
            define('APPLICATION_MODULE',  $uriParts[$i]);
            return $uriParts[$i];
        }
        define('APPLICATION_MODULE',  'default');
        return 'default';
    }

    /**
     * @return array moduleDirs
     */
    public function getModules(){
        $modules = scandir(APPLICATION_PATH.'/modules');
        foreach ($modules as $key => &$module) {
            if(!is_dir(APPLICATION_PATH .'/modules/'.$module) or $module === '.' or $module === '..' or $module === '.svn'){
                unset($modules[$key]);
            }
        }
        return $modules;
    }
    /**
     * alias of getModules
     */
    public function getModuleDirs() {
        return $this->getModules();
    }

    /**
     * @return array $applicationInis array mit den Pfaden zu allen einzubindenden application.inis
     */
    private function getApplicationInis(){
        if(is_null($this->currentModule)){
          $this->currentModule = $this->getCurrentModule();
        }
        $applicationInis = $this->getIniList();
        $result = array();
        foreach($applicationInis as $ini) {
            if(!file_exists($ini)){
                continue;
            }
            $result[] = $ini;
        }
        return $result;
    }

    /**
     * gibt die default Liste mit zu inkludierenden ini's zurück. Unabhängig davon ob es die Datei wirklich gibt.
     */
    protected function getIniList() {
        $applicationInis = array();
        //the main configuration file:
        $applicationInis[] = APPLICATION_PATH.'/config/application.ini';
        //the main configuration file of a module, provided by the module:
        $applicationInis[] = APPLICATION_PATH.'/modules/'.$this->currentModule.'/configs/module.ini'; 
        //the application configuration file of a module, provided by the application, can overwrite module settings:
        $applicationInis[] = APPLICATION_PATH.'/config/'.$this->currentModule.'.ini';
        //a customized configuration file for the local installation:
        $applicationInis[] = APPLICATION_PATH.'/config/installation.ini';
        //a customized configuration file for the local installation, called only for a specific module:
        // this feature is currently not documented!
        $applicationInis[] = APPLICATION_PATH.'/config/installation-'.$this->currentModule.'.ini';
        
        //FIXME in theory the following lines are obsolete since, the contained informations are in installation.ini 
        if(APPLICATION_AGENCY) {
          $applicationInis[] = APPLICATION_PATH.'/iniOverwrites/'.APPLICATION_AGENCY.'/application.ini';
          $applicationInis[] = APPLICATION_PATH.'/iniOverwrites/'.APPLICATION_AGENCY.'/'.$this->currentModule.'Application.ini';
        }
        return $applicationInis;
    }
    
    
    /***
     * Define additional transalte5 constants. This will be initialized after the application ini is loaded
     */
    protected function initAdditionalConstants(){
        define('NOW_ISO', date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']));
    }
}