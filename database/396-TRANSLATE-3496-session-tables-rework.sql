-- /*
-- START LICENSE AND COPYRIGHT
--
--  This file is part of translate5
--
--  Copyright (c) 2013 - '.(date('Y')).' Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.
--
--  Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com
--
--  This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
--  as published by the Free Software Foundation and appearing in the file agpl3-license.txt
--  included in the packaging of this file.  Please review the following information
--  to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3 requirements will be met:
--  http://www.gnu.org/licenses/agpl.html
--
--  There is a plugin exception available for use with this release of translate5 for
--  translate5: Please see http://www.translate5.net/plugin-exception.txt or
--  plugin-exception.txt in the root folder of translate5.
--
--  @copyright  Marc Mittag, MittagQI - Quality Informatics
--  @author     MittagQI - Quality Informatics
--  @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execption
-- 			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt
--
-- END LICENSE AND COPYRIGHT
-- */

set foreign_key_checks = 0;

ALTER TABLE `session` ADD COLUMN `internalSessionUniqId` varchar (32);

ALTER TABLE `session`
    ADD CONSTRAINT `internalSessionUniqId`
        UNIQUE (`internalSessionUniqId`);

ALTER TABLE `session`
    CHANGE `modified`
    `modified` TIMESTAMP NOT NULL
    DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP;

DROP TABLE `sessionUserLock`;

CREATE TABLE `sessionUserLock` (
   `login` varchar(255) NOT NULL,
   `internalSessionUniqId` varchar(32) DEFAULT NULL,
   PRIMARY KEY (`login`),
   KEY `userlock_sessionid_fk` (`internalSessionUniqId`),
   CONSTRAINT `sessionUserLock_ibfk_1` FOREIGN KEY (`internalSessionUniqId`) REFERENCES `session` (`internalSessionUniqId`) ON DELETE CASCADE,
   CONSTRAINT `sessionUserLock_ibfk_2` FOREIGN KEY (`login`) REFERENCES `Zf_users` (`login`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE `sessionMapInternalUniqId`;

set foreign_key_checks = 1;
