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

use MittagQI\Translate5\Acl\Rights;
use MittagQI\ZfExtended\Service\ServiceAbstract;

/**
 * provides basic functionality for plugins
 */
abstract class ZfExtended_Plugin_Abstract
{
    /**
     * A public plug-in is available for everybody
     * @var string
     */
    public const TYPE_PUBLIC = 'public';

    /**
     * A private plug-in is only available if purchased
     * @var string
     */
    public const TYPE_PRIVATE = 'private';

    /**
     * A private plug-in is only available for specific installations
     * @var string
     */
    public const TYPE_CLIENT_SPECIFIC = 'clientspecific';

    /**
     * The plug-in type
     */
    protected static string $type = self::TYPE_PUBLIC;

    protected static bool $enabledByDefault = false;

    /**
     * A human-readable description of the plug-in
     */
    protected static string $description = 'Please overwrite me in the plug-in init';

    /**
     * Set to true in the concrete plug-in, if it should be activated when running tests
     */
    protected static bool $activateForTests = false;

    /**
     * Set to true in the concrete plug-in, if only the configs are needed in the test
     * (and the plugin is not activated by default for tests)
     */
    protected static bool $configureForTests = false;

    /**
     * Here configs can be defined that are used to fill the test-DB
     * Structure is [ 'runtimeOptions.plugins.pluginname.configName' => value ]
     * If value is NULL, it will be fetched from the application DB, otherwise the defined value is taken
     */
    protected static array $testConfigs = [];

    /**
     * Represents the services we have. They must be given in the format name => Service class name
     * where name usually represents the docker service name, e.g. [ 'someservice' => editor_Plugins_SomePlugin_SomeService::class ]
     * @var string[]
     */
    protected static array $services = [];

    /**
     * Return the plug-in description
     */
    public static function getDescription(): string
    {
        return static::$description;
    }

    /**
     * Return the plug-in type
     */
    public static function getType(): string
    {
        return static::$type;
    }

    /**
     * Return if the plug-in should be enabled on installation
     */
    public static function isEnabledByDefault(): bool
    {
        return static::$enabledByDefault;
    }

    /**
     * Return if the plug-in is needed for the test-suite
     */
    public static function isNeededForTests(): bool
    {
        return static::$activateForTests;
    }

    /**
     * Returns if the configs of the plugin are needed for tests
     */
    public static function hasConfigsForTests(): bool
    {
        return static::$activateForTests || static::$configureForTests;
    }

    /**
     * Retrieves an instance of the named service
     * @throws ZfExtended_Exception
     */
    public static function createService(string $serviceName, Zend_Config $config = null): ServiceAbstract
    {
        $pluginName = ZfExtended_Plugin_Manager::getPluginNameByClass(static::class);
        if (! array_key_exists($serviceName, static::$services)) {
            throw new ZfExtended_Exception('Service "' . $serviceName . '" not configured in plugin ' . $pluginName);
        }
        if ($config == null) {
            $config = Zend_Registry::get('config');
        }

        return ZfExtended_Factory::get(static::$services[$serviceName], [$serviceName, $pluginName, $config]);
    }

    /**
     * Retrieves all services the plugin uses
     * @return ServiceAbstract[] $name => $service
     */
    public static function createAllServices(Zend_Config $config): array
    {
        $services = [];
        $pluginName = ZfExtended_Plugin_Manager::getPluginNameByClass(static::class);
        foreach (static::$services as $serviceName => $serviceClass) {
            $services[$serviceName] = ZfExtended_Factory::get($serviceClass, [$serviceName, $pluginName, $config]);
        }

        return $services;
    }

    /**
     * See ::$testConfigs for the structure of the returned array
     */
    public static function getTestConfigs(Zend_Config $config): array
    {
        $configs = [static::$testConfigs];
        foreach (static::createAllServices($config) as $service) {
            $configs[] = $service->getTestConfigs();
        }

        return array_merge(...$configs);
    }

