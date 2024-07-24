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
 * @package ZfExtended
 * @version 2.0
 * @deprecated
 */
class ZfExtended_Models_Installer_Modules_Top extends ZfExtended_Models_Installer_Modules_Abstract
{
    /**
     * top column headlines
     * @var array
     */
    protected $headlines = [
        'id' => 'DB id:',
        'parentId' => 'DB par. id:',
        'state' => 'state:',
        'pid' => 'Process id:',
        'starttime' => 'Starttime:',
        'endtime' => 'Endtime:',
        'taskGuid' => 'TaskGuid:',
        'worker' => 'Worker:',
    ];

    /**
     * top column headlines
     * @var array
     */
    protected $columsToPad = [
        'id' => 8,
        'parentId' => 12,
        'state' => 10, //max is scheduled
        'pid' => 12,
        'starttime' => 20,
        'endtime' => 20,
        'taskGuid' => 39,
        'worker' => 0,
    ];

    public function run()
    {
        $this->addZendToIncludePath();
        $this->initApplication();

        $worker = new ZfExtended_Models_Worker();
        $allWorker = $worker->loadAll();

        $resultNotListed = [];
        $statesToIgnore = [$worker::STATE_DEFUNCT, $worker::STATE_DONE];

        $result = [''];
        array_unshift($allWorker, $this->headlines);
        foreach ($allWorker as $worker) {
            if (in_array($worker['state'], $statesToIgnore)) {
                settype($resultNotListed[$worker['state']], 'integer');
                $resultNotListed[$worker['state']]++;

                continue;
            }
            $row = [];
            foreach ($this->columsToPad as $key => $pad) {
                if (empty($pad)) {
                    $row[] = $worker[$key];
                } else {
                    $row[] = str_pad($worker[$key], $pad, ' ', STR_PAD_RIGHT);
                }
            }
            $result[] = join('', $row);
        }

        if (! empty($resultNotListed)) {
            $result[] = '';
            $result[] = '  Not listed workers: ';
            foreach ($resultNotListed as $worker => $count) {
                $result[] = '    ' . $worker . ' count ' . $count;
            }
        }

        $this->logger->log(join("\n", $result));
    }
}
