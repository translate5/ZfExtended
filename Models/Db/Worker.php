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
 *
 */
/**
 * Class to access worker-table
 */
class ZfExtended_Models_Db_Worker extends Zend_Db_Table_Abstract {
    protected $_name    = 'Zf_worker';
    public $_primary = 'id';
    
    /**
     * By prepending this function call to update/delete queries, dead locks may be reduced there.
     * According to https://dev.mysql.com/doc/refman/8.0/en/innodb-transaction-isolation-levels.html
     * the usage of READ COMMITTED reduces the risk of dead locks for update and delete statements,
     * since record locks are released after evaluating the WHERE condition of the statement with that level.
     */
    public function reduceDeadlocks() {
        $this->getAdapter()->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }
}