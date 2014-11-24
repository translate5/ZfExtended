--  /*
--  START LICENSE AND COPYRIGHT
--  
--  This file is part of Translate5 Editor PHP Serverside and build on Zend Framework
--  
--  Copyright (c) 2013 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.
-- 
--  Contact:  http://www.MittagQI.com/  /  service (ÄTT) MittagQI.com
-- 
--  This file may be used under the terms of the GNU General Public License version 3.0
--  as published by the Free Software Foundation and appearing in the file gpl3-license.txt 
--  included in the packaging of this file.  Please review the following information 
--  to ensure the GNU General Public License version 3.0 requirements will be met:
--  http://www.gnu.org/copyleft/gpl.html.
-- 
--  For this file you are allowed to make use of the same FLOSS exceptions to the GNU 
--  General Public License version 3.0 as specified by Sencha for Ext Js. 
--  Please be aware, that Marc Mittag / MittagQI take no warranty  for any legal issue, 
--  that may arise, if you use these FLOSS exceptions and recommend  to stick to GPL 3. 
--  For further information regarding this topic please see the attached license.txt
--  of this software package.
--  
--  MittagQI would be open to release translate5 under EPL or LGPL also, if this could be
--  brought in accordance with the ExtJs license scheme. You are welcome to support us
--  with legal support, if you are interested in this.
--  
--  
--  @copyright  Marc Mittag, MittagQI - Quality Informatics
--  @author     MittagQI - Quality Informatics
--  @license    GNU General Public License version 3.0 http://www.gnu.org/copyleft/gpl.html
--              with FLOSS exceptions (see floss-exception.txt and ux-exception.txt at the root level)
--  
--  END LICENSE AND COPYRIGHT 
--  */
-- 

DROP TABLE IF EXISTS `LEK_worker`;

CREATE TABLE `LEK_worker` (
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