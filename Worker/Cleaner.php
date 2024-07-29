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

use Zend_Exception;
use ZfExtended_Models_Worker;

class Cleaner
{
    private const DELETABLE_STATES = [
        ZfExtended_Models_Worker::STATE_DONE,
        ZfExtended_Models_Worker::STATE_DEFUNCT,
    ];

    public int $id;

    public int $parentId;

    public ?self $parent = null;

    public bool $processed = false;

    public ZfExtended_Models_Worker $worker;

    /* @var self[] $children */
    public array $children = [];

    public static function clean(): void
    {
        $worker = new ZfExtended_Models_Worker();
        $allWorkers = $worker->loadAll();
        if (empty($allWorkers)) {
            return;
        }
        $workerDb = $worker->db;
        $yesterday = date('Y-m-d H:i:s', time() - 24 * 3600);

        //prepare treeable list
        $objectList = [];
        foreach ($allWorkers as $worker) {
            $workerObj = new self();
            $workerObj->id = (int) $worker['id'];
            $workerObj->worker = new ZfExtended_Models_Worker();
            $workerObj->worker->init($worker);
            $workerObj->parentId = (int) $worker['parentId'];

            $objectList[$worker['id']] = $workerObj;

            self::stallLongRunning($workerObj, $yesterday);
        }

        unset($allWorkers);

        //build tree
        foreach ($objectList as $worker) {
            if (array_key_exists($worker->parentId, $objectList)) {
                $worker->parent = $objectList[$worker->parentId];
                $objectList[$worker->parentId]->children[] = $worker;
            }
        }

        //find deletable workers
        $toBeDeleted = [];
        foreach ($objectList as $worker) {
            if ($worker->processed) {
                continue;
            }
            $worker->checkGroupDeletion($toBeDeleted);
        }

        foreach ($toBeDeleted as $worker) {
            Logger::getInstance()->log($worker, 'delete');
        }

        if (! empty($toBeDeleted)) {
            $workerDb->delete([
                'id in (?)' => array_keys($toBeDeleted),
            ]);
        }
    }

    public function checkGroupDeletion(array &$toBeDeletedCollection): void
    {
        $root = $this->getRootParent();
        $group = $root->getRecursiveChildren();
        $group[] = $root;
        $allDeleteable = $this->isDeletable();

        foreach ($group as $worker) {
            $worker->processed = true;
            $allDeleteable = $allDeleteable && $worker->isDeletable();
        }

        if (! $allDeleteable) {
            return;
        }

        foreach ($group as $worker) {
            $toBeDeletedCollection[$worker->id] = $worker->worker;
        }
    }

    private function getRootParent(): self
    {
        $current = $this;
        while (! is_null($current->parent)) {
            $current = $current->parent;
        }

        return $current;
    }

    private function getRecursiveChildren(): array
    {
        $result = [];
        foreach ($this->children as $child) {
            $result = array_merge($result, [$child], $child->getRecursiveChildren());
        }

        return $result;
    }

    private function isDeletable(): bool
    {
        return in_array($this->worker->getState(), self::DELETABLE_STATES);
    }

    private static function stallLongRunning(Cleaner $workerObj, string $yesterday): void
    {
        //FIXME decide what to do with getMaxRuntime - use instead the fixed day date?
        if ($workerObj->worker->isRunning() && $workerObj->worker->getStarttime() < $yesterday) {
            try {
                //set a stalled worker (running for 24h) to defunc - will be deleted on next cron
                $workerObj->worker->defuncRemainingOfGroup(includeRunning: true);
                Logger::getInstance()->log($workerObj->worker, 'defunc stalled');
            } catch (Zend_Exception) {
                // just ignore, would be handled on next cron run
            }
        }
    }
}
