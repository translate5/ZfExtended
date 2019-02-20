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
    
    protected $errorCodeToUse = 'E1025';
    
    protected static $localErrorCodes = [
        'E1025' => '422 Unprocessable Entity',
        'E1026' => '422 Unprocessable Entity on FileUpload',
    ];
    
    /**
     * Fixed to errorcode E1025
     * @param array $invalidFields associative array of invalid fieldnames and an error string what is wrong with the field
     * @param Exception $previous
     */
    public function __construct(array $invalidFields, Exception $previous = null) {
        $data = [
            'errors' => [],
            'errorsTranslated' => [],
        ];
        //if one field has multiple errors, this must be a plain array 
        foreach($invalidFields as $field => $error) {
            if(is_array($error)) {
                $data['errors'][$field] = array_keys($error);
                $data['errorsTranslated'][$field] = array_values($error);
            }
            else {
                $data['errors'][$field] = [$error];
                $data['errorsTranslated'][$field] = [$error];
            }
        }
        parent::__construct($this->errorCodeToUse, $data, $previous);
    }
}