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
 */
class ZfExtended_Logger_Writer_Database extends ZfExtended_Logger_Writer_Abstract
{

    protected string $insertedId;
    protected array $insertedData;

    public function write(ZfExtended_Logger_Event $event): void
    {
        $db = ZfExtended_Factory::get('ZfExtended_Models_Db_ErrorLog');
        /* @var $db ZfExtended_Models_Db_ErrorLog */

        //if this event is a duplication, we update the entry and do not insert a new one
        $duplicateCount = $this->getDuplicateCount($event);
        if ($duplicateCount > 0 && $db->incrementDuplicate($event->duplicationHash, $duplicateCount)) {
            return;
        }

        //get this data directly from the event:
        $directlyFromEvent = [
            'level',
            'domain',
            'worker',
            'eventCode',
            'message',
            'appVersion',
            'file',
            'line',
            'trace',
            'httpHost',
            'url',
            'method',
            'userLogin',
            'userGuid'
        ];

        $data = [];
        foreach ($directlyFromEvent as $key) {
            $data[$key] = $event->$key;
        }
        if (!empty($event->exception)) {
            $data['message'] = get_class($event->exception) . ': ' . $data['message'];
        }

        $data['message'] = mb_substr($data['message'], 0, 512);
        $data['last'] = NOW_ISO;
        $data['duplicates'] = 0;
        $data['duplicateHash'] = $event->duplicationHash;

        //we track previous exceptions seperate in the DB if possible
        if (!empty($event->previous)) {
            if ($event->previous instanceof ZfExtended_Logger_Event) {
                $this->write($event->previous);
                $event->extra['_previous_exception_id'] = '#' . $this->insertedId;
            } elseif ($event->previous instanceof Exception) {
                $event = clone $event;
                $event->extra['_previous_exception'] = (string)$event->previous;
            }
        }

        //flatten entities to their dataobjects and handles JSON errors:
        $data['extra'] = $event->getExtraAsJson();
        $this->insertedId = (string) $db->insert($data);
        $this->insertedData = $data;
    }
}