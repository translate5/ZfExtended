-- /*
-- START LICENSE AND COPYRIGHT
--
--  This file is part of translate5
--
--  Copyright (c) 2013 - '.(date('Y')).' Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.
--
--  Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com
--
--  This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
--  as published by the Free Software Foundation and appearing in the file agpl3-license.txt
--  included in the packaging of this file.  Please review the following information
--  to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3 requirements will be met:
--  http://www.gnu.org/licenses/agpl.html
--
--  There is a plugin exception available for use with this release of translate5 for
--  translate5: Please see http://www.translate5.net/plugin-exception.txt or
--  plugin-exception.txt in the root folder of translate5.
--
--  @copyright  Marc Mittag, MittagQI - Quality Informatics
--  @author     MittagQI - Quality Informatics
--  @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execption
-- 			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt
--
-- END LICENSE AND COPYRIGHT
-- */

CREATE TABLE Zf_errorlog_innodb LIKE Zf_errorlog;
ALTER TABLE Zf_errorlog_innodb ENGINE=InnoDB;

ALTER TABLE Zf_errorlog_innodb ADD FULLTEXT INDEX ft_message (message);
ALTER TABLE Zf_errorlog_innodb ADD INDEX idx_created (created);
ALTER TABLE Zf_errorlog_innodb ADD INDEX idx_level (level);
ALTER TABLE Zf_errorlog_innodb ADD INDEX idx_domain (domain);
ALTER TABLE Zf_errorlog_innodb ADD INDEX idx_eventCode (eventCode);

INSERT INTO Zf_errorlog_innodb SELECT * FROM Zf_errorlog;
-- down from here new entries will still go into current log table, not copied anymore, so store highest ID.
SELECT MAX(id) INTO @last_id_before_rename FROM Zf_errorlog_innodb;

RENAME TABLE Zf_errorlog TO Zf_errorlog_old, Zf_errorlog_innodb TO Zf_errorlog;

-- now we have to get the new log entries not copied previously
INSERT INTO Zf_errorlog (created, last, duplicates, duplicateHash, level, domain, worker, eventCode, message, appVersion, file, line, trace, extra, httpHost, url, method, userLogin, userGuid)
SELECT created, last, duplicates, duplicateHash, level, domain, worker, eventCode, message, appVersion, file, line, trace, extra, httpHost, url, method, userLogin, userGuid
FROM Zf_errorlog_old
WHERE id > @last_id_before_rename;

DROP table Zf_errorlog_old;