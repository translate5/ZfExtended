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
declare(strict_types=1);

namespace MittagQI\ZfExtended\Worker;

use MittagQI\ZfExtended\Logger\SimpleFileLogger;
use ZfExtended_Models_Worker;

class Logger
{
    public const LOG_NAME = 'worker.log';

    private static ?self $instance = null;

    protected function __construct(
        private readonly SimpleFileLogger $fileLogger
    ) {
        // just beeing protected
    }

    public static function getInstance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self(
                new SimpleFileLogger(self::LOG_NAME)
            );
        }

        return self::$instance;
    }

    public function log(ZfExtended_Models_Worker $worker, string $type, bool $full = false, string $event = ''): void
    {
        $msg = $this->fileLogger->getTime() . ' ' . $type . ' ' . $worker->getId() . ' ' . $worker->getWorker();

        if ($full) {
            $msg .= ' data: ' . json_encode($worker->getDataObject());
        }

        if ($type == $worker::STATE_RUNNING) {
            $msg .= ' pid: ' . $worker->getPid();
        }

        if (! empty($worker->getTaskGuid())) {
            $msg .= ' task: ' . $worker->getTaskGuid();
        }

        if (! empty($event)) {
            $msg .= ' event: ' . $event;
        }

        error_log($msg . PHP_EOL, 3, APPLICATION_DATA . '/logs/' . self::LOG_NAME);
    }

    public function logEvent(ZfExtended_Models_Worker $worker, string $event): void
    {
        $this->log($worker, $worker->getState(), false, $event);
    }

    public function logRaw(string $msg): void
    {
        $this->fileLogger->log($msg);
    }
}
