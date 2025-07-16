<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library

 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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

/* * #@+
 * @author Marc Mittag
 * @package ZfExtended
 * @version 2.0
 *
 */
/*
 * Methoden, um Verzeichnisse aufzuräumen / zu löschen
 *
 *
 */

/**
 * @deprecated FIXME DELETE CLASS ABOUT 09.2025
 */
class ZfExtended_Controller_Helper_Recursivedircleaner extends Zend_Controller_Action_Helper_Abstract
{
    /**
     * @deprecated use ZfExtended_Utils::recursiveDelete instead
     * @see ZfExtended_Utils::recursiveDelete
     */
    public function delete(string $directory)
    {
        throw new \RuntimeException('DEPRECATED USE ZfExtended_Utils::recursiveDelete INSTEAD');
    }
}
