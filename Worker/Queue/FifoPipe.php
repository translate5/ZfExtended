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

namespace MittagQI\ZfExtended\Worker\Queue;

class FifoPipe
{
    public const NOTIFICATION_NOT_USED = 0;

    public const NOTIFICATION_SUCCESS = 1;

    public const NOTIFICATION_FAILED = 2;

    private const FIFO_PATH = APPLICATION_DATA . '/tmp/worker.fifo';

    /**
     * Waiting for new queue notifications in seconds
     */
    private const WAIT_FOR_NOTIFICATIONS = 180;

    private mixed $pipeResource = null;

    private int $lastActivity = 0;

    public static function notifyRunning(): int
    {
        //static since not controlled by internal $pipeResource instance

        //use fifo notification only if enabled by queuer
        if (file_exists(self::FIFO_PATH)) {
            $pipe = fopen(self::FIFO_PATH, 'w+');

            if ($pipe) {
                $size = fwrite($pipe, "notify\n");
                fflush($pipe);
                fclose($pipe);

                return $size > 0 ? self::NOTIFICATION_SUCCESS : self::NOTIFICATION_FAILED;
            }
        }

        return self::NOTIFICATION_NOT_USED;
    }

    public function initReader(bool $enabled): void
    {
        if (! $enabled) {
            return;
        }
        $umask = umask(0);
        if (! posix_mkfifo(self::FIFO_PATH, 0666) && ! file_exists(self::FIFO_PATH)) {
            error_log('Could not create temporary fifo file ' . self::FIFO_PATH);
        }
        umask($umask);

        $this->lastActivity = time();
        $this->pipeResource = fopen(self::FIFO_PATH, 'r+');
        stream_set_blocking($this->pipeResource, false);
    }

    /**
     * @return bool return true when checks should stop
     * @throws EmptyPipeException
     */
    public function checkPipe(): bool
    {
        $isQueueRequested = false;
        if (is_resource($this->pipeResource)) {
            // read all queue pings in a bunch
            while (true) {
                $line = fgets($this->pipeResource);
                // error_log('READ ' . $line);
                if ($line === false) {
                    break;
                } else {
                    $this->lastActivity = time();
                    $isQueueRequested = true;
                }
            }

            if (time() - $this->lastActivity > self::WAIT_FOR_NOTIFICATIONS) {
                throw new EmptyPipeException();
            }
            usleep(500000);
        }

        return $isQueueRequested;
    }

    public function close(): void
    {
        if (is_resource($this->pipeResource)) {
            fclose($this->pipeResource);
        }
    }

    /**
     * @return bool false if timedout or error, true if new data is there
     */
    public function waitForPipe(): bool
    {
        $this->lastActivity = time();

        $read = [$this->pipeResource];
        $write = null;
        $except = null;

        $res = stream_select($read, $write, $except, self::WAIT_FOR_NOTIFICATIONS);

        return $res > 0;
    }
}
