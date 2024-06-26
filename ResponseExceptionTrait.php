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
 * provides reusable functionality for exceptions, so that they can be
 * used as exceptions transporting a translated response
 */
trait ZfExtended_ResponseExceptionTrait
{
    /**
     * Creates this exceptions as a response, that means:
     * its an error that can be recovered by the user, therefore the user should receive
     *   information about the error in the Frontend.
     * The exception level is set to debug, the given error messages must be given in german,
     *   since they are translated into the GUI language automatically
     * The errorcode is fix to defined value in the exception
     *
     * @param array $extraData additional data which will not going to be translated
     * @throws Zend_Exception
     */
    public static function createResponse(
        string $errorCode,
        array $invalidFields,
        array $data = [],
        Exception $previous = null,
        array $extraData = []
    ): ZfExtended_ErrorCodeException {
        $t = ZfExtended_Zendoverwrites_Translate::getInstance();
        /* @var $t ZfExtended_Zendoverwrites_Translate */
        $logger = Zend_Registry::get('logger');
        /* @var $logger ZfExtended_Logger */

        $data['errors'] = [];
        $data['errorsTranslated'] = [];
        $numericKeysOnly = true;

        //if one field has multiple errors, this must be a plain array
        foreach ($invalidFields as $field => $error) {
            if (is_array($error)) {
                $data['errors'][$field] = array_keys($error);
                $data['errorsTranslated'][$field] = array_values($error);
                $numericKeysOnly = $numericKeysOnly && ($data['errorsTranslated'][$field] === $error);
            } else {
                $data['errors'][$field] = [$error];
                $data['errorsTranslated'][$field] = [$error];
            }
            //translate the field
            $data['errorsTranslated'][$field] = array_map(function ($text) use ($t, $logger, $data) {
                $text = $t->_($text);

                return $logger->formatMessage($text, $data);
            }, $data['errorsTranslated'][$field]);
        }
        //if there are no untranslated error strings, we don't send them
        if ($numericKeysOnly) {
            unset($data['errors']);
        }
        if (! empty($extraData)) {
            $data[self::EXTRA_DATA_FIELD] = $extraData;
        }
        $e = new self($errorCode, $data, $previous);
        $e->level = ZfExtended_Logger::LEVEL_DEBUG;

        return $e;
    }

    /**
     * This function creates a response exception, initialized with the data of a usual ErrorCodeException
     * This is useful in situations where the underlying code produces an error which can be handled in the frontend.
     *   This implies an answer to the GUI as response exception.
     *
     * @param array $data optional, additional data.
     * @throws Zend_Exception
     */
    public static function createResponseFromOtherException(
        ZfExtended_ErrorCodeException $previous,
        array $invalidFields,
        array $data = []
    ): ZfExtended_ErrorCodeException {
        self::addCodes([
            $previous->getErrorCode() => $previous->getMessage(),
        ], $previous->getDomain());
        $data = array_merge($previous->getErrors(), $data);

        return self::createResponse($previous->getErrorCode(), $invalidFields, $data, $previous);
    }
}
