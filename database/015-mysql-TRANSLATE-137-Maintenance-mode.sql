INSERT INTO `Zf_configuration` (`name`, `confirmed`, `module`, `category`, `value`, `default`, `defaults`, `type`, `description`) VALUES ('runtimeOptions.maintenance.startDate', '1', 'app', 'system', '', '', '', 'string', 'The server maintenance start date ');

INSERT INTO `Zf_configuration` (`name`, `confirmed`, `module`, `category`, `value`, `default`, `defaults`, `type`, `description`) VALUES ('runtimeOptions.maintenance.timeToNotify', '1', 'app', 'system', '', '', '', 'string', 'The countdown minutes before the maintenance is started');

INSERT INTO `Zf_configuration` (`name`, `confirmed`, `module`, `category`, `value`, `default`, `defaults`, `type`, `description`) VALUES ('runtimeOptions.maintenance.timeToLoginLock', '1', 'app', 'system', '', '', '', 'string', 'minutes before the point in time of the update the application is locked for new log-ins');
