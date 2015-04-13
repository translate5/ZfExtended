-- /*
-- START LICENSE AND COPYRIGHT
-- 
--  This file is part of translate5
--  
--  Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.
-- 
--  Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com
-- 
--  This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
--  as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
--  included in the packaging of this file.  Please review the following information 
--  to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
--  http://www.gnu.org/licenses/agpl.html
-- 
--  There is a plugin exception available for use with this release of translate5 for
--  open source applications that are distributed under a license other than AGPL:
--  Please see Open Source License Exception for Development of Plugins for translate5
--  http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
--  folder of translate5.
--   
--  @copyright  Marc Mittag, MittagQI - Quality Informatics
--  @author     MittagQI - Quality Informatics
--  @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
-- 			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt
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
