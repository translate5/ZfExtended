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

/**
 */
class ZfExtended_Models_Entity_Exceptions_IntegrityConstraint extends ZfExtended_ErrorCodeException {
    /**
     * @var string
     */
    protected $domain = 'core.entity';
    
    static protected $localErrorCodes = [
        'E1016' => 'Integrity Constraint Violation on saving/deleting {entity}',
    ];
    
    public function setErrors($errors) {
        if(!empty($errors['entity'])) {
            $this->domain .= '.'.$errors['entity'];
        }
        return parent::setErrors($errors);
    }
    
    public function isInMessage($fragment) {
        return strpos($this->getMessage(), $fragment) !== false || strpos($this->getPrevious()->getMessage(), $fragment) !== false;
    }
    
    //Problem bei der Nutzung (Szenario: LangRes hinzufügen, mit bereits gelöschtem Customer)
    //Default Fehler führt im Frontend zu leerem Popup weil, errors unten mit der Message und den Daten befüllt ist. 
    // Hier ist das Fehler handling inkonsquent und unsauber....

    /*
    {,…}
    errors: [{_errorMessage: "Integrity Constraint Violation on saving/deleting {entity}", _errorCode: 500},…]
    0: {_errorMessage: "Integrity Constraint Violation on saving/deleting {entity}", _errorCode: 500}
    1: {entity: "editor_Models_LanguageResources_CustomerAssoc",…}
    httpStatus: 500
    */
}