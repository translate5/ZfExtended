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
 * Currently no need for different formatters (like in Zend_Log), so we just provide a toString and a toHtml function.  
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
     * String representation of the above numeric level
     * @var string
     */
    public $levelName;
    
    /**
     * hierarchical area code, for further filtering, can be for example: 
     * FIXME better examples import / export / Plugin XYZ, and so on 	from exception type
     * @var string
     */
    public $domain;
    
    /**
     * @var Exception
     */
    public $exception;
    
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
     * the current application version
     * @var string
     */
    public $appVersion;
    
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
     * the worker where the error happened
     * @var string
     */
    public $worker;
    
    /**
     * the trace to the event 
     * @var string
     */
    public $trace = '';
    
    /**
     * the called HTTP host of the request
     * @var string
     */
    public $httpHost;
    
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
        $msg = [];
        $e = empty($this->exception) ? '' : ' '.get_class($this->exception);
        $msg[] = $this->levelName.$e.': '.$this->eventCode.' - '.$this->message;
        $msg[] = 'in '.$this->domain.' '.$this->file.' ('.$this->line.') ';
        if(!empty($this->userGuid)) {
            $msg[] = 'User: '.$this->userLogin.' ('.$this->userGuid.') ';
        }
        if(!empty($this->url)) {
            $msg[] = 'Request: '.$this->method.' '.$this->url;
        }
        if(!empty($this->worker)) {
            $msg[] = 'Worker: '.$this->worker;
        }
        $extra = $this->convertExtra();
        if(!empty($extra)) {
            $msg[] = 'Extra: '.print_r($extra,1);
        }
        if(!empty($this->trace)) {
            $msg[] = 'Trace: ';
            $msg[] = $this->trace;
        }
        
        //FIXME implement a nice, flexible, changeable formatter here
        return join("\n", $msg);
    }
    
    /**
     * Flattens the data in the extra array
     * @return NULL|array
     */
    protected function convertExtra() {
        if(empty($this->extra)) {
            return '';
        }
        return print_r(array_map(function($item){
            if($item instanceof ZfExtended_Models_Entity_Abstract) {
                return $item->getDataObject();
            }
            return $item;
        }, $this->extra),1);
    }
    
    /**
     * return event as HTML
     * @return string
     */
    public function toHtml() {
        $msg = ['<table><tr>'];
        if(!empty($this->exception)) {
            $msg[] = '<td>Exception:</td><td>'.get_class($this->exception).'</td>';
        }
        $msg[] = '<td>Level:</td><td>'.$this->levelName.'</td>';
        $msg[] = '<td>Errorcode:</td><td>'.$this->eventCode.'</td>';
        $msg[] = '<td>Message:</td><td>'.$this->message.'</td>';
        $msg[] = '<td>Domain:</td><td>'.$this->domain.'</td>';
        $msg[] = '<td>File (Line):</td><td>'.$this->file.' ('.$this->line.')</td>';
        if(!empty($this->userGuid)) {
            $msg[] = '<td>User:</td><td>'.$this->userLogin.' ('.$this->userGuid.')</td>';
        }
        if(!empty($this->url)) {
            $msg[] = '<td>Request:</td><td>'.$this->method.' '.$this->url.'</td>';
        }
        if(!empty($this->worker)) {
            $msg[] = '<td>Worker:</td><td>'.$this->worker.'</td>';
        }
        if(!empty($this->extra)) {
            if(is_object($item) && $item instanceof ZfExtended_Models_Entity_Abstract) {
                $item = $item->getDataObject();
            }
            $msg[] = '<td>Extra:</td><td>'.htmlspecialchars(print_r($this->extra,1)).'</td>';
        }
        if(!empty($this->trace)) {
            $msg[] = '<td colspan="2">Trace:</td>';
            $msg[] = '<td colspan="2"><pre>'.$this->trace.'</pre></td>';
        }
        if(!empty($_REQUEST)) {
            $msg[] = '<td colspan="2">Request:</td>';
            $msg[] = '<td colspan="2"><pre>'.htmlspecialchars(print_r($_REQUEST,1)).'</pre></td>';
        }
        $msg[] = '</tr></table>';
        
        //FIXME implement a nice, flexible, changeable formatter here
        return join("</tr>\n<tr>", $msg);
    }
}