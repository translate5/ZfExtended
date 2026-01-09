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
 * SECTION TO INCLUDE PROGRAMMATIC LOCALIZATION
 * ============================================
 * $translate->_('Daten nicht gefunden!');
 */

/**
 * does not extend ZfExtended_NotFoundException since ZfExtended_NotFoundException is
 * the message, that a route to a resource is not found and
 * ZfExtended_Models_Entity_NotFoundException is the message that an DB-Entity is
 * not found. Both have 404-status-code but often should be handled different.
 */

class ZfExtended_Models_Entity_NotFoundException extends ZfExtended_Exception
{
    /**
     * @var string
     */
    protected $defaultMessage = 'Daten nicht gefunden!';

    /**
     * @var bool
     */
    protected $defaultMessageTranslate = true;

    /**
     * @var integer
     */
    protected $defaultCode = 404;
}
