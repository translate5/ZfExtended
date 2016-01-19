-- /*
-- START LICENSE AND COPYRIGHT
-- 
--  This file is part of ZfExtended library
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
