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

use MittagQI\Translate5\Export\QueuedExportCleanUpService;
use MittagQI\ZfExtended\Worker\Cleaner;
use MittagQI\ZfExtended\Worker\Queue;
use MittagQI\ZfExtended\Worker\Rescheduler;

/**
 * This resource bundles recurring jobs for cleaning up stuff in the application
 */
class ZfExtended_Resource_GarbageCollector extends Zend_Application_Resource_ResourceAbstract
{
    /**
     * @deprecated remove the feature switch after feature was rolled out and tested in the wild
     */
    private bool $exportCleanining;

    public function __construct($options = null)
    {
        parent::__construct($options);
        $this->exportCleanining = (bool) ($this->_options['featureSwitchExportCleaning'] ?? false);
    }

    public function init()
    {
        // currently nothing to do
    }

    /**
     * Start garbage collection
     * This is only used via cron-jobs currently
     * @throws ReflectionException
     * @throws Zend_Exception
     */
    public function cleanUp(): void
    {
        //start zfextended stuff to be cleaned
        $this->cleanUpWorker();

        // clean outdated session data
        $this->cleanUpSession();

        // cleanup chache (Zf_memcache)
        $this->cleanUpCache();

        $this->cleanUpData();

        //trigger cleanup-event for module specific clean up
        $events = ZfExtended_Factory::get(ZfExtended_EventManager::class, [get_class($this)]);
        $events->trigger('cleanUp', $this);
    }

    /**
     * @throws ReflectionException
     * @throws Zend_Exception
     */
    protected function cleanUpWorker(): void
    {
        Cleaner::clean();
        // re-schedule delayed workers
        Rescheduler::reschedule();
        // basically calling the queue is preventing garbage in the worker table...
        ZfExtended_Factory::get(Queue::class)->trigger();
    }

    /**
     * Cleans up outdated session-data
     */
    protected function cleanUpSession(): void
    {
        // the implementation in ZfExtended_Session_SaveHandler_DbTable will not use the argument
        // anyway so no need to bother with the config ...
        $saveHandler = Zend_Session::getSaveHandler();
        if ($saveHandler !== null) {
            Zend_Session::getSaveHandler()->gc(864000);
        }
    }

    /**
     * cleanup chache (DB: Zf_memcache)
     * @throws Zend_Cache_Exception
     */
    protected function cleanUpCache(): void
    {
        $cache = Zend_Cache::factory('Core', new ZfExtended_Cache_MySQLMemoryBackend());
        $cache->clean('old');
    }

    protected function cleanUpData(): void
    {
        if ($this->exportCleanining) {
            $exportCleanUpService = new QueuedExportCleanUpService();
            $exportCleanUpService->cleanUp();
        }
    }
}
