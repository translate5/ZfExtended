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

CREATE TABLE `Zf_worker` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `state` varchar(38) NOT NULL DEFAULT 'waiting',
  `worker` varchar(255) NOT NULL DEFAULT '',
  `resource` varchar(255) NOT NULL DEFAULT '',
  `slot` varchar(255) NOT NULL DEFAULT '',
  `maxParallelProcesses` int(11) NOT NULL DEFAULT '1',
  `taskGuid` varchar(38) DEFAULT '',
  `parameters` longtext,
  `pid` int(11) DEFAULT NULL,
  `starttime` varchar(255) NOT NULL DEFAULT '',
  `maxRuntime` varchar(255) NOT NULL DEFAULT '',
  `hash` varchar(255) NOT NULL DEFAULT '',
  `blockingType` varchar(38) NOT NULL DEFAULT 'slot',
  PRIMARY KEY (`id`),
  KEY `worker` (`worker`),
  KEY `slot` (`slot`),
  KEY `taskGuid` (`taskGuid`),
  KEY `starttime` (`starttime`),
  KEY `hash` (`hash`),
  KEY `state` (`state`),
  KEY `resource` (`resource`),
  KEY `maxRuntime` (`maxRuntime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
