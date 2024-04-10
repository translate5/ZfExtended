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

/*
 * Validiert dass zwei Felder den gleichen Inhalt haben
 */
class ZfExtended_Validate_IdenticalField extends Zend_Validate_Abstract
{
    public const NOT_MATCH = 'notMatch';

    public const MISSING_FIELD_NAME = 'missingFieldName';

    public const INVALID_FIELD_NAME = 'invalidFieldName';

    /**
     * @var array
     */
    protected $_messageTemplates = [
        self::MISSING_FIELD_NAME =>
          'DEVELOPMENT ERROR: Field name to match against was not provided.',
        self::INVALID_FIELD_NAME =>
          'DEVELOPMENT ERROR: The field "%fieldName%" was not provided to match against.',
        self::NOT_MATCH =>
          'Sollte mit "%fieldTitle%" identisch sein.',
    ];

    /**
     * @var array
     */
    protected $_messageVariables = [
        'fieldName' => '_fieldName',
        'fieldTitle' => '_fieldTitle',
    ];

    /**
     * Name of the field as it appear in the $context array.
     *
     * @var string
     */
    protected $_fieldName;

    /**
     * Title of the field to display in an error message.
     *
     * If evaluates to false then will be set to $this->_fieldName.
     *
     * @var string
     */
    protected $_fieldTitle;

    /**
     * Sets validator options
     *
     * @param  string $fieldName
     * @param  string $fieldTitle
     */
    public function __construct($fieldName, $fieldTitle = null)
    {
        $this->setFieldName($fieldName);
        $this->setFieldTitle($fieldTitle);
    }

    /**
     * Returns the field name.
     *
     * @return string
     */
    public function getFieldName()
    {
        return $this->_fieldName;
    }

    /**
     * Sets the field name.
     *
     * @param  string $fieldName
     * @return Zend_Validate_Abstract Provides a fluent interface
     */
    public function setFieldName($fieldName)
    {
        $this->_fieldName = $fieldName;

        return $this;
    }

    /**
     * Returns the field title.
     *
     * @return integer
     */
    public function getFieldTitle()
    {
        return $this->_fieldTitle;
    }

    /**
     * Sets the field title.
     *
     * @param  string:null $fieldTitle
     * @return Zend_Validate_Abstract Provides a fluent interface
     */
    public function setFieldTitle($fieldTitle = null)
    {
        $this->_fieldTitle = $fieldTitle ? $fieldTitle : $this->_fieldName;

        return $this;
    }

    /**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if and only if a field name has been set, the field name is available in the
     * context, and the value of that field name matches the provided value.
     *
     * @param  string $value
     *
     * @return boolean
     */
    public function isValid($value, $context = null)
    {
        $this->_setValue($value);
        $field = $this->getFieldName();

        if (empty($field)) {
            $this->_error(self::MISSING_FIELD_NAME);

            return false;
        } elseif (! isset($context[$field])) {
            $this->_error(self::INVALID_FIELD_NAME);

            return false;
        } elseif (is_array($context)) {
            if ($value == $context[$field]) {
                return true;
            }
        } elseif (is_string($context) && ($value == $context)) {
            return true;
        }
        $this->_error(self::NOT_MATCH);

        return false;
    }
}
