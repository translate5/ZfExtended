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
 * @method void setId(int $id)
 * @method void setName(string $name)
 * @method void setGuiName(string $guiName)
 * @method void setGuiGroup(string $guiGroup)
 * @method void setConfirmed(bool $confirmed)
 * @method void setModule(string $module)
 * @method void setCategory(string $category)
 * @method void setValue(string $value)
 * @method void setDefault(string $default)
 * @method void setDefaults(string $defaults) comma seperated values!
 * @method void setType(string $type)
 * @method void setTypeClass(string $typeClass)
 * @method void setDescription(string $desc)
 * @method void setComment(string $comment)
 *
 * @method string getId()
 * @method string getName()
 * @method string getGuiName()
 * @method string getGuiGroup()
 * @method string getConfirmed()
 * @method string getModule()
 * @method string getCategory()
 * @method string getValue()
 * @method string getDefault()
 * @method string getDefaults()
 * @method string getType()
 * @method string getLevel()
 * @method string getTypeClass()
 * @method string getDescription()
 * @method string getComment()
 *
 * The conversion from DB Storage Format to Zend Config Format is done by ZfExtended_Resource_DbConfig
 */
class ZfExtended_Models_Config extends ZfExtended_Models_Entity_Abstract
{
    protected $dbInstanceClass = 'ZfExtended_Models_Db_Config';
    //protected $validatorInstanceClass = 'ZfExtended_Models_Validator_User';

    /**
     * Sets the given configuration value for the configuration identified via the given name
     * loads internally the configuration instance so that the instance is a fully loaded instance then
     * @param string $name the configuration name
     * @param string $value the value to be set
     * @return ZfExtended_Models_Config
     */
    public function update(string $name, string $value, string $comment = null, bool $forceSystemLevel = false): static
    {
        $update = [
            'value' => $value,
        ];
        if (! is_null($comment)) {
            $update['comment'] = $comment;
        }
        if ($forceSystemLevel) {
            $update['level'] = 1;
        }
        $this->db->update($update, [
            'name = ?' => $name,
        ]);
        $this->loadByName($name);

        return $this;
    }

    /**
     * loads the config entry to the given name
     * @param string $name
     */
    public function loadByName($name)
    {
        try {
            $s = $this->db->select()->where('name = ?', $name);
            $row = $this->db->fetchRow($s);
        } catch (Exception $e) {
            $this->notFound('NotFound after other Error', $e);
        }
        if (! $row) {
            $this->notFound(__CLASS__ . '#name', $name);
        }
        //load implies loading one Row, so use only the first row
        $this->row = $row;
    }

    /**
     * loads all tasks of the given tasktype that are associated to a specific user as PM
     * @return array
     * @deprecated must be removed see TRANSLATE-3559
     */
    public function loadListByNamePart(string $name)
    {
        $s = $this->db->select()
            ->where('name like ?', '%' . $name . '%')
            ->order('name ASC');

        return parent::loadFilterdCustom($s);
    }
}
