ALTER TABLE `Zf_users` 
ADD COLUMN `sourceLanguages` VARCHAR(500) NULL AFTER `locale`,
ADD COLUMN `targetLanguage` VARCHAR(500) NULL AFTER `sourceLanguage`;

