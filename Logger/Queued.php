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
 * Do not write the generated log entries, but queues them.
 * Call flush method to flush the queue to the writers.
 */
class ZfExtended_Logger_Queued extends ZfExtended_Logger
{
    /**
     * @var array
     */
    protected $eventQueue = [];

    /**
     * Only allowed parameter is the domain for this instance. For custom writers prepare a custom logger and pass it to the flush method.
     * @param string $domain
     */
    public function __construct($domain = ZfExtended_Logger::CORE_DOMAIN)
    {
        $this->domain = $domain;
        $r = new ReflectionClass($this);
        // enables different log levels logging
        $this->logLevels = array_flip($r->getConstants());
    }

    /**
     * pass the event to each configured writer, or to the given one only
     */
    protected function processEvent(ZfExtended_Logger_Event $event, array $writersToUse = [])
    {
        $this->eventQueue[] = $event;
    }

    /**
     * Returns true if there are queue logs
     */
    public function hasQueuedLogs(): bool
    {
        return ! empty($this->eventQueue);
    }

    /**
     * log all queue entries and clear queue
     * @param array $commonExtraData Optional extraData added to each Event
     * @param string $domain Optional, reset the events domain to the given one
     * @param ZfExtended_Logger $logger Optional, if no custom logger provide the default registered one is used to write the logs
     */
    public function flush(array $commonExtraData = [], string $domain = null, ZfExtended_Logger $logger = null)
    {
        //if we use a given logger, we also use that loggers domain, easiest way to set a custom domain, since for custom domains mostly a custom logger already exists
        $logger = $logger ?? Zend_Registry::get('logger');
        /* @var $logger ZfExtended_Logger */

        foreach ($this->eventQueue as $event) {
            /* @var $event ZfExtended_Logger_Event */
            $event->mergeFromArray([
                'extra' => $commonExtraData,
                'domain' => $domain ?? $event->domain,
            ]);
            $logger->processEvent($event);
        }
        $this->eventQueue = [];
    }
}
