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
 * Manages the internalSessionUniqId of a session in a seperate entity
 * This is a quite stupid architecture as a public unique alias for the session could be saved much more easily as a seperate column in the session-table instead of a seperate table
 */
class ZfExtended_Models_Db_SessionMapInternalUniqId extends Zend_Db_Table_Abstract {
    protected $_name = 'sessionMapInternalUniqId';
    public $_primary = 'id';

    public function name(): string
    {
        return $this->_name;
    }
}

