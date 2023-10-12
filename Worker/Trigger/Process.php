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

namespace MittagQI\ZfExtended\Worker\Trigger;

use ZfExtended_Debug;

class Process implements TriggerInterface
{
    /**
     * Trigger worker with id = $id.
     * To run mutex-save, the current hash is needed
     *
     * @param string $id
     * @param string $hash
     * @param string $worker
     * @param string|null $taskGuid
     * @return bool
     */
    public function triggerWorker(string $id, string $hash, string $worker, ?string $taskGuid): bool
    {
        $this->exec('worker:run ' . $id . ' -n --porcelain --debug="(' . $worker . ':' . $taskGuid . ')"');
        return true;
    }

    private function exec(string $workerCmd): void
    {
        chdir(APPLICATION_ROOT);
        $cmd = '';
        $debug =
            isset($_COOKIE['XDEBUG_SESSION'])
            || isset($_SERVER['XDEBUG_CONFIG'])
            || ZfExtended_Debug::hasLevel('core', 'worker');
        if ($debug) {
            $cmd .= 'XDEBUG_MODE=debug XDEBUG_SESSION=1 PHP_IDE_CONFIG="serverName=default_upstream" ';
        }
        $cmd .= 'nohup ./translate5.sh ' . $workerCmd . ' >/dev/null 2>&1 &';
        exec($cmd);
    }

    public function triggerQueue(): bool
    {
        $this->exec('worker:queue -n --porcelain');
        return true;
    }
}
