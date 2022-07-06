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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */

/**
 * Synthetic class for documentation purposes
 * Adds hints to IDE autocompletion
 * @see ZfExtended_RestController
 * @see Zend_View_Interface
 */
class ZfExtended_View extends Zend_View implements Zend_View_Interface {

    /**
     * @var Zend_Db_Table_Select[]
     * @see ZfExtended_Models_Entity_Abstract::loadAll, ZfExtended_RestController::indexAction and override
     * @see ZfExtended_Models_Entity_Abstract::getDataObject, ZfExtended_RestController::postAction,ZfExtended_RestController::putAction and overrides
     */
    public array|object $rows;

    /**
     * @var int - total amount of rows for a given sql statement
     */
    public int $total;

}