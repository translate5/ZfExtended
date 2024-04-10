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

class ZfExtended_Logger_Writer_ErrorLog extends ZfExtended_Logger_Writer_Abstract
{
    public function write(ZfExtended_Logger_Event $event): void
    {
        //FIXME duplicate handling:print all? ignore all duplicates?
        // print one line prefixed with repeated >10/>50/100/>200/>500/>1000 times
        //         $duplicateCount = $this->getDuplicateCount($event);
        //         if($duplicateCount > 0) {
        //             $db->incrementDuplicate($event->duplicationHash, $duplicateCount);
        //             return;
        //         }
        if (in_array($event->eventCode, ['E9999', 'E0001', 'E0002'])) {
            if (is_dir(APPLICATION_ROOT . '/.idea')) {
                //change trace so that phpstorm can directly jump to
                $event = preg_replace_callback(['/(\s+#\d+ )([^(]+)\((\d+)\):/', '#(\s+in [^/]+)(/[^(]+) \((\d+)\)#'], function ($matches) {
                    $file = str_replace(APPLICATION_ROOT, '', $matches[2]);
                    $url = (string) 'phpstorm://open?file=' . $file . '&line=' . $matches[3];

                    return $matches[1] . 'in ' . $url;
                }, $event);
            }
            error_log($event);
        } else {
            error_log($event->oneLine());
        }
    }
}
