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

use MittagQI\ZfExtended\Worker\Cleaner;

/**
 * This resource bundles recurring jobs for cleaning up stuff in the application
 */
class ZfExtended_Resource_GarbageCollector extends Zend_Application_Resource_ResourceAbstract
{
    public const ORIGIN_CRON = 'cron';

    public const ORIGIN_REQUEST = 'request';

    /**
     * @var Zend_Config
     */
    protected $config;

    public function init()
    {
        $bootstrap = $this->getBootstrap();
        $bootstrap->bootstrap('db');
        $bootstrap->bootstrap('ZfExtended_Resource_ErrorHandler');
        $bootstrap->bootstrap('ZfExtended_Resource_DbConfig');

        $cache = Zend_Cache::factory('Core', new ZfExtended_Cache_MySQLMemoryBackend());
        $backend = $cache->getBackend();
        $this->config = Zend_Registry::get('config');
        /* @var $backend ZfExtended_Cache_MySQLMemoryBackend */
        $interval = 15 * 60;
        $key = 'ZfExtended_Resource_GarbageCollector::cleanByInterval';
        //using cache backend as time based mutex to ensure that cleanup is not done more often as each 15 minutes:
        $url = substr($_SERVER['REQUEST_URI'], 0, 4000); //restrict the URL length
        if ($this->checkOrigin(self::ORIGIN_REQUEST) && $backend->updateIfOlderThen($key, $url, $interval)) {
            $this->cleanUp(self::ORIGIN_REQUEST);
        }
    }

    /**
     * Start garbage collection, $callOrigin is a string to identify from where cleanUp was called
     * @param string $callOrigin
     */
    public function cleanUp($callOrigin)
    {
        //if given origin is allowed via config we trigger garbage collection
        if (! $this->checkOrigin($callOrigin)) {
            return;
        }

        //start zfextended stuff to be cleaned
        $this->cleanUpWorker();

        // clean outdated session data
        $this->cleanUpSession();

        // cleanup chache (Zf_memcache)
        $this->cleanUpCache();

        //start clean up stuff in other parts of the application
        $triggerEvents = function () {
            //trigger event for module specific clean up
            $events = ZfExtended_Factory::get('ZfExtended_EventManager', [get_class($this)]);
            /* @var $events ZfExtended_EventManager */
            $events->trigger('cleanUp', $this);
        };

        //if called directly via the self::init method here, we have to trigger the events to a later point in the application run.
        // since self::init call is before the event binding in later processed module Bootstraps
        // we do that by adding the GarbageCollection Controller plugin - only if needed/triggered
        if ($callOrigin == self::ORIGIN_REQUEST) {
            $plugin = new ZfExtended_Controllers_Plugins_GarbageCollector($triggerEvents);
            $front = Zend_Controller_Front::getInstance();
            $front->registerPlugin($plugin);
        } else {
            //in cron call, we can trigger events directly
            $triggerEvents();
        }
    }

    /**
     * checks if the given call origin is allowed to start garbage collection
     * reconfigures the garbageCollector invocation to type "cron" if called once via cron
     * @param string $callOrigin
     * @return boolean true if origin matches the configured one
     */
    protected function checkOrigin($callOrigin)
    {
        if (isset($this->config) && isset($this->config->runtimeOptions->garbageCollector)) {
            $configuredInvocation = $this->config->runtimeOptions->garbageCollector->invocation;
        } else {
            $configuredInvocation = false;
        }

        //if origin is already cron, or if a origin different as cron is given: we return true if origin == allowed invocation origin
        if ($configuredInvocation == self::ORIGIN_CRON || $callOrigin != self::ORIGIN_CRON) {
            return $configuredInvocation == $callOrigin;
        }
        //if the config value was not cron but the cleanup was triggered via cron, we set the config to cron and return true
        $config = ZfExtended_Factory::get('ZfExtended_Models_Config');
        /* @var $config ZfExtended_Models_Config */
        $config->update('runtimeOptions.garbageCollector.invocation', self::ORIGIN_CRON);

        return true;
    }

    protected function cleanUpWorker(): void
    {
        Cleaner::clean();
    }

    /**
     * Cleans up outdated session-data
     */
    protected function cleanUpSession()
    {
        // the implementation in ZfExtended_Session_SaveHandler_DbTable will not use the argument anyway so no need to bother with the config ...
        $saveHandler = Zend_Session::getSaveHandler();
        if ($saveHandler !== null) {
            Zend_Session::getSaveHandler()->gc(864000);
        }
    }

    /**
     * cleanup chache (DB: Zf_memcache)
     */
    protected function cleanUpCache()
    {
        $cache = Zend_Cache::factory('Core', new ZfExtended_Cache_MySQLMemoryBackend());
        $cache->clean('old');
    }
}
