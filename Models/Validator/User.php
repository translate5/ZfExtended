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
 * @package ZfExtended
 * @version 2.0
 *
 */
class ZfExtended_Models_Validator_User extends ZfExtended_Models_Validator_Abstract
{
    /**
     * Validators for User Entity
     * Validation will be done on calling entity->validate
     */
    protected function defineValidators(): void
    {
        $this->addValidator('id', 'int');
        $this->addValidator('userGuid', 'guid');
        $this->addValidator('firstName', 'stringLength', [
            'min' => 1,
            'max' => 255,
        ]);
        $this->addValidator('surName', 'stringLength', [
            'min' => 1,
            'max' => 255,
        ]);
        $this->addValidator('gender', 'inArray', [['f', 'm', 'n']]);
        $this->addValidator('locale', 'stringLength', [
            'min' => 2,
            'max' => 3,
        ]);
        $this->addValidator('roles', 'stringLength', [
            'min' => 0,
            'max' => 255,
        ]);
        $this->addValidator('parentIds', 'stringLength', [
            'min' => 0,
            'max' => 255,
        ]);
        $this->setLoginValidator();
        //FIXME make a regex here!
        $this->setEmailValidator();
        $this->setPasswdValidator();
        $this->addValidator('customers', 'stringLength', [
            'min' => 0,
            'max' => 255,
        ]);

        $this->addValidator('editable', 'boolean');

        $this->addValidator('openIdIssuer', 'stringLength', [
            'min' => 0,
            'max' => 500,
        ]);
        $this->addValidator('openIdSubject', 'stringLength', [
            'min' => 0,
            'max' => 255,
        ]);
    }

    protected function setEmailValidator(): void
    {
        $me = $this;
        $this->addValidatorCustom('email', function ($v) use ($me) {
            $valid = filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
            if (! $valid) {
                $me->addMessage('email', 'invalidEmail', 'invalidEmail');
            }

            return $valid;
        });
    }

    protected function setPasswdValidator(): void
    {
        $me = $this;
        $passwdValidator = function ($value) use ($me) {
            if (ZfExtended_Utils::emptyString($value)) {
                return true;
            }
            $message = [];
            if (ZfExtended_PasswordCheck::isValid($value, $message) === false) {
                $me->addMessage('passwd', 'invalidPasswd', $message);

                return false;
            }

            return true;
        };
        $this->addValidatorCustom('passwd', $passwdValidator, true);
    }

    /**
     * @throws Zend_Exception
     * @throws Zend_Validate_Exception
     */
    private function setLoginValidator(): void
    {
        $regexValidator = new Zend_Validate_Regex('/^[\w\-_@.]+$/u');
        $regexValidator->setMessage('Der Benutzername enthält Zeichen, die nicht verwendet werden dürfen!', Zend_Validate_Regex::NOT_MATCH);

        $chain = new Zend_Validate();
        $chain->addValidator(new Zend_Validate_StringLength([
            'min' => 6,
            'max' => 255,
        ]));
        $chain->addValidator($regexValidator);

        $this->addValidatorInstance('login', $chain);
    }
}
