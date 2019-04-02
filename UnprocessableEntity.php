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
 * Should be used if the request to the server contains data which is not valid
 * points to HTTP 422 Unprocessable Entity
 */
class ZfExtended_UnprocessableEntity extends ZfExtended_ErrorCodeException {
    use ZfExtended_ResponseExceptionTrait;
    
    /**
     * @var integer
     */
    protected $httpReturnCode = 422;
    
    /**
     * By default we log that as INFO, if created as response then the level is set to DEBUG
     * @var integer
     */
    protected $level = ZfExtended_Logger::LEVEL_INFO;
    
    protected static $localErrorCodes = [
        'E1025' => '422 Unprocessable Entity',
        'E1026' => '422 Unprocessable Entity on FileUpload',
    ];
    
}