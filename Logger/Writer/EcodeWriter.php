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
class ZfExtended_Logger_Writer_EcodeWriter extends ZfExtended_Logger_Writer_Abstract {

    const ECODE_FILE = APPLICATION_ROOT.'/docs/ERRORCODES.md';

    public function write(ZfExtended_Logger_Event $event) {
        //sanitize event message (no pipes allowed)
        $event->messageRaw = str_replace('|', '/', $event->messageRaw);

        $ecodes = file(self::ECODE_FILE);

        $lastEcodeLine = 0;
        $replace = false;
        foreach($ecodes as $idx => $line) {
            $linkKey = '['.$event->eventCode.'](#'.$event->eventCode.')';
            //FIXME do not replace E0000 and E9999
            if(str_contains($line, $linkKey)) {
                $replace = true;
                $lastEcodeLine = $idx;
                break;
            }
            if(preg_match('~\[E[\d]{4}]\(#E[\d]{4}\)~', $line)) {
                $lastEcodeLine = $idx;
            }
        }

        if($replace) {
            $result = $this->getReplaceMessage($event, $ecodes[$lastEcodeLine]);
            if(!is_null($result)) {
                $ecodes[$lastEcodeLine] = $result;
            }
        }
        //if a new one, or nothing replaced above, then we just insert a new row.
        if(!$replace || is_null($result)) {
            //insert after last found ecode a new one
            array_splice( $ecodes, $lastEcodeLine + 1, 0, [$this->getNewEcodeLine($event)] );
        }
        if(!file_put_contents(self::ECODE_FILE, join('', $ecodes))) {
            error_log('Could not save '.self::ECODE_FILE);
        }
    }

    /**
     * returns a new line for the event code MD file
     * @param ZfExtended_Logger_Event $event
     * @return string
     */
    private function getNewEcodeLine(ZfExtended_Logger_Event $event): string
    {
        return sprintf('| [%s](#%s)  | TODO    | %s | TODO DESCRIPTION / SOLUTION', $event->eventCode, $event->eventCode, $event->messageRaw)."\n";
    }

    /**
     * returns the replaced line or null if nothing could be replaced
     * @param ZfExtended_Logger_Event $event
     * @param string $line
     * @return string|null
     */
    private function getReplaceMessage(ZfExtended_Logger_Event $event, string $line): ?string
    {
        $count = 0;
        $result = preg_replace('~(\s*\|\s*\[E[\d]{4}]\(#E[\d]{4}\)\s*\|[^|]+\|)([^|]+)(.*$)~','$1 '.$event->messageRaw.' $3', $line, count: $count);
        if($count > 0) {
            return $result;
        }
        return null;
    }
}