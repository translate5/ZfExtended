INSERT INTO `Zf_configuration` (`name`, `confirmed`, `module`, `category`, `value`, `default`, `defaults`, `type`, `description`) VALUES ('runtimeOptions.mntStartDate', '1', 'app', 'system', '', '', '', 'string', 'The server maintenance start date ');

INSERT INTO `Zf_configuration` (`name`, `confirmed`, `module`, `category`, `value`, `default`, `defaults`, `type`, `description`) VALUES ('runtimeOptions.mntCountdown', '1', 'app', 'system', '', '', '', 'string', 'The countdown minutes before the maintenance is started');

INSERT INTO `Zf_configuration` (`name`, `confirmed`, `module`, `category`, `value`, `default`, `defaults`, `type`, `description`) VALUES ('runtimeOptions.mntLoginBlock', '1', 'app', 'system', '', '', '', 'string', 'minutes before the point in time of the update the application is locked for new log-ins');
