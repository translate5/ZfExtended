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
 * provides basic functionality for plugins
 */
class ZfExtended_Plugin_Manager
{
    public const EVENT_AFTER_PLUGIN_BOOTSTRAP = 'afterPluginBootstrap';

    /**
     * returns the Plugin Name distilled from class name
     */
    public static function getPluginNameByClass(string $className): string
    {
        $parts = explode('_', $className);
        reset($parts);
        do {
            if (strtolower(current($parts)) === 'plugins') {
                return next($parts);
            }
        } while (next($parts) !== false);

        return $className;
    }

    /**
     * Container for the Plugin Instances
     * @var array
     */
    protected $pluginInstances = [];

    protected $pluginNames = [];

    protected $allLocalePaths = [];

    protected $allFrontendControllers = [];

    private array $activePlugins = [];

    public function __construct()
    {
        $config = Zend_Registry::get('config');
        if (! isset($config->runtimeOptions->plugins)) {
            return;
        }

        $this->activePlugins = array_unique($config->runtimeOptions->plugins->active->toArray());
    }

    /**
     * returns a list of active plugins according to the config
     * @throws Zend_Exception
     */
    public function getActive(): array
    {
        return $this->activePlugins;
    }

    /**
     * returns true if a given plugin name is active
     * @throws Zend_Exception
     */
    public function isActive(string $plugin): bool
    {
        return in_array($this->getAvailable()[$plugin] ?? '', $this->getActive());
    }

    /**
     * Activates the plugin given by name
     * @param bool $activate true to activate, false to deactivate
     * @throws Zend_Db_Statement_Exception
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityConstraint
     * @throws ZfExtended_Models_Entity_Exceptions_IntegrityDuplicateKey
     */
    public function setActive(string $plugin, bool $activate = true): bool
    {
        $plugin = strtolower($plugin);
        $available = $this->getAvailable();
        $keys = array_map('strtolower', array_keys($available));
        $available = array_combine($keys, array_values($available));
        if (empty($available[$plugin])) {
            return false;
        }
        $config = ZfExtended_Factory::get('editor_Models_Config');
        /* @var $config editor_Models_Config */
        $config->loadByName('runtimeOptions.plugins.active');
        $active = json_decode($config->getValue());
        if (! is_array($active)) {
            $active = [];
        }
        if ($activate) {
            $active[] = $available[$plugin];
        } else {
            $active = array_unique($active);
            $found = array_search($available[$plugin], $active);
            if ($found === false) {
                return true;
            }
            unset($active[$found]);
        }
        $config->setValue(json_encode(array_unique(array_values($active))));
        $config->save();

        $this->activePlugins = $active;

        return true;
    }

    /**
     * @throws Zend_Exception
     */
    public function bootstrap(): void
    {
        $pluginClasses = $this->getActive();
        if (empty($pluginClasses)) {
            return;
        }

        //TRANSLATE-569: ensure that only the plugin config for the affected module is loaded.
        $logger = Zend_Registry::get('logger');
        /* @var $logger ZfExtended_Logger */
        foreach ($pluginClasses as $pluginClass) {
            try {
                $name = static::getPluginNameByClass($pluginClass);
                $plugin = ZfExtended_Factory::get($pluginClass, [$name]);
                /* @var $plugin ZfExtended_Plugin_Abstract */
                if ($plugin->getModuleName() !== Zend_Registry::get('module')) {
                    continue;
                }
                $this->pluginNames[$pluginClass] = $name;
                $this->pluginInstances[$pluginClass] = $plugin;
                /* @var $plugin ZfExtended_Plugin_Abstract */
                $localePath = $plugin->getLocalePath();
                if ($localePath) {
                    $this->allLocalePaths[$name] = $localePath;
                }
            } catch (ReflectionException $exception) {
                $logger->warn('E1218', 'The PHP class for the activated plug-in "{plugin}" does not exist.', [
                    'plugin' => $pluginClass,
                    'originalExceptionMsg' => $exception->getMessage(),
                    'path' => get_include_path(),
                ]);
            }
        }

        //due to ACL restrictions we have first load all plugins, then we can call getFrontendControllers
        foreach ($this->pluginInstances as $plugin) {
            $this->allFrontendControllers = array_merge(
                $this->allFrontendControllers,
                $plugin->getFrontendControllers()
            );
        }

        $events = ZfExtended_Factory::get(ZfExtended_EventManager::class, [$this::class]);
        $events->trigger(self::EVENT_AFTER_PLUGIN_BOOTSTRAP, $this, [
            'pluginManager' => $this,
        ]);
    }

    /**
     * return frontend controllers for all active plugins
     */
    public function getActiveFrontendControllers(): array
    {
        return $this->allFrontendControllers;
    }

    /**
     * return absolute path to locale directories for all active plugins
     */
    public function getActiveLocalePaths(): array
    {
        return $this->allLocalePaths;
    }

