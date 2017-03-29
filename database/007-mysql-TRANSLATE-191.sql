-- /*
-- START LICENSE AND COPYRIGHT
-- 
--  This file is part of ZfExtended library
--  
--  Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.
-- 
--  Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com
-- 
--  This file may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
--  as published by the Free Software Foundation and appearing in the file lgpl3-license.txt 
--  included in the packaging of this file.  Please review the following information 
--  to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
-- https://www.gnu.org/licenses/lgpl-3.0.txt
-- 
--  @copyright  Marc Mittag, MittagQI - Quality Informatics
--  @author     MittagQI - Quality Informatics
--  @license    GNU LESSER GENERAL PUBLIC LICENSE version 3
-- 			 https://www.gnu.org/licenses/lgpl-3.0.txt
-- 
-- END LICENSE AND COPYRIGHT
-- */

ALTER TABLE `sessionMapInternalUniqId` CONVERT TO CHARACTER SET utf8;
ALTER TABLE `sessionMapInternalUniqId` ADD UNIQUE KEY (`internalSessionUniqId`);

CREATE TABLE `sessionUserLock` (
  `login` varchar(255) NOT NULL,
  `internalSessionUniqId` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`login`),
  FOREIGN KEY userlock_sessionid_fk (`internalSessionUniqId`) REFERENCES `sessionMapInternalUniqId` (`internalSessionUniqId`) ON DELETE CASCADE,
  FOREIGN KEY userlock_user_fk (`login`) REFERENCES `Zf_users` (`login`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
