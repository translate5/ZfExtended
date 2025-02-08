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
 * This class extends the original Zend EventManager.
 *
 * Extensions:
 *
 * -    trigger(): if $this->logTrigger is set to true, every triggered event is written to error_log
 */
class ZfExtended_EventManager extends Zend_EventManager_EventManager
{
    /**
     * @var boolean
     */
    protected bool $logTrigger = false;

    /**
     * @param null|string|int|array|Traversable $identifiers
     */
    public function __construct($identifiers = null)
    {
        parent::__construct($identifiers);
        $this->logTrigger = ZfExtended_Debug::hasLevel('core', 'EventTrigger');
    }

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
    public function trigger($event, $target = null, $argv = [], $callback = null)
    {
        if ($this->logTrigger) {
            $targetName = $target;
            if (is_object($target)) {
                $targetName = get_class($target);
            }
            error_log("event triggered: " . $event . "; target: " . $targetName);
        }

        return parent::trigger($event, $target, $argv, $callback);
    }
}
