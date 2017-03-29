<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library
 
 Copyright (c) 2013 - 2017 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file lgpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
https://www.gnu.org/licenses/lgpl-3.0.txt

 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU LESSER GENERAL PUBLIC LICENSE version 3
			 https://www.gnu.org/licenses/lgpl-3.0.txt

END LICENSE AND COPYRIGHT
*/

/**#@+ 
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 * 
 */
/**
 * handles invalid logins
 *
 */
class ZfExtended_Models_Invalidlogin extends ZfExtended_Models_Db_Invalidlogin {
    /**
     * @var string 
     */
    protected $login;

    /**
     * Invalidlogin wird mit dem betroffenen Login / E-Mail-Adresse initialisiert
     * @param string $login
     */
    public function  __construct(string $login, $maximum = 3) {
        $this->login = $login;
        
        $config = Zend_Registry::get('config');
        if($maximum == 3 && isset($config->runtimeOptions->invalidLogin->maximum)){
            $maximum = $config->runtimeOptions->invalidLogin->maximum;
        }
        $this->maximum = $maximum;
        
        parent::__construct();
        
        $this->delete("created < '".date('Y-m-d h:i:s',time()-24*3600)."'");
    }

    /**
     * erhöht den Invalid Login Counter eines Logins / einer login um eins, gibt $this zurück
     * @return void
     */
    public function increment(){
        $this->insert(
            array(
                'login' => $this->login,
            )
        );
    }

    /**
     * setzt den Invalid Login Counter eines Logins / einer login zurück, gibt $this zurück
     * @return void
     */
    public function resetCounter() {
        $where = $this->getAdapter()->quoteInto('login = ?', $this->login);
        $this->delete($where);
    }

    /**
     * gibt zurück ob eine Login / eine login-Adresse die maximale Anzahl an falschen Logins erreicht hat.
     * @return boolean
     */
    public function hasMaximumInvalidations() {
        $row = $this->fetchRow($this->select(array('invalidlogins' => 'COUNT(*)'))
                ->reset( Zend_Db_Select::COLUMNS )
                ->columns(array('invalidlogins' => 'COUNT(*)'))
                ->where('login = ? and created > (NOW() - INTERVAL 1 DAY)', $this->login));

        return ($row['invalidlogins'] >= $this->maximum);
    }
}