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

namespace MittagQI\ZfExtended;

use Exception;
use ZfExtended_ErrorCodeException;
use ZfExtended_Logger;
use ZfExtended_ResponseExceptionTrait;

/**
 * Since no access is mostly an ACL misconfiguration we leave logging enabled.
 */
class MismatchException extends ZfExtended_ErrorCodeException
{
    use ZfExtended_ResponseExceptionTrait;

    /**
     * @var integer
     */
    protected $httpReturnCode = 400;

    /**
     * By default, we log that as INFO, if created as response then the level is set to DEBUG
     * @var integer
     */
    protected $level = ZfExtended_Logger::LEVEL_INFO;

    protected static array $localErrorCodes = [
        'E2000' => 'Param "{0}" - is not given',                                           // REQ
        'E2001' => 'Value "{0}" of param "{1}" - is in invalid format',                    // REX
        'E2002' => 'No object of type "{0}" was found by key "{1}"',                       // KEY
        'E2003' => 'Wrong value',                                                          // EQL
        'E2004' => 'Value "{0}" of param "{1}" - is not in the list of allowed values',    // FIS
        'E2005' => 'Value "{0}" of param "{1}" - is in the list of disabled values',       // DIS
        'E2006' => 'Value "{0}" of param "{1}" - is not unique. It should be unique.',     // UNQ
        'E2007' => 'Extension "{0}" of file "{1}" - is not in the list of allowed values', // EXT
        'E2008' => 'Object of type "{0}" already exists having key "{1}"',                 // KEY (negation)
        'E2009' => 'Value "{0}" of param "{1}" should be minimum "{2}"',                   // MIN
        'E2010' => 'Value "{0}" of param "{1}" should be maximum "{2}"',                   // MAX
    ];

    /**
     * Overridden to use custom message if given
     */
    public function __construct($errorCode, array $extra = [], Exception $previous = null)
    {
        // Call parent
        parent::__construct($errorCode, $extra, $previous);

        // If custom message is given
        if ($extra['custom'] ?? 0) {
            // Get that
            $msg = $extra['custom'];

            // Else get default one
        } else {
            $msg = $this->getMessage();
        }

        // If message have placeholders like {0}, {1}, {2} etc
        if (preg_match('~{([0-9])}~', $msg)) {
            // Replace those with values from $extra arg
            $msg = preg_replace_callback('~{([0-9])}~', fn ($m) => htmlentities($extra[$m[1]] ?? $m[1]), $msg);
        }

        // Spoof msg
        $this->setMessage($msg);
    }
}
