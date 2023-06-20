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

class Process implements TriggerInterface
{
    /**
     * Trigger worker with id = $id.
     * To run mutex-save, the current hash is needed
     *
     * @param int $id
     * @param string $hash
     * @return bool
     */
    public function triggerWorker($id, string $hash): bool
    {
        chdir(APPLICATION_ROOT);
        exec('nohup ./translate5.sh worker:run ' . $id . ' -n --porcelain >/dev/null 2>&1 &');
        return true; //FIXME check result code of nuhup
    }

    public function triggerQueue(): bool
    {
        chdir(APPLICATION_ROOT);
        exec('nohup ./translate5.sh worker:queue -n --porcelain >/dev/null 2>&1 &');
        return true; //FIXME check result code of nuhup
    }
}
