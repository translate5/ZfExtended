/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3 requirements will be met:
 http://www.gnu.org/licenses/agpl.html
  
 There is a plugin exception available for use with this release of translate5 for
 translate5 plug-ins that are distributed under GNU AFFERO GENERAL PUBLIC LICENSE version 3:
 Please see http://www.translate5.net/plugin-exception.txt or plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execption
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

CREATE TABLE `Zf_errorlog` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP NULL COMMENT 'first occurence of error',
  `last` TIMESTAMP NULL DEFAULT NULL COMMENT 'last occurence of error',
  `count` int(11) NOT NULL DEFAULT 1 COMMENT 'count of same error between created and last',
  `level` TINYINT(2) NOT NULL DEFAULT 4 COMMENT 'Error level: FATAL: 1; ERROR: 2; WARN: 4; INFO: 8; DEBUG: 16; TRACE: 32;',
  `domain` VARCHAR(128) NOT NULL DEFAULT 'core' COMMENT 'filterable, hierarchical context domain of the error',
  `worker` VARCHAR(128) NULL COMMENT 'worker class if error happened in a worker',
  `eventCode` VARCHAR(10) NOT NULL DEFAULT 'E0000' COMMENT 'Project unique event code (yeah, not only errors are logged)',
  `message` VARCHAR(512) NULL COMMENT 'human readable description of the error',
  `file` VARCHAR(512) NULL COMMENT 'file where the error happened',
  `line` VARCHAR(16) NULL COMMENT 'line where the error happened',
  `trace` text NULL COMMENT 'stack trace to the error',
  `extra` text NULL COMMENT 'extra data to the error',
  `url` VARCHAR(1024) NULL COMMENT 'the called URL',
  `method` VARCHAR(16) NULL COMMENT 'the called HTTP Method',
  `userLogin` VARCHAR(255) NULL COMMENT 'the authenticated user',
  `userGuid` VARCHAR(38) NULL COMMENT 'the authenticated user',
  PRIMARY KEY (`id`),
  KEY `origin_level` (`domain`,`level`)
) ENGINE = MyISAM DEFAULT CHARSET=utf8;
-- mysam for performance, no inno features needed here.
