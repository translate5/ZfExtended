UPDATE `Zf_configuration` 
SET `value` = 30
WHERE name = 'runtimeOptions.maintenance.timeToNotify' AND (`value` = '' OR `value` is null);

UPDATE `Zf_configuration` 
SET `default` = 30 
WHERE name = 'runtimeOptions.maintenance.timeToNotify' AND (`default` = '' OR `default` is null);

UPDATE `Zf_configuration` 
SET `value` = 5 
WHERE name = 'runtimeOptions.maintenance.timeToLoginLock' AND (`value` = '' OR `value` is null);

UPDATE `Zf_configuration` 
SET `default` = 5
WHERE name = 'runtimeOptions.maintenance.timeToLoginLock' AND (`default` = '' OR `default` is null);
