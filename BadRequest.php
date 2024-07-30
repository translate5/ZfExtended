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
 * Bad Gateway means: Our System is OK, but the requested third party systems gives an error.
 */
class ZfExtended_BadRequest extends ZfExtended_ErrorCodeException
{
    /**
     * @var int
     */
    protected $httpReturnCode = 400;

    /**
     * @var int
     */
    protected $level = ZfExtended_Logger::LEVEL_ERROR;

    protected static $localErrorCodes = [
        'E1559' => '400 Bad Request',
        'E1560' => '400 Bad Request - invalid JSON provided, JSON decode error: {error}',
        'E1623' => '400 Bad Request - invalid Markup provided',
    ];
}
