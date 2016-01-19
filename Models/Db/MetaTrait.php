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
 * @package editor
 * @version 1.0
 * 
 */
/**
 * shared entity meta DB access methods
 */
trait ZfExtended_Models_Db_MetaTrait {
    /**
     * Adds a columns to the meta table
     * type is one of editor_Models_Segment_Meta::META_TYPE_* constants
     * @param string $name
     * @param string $type
     * @param mixed $default
     * @param string $comment
     * @param integer $length
     */
    public function addColumn($columnname, $type, $default, $comment, $length = 0) {
        switch($type) {
            case ZfExtended_Models_Entity_MetaAbstract::META_TYPE_BOOLEAN:
                $type = 'TINYINT';
                $default = (int)(boolean)$default;
                break;
            case ZfExtended_Models_Entity_MetaAbstract::META_TYPE_INTEGER:
                $type = 'INT(11)';
                $default = (int)$default;
                break;
            case ZfExtended_Models_Entity_MetaAbstract::META_TYPE_FLOAT:
                $type = 'FLOAT(5,2)';
                $default = (float)$default;
                break;
            case ZfExtended_Models_Entity_MetaAbstract::META_TYPE_STRING:
                $type = 'VARCHAR('.(empty($length) ? '255' : $length).')';
                $default = "'".(string)addslashes($default)."'";
                break;
            default:
                break;
        }
        if(is_null($default)) {
            $default = 'NULL';
        }
        $db = $this->getAdapter();
        $alter = 'ALTER TABLE `%s` ADD COLUMN `%s` %s DEFAULT %s COMMENT "%s";';
        $db->query(sprintf($alter, $this->_name, $columnname, $type, $default, addslashes($comment)));
        $this->_metadata = array();
        $this->_metadataCache = null;
        $this->_setupMetadata();
    }
}