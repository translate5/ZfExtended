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
 * @package editor
 * @version 1.0
 *
 */
/**
 * shared entity meta DB access methods
 */
trait ZfExtended_Models_Db_MetaTrait
{
    /**
     * Adds a columns to the meta table
     * type is one of editor_Models_Segment_Meta::META_TYPE_* constants
     * @param string $type
     * @param mixed $default
     * @param string $comment
     * @param int $length
     */
    public function addColumn($columnname, $type, $default, $comment, $length = 0)
    {
        switch ($type) {
            case ZfExtended_Models_Entity_MetaAbstract::META_TYPE_BOOLEAN:
                $type = 'TINYINT';
                $default = (int) (bool) $default;

                break;
            case ZfExtended_Models_Entity_MetaAbstract::META_TYPE_INTEGER:
                $type = 'INT(11)';
                $default = (int) $default;

                break;
            case ZfExtended_Models_Entity_MetaAbstract::META_TYPE_FLOAT:
                $type = 'FLOAT(5,2)';
                $default = (float) $default;

                break;
            case ZfExtended_Models_Entity_MetaAbstract::META_TYPE_STRING:
                $type = 'VARCHAR(' . (empty($length) ? '255' : $length) . ')';
                $default = "'" . (string) addslashes($default) . "'";

                break;
            default:
                break;
        }
        if (is_null($default)) {
            $default = 'NULL';
        }
        $db = $this->getAdapter();
        $alter = 'ALTER TABLE `%s` ADD COLUMN `%s` %s DEFAULT %s COMMENT "%s";';
        $db->query(sprintf($alter, $this->_name, $columnname, $type, $default, addslashes($comment)));
        $this->_metadata = [];
        $this->_metadataCache = null;
        $this->_setupMetadata();
    }
}
