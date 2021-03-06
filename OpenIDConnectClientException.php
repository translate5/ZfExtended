<?php
/*
 START LICENSE AND COPYRIGHT
 
 This file is part of translate5
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.
 
 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com
 
 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt
 included in the packaging of this file.  Please review the following information
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3 requirements will be met:
 http://www.gnu.org/licenses/agpl.html
 
 There is a plugin exception available for use with this release of translate5 for
 translate5: Please see http://www.translate5.net/plugin-exception.txt or
 plugin-exception.txt in the root folder of translate5.
 
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execption
 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt
 
 END LICENSE AND COPYRIGHT
 */

/**
 * Open id client exception
 *
 */
class ZfExtended_OpenIDConnectClientException extends ZfExtended_ErrorCodeException {
    
    /**
     * @var string
     */
    protected $domain = 'core.openidconnect';
    
    static protected $localErrorCodes = [
        'E1165' => 'Error on openid authentication.',
        //the following messages are shown in the frontend, so they should not expose sensitive information:
        'E1328' => 'OpenID connect authentication is only usable with SSL/HTTPS enabled!',
        'E1329' => 'OpenID connect: The default server and the claim roles are not defined.',
        'E1330' => 'The customer server roles are empty but there are roles from the provider.',
        'E1331' => 'Invalid claims roles for the allowed server customer roles',
    ];
}