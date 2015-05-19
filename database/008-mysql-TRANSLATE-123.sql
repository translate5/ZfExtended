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

CREATE TABLE `Zf_configuration` (
  `id` int (11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'corresponds to the old INI key',
  `confirmed` tinyint NOT NULL DEFAULT 0 COMMENT 'used for new values, 0 not confirmed by user, 1 confirmed',
  `module` varchar(100) DEFAULT NULL COMMENT 'the PHP module this config value was defined for',
  `category` varchar(100) NOT NULL DEFAULT 'other' COMMENT 'field to categorize the config values',
  `value` varchar(1024) DEFAULT NULL COMMENT 'the config value, if data exceeds 1024byte (especially for list and map) data should be stored in a own table',
  `default` varchar(1024) DEFAULT NULL COMMENT 'the system default value for this config',
  `defaults` varchar(1024) DEFAULT NULL COMMENT 'a comma separated list of default values, only one of this value is possible to be set by the GUI',
  `type` enum('string', 'integer', 'boolean', 'list', 'map','absolutepath') NOT NULL DEFAULT 'string' COMMENT 'the type of the config value is needed also for GUI',
  `description` varchar(1024) NOT NULL COMMENT 'contains a human readable description for what this config is for',
  PRIMARY KEY (`id`),
  UNIQUE KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `Zf_acl_rules` (
  `id` int (11) NOT NULL AUTO_INCREMENT,
  `module` varchar(100) DEFAULT NULL COMMENT 'the PHP module this acl rule was defined for',
  `role` varchar(100) NOT NULL COMMENT 'the name of the role which has the defined rule',
  `resource` varchar(100) NOT NULL COMMENT 'the resource to be allowed',
  `right` varchar(100) NOT NULL COMMENT 'the single right to be allowed',
  PRIMARY KEY (`id`),
  UNIQUE KEY (`module`,`role`,`resource`,`right`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
