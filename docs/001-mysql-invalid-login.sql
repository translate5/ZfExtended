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
CREATE TABLE IF NOT EXISTS `invalidlogin` (
  `invalidLoginId` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(255) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`invalidLoginId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=0;

RENAME TABLE `invalidlogin` TO  `Zf_invalidlogin` ;

ALTER TABLE  `Zf_invalidlogin` CHANGE  `eMail`  `login` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL

ALTER TABLE  `Zf_invalidlogin` CHANGE  `invalidLoginId`  `id` INT( 11 ) NOT NULL AUTO_INCREMENT;
