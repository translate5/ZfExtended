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

CREATE TABLE IF NOT EXISTS `invalidlogin` (
  `invalidLoginId` int(11) NOT NULL AUTO_INCREMENT,
  `eMail` varchar(255) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`invalidLoginId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0;

RENAME TABLE `invalidlogin` TO  `Zf_invalidlogin` ;

ALTER TABLE  `Zf_invalidlogin` CHANGE  `eMail`  `login` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

ALTER TABLE  `Zf_invalidlogin` CHANGE  `invalidLoginId`  `id` INT( 11 ) NOT NULL AUTO_INCREMENT;
