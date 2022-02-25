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
    
    /**
     * @var ZfExtended_Logger_Event
     */
    public $previous;
    
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
    public $extra = [];
    
    /**
     * extra data flattened and sanitized (lazy filled by getExtraFlattenendAndSanitized)
     * @var array
     */
    public $extraFlat = [];
    
    /**
     * Hash to identify duplications
     * @var string|null
     */
    public $duplicationHash = null;
    
    /**
     * overwrites the data defined in the associative array into the current event
     * The extra array is merged - same named keys in the extra array are overwritten
     * @param array $dataToMerge
     */
    public function mergeFromArray(array $dataToMerge) {
        foreach($dataToMerge as $key => $value) {
            if($key == 'extra') {
                $this->extra = array_merge($this->extra, $value);
                continue;
            }
            if(property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Returns just the created, level, ecode, message, file and URL in one line.
     * @string
     */
    public function oneLine(): string {
        $e = empty($this->exception) ? '' : ' '.get_class($this->exception);
        $msg = [];
        $msg[] = $this->levelName.$e.': '.$this->eventCode.' - '.$this->message;
        $msg[] = '  in '.$this->domain.' '.$this->file.' ('.$this->line.') ';
        $msg[] = ' by '.$this->method.' '.$this->url;
        return join('', $msg);
    }
    
    public function __toString() {
        $msg = [];
        $e = empty($this->exception) ? '' : ' '.get_class($this->exception);
        $msg[] = $this->levelName.$e.': '.$this->eventCode.' - '.$this->message;
        $msg[] = '  in '.$this->domain.' '.$this->file.' ('.$this->line.') ';
        if(!empty($this->userGuid)) {
            $msg[] = '  User: '.$this->userLogin.' ('.$this->userGuid.') ';
        }
        if(!empty($this->url)) {
            $msg[] = '  Request: '.$this->method.' '.$this->url;
        }
        if(!empty($this->worker)) {
            $msg[] = '  Worker: '.$this->worker;
        }
        if(!empty($this->extra)) {
            $msg[] = '  Extra: '.print_r($this->getExtraFlattenendAndSanitized(),1);
        }
        if(!empty($this->trace)) {
            $msg[] = '  Trace: ';
            $this->trace = '    '.join("\n    ", explode("\n", $this->trace));
            $msg[] = $this->trace;
        }
        
        if(!empty($this->previous)) {
            $msg[] = "\n Previous Exception: \n";
            $msg[] = (string) $this->previous;
        }
        
        //FIXME implement a nice, flexible, changeable formatter here
        return join("\n", $msg)."\n";
    }
    
    /**
     * return event as HTML
     * @return string
     */
    public function toHtml() {
        $start = '<table><tr>';
        $msg = [];
        if(!empty($this->exception)) {
            $msg[] = '<td>Exception:</td><td>'.get_class($this->exception).'</td>';
        }
        $msg[] = '<td>Level:</td><td>'.$this->getColorizedLevel().'</td>';
        $msg[] = '<td>Errorcode:</td><td>'.$this->getCodeAnchor().'</td>';
        $msg[] = '<td style="vertical-align:top;">Message:</td><td>'.$this->messageToHtml($this->message).'</td>';
        $msg[] = '<td>Domain:</td><td>'.$this->domain.'</td>';
        $msg[] = '<td>Version:</td><td>'.$this->appVersion.'</td>';
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
            $msg[] = '<td>Extra:</td><td><pre>'.htmlspecialchars(print_r($this->getExtraFlattenendAndSanitized(),1)).'</pre></td>';
        }
        if(!empty($this->trace)) {
            $msg[] = '<td colspan="2">Trace:</td>';
            $msg[] = '<td colspan="2"><pre>'.$this->trace.'</pre></td>';
        }
        if(!empty($_REQUEST)) {
            $copy = $_REQUEST;
            $msg[] = '<td colspan="2">Request:</td>';
            $msg[] = '<td colspan="2"><pre>'.htmlspecialchars(print_r($this->sanitizeContent($copy),1)).'</pre></td>';
        }
        $end = '</tr></table>';
        if(!empty($this->previous)) {
            $end .= $this->previous->toHtml();
        }
        
        //FIXME implement a nice, flexible, changeable formatter here
        return $start.join("</tr>\n<tr>", $msg).$end;
    }
    
    /**
     * Generates a HTML anchor link to the event code documentation
     * @return string
     */
    public function getCodeAnchor(): string {
        $config = Zend_Registry::get('config');
        $link = '<a href="%s">%s</a>';
        return sprintf($link, str_replace('{0}', $this->eventCode, $config->runtimeOptions->errorCodesUrl), $this->eventCode);
    }
    
    /**
     * returns the levelname colorized
     * @return string
     */
    protected function getColorizedLevel() {
        switch ($this->level) {
            case ZfExtended_Logger::LEVEL_FATAL:
                return '<b style="color:#b60000;">'.$this->levelName.'</b>';
            case ZfExtended_Logger::LEVEL_ERROR:
                return '<span style="color:#b60000;">'.$this->levelName.'</span>';
            case ZfExtended_Logger::LEVEL_WARN:
                return '<span style="color:#e89b00;">'.$this->levelName.'</span>';
        }
        return $this->levelName;
    }
    
    /**
     * Does some magic formatting for the event message converted to HTML
     * @param string $message
     */
    protected function messageToHtml(string $message) : string {
        $message = htmlentities($message);
        $message = str_replace("\n", "<br>\n", $message);
        $message = preg_replace('/([^a-zA-Z]|^)(OFFLINE)([^a-zA-Z]|$)/s', '$1<span style="font-weight:bold;color:#c83335;">$2</span>$3', $message);
        $message = preg_replace('/([^a-zA-Z]|^)(ONLINE)([^a-zA-Z]|$)/s', '$1<span style="font-weight:bold;color:#00aa00;">$2</span>$3', $message);
        return $message;
    }
    
    /**
     * Converts the flattened extra data to JSON, uses the data object of entities, on JSON errors the error and the raw data is returned
     * @return string
     */
    public function getExtraAsJson(): ?string {
        $data = $this->getExtraFlattenendAndSanitized();
        if(empty($data)) {
            return null;
        }
        $result = json_encode($data);
        if(empty($result) && json_last_error() > JSON_ERROR_NONE) {
            $result = 'JSON Error: '.json_last_error_msg().' ('.json_last_error().")\n";
            $result .= 'Raw Data: '.print_r($data, 1);
        }
        return $result;
    }
    
    /**
     * Loops over the internal data and obfuscates possible private data
     * WARNING: flattens also the extra data container!
     */
    public function getExtraFlattenendAndSanitized() {
        if(!empty($this->extraFlat)) {
            return $this->extraFlat;
        }

        return $this->extraFlat = $this->sanitizeContent(array_map(function($item) {
            if(is_object($item) && $item instanceof ZfExtended_Models_Entity_Abstract) {
                return $this->sanitizeContent($item->getDataObject());
            }
            return $item;
        }, (array) $this->extra));
    }

    /**
     * Accepts strings (URL with GET parameters), arrays or objects which are sanitized (searched for the given key and content is replaced
     * @param mixed $toSanitize
     */
    protected function sanitizeContent($toSanitize) {
        $sensitiveKeys = ['passwd', 'password', 'authhash', 'sessiontoken', 'authtoken', 'session_id', 'staticauthhash', 'auth_key'];
        
        //if it is a string we assume it is a URL with parameters
        if(is_string($toSanitize)) {
            return preg_replace('/(\?|&)('.join('|', $sensitiveKeys).')=([^&#]+)/', '$1$2=XXX', $toSanitize);
        }
        $isObject = is_object($toSanitize);
        foreach($toSanitize as $key => $value) {
            if($value && in_array(strtolower($key), $sensitiveKeys)) {
                $value = substr($value, 0, 2).'XXX...';
                if($isObject) {
                    $toSanitize->$key = $value;
                } else {
                    $toSanitize[$key] = $value;
                }
            }
        }
        return $toSanitize;
    }
}