<?php
/*
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

set_time_limit(0);

//uncomment the following line, so that the file is not marked as processed:
// $this->doNotSavePhpForDebugging = false;

//should be not __FILE__ in the case of wanted restarts / renaming etc...
// and must not be a constant since in installation the same named constant would we defined multiple times then
$SCRIPT_IDENTIFIER = '040-TRANSLATE-3051-passwd-hash.php';

/* @var $this ZfExtended_Models_Installer_DbUpdater */

/**
 * define database credential variables
 */
$argc = count($argv);
if(empty($this) || empty($argv) || $argc < 5 || $argc > 7) {
    die("please dont call the script direct! Call it by using DBUpdater!\n\n");
}

$secret = null;
$iniKey = 'runtimeOptions.authentication.secret';
$defaultIni = APPLICATION_PATH.'/config/installation.ini';
$dataIni = APPLICATION_ROOT.'/data/installation.ini';

//check if the installer - or someone else - did already provide a secret in one of the main installation.ini files
foreach([$defaultIni, $dataIni] as $ini) {
    if(file_exists($ini)) {
        $data = parse_ini_file($ini);
        if(array_key_exists($iniKey, $data)) {
            $secret = trim($data[$iniKey]);
            $this->output('Found a secret in ini '.$ini.' use the latest one if multiple found.');
            // do not break here, since that reflects the overwrite / search order of the internal list of ini files
        }
    }
}
//if no secret found, create and set one
if(empty($secret)) {
    $secret = bin2hex(random_bytes(32));

    $written = 0;
    foreach([$dataIni, $defaultIni] as $ini) {
        if(!file_exists($ini) || !is_writable($ini)) {
            continue;
        }
        $content[] = '';
        $content[] = '';
        $content[] = '; secret for encryption of the user passwords';
        $content[] = '; WHEN YOU CHANGE THAT ALL PASSWORDS WILL BE INVALID!';
        $content[] = 'runtimeOptions.authentication.secret = '.$secret;
        $content[] = '';
        $written = file_put_contents($ini, join("\n", $content), FILE_APPEND);

        $this->output('Added a new password secret to ini '.$ini);
        break; // we write the data only once!
    }

    //if nothing was written, the script must fail!
    if(! $written) {
        throw new ZfExtended_Exception(__FILE__.': write new password hash secret to ini file '.$ini.' FAILED - stop migration script.');
    }
}

$user = ZfExtended_Factory::get(ZfExtended_Models_User::class);
$result = $user->db->fetchAll();
$auth = ZfExtended_Authentication::getInstance();
foreach($result as $rowObject) {
    //null passwords or no md5 hashes are ignored (for reruns)
    if(is_null($rowObject->passwd) || strlen($rowObject->passwd) > 32) {
        continue;
    }
    error_log($rowObject->login.' # '.$secret.' # '.$rowObject->passwd.' # '.$auth->encryptPassword($rowObject->passwd, $secret));
    $user->db->update([
        'passwd' => $auth::COMPAT_PREFIX.$auth->encryptPassword($rowObject->passwd)
    ], ['id = ?' => $rowObject->id]);
}

