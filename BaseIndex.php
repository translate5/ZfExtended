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

/**
 * This fatal errors should be handled in our custom shutdown functions
 */
const FATAL_ERRORS_TO_HANDLE = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;

/**
 * Standard Inhalt der index.php gekapselt
 */
class ZfExtended_BaseIndex
{
    /**
     * A collection of constants which define the Environment
     * The Environment references the sections in the ini-files
     * For API-test, there can be a second environment "test", which holds an own database-name
     * There can be API-tests working on the test-environment/db or the normal application environment
     */
    public const ENVIRONMENT_APP = 'application';

    public const ENVIRONMENT_DATA = 'data';

    public const ENVIRONMENT_TEST = 'test';

    public const ENVIRONMENT_TESTDATA = 'testdata';

    public const ORIGIN_TEST = 't5test';

    public const ORIGIN_APPTEST = 't5apptest';

    protected string $currentModule = 'default';

    /**
     * singleton instance
     */
    protected static ?ZfExtended_BaseIndex $_instance = null;

    /**
     * If set to true load additional maintenance.ini config file
     * @var boolean
     */
    public static bool $addMaintenanceConfig = false;

    /**
     * Konstruktor enthält alles, was normaler Weise die index.php enthält
     *
     * - Definition des Pfades zur Portal-Applikation
     * - Initialiserung des Application-Objekts, bootstrap und run
     *
     * sowie den Algorithmus zur Einbindung der application.ini-Dateien auf
     * verschiedenen Ebenen, wie dies im Kopf der application.ini selbst
     * dokumentiert ist
     */
    protected function __construct($indexpath)
    {
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $msg = ['Please use PHP version ~ 8.0!'];
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $msg[] = 'Please update your xampp package manually or reinstall Translate5 with the latest windows installer from https://www.translate5.net';
                $msg[] = 'Warning: Reinstallation can lead to data loss! Please contact support@translate5.net when you need assistance in data conversion!';
            }
            die(join("<br>\n", $msg));
        }

        if (! mb_internal_encoding("UTF-8")) {
            throw new Exception('mb_internal_encoding("UTF-8") could not be set!');
        }
        //set the locales to the ones configured by env variables, see TRANSLATE-2992
        setlocale(LC_ALL, '');
        if (! defined('APPLICATION_ROOT')) {
            define('APPLICATION_ROOT', realpath(dirname($indexpath) . DIRECTORY_SEPARATOR . '..'));
        }
        $appData = APPLICATION_ROOT . DIRECTORY_SEPARATOR . self::ENVIRONMENT_DATA;
        defined('APPLICATION_PATH') || define('APPLICATION_PATH', APPLICATION_ROOT . DIRECTORY_SEPARATOR . 'application');

        // Define application environment from Request-Origin for API-tests: this is only allowed if installation
        // is set-up as test installation. Therefore we have to parse installation.ini manually
        if (array_key_exists('HTTP_ORIGIN', $_SERVER)
            && ($_SERVER['HTTP_ORIGIN'] === self::ORIGIN_TEST
                || $_SERVER['HTTP_ORIGIN'] === self::ORIGIN_APPTEST)) {
            // we have to check if the installation is allowed to switch the environment - security!
            $iniVars = parse_ini_file(APPLICATION_PATH . '/config/installation.ini');
            if ($iniVars !== false
                && array_key_exists('testSettings.testsAllowed', $iniVars)
                && $iniVars['testSettings.testsAllowed'] === '1') {
                // CRUCIAL: this will trigger the "test" section in ini-files
                if ($_SERVER['HTTP_ORIGIN'] === self::ORIGIN_TEST) {
                    defined('APPLICATION_ENV') || define('APPLICATION_ENV', self::ENVIRONMENT_TEST);
                    $appData = APPLICATION_ROOT . DIRECTORY_SEPARATOR . self::ENVIRONMENT_TESTDATA;
                }
                // this defines a marker to detect API-Tests in the code. It's just meant for configurational
                // tasks as special algorithmical behaviour for tests is a no-go
                defined('APPLICATION_APITEST') || define('APPLICATION_APITEST', true);
            }
        }
        // Define application environment
        defined('APPLICATION_DATA') || define('APPLICATION_DATA', $appData);
        defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ?: self::ENVIRONMENT_APP));
        defined('APPLICATION_RUNDIR') || define('APPLICATION_RUNDIR', (getenv('APPLICATION_RUNDIR') ?: ''));
        $this->currentModule = $this->getCurrentModule();
    }

    /**
     * @throws Exception
     */
    public static function getInstance(): ZfExtended_BaseIndex
    {
        if (null === self::$_instance) {
            self::$_instance = new self($_SERVER['SCRIPT_FILENAME']);
        }

        return self::$_instance;
    }

    /**
     * (re-)initializes important registry values
     *
     * @throws Zend_Application_Bootstrap_Exception
     */
    public function initRegistry(Zend_Application_Bootstrap_Bootstrap $bootstrap): void
    {
        Zend_Registry::set('bootstrap', $bootstrap);
        $bootstrap->bootstrap('frontController');

        $front = $bootstrap->getResource('frontController');
        Zend_Registry::set('frontController', $front);

        $config = new Zend_Config($bootstrap->getOptions());
        Zend_Registry::set('config', $config);

        $bootstrap->bootstrap('db');
        Zend_Registry::set('db', $bootstrap->getResource('db'));

        $bootstrap->bootstrap('cachemanager');
        $cache = $bootstrap->getResource('cachemanager')->getCache('zfExtended');
        Zend_Registry::set('cache', $cache);
        Zend_Registry::set('module', $this->currentModule);
    }

    /**
     * start the application
     * @throws Zend_Application_Exception
     * @throws Zend_Db_Statement_Exception
     */
    public function startApplication(): void
    {
        try {
            $app = $this->initApplication();
            $app->bootstrap()->run();
        } catch (Zend_Db_Statement_Exception $e) {
            if (str_contains($e->getMessage(), 'SQLSTATE[HY000]: General error: 1021 Disk full')) {
                $this->handleDatabaseDown($e);
            } else {
                throw $e;
            }
        } catch (Zend_Db_Adapter_Exception $e) {
            $this->handleDatabaseDown($e);
        }
    }

    /**
     * @throws Zend_Application_Exception
     */
    public function initApplication(): Zend_Application|ZfExtended_Application
    {
        //include optional composer vendor autoloader
        if (file_exists(APPLICATION_ROOT . '/vendor/autoload.php')) {
            require_once APPLICATION_ROOT . '/vendor/autoload.php';
        }

        require_once 'Zend/Loader/Autoloader.php';
        Zend_Loader_Autoloader::getInstance()->setFallbackAutoloader(true);
        /** Zend_Application */
        require_once __DIR__ . '/Application.php';
        ZfExtended_Application::setConfigParserOptions([
            'scannerMode' => INI_SCANNER_TYPED,
        ]);
        $application = new ZfExtended_Application(APPLICATION_ENV, [
            'config' => $this->getApplicationInis(),
        ]);
        $this->initAdditionalConstants();

        // set the available modules
        defined('APPLICATION_MODULES') || define('APPLICATION_MODULES', array_filter($application->getOption('modules')['order'], function ($module) {
            return is_dir(APPLICATION_PATH . '/modules/' . $module);
        }));

        // for each available module, call the module specific function. This will register the module as applet
        foreach (APPLICATION_MODULES as $module) {
            require_once $module . '/Bootstrap.php';
            $class = ucfirst($module) . '_Bootstrap';
            if (method_exists($class, 'initModuleSpecific')) {
                $class::initModuleSpecific();
            }
        }

        return $application;
    }

    /**
     * gets paths to all libs. Later ones should overwrite previous ones  (therefore reverse order than in application.ini)
     * @deprecated
     */
    public function getModulePaths(): array
    {
        $paths = [];
        foreach (APPLICATION_MODULES as $module) {
            $paths[] = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $module);
        }

        return $paths;
    }

    /**
     * gets paths to all libs. Later ones should overwrite previous ones  (therefore reverse order than in application.ini)
     * @throws Zend_Exception
     */
    public function getLibPaths(): array
    {
        $config = Zend_Registry::get('config');
        $paths = [];
        $libs = array_reverse($config->runtimeOptions->libraries->order->toArray());
        foreach ($libs as $lib) {
            $paths[] = realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..' .
                    DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . $lib);
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
     * @param bool $withAcl default true, enables resetting the ACLs, false to prevent this
     * @return string the old module
     * @throws Zend_Exception
     */
    public function setModule(string $module, bool $withAcl = true): string
    {
        if (! is_dir(APPLICATION_PATH . '/modules/' . $module)) {
            throw new Zend_Exception('The module-directory ' . APPLICATION_PATH .
                    '/modules/' . $module . ' does not exist.');
        }
        if (! class_exists('Zend_Registry')) {
            throw new Zend_Exception('application not started yet - Zend_Registry does not exist!');
        }
        $oldModule = $this->currentModule;
        $this->currentModule = $module;
        $bootstrap = Zend_Registry::get('bootstrap');
        $bootstrap->getApplication()->setOptions([
            'config' => $this->getApplicationInis(),
        ]);
        $bootstrap->setOptions($bootstrap->getApplication()->getOptions());
        $this->initRegistry($bootstrap);
        //update the loaded ACLs:
        $withAcl && ZfExtended_Acl::getInstance(true);

        return $oldModule;
    }

    /**
     * adds the options of the passed module-name
     * - options already set stay as they are and do not get overridden
     *
     * @throws Zend_Application_Bootstrap_Exception
     * @throws Zend_Exception
     */
    public function addModuleOptions(string $module): void
    {
        $bootstrap = Zend_Registry::get('bootstrap');
        $oldOptions = $bootstrap->getApplication()->getOptions();
        $this->setModule($module, false);
        $newOptions = $bootstrap->getApplication()->getOptions();
        $options = $bootstrap->getApplication()->mergeOptions($newOptions, $oldOptions);
        $bootstrap->getApplication()->setOptions($options);
        $bootstrap->setOptions($bootstrap->getApplication()->getOptions());
        $this->initRegistry($bootstrap);
    }

    /**
     * defines the current module
     */
    private function getCurrentModule(): string
    {
        $module = 'default';
        $path = APPLICATION_PATH . '/modules/';
        $allModules = array_filter(scandir($path), function ($module) use ($path) {
            return ! str_starts_with($module, '.') && is_dir($path . $module);
        });
        $runDirParts = explode('/', APPLICATION_RUNDIR);
        $uriParts = explode('/', $_SERVER['REQUEST_URI']);

        do {
            $uriPart = array_shift($uriParts);
            $runDirPart = array_shift($runDirParts);
        } while ($uriPart === $runDirPart);

        if (in_array($uriPart, $allModules)) {
            $module = $uriPart;
        }

        define('APPLICATION_MODULE', $module);

        return $module;
    }

    /**
     * initializes all inis to be parsed
     */
    private function getApplicationInis(): array
    {
        $applicationInis = [
            //the main configuration file:
            APPLICATION_PATH . '/config/application.ini',
            //the main configuration file of a module, provided by the module:
            APPLICATION_PATH . '/modules/' . $this->currentModule . '/configs/module.ini',
            //the application configuration file of a module, provided by the application, can overwrite module settings:
            APPLICATION_PATH . '/config/' . $this->currentModule . '.ini',
        ];

        if (self::$addMaintenanceConfig) {
            //this additional config file is loaded when running the CLI configuration / maintenance scripts.
            $applicationInis[] = APPLICATION_PATH . '/config/maintenance.ini';
        }

        //a customized configuration file for the local installation:
        $applicationInis[] = APPLICATION_PATH . '/config/installation.ini';
        //for installations with read only/shared code base only the data directory is usable for the instance, so we have to load optionally the installation.ini from there
        $applicationInis[] = APPLICATION_ROOT . '/data/installation.ini';
        //a customized configuration file for the local installation, called only for a specific module:
        // this feature is currently not documented!
        $applicationInis[] = APPLICATION_PATH . '/config/installation-' . $this->currentModule . '.ini';

        return array_filter($applicationInis, function ($iniFile) {
            return file_exists($iniFile);
        });
    }

    /***
     * Define additional translate5 constants. This will be initialized after the application ini is loaded
     */
    protected function initAdditionalConstants(): void
    {
        defined('ACL_ROLE_PM') || define('ACL_ROLE_PM', 'pm');
        defined('NOW_ISO') || define('NOW_ISO', date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']));
    }

    private function handleDatabaseDown(Exception|Zend_Db_Adapter_Exception $e): void
    {
        error_log($e);
        if (str_contains($e->getMessage(), 'SQLSTATE[HY000] [2002] No such file or directory')) {
            error_log('Fatal: Could not connect to the database! Database down?');
        } elseif (str_contains($e->getMessage(), 'SQLSTATE[HY000] [1045] Access denied for user')) {
            error_log('Fatal: Could not connect to the database! Wrong credentials?');
        } elseif (str_contains($e->getMessage(), 'SQLSTATE[HY000] [1044] Access denied for user')) {
            error_log('Fatal: Could not connect to the database! Wrong DB given?');
        } elseif (str_contains($e->getMessage(), 'SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo failed')) {
            error_log('Fatal: Could not connect to the database! Wrong host given?');
        } else {
            error_log('Fatal: Could not connect to the database! Message from DB: ' . $e->getMessage());
        }
        header('HTTP/1.1 500 Internal Server Error');
        if (ZfExtended_Utils::requestAcceptsJson()) {
            die('{"success": false, "httpStatus": 500, "errorMessage": "<b>Fatal: Could not connect to the database!</b> <br>If you get this message in the Browser: try to reload the application. <br>See error log for details."}');
        }
        include('layouts/dbdown.phtml');
    }
}
