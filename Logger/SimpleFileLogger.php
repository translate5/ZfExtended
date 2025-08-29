<?php

declare(strict_types=1);

namespace MittagQI\ZfExtended\Logger;

use DateTime;

/**
 * Simple logger to write log data into a file in the log dir
 * filename must be NAME.log and added to \MittagQI\Translate5\Cronjob\Cronjobs::rotateLogs
 * @see \MittagQI\Translate5\Cronjob\Cronjobs::rotateLogs
 */
class SimpleFileLogger
{
    private string $logDirPath;

    /**
     * @param int $maxSize If given, this will be the max size of logged data in Bytes. Bigger logs will be truncated
     */
    public function __construct(
        string $fileName,
        private int $maxSize = 0
    ) {
        $this->logDirPath = APPLICATION_DATA . '/logs/' . $fileName;
    }

    public function log(string $msg): void
    {
        // securing non-rotated logfiles: prevent growing too big ...
        if ($this->maxSize > 0) {
            $size = filesize($this->logDirPath);
            if ($size > $this->maxSize) {
                $remain = -1 * (int) floor($this->maxSize / 2);
                $lastHalf = file_get_contents($this->logDirPath, false, null, $remain);
                file_put_contents($this->logDirPath, $lastHalf);
            }
        }
        error_log($this->getTime() . ' ' . $msg . PHP_EOL, 3, $this->logDirPath);
    }

    public function getTime(): string
    {
        return (new DateTime())->format('Y-m-d H:i:s.u P');
    }
}
