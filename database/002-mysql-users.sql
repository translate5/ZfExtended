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

CREATE TABLE Zf_users (
  `id` int (11) NOT NULL AUTO_INCREMENT,
  `userGuid` varchar(38) NOT NULL,
  `firstName` varchar (255) NOT NULL,
  `surName` varchar (255) NOT NULL,
  `gender` char (1) NOT NULL,
  `login` varchar (255) NOT NULL,
  `email` varchar (255) NOT NULL,
  `roles` varchar (255) NULL default NULL,
  `passwd` varchar (38) NOT NULL,
  `passwdReset` boolean NOT NULL default 1,
  PRIMARY KEY (`id`),
  UNIQUE (`userGuid`),
  UNIQUE (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
