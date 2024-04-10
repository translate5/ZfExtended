<?php

namespace MittagQI\ZfExtended\Logger;

/**
 * Simple custom logger to write log/debug input in log file on the disk
 */
class CustomFileLogger
{
    private $logDirPath;

    private $enabled = false;

    private $logBuffer;

    private $currentLogFile;

    public function __construct()
    {
        $this->logDirPath = APPLICATION_DATA . '/logs/';
        $this->logBuffer = [];
        $this->currentLogFile = $this->getLogFilePath();
    }

    public function log(string $message): void
    {
        $this->logBuffer[] = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    private function getLogFilePath(): string
    {
        return $this->logDirPath . 'custom-log_' . date('m.d.Y') . '.log';
    }

    public function write(): void
    {
        if ($this->enabled && count($this->logBuffer) > 0) {
            $logs = implode('', $this->logBuffer);
            file_put_contents($this->currentLogFile, $logs, FILE_APPEND);
        }

        // Clear the log buffer after writing to the file.
        $this->logBuffer = [];

        // Check if the date has changed and update the log file accordingly.
        $newLogFile = $this->getLogFilePath();
        if ($this->currentLogFile !== $newLogFile) {
            $this->currentLogFile = $newLogFile;
        }
    }
}
