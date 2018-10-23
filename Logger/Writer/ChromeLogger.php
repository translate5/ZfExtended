<?php
/*
 START LICENSE AND COPYRIGHT
 
 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.
 
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
 * Logs to the ChromeLogger extension in the Chrome Browser
 * https://craig.is/writing/chrome-logger
 * https://craig.is/writing/chrome-logger/techspecs 
 */
class ZfExtended_Logger_Writer_ChromeLogger extends ZfExtended_Logger_Writer_Abstract {
    
    protected static $rows = [];
    protected static $registered = false;
    
    public function __construct() {
        if(self::$registered) {
            return;
        }
        try {
            $front = Zend_Controller_Front::getInstance();
            $front->registerPlugin(new ZfExtended_Logger_Writer_ChromeLoggerControllerPlugin());
        }
        catch(Exception $e) {
            //since we are in bootstrapping the logger itself, we can not rely on him and have to write to error_log directly
            error_log('Could not init ChromLogger: '.$e);
        }
        self::$registered = true;
    }
    
    public function write(ZfExtended_Logger_Event $event) {
        switch ($event->level) {
            case ZfExtended_Logger::LEVEL_FATAL:
            case ZfExtended_Logger::LEVEL_ERROR:
                $type = 'error';
                break;
            case ZfExtended_Logger::LEVEL_WARN:
                $type = 'warn';
                break;
            case ZfExtended_Logger::LEVEL_TRACE:
            case ZfExtended_Logger::LEVEL_DEBUG:
                $type = 'info';
                break;
            case ZfExtended_Logger::LEVEL_INFO:
            default:
                $type = ''; // defaults to log
                break;
        }
        $trace = $event->file.' ('.$event->line.')';
        $row = [
            'message' => $event->message,
            'eventCode' => $event->eventCode,
            'extra' => $event->extra,
        ];
        $row = [
            $event->message,
            $event->eventCode,
            $event->extra,
        ];
        self::$rows[] = [$row, $trace, $type];
    }
    
    public function validateOptions(array $options) {
        //no special options needed at the moment
    }
    
    public static function writeHeader() {
        if(empty(self::$rows)) {
            return;
        }
        $data = new stdClass();
        $data->version = "0.1";
        $data->columns = ["log", "backtrace", "type"];
        $data->rows = self::$rows;
        header('X-ChromeLogger-Data: '.base64_encode(json_encode(self::$rows)));
    }
    
}
