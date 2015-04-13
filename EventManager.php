<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**
 * This class extends the original Zend EventManager.
 * 
 * Extensions:
 * 
 * -    trigger(): if $this->logTrigger is set to true, every triggered event is written to error_log
 * 
 */
class ZfExtended_EventManager extends Zend_EventManager_EventManager
{
    /**
     * 
     * @var boolean 
     */
    protected $logTrigger = false;
    
    
    /**
     * ZfExtended:
     * if $this->logTrigger is set to true, all triggered events are 
     * written to error_log wich can be used for debuging
     * 
     * Zend:
     * Trigger all listeners for a given event
     *
     * Can emulate triggerUntil() if the last argument provided is a callback.
     *
     * @param  string $event
     * @param  string|object $target Object calling emit, or symbol describing target (such as static method name)
     * @param  array|ArrayAccess $argv Array of arguments; typically, should be associative
     * @param  null|callback $callback
     * @return Zend_EventManager_ResponseCollection All listener return values
     */
    public function trigger($event, $target = null, $argv = array(), $callback = null)
    {
        if ($this->logTrigger)
        {
            $targetName = $target;
            if (is_object($target)) {
                $targetName = get_class($target); 
            }
            error_log("event triggered: ".$event."; target: ".$targetName);
        }
        return parent::trigger($event, $target, $argv, $callback);
    }
}