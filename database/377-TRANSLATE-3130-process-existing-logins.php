<?php
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

set_time_limit(0);

//uncomment the following line, so that the file is not marked as processed:
//$this->doNotSavePhpForDebugging = false;

//should be not __FILE__ in the case of wanted restarts / renamings etc
// and must not be a constant since in installation the same named constant would we defined multiple times then
$SCRIPT_IDENTIFIER = '377-TRANSLATE-3130-process-existing-logins.php';

$argc = count($argv);
if (empty($this) || empty($argv) || $argc < 5 || $argc > 7) {
    die("please dont call the script direct! Call it by using DBUpdater!\n\n");
}

// no need to run this in tests or installs
if ($this->isTestOrInstallEnvironment()) { // @phpstan-ignore-line
    return;
}

$db = Zend_Db_Table::getDefaultAdapter();

$users = $db->query("SELECT `id`, `login`, `email` FROM `Zf_users` WHERE `login` LIKE '% %'")->fetchAll();

$userModel = ZfExtended_Factory::get(ZfExtended_Models_User::class);

if (count($users) === 0) {
    $this->output('No users to process.');
} else {
    foreach ($users as $userData) {
        $newLogin = trim($userData['login']);
        $newLogin = str_replace(' ', '_', $newLogin);

        $db->query("UPDATE `Zf_users` SET `login` = '{$newLogin}' WHERE `id` = {$userData['id']}");

        $userModel->load($userData['id']);
        $mailer = new \ZfExtended_TemplateBasedMail();
        $mailer->setTemplate('loginChanged.phtml');
        $mailer->setParameters([
            'newLogin' => $newLogin,
        ]);
        $mailer->sendToUser($userModel);
    }

    $this->output(sprintf('Processed %d users.', count($users)));
}
