<?php
 /*
   START LICENSE AND COPYRIGHT

  This file is part of the ZfExtended library and build on Zend Framework

  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com

  This file is multi-licensed under the EPL, LGPL and GPL licenses. 

  It may be used under the terms of the Eclipse Public License - v 1.0
  as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
  included in the packaging of this file.  Please review the following information 
  to ensure the Eclipse Public License - v 1.0 requirements will be met:
  http://www.eclipse.org/legal/epl-v10.html.

  Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
  or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
  Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
  included in the packaging of this file.  Please review the following information 
  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
  GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
  http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
  
  @copyright  Marc Mittag, MittagQI - Quality Informatics
  @author     MittagQI - Quality Informatics
  @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html

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