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

CREATE TABLE IF NOT EXISTS `passwdreset` (
  `passwdResetId` int(11) NOT NULL AUTO_INCREMENT,
  `resetHash` varchar(32) DEFAULT NULL,
  `employeeId` int(11) DEFAULT NULL,
  `expiration` int(11) DEFAULT NULL,
  PRIMARY KEY (`passwdResetId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0 ;

RENAME TABLE `passwdreset` TO  `Zf_passwdreset` ;

ALTER TABLE  `Zf_passwdreset` CHANGE  `passwdResetId`  `id` INT( 11 ) NOT NULL AUTO_INCREMENT;
ALTER TABLE  `Zf_passwdreset` CHANGE  `employeeId`  `userId` INT( 11 ) DEFAULT NULL;
ALTER TABLE  `Zf_passwdreset` ADD  `internalSessionUniqId` VARCHAR( 32 ) NOT NULL;

alter table `Zf_passwdreset`
add constraint `Zf_passwdreset_userId_FK` FOREIGN KEY ( `userId` ) references Zf_users(id) ON DELETE CASCADE;
