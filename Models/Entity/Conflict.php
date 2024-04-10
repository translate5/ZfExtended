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
 * Should be used if the status of the entity does prevent normal processing: The entity is locked, the entity is used/referenced in other places.
 * In other words: the entity it self is reasonable that the request can not be processed.
 * Encapsulates 409 Conflict
 */
class ZfExtended_Models_Entity_Conflict extends ZfExtended_ErrorCodeException
{
    use ZfExtended_ResponseExceptionTrait;

    /**
     * @var integer
     */
    protected $httpReturnCode = 409;

    /**
     * By default we log that as INFO, if created as response then the level is set to DEBUG
     * @var integer
     */
    protected $level = ZfExtended_Logger::LEVEL_INFO;

    protected static $localErrorCodes = [
        'E1041' => '409 Conflict',
    ];
}
