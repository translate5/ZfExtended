<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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

namespace MittagQI\ZfExtended\Acl;

/**
 * common functions to read out the resource and right information
 */
class SystemResource extends AbstractResource {
    /**
     * the resource ID used as resource in DB
     */
    public const ID = 'system';

    /**
     * allows to see all users instead only the users of the user hierarchy
     * TODO check usages
     */
    public const SEE_ALL_USERS = 'seeAllUsers';
    /**
     * allows session deletion also by internal id and not only by session id
     * TODO check usage in ACL table, still used? evil feature?
     */
    public const SESSION_DELETE_BY_INTERNAL_ID = 'sessionDeleteByInternalId';
    /**
     * defines which roles should receive the daily error log summary
     */
    public const SYSTEM_LOG_SUMMARY = 'systemLogSummary';
}
