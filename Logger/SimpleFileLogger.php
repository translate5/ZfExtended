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

    public function __construct(string $fileName)
    {
        $this->logDirPath = APPLICATION_DATA . '/logs/' . $fileName;
    }

    public function log(string $msg): void
    {
        error_log($this->getTime() . ' ' . $msg . PHP_EOL, 3, $this->logDirPath);
    }

    public function getTime(): string
    {
        return (new DateTime())->format('Y-m-d H:i:s.u P');
    }
}
