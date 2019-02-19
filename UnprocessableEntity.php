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
    /**
     * @var integer
     */
    protected $httpReturnCode = 422;
    
    protected static $localErrorCodes = ['E1025' => '422 Unprocessable Entity'];
    
    /**
     * Fixed to errorcode E1025
     * @param array $invalidFields associative array of invalid fieldnames and an error string what is wrong with the field
     * @param Exception $previous
     */
    public function __construct(array $invalidFields, Exception $previous = null) {
        parent::__construct('E1025', $invalidFields, $previous);
    }
}