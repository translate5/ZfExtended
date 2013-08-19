--  /*
--  START LICENSE AND COPYRIGHT
--  
--  This file is part of Translate5 Editor PHP Serverside and build on Zend Framework
--  
--   Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.
-- 
--   Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com
-- 
--   This file is multi-licensed under the EPL, LGPL and GPL licenses. 
-- 
--   It may be used under the terms of the Eclipse Public License - v 1.0
--   as published by the Eclipse Foundation and appearing in the file eclipse-license.txt 
--   included in the packaging of this file.  Please review the following information 
--   to ensure the Eclipse Public License - v 1.0 requirements will be met:
--   http://www.eclipse.org/legal/epl-v10.html.
-- 
--   Also it may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
--   or the GNU GENERAL PUBLIC LICENSE version 3 as published by the 
--   Free Software Foundation, Inc. and appearing in the files lgpl-license.txt and gpl3-license.txt
--   included in the packaging of this file.  Please review the following information 
--   to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3 requirements or the
--   GNU GENERAL PUBLIC LICENSE version 3 requirements will be met:
--   http://www.gnu.org/licenses/lgpl.html   /  http://www.gnu.org/licenses/gpl.html
--   
--   @copyright  Marc Mittag, MittagQI - Quality Informatics
--   @author     MittagQI - Quality Informatics
--   @license    Multi-licensed under Eclipse Public License - v 1.0 http://www.eclipse.org/legal/epl-v10.html, GNU LESSER GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/lgpl.html and GNU GENERAL PUBLIC LICENSE version 3 http://www.gnu.org/licenses/gpl.html
--  
--  END LICENSE AND COPYRIGHT 
--  */
-- 
CREATE TABLE IF NOT EXISTS `passwdreset` (
  `passwdResetId` int(11) NOT NULL AUTO_INCREMENT,
  `resetHash` varchar(32) DEFAULT NULL,
  `employeeId` int(11) DEFAULT NULL,
  `expiration` int(11) DEFAULT NULL,
  PRIMARY KEY (`passwdResetId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0 ;

RENAME TABLE `passwdreset` TO  `LEK_passwdreset` ;

ALTER TABLE  `LEK_passwdreset` CHANGE  `passwdResetId`  `id` INT( 11 ) NOT NULL AUTO_INCREMENT;
ALTER TABLE  `LEK_passwdreset` CHANGE  `employeeId`  `userId` INT( 11 ) DEFAULT NULL;
ALTER TABLE  `LEK_passwdreset` ADD  `internalSessionUniqId` VARCHAR( 32 ) NOT NULL;

alter table LEK_passwdreset
add constraint LEK_passwdreset_userId_FK FOREIGN KEY ( userId ) references LEK_users(id) ON DELETE CASCADE;
