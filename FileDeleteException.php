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

namespace MittagQI\ZfExtended;

/**
 * Backend marker exception to markk a failed file-write
 */
class FileDeleteException extends \Exception
{
    public string $path;

    /**
     * Pass the affected absolute path that cannot be deleted
     */
    public function __construct(string $absolutePath, int $code = 0, ?\Throwable $previous = null)
    {
        $this->path = $absolutePath;
        parent::__construct('Could not delete file “' . $absolutePath . '”', $code, $previous);
    }
}
