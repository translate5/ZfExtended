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
 */
class ZfExtended_Models_Filter_Exception extends ZfExtended_ErrorCodeException {
    /**
     * @var string
     */
    protected $domain = 'core.api.filter';
    
    static protected $localErrorCodes = [
        'E1220' => 'Errors in parsing filters Filterstring: "{filter}"',
        'E1221' => 'Illegal type "{type}" in filter',
        'E1222' => 'Illegal chars in field name "{field}"',
        'E1223' => 'Illegal field "{field}" requested',
        'E1224' => 'Unkown filter operator "{operator}" from ExtJS 5 Grid Filter!',
        'E1225' => 'Given tableClass "{tableClass}" is not a subclass of Zend_Db_Table_Abstract!',
    ];
}