    /**
     * Retrieves the mocked service configurations - if any
     */
    public static function getMockConfigs(Zend_Config $config): array
    {
        $configs = [];
        foreach (static::createAllServices($config) as $service) {
            if ($service->isMockedService()) {
                $configs[] = $service->getMockConfigs();
            }
        }

        return array_merge(...$configs);
    }

    /**
     * Retrieves if the plugin has a service of the given name
     */
    public static function hasService(string $serviceName): bool
    {
        return array_key_exists($serviceName, static::$services);
    }

    /**
     * Contains absolute plugin path
     * @var string
     */
    protected $absolutePluginPath = '';

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
    protected $frontendControllers = [];

    /**
     * A folder relative to the plugin root which contains the plugin translations
     * if false there are no translations added to the translation framework
     * if used, should be by convention: "locales"
     * @var string
     */
    protected $localePath = false;

    protected $publicFileTypes = ['js', 'resources'];

    public function __construct($pluginName)
    {
        $this->pluginName = $pluginName;
        $this->eventManager = Zend_EventManager_StaticEventManager::getInstance();
        $c = Zend_Registry::get('config');
        if (empty($c->runtimeOptions->plugins)) {
            // No Plugin Configuration found!
            throw new ZfExtended_Plugin_Exception('E1235');
        }
        $this->config = $c->runtimeOptions->plugins->$pluginName;
        $this->activePlugins = $c->runtimeOptions->plugins->active->toArray();
        $rc = new ReflectionClass($this);
        $this->absolutePluginPath = rtrim(dirname($rc->getFileName()), "/\\");
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
    public function getFrontendControllers()
    {
        return array_values($this->frontendControllers);
    }

    /**
     * reusable function to filter frontend controlles by ACL
     * This is not used by default.
     * @throws Zend_Acl_Exception
     */
    protected function getFrontendControllersFromAcl(string $resourceId = Rights::ID): array
    {
        $result = [];
        $auth = ZfExtended_Authentication::getInstance();
        if (! $auth->isAuthenticated()) {
            return $result;
        }
        $acl = ZfExtended_Acl::getInstance();
        if (! $acl->has($resourceId)) {
            return $result;
        }
        foreach ($this->frontendControllers as $right => $controller) {
            if ($acl->isInAllowedRoles($auth->getUserRoles(), $resourceId, $right)) {
                $result[] = $controller;
            }
        }

        return $result;
    }

    /**
     * return the plugins absolute locale path
     * @return string|false
     */
    public function getLocalePath()
    {
        if (! $this->localePath) {
            return false;
        }

        return $this->getPluginPath() . '/' . $this->localePath;
    }

    /**
     * Returns the web directory for public resources
     */
    public function getResourcePath(string $resource): string
    {
        //the parts /plugins/resources/ are defined by convention
        return APPLICATION_RUNDIR . '/' . Zend_Registry::get('module') . '/plugins/resources/' . $this->pluginName . '/' . ltrim($resource, '/');
    }

    /**
     * SubClasses of $classname are recognized as fulfilled dependency!
     * @param string $classname
     * @throws ZfExtended_Plugin_MissingDependencyException
     */
    protected function dependsOn($classname)
    {
        if (in_array($classname, $this->activePlugins)) {
            return;
        }
        foreach ($this->activePlugins as $oneActive) {
            if (is_subclass_of($oneActive, $classname)) {
                return;
            }
        }

        //A Plugin is missing or not active
        throw new ZfExtended_Plugin_MissingDependencyException('E1236', [
            'plugin' => $classname,
        ]);
    }

    /**
     * @param string $classname
     * @throws ZfExtended_Plugin_ExclusionException
     */
    protected function blocks($classname)
    {
        if (in_array($classname, $this->activePlugins)) {
            //Plugins are not allowed to be active simultaneously
            throw new ZfExtended_Plugin_ExclusionException('E1237', [
                'current' => get_class($this),
                'blocked' => $classname,
            ]);
        }
    }

    /**
     * Check if the folder contains file
     * @param string $dir
     * @return boolean
     */
    protected function isFolderEmpty($dir)
    {
        foreach (new DirectoryIterator($dir) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * returns the absolute plugin path
     * @return string
     */
    public function getPluginPath()
    {
        return $this->absolutePluginPath;
    }

    /**
     * returns the plugin specific config
     * @throws ZfExtended_Exception
     * @return Zend_Config
     */
    public function getConfig()
    {
        if (empty($this->config)) {
            //No Plugin Configuration found for plugin
            throw new ZfExtended_Plugin_Exception('E1238', [
                'plugin' => $this->pluginName,
            ]);
        }

        return $this->config;
    }

    /**
     * Retrieves an instance of the named service
     * @throws ZfExtended_Exception
     */
    public function getService(string $serviceName, Zend_Config $config = null): ServiceAbstract
    {
        if ($config == null) {
            $config = Zend_Registry::get('config');
        }
        if (! array_key_exists($serviceName, static::$services)) {
            throw new ZfExtended_Exception('Service "' . $serviceName . '" not configured in plugin ' . get_class($this));
        }

        return ZfExtended_Factory::get(static::$services[$serviceName], [$serviceName, $this->pluginName, $config]);
    }

    /**
     * Retrieves all services configured for this plugin
     * Returned will be an assoc array like $serviceName => $service
     * @return ServiceAbstract[]
     * @throws ZfExtended_Plugin_Exception
     */
    public function getServices(Zend_Config $config = null): array
    {
        if ($config == null) {
            $config = Zend_Registry::get('config');
        }
        $services = [];
        foreach (static::$services as $serviceName => $serviceClass) {
            $services[$serviceName] = ZfExtended_Factory::get($serviceClass, [$serviceName, $this->pluginName, $config]);
        }

        return $services;
    }

    /**
     * Adds a sub-folder to the plugins "public" folder to make in publically accessible. Per default these are 'js' and 'css'
     * @param string $newType
     */
    public function addPublicSubFolder($newType)
    {
        array_push($this->publicFileTypes, $newType);
    }

    /**
     * Adds the given controller to the application
     * Give just the Controller Name, Controller directory in Plugins is by convention "Controllers" and file must end with .php
     * @param string $controller
     */
    public function addController($controller)
    {
        require_once $this->getPluginPath() . '/Controllers/' . $controller . '.php';
    }

    /**
     * @return string[]
     */
    public function getPublicSubFolder()
    {
        return $this->publicFileTypes;
    }

    public function isPublicSubFolder(string $requestedType, array $config): bool
    {
        return in_array($requestedType, $this->getPublicSubFolder());
    }

    /**
     * returns the requested file to be flushed to the browser or null if not allowed/not possible
     */
    public function getPublicFile(string $requestedType, array $requestedFileParts, array $config): ?SplFileInfo
    {
        $absolutePath = null;
        //get public files of the plugin to make a whitelist check of the file string from userland
        $allowedFiles = $this->getPublicFiles($requestedType, $absolutePath, $config);
        $file = join(DIRECTORY_SEPARATOR, $requestedFileParts);
        if (empty($allowedFiles) || ! in_array($file, $allowedFiles)) {
            return null;
        }

        //concat the absPath from above with filepath
        return new SplFileInfo($absolutePath . DIRECTORY_SEPARATOR . $file);
    }

    /**
     * returns a list of files from plugins public directory. List is normally used as whitelist on file inclusion.
     * @param string $subdirectory      optional, subdirectory to start in
     * @param string|null $absolutePath optional, passed by reference to get the absolutePath from this method
     * @param array $config             may hold further controller specific environment variables
     * @return string[]
     */
    public function getPublicFiles(string $subdirectory, ?string &$absolutePath, array $config): array
    {
        $publicDirectory = $this->absolutePluginPath . '/public/' . $subdirectory;
        $absolutePath = $publicDirectory;
        $objects = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $publicDirectory,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $result = [];
        foreach ($objects as $file) {
            if ($file->isFile()) {
                $result[] = trim(str_replace([$publicDirectory, '\\'], ['', '/'], $file), '/');
            }
        }

        return $result;
    }

    /**
     * Return the plugin module name. The module name is parsed from
     * the plugin class (each plugin class starts with the module name)
     */
    public function getModuleName(): string
    {
        return current(explode('_', get_class($this)));
    }
}
