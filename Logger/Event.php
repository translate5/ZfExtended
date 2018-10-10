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
 * The "event" to be logged
 */
class ZfExtended_Logger_Event {
    
    /**
     * datetime, when the logentry was created
     * @var string
     */
    public $created;
    
    /**
     * Event Level, fatal to trace, see ZfExtended_Logger LEVEL_* constants
     * @var integer
     */
    public $level;
    
    /**
     * hierarchical area code, for further filtering, can be for example: 
     * FIXME better examples import / export / Plugin XYZ, and so on 	from exception type
     * @var string
     */
    public $domain;
    
    //worker 	worker class (loop over debug_backtrace, and ue is_subclass_of) 	automatically
    // → FIXME is the domain usable for that? 
    
    /**
     * the project unique event code
     * @var string
     */
    public $eventCode;
    
    /**
     * a human readable error string, may contain format placeholders {0} and {NAME} where the numeric or textual indizes are mapped to the extra data array
     * @var string
     */
    public $message;
    
    /**
     * the file where the error happened
     * @var string
     */
    public $file;
    
    /**
     * the line where the error happened
     * @var string
     */
    public $line;
    
    /**
     * the trace to the event 
     * @var array
     */
    public $trace = '';
    
    /**
     * the URL of the request
     * @var string
     */
    public $url;
    
    /**
     * the HTTP method of the request
     * @var string
     */
    public $method;
    
    /**
     * the currently authenticated userGuid
     * @var string
     */
    public $userGuid;
    
    /**
     * the currently authenticated user
     * @var string
     */
    public $userLogin;
    
    /**
     * extra data to the event as associated error
     * @var array
     */
    public $extra;
    
    public function __toString() {
        //FIXME implement a nice, flexible, changeable formatter here
        return print_r($this,1);
    }
}