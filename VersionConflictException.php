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

class ZfExtended_VersionConflictException extends ZfExtended_Exception {
    protected $defaultCode = 409;
    protected $defaultMessage = 'Die Ausgangsdaten wurden in der Zwischenzeit verändert. Bitte aktualisieren Sie Ihre Ansicht!';
    protected $defaultMessageTranslate = true;
    protected $loggingEnabled = false;

    public function __construct(Throwable $previous = null)
    {
        parent::__construct(previous: $previous);
    }

    /**
     * Consumes a DB exception and converts it to ZfExtended_VersionConflictException if suitable
     * @throws Zend_Db_Statement_Exception
     * @throws Zend_Exception
     * @throws ZfExtended_VersionConflictException
     */
    public static function logAndThrow(Zend_Db_Statement_Exception $e)
    {
        $m = $e->getMessage();
        if (stripos($m, 'raise_version_conflict does not exist') !== false) {
            $newE = new ZfExtended_VersionConflictException($e);
            //by default this exception is not logged, but still we wanna have an info about that
            $log = Zend_Registry::get('logger');
            $log->exception($newE, [
                'level' => $log::LEVEL_INFO,
                'previous' => null, //we clean the previous here, since it just doubles the info
                'trace' => $e->getTraceAsString(), // we provide the original trace, this makes more sense
            ]);
            throw $newE;
        }
        throw $e;
    }
}