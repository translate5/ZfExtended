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
 * Class to access errorlog-table
 */
class ZfExtended_Models_Db_ErrorLog extends Zend_Db_Table_Abstract {
    protected $_name    = 'Zf_errorlog';
    public $_primary = 'id';
    
    /**
     * Updates the duplicate data in the error log table, returns false if no entry was updated, true otherwise
     * @param string $hash
     * @param int $count
     * @return bool
     */
    public function incrementDuplicate(string $hash, int $count): bool {
        //update the duplicates info for the newest entry with the same hash
        $rowCount = $this->update([
            'duplicates' => $count,
            'last' => NOW_ISO,
        ], $this->getAdapter()->quoteInto('id = (SELECT id FROM (SELECT id FROM Zf_errorlog WHERE duplicateHash = ? ORDER BY id DESC limit 1) as x)', $hash));

        
        //if no row update return false to trigger insert outside
        return $rowCount > 0;
    }
}
