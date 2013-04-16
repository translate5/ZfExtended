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
 * @package portal
 * @version 2.0
 *
 */
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
        if(!mb_internal_encoding("UTF-8")){
            throw new Exception('mb_internal_encoding("UTF-8") could not be set!');
        }
        $this->application_path = realpath(dirname($indexpath) . '/../application');
        defined('APPLICATION_PATH')
            || define('APPLICATION_PATH',$this->application_path);

        // Define application environment
        if(isset($_SERVER['REQUEST_URI']) and 
                (
                    strpos($_SERVER['REQUEST_URI'], 'wsdl?wsdl=show')!== false or 
                    strpos($_SERVER['REQUEST_URI'], '/wsdl.xml')!== false
                )){
            define('APPLICATION_ENV', 'wsdl');
        }
        defined('APPLICATION_ENV')||
                define('APPLICATION_ENV', ( getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'application'));
        defined('APPLICATION_AGENCY')||
                define('APPLICATION_AGENCY', ( getenv('APPLICATION_AGENCY') ? getenv('APPLICATION_AGENCY') : $this->getAgency()));
        defined('APPLICATION_RUNDIR')||
                define('APPLICATION_RUNDIR', ( getenv('APPLICATION_RUNDIR') ? getenv('APPLICATION_RUNDIR') : ''));

        $this->applicationInis = $this->getApplicationInis();
    }
    /**
     *@param $indexpath filesystem-path to the index.php of the application; 
     *      gets set to $_SERVER['SCRIPT_FILENAME'], if not set (only relevant on first invocation)
     * @return self
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
    }
    /**
     * (re-)configures the session based on the config in the Zend_Registry
     *
     * @param Zend_Application_Bootstrap_Bootstrap bootstrap
     * @return void
     */
    public function reConfigureSession(Zend_Application_Bootstrap_Bootstrap $bootstrap) {
        $config = Zend_Registry::get('config');
        $session = new Zend_Session_Namespace();
        $session->runtimeOptions = $config->runtimeOptions;
        //nicht innerhalb des if-blocks davor, da die defines sonst in unechten forks nicht gesetzt sind
        foreach ($session->runtimeOptions->defines as $key => $val) {
            defined($key)|| define($key,$val);
        }
    }
    /**
     * Singleton Instanz auf NULL setzen, um sie neu initialiseren zu können
     *
     * @return void
     */
    public function startApplication() {
        if(class_exists('Zend_Registry')){
            throw new Zend_Exception('application already started - Zend_Registry exists!');
        }
        require_once 'Zend/Loader/Autoloader.php';
        Zend_Loader_Autoloader::getInstance()->setFallbackAutoloader(true);
        /** Zend_Application */
        require_once 'Zend/Application.php';
        $a = new Zend_Application( APPLICATION_ENV,
                array( 'config' => $this->applicationInis));
        $a->bootstrap()->run();
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
        $isMainT5 = (empty($sub) && $sub === 'www'); 
        if($isLive && !$isMainT5) {
            return $sub;
        }
        return 'translate5';
    }
    
    /**
     * Changes the module of the ZF-Application
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
     */
    public function setModule($module){
        if(!is_dir(APPLICATION_PATH.'/modules/'.  $module)){
            throw new Zend_Exception('The module-directory '.APPLICATION_PATH.
                    '/modules/'.  $module.' does not exist.');
        }
        if(!class_exists('Zend_Registry')){
            throw new Zend_Exception('application not started yet - Zend_Registry does not exist!');
        }
        $this->currentModule = $module;
        $this->applicationInis = $this->getApplicationInis();
        $bootstrap = Zend_Registry::get('bootstrap');
        $bootstrap->getApplication()->setOptions(array('config'=> $this->applicationInis));
        $bootstrap->setOptions($bootstrap->getApplication()->getOptions());
        $this->initRegistry($bootstrap);
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
        $this->setModule($module);
        $newOptions = $bootstrap->getApplication()->getOptions();
        $options = $bootstrap->getApplication()->mergeOptions($newOptions,$oldOptions);
        $bootstrap->getApplication()->setOptions($options);
        $bootstrap->setOptions($bootstrap->getApplication()->getOptions());
        $this->initRegistry($bootstrap);
        $this->reConfigureSession($bootstrap);
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
    private function getModuleDirs(){
        $modules = scandir(APPLICATION_PATH.'/modules');
        foreach ($modules as $key => &$module) {
            if(!is_dir(APPLICATION_PATH .'/modules/'.$module) or $module === '.' or $module === '..' or $module === '.svn'){
                unset($modules[$key]);
            }
        }
        return $modules;
    }

    /**
     * @return array $applicationInis array mit den Pfaden zu allen einzubindenden application.inis
     */
    private function getApplicationInis(){
        if(is_null($this->currentModule)){
          $this->currentModule = $this->getCurrentModule();
        }
        $applicationInis = $this->getIniList();
        //error_log(print_r($applicationInis,1));
        $result = array();
        foreach($applicationInis as $ini) {
            if(!file_exists($ini)){
                continue;
            }
            $result[] = $ini;
        }
        //error_log(print_r($result,1));
        return $result;
    }

    /**
     * gibt die default Liste mit zu inkludierenden ini's zurück. Unabhängig davon ob es die Datei wirklich gibt.
     */
    protected function getIniList() {
        $applicationInis = array();
        $applicationInis[] = APPLICATION_PATH . '/application.ini';
        $applicationInis[] = APPLICATION_PATH.'/modules/'.$this->currentModule.'/configs/application.ini';
        if(APPLICATION_AGENCY) {
          $applicationInis[] = APPLICATION_PATH.'/iniOverwrites/'.APPLICATION_AGENCY.'/application.ini';
          $applicationInis[] = APPLICATION_PATH.'/iniOverwrites/'.APPLICATION_AGENCY.'/'.$this->currentModule.'Application.ini';
        }
        return $applicationInis;
    }
}