    /**
     * Intelligent Method to return the stored plugin instance by
     *   the direct plugin name,
     *   the plugin classname
     *   or either the classname of a class belonging to the plugin (for example a worker belonging to the plugin)
     * @param string $key the plugin key/class to look for
     * @throws ZfExtended_Plugin_Exception
     */
    public function get($key): ?ZfExtended_Plugin_Abstract
    {
        if (isset($this->pluginInstances[$key])) {
            return $this->pluginInstances[$key];
        }
        $name = static::getPluginNameByClass($key);
        $classes = array_keys($this->pluginNames, $name, true);
        $cnt = count($classes);
        if ($cnt === 0) {
            return null;
        }
        if ($cnt === 1) {
            $key = $classes[0];

            return empty($this->pluginInstances[$key]) ? null : $this->pluginInstances[$key];
        }

        //if some one ever traps here: search key ordered by "_" in the plugin class list (or implement something like a search tree)
        // Multiple Plugin Classes found to key
        throw new ZfExtended_Plugin_Exception('E1234', [
            'key' => $key,
            'foundClasses' => $classes,
        ]);
    }

    /**
     * returns a list of loaded plugins for the current module
     * @return string[]
     */
    public function getLoaded(): array
    {
        return array_keys($this->pluginInstances);
    }

    /**
     * returns the instances of the loaded plugins for this module
     * @return ZfExtended_Plugin_Abstract[]
     */
    public function getInstances(): array
    {
        return array_values($this->pluginInstances);
    }

    /**
     * Get all plugin names for the current module.
     * @throws Zend_Exception
     */
    public function getAllPluginNames(): array
    {
        $module = Zend_Registry::get('module');
        $path = APPLICATION_PATH . '/modules/' . $module . '/Plugins';
        if (! is_dir($path)) {
            return [];
        }
        $glob = glob($path . '/*');

        if ($glob === false) {
            return [];
        }

        $result = array_map(function ($dir) {
            if (! is_dir($dir)) {
                return false;
            }
            $dir = explode('/', $dir);

            return end($dir);
        }, $glob);

        return array_filter($result);
    }

    /**
     * returns a list of installed plug-ins
     * @return array ['PluginName'=>'Plugin_Init_Class',...]
     */
    public function getAvailable(): array
    {
        $result = [];
        $moduleDirs = new FilesystemIterator(APPLICATION_PATH . '/modules/');
        foreach ($moduleDirs as $moduleDirInfo) {
            //get plugins of this module
            $pluginDirPath = $moduleDirInfo->getPathname() . '/Plugins';
            if (! \is_dir($pluginDirPath) || ! in_array($moduleDirInfo->getBaseName(), APPLICATION_MODULES)) {
                continue;
            }
            $pluginDirs = new FilesystemIterator($pluginDirPath);
            foreach ($pluginDirs as $pluginDirInfo) {
                /* @var $pluginDirInfo \SplFileInfo */

                if (! $pluginDirInfo->isDir()) {
                    continue;
                }
                $name = $pluginDirInfo->getBasename();
                if (file_exists($pluginDirInfo . '/Init.php')) {
                    $result[$name] = $moduleDirInfo->getBasename() . '_Plugins_' . $name . '_Init';

                    continue;
                }
                if (file_exists($pluginDirInfo . '/Bootstrap.php')) {
                    $result[$name] = $moduleDirInfo->getBasename() . '_Plugins_' . $name . '_Bootstrap';

                    continue;
                }
            }
        }

        return $result;
    }

    /***
     * Return plugin config prefix for all inactive plugins
     * @return array
     */
    public function getInactivePluginsConfigPrefix(): array
    {
        $allNames = $this->getAllPluginNames();
        $active = $this->getActive();
        $filtered = array_map(function ($item) use ($active) {
            $initClass = 'editor_Plugins_' . ucfirst($item) . '_Init';
            $bootstrapClass = 'editor_Plugins_' . ucfirst($item) . '_Bootstrap';
            if (! in_array($initClass, $active) && ! in_array($bootstrapClass, $active)) {
                return 'runtimeOptions.plugins.' . $item;
            }

            return '';
        }, $allNames);

        return array_filter($filtered);
    }

    /**
     * Activates only the plug-ins as defined for the tests
     */
    public function activateTestRelevantOnly(): void
    {
        $plugins = $this->getAvailable();
        $active = [];
        foreach ($plugins as $plugin => $cls) {
            if ($cls::isNeededForTests()) {
                $active[] = $cls;
            }
        }
        $config = ZfExtended_Factory::get(editor_Models_Config::class);
        $config->update('runtimeOptions.plugins.active', json_encode($active));
    }

    /**
     * Retrieve the plugin configs that need to be copied or added to the test-DB
     */
    public function getTestConfigs(): array
    {
        $testConfigs = [];
        $config = Zend_Registry::get('config');
        $plugins = $this->getAvailable();
        /* @var ZfExtended_Plugin_Abstract $cls */
        foreach ($plugins as $plugin => $cls) {
            if ($cls::hasConfigsForTests()) {
                $testConfigs = array_merge($testConfigs, $cls::getTestConfigs($config));
            }
        }

        return $testConfigs;
    }

    /**
     * Retrieves the plugin service configs that represent mocked services
     */
    public function getMockConfigs(): array
    {
        $mockConfigs = [];
        $config = Zend_Registry::get('config');
        $plugins = $this->getAvailable();
        /* @var ZfExtended_Plugin_Abstract $cls */
        foreach ($plugins as $plugin => $cls) {
            if ($cls::hasConfigsForTests()) {
                $mockConfigs = array_merge($mockConfigs, $cls::getMockConfigs($config));
            }
        }

        return $mockConfigs;
    }
}
