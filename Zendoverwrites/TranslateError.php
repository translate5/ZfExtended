<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5
 
 Copyright (c) 2013 - 2015 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt 
 included in the packaging of this file.  Please review the following information 
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 open source applications that are distributed under a license other than AGPL:
 Please see Open Source License Exception for Development of Plugins for translate5
 http://www.translate5.net/plugin-exception.txt or as plugin-exception.txt in the root
 folder of translate5.
  
 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execptions
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

/**#@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/**
 * We have to fake the translation class for errors occuring before all translation related stuff is initialised completly
 * This class is used automatically if an exception is thrown on using ZfExtended_Zendoverwrites_Translate::getInstance
 */
class  ZfExtended_Zendoverwrites_TranslateError extends ZfExtended_Zendoverwrites_Translate {
    public function __construct(Exception $e) {
        //the logged error here show only why Translate could not be instanced, 
        // it does not show the error which was leading to use the Translation Object in ErrorController
        error_log("Error on instancing ZfExtended_Zendoverwrites_Translate, error was: ".$e->getMessage().' in '.$e->getTraceAsString());
    }

    public function _($s){
        return $s;
    }
}
