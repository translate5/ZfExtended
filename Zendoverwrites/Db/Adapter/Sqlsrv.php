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

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 */
/**
 * Erweitert Zend_Db_Adapter_Sqlsrv so, dass unicode-data korrekt in den sql-server geschrieben werden kann
 *
 * - näheres siehe http://framework.zend.com/issues/browse/ZF-9255
 *
 */
class  ZfExtended_Zendoverwrites_Db_Adapter_Sqlsrv extends Zend_Db_Adapter_Sqlsrv
{
    /**
     * Default class name for a DB statement.
     *
     * @var string
     */
    protected $_defaultStmtClass = 'ZfExtended_Zendoverwrites_Db_Statement_Sqlsrv';

    /**
     * Quote a raw string.
     *
     * @param string $value     Raw string
     * @return string           Quoted string
     */
    protected function _quote($value)
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_float($value)) {
            return sprintf('%F', $value);
        }

        //auskommentiert auf Basis von http://framework.zend.com/issues/secure/attachment/12797/Sqlsrv_Unicode.patch
		//return "'" . str_replace("'", "''", $value) . "'";
		//statt dessen:
		return "N'" . str_replace("'", "''", $value) . "'";
    }
}