<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 */
/**
 * Erweitert Zend_Db_Statement_Sqlsrv so, dass unicode-data korrekt in den sql-server geschrieben werden kann
 *
 * - näheres siehe http://framework.zend.com/issues/browse/ZF-9255
 *
 */
class  ZfExtended_Zendoverwrites_Db_Statement_Sqlsrv extends Zend_Db_Statement_Sqlsrv
{
    /**
     * Remove parts of a SQL string that contain quoted strings
     * of values or identifiers.
     *
     * @param string $sql
     * @return string
     */
    protected function _stripQuoted($sql)
    {
        // get the character for delimited id quotes,
        // this is usually " but in MySQL is `
        $d = $this->_adapter->quoteIdentifier('a');
        $d = $d[0];

        // get the value used as an escaped delimited id quote,
        // e.g. \" or "" or \`
        $de = $this->_adapter->quoteIdentifier($d);
        $de = substr($de, 1, 2);
        $de = str_replace('\\', '\\\\', $de);

        // get the character for value quoting
        // this should be '
        $q = $this->_adapter->quote('a');
		//auskommentiert auf Basis von http://framework.zend.com/issues/secure/attachment/12797/Sqlsrv_Unicode.patch
        //$q = $q[0];
		//dafür die folgenden drei Zeilen
		preg_match('/^(.*)a(.*)$/', $q, $matches);
        $qStart = $matches[1];
        $qEnd = $matches[2];

        // get the value used as an escaped quote,
        // e.g. \' or ''
		//auskommentiert auf Basis von http://framework.zend.com/issues/secure/attachment/12797/Sqlsrv_Unicode.patch
        //$qe = $this->_adapter->quote($q);
        //$qe = substr($qe, 1, 2);
        //dafür die folgenden zwei Zeilen
		$qe = $this->_adapter->quote($qEnd);
        $qe = substr($qe, strlen($qStart), (-1)*strlen($qEnd));
		//hier wieder orig-codec
		$qe = str_replace('\\', '\\\\', $qe);

        // get a version of the SQL statement with all quoted
        // values and delimited identifiers stripped out
        // remove "foo\"bar"
		//auskommentiert auf Basis von http://framework.zend.com/issues/secure/attachment/12797/Sqlsrv_Unicode.patch
        //$sql = preg_replace("/$q($qe|\\\\{2}|[^$q])*$q/", '', $sql);
		//dafür die nächste
		$sql = preg_replace("/$qStart($qe|\\\\{2}|[^$qEnd])*$qEnd/", '', $sql);
        // remove 'foo\'bar'
        if (!empty($q)) {
			//auskommentiert auf Basis von http://framework.zend.com/issues/secure/attachment/12797/Sqlsrv_Unicode.patch
            //$sql = preg_replace("/$q($qe|[^$q])*$q/", '', $sql);
			//dafür die nächste
			$sql = preg_replace("/$qStart($qe|[^$qEnd])*$qEnd/", '', $sql);
        }

        return $sql;
    }
}