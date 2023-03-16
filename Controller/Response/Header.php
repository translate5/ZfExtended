<?php
/*
START LICENSE AND COPYRIGHT

 This file is part of translate5

 Copyright (c) 2013 - 2021 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file agpl3-license.txt
 included in the packaging of this file.  Please review the following information
 to ensure the GNU AFFERO GENERAL PUBLIC LICENSE version 3 requirements will be met:
 http://www.gnu.org/licenses/agpl.html

 There is a plugin exception available for use with this release of translate5 for
 translate5: Please see http://www.translate5.net/plugin-exception.txt or
 plugin-exception.txt in the root folder of translate5.

 @copyright  Marc Mittag, MittagQI - Quality Informatics
 @author     MittagQI - Quality Informatics
 @license    GNU AFFERO GENERAL PUBLIC LICENSE version 3 with plugin-execption
			 http://www.gnu.org/licenses/agpl.html http://www.translate5.net/plugin-exception.txt

END LICENSE AND COPYRIGHT
*/

namespace MittagQI\ZfExtended\Controller\Response;

use MittagQI\ZfExtended\Cors;

/**
 * Simple Utility to create headers for various purposes
 */
class Header
{
    /**
     * Sends the neccessary Headers to download a file
     * @param string|null $fileName
     * @param string|null $contentType
     * @param string|null $cacheControl
     * @param int $contentLength
     * @param array $additionalHeaders: assoc array, $headerName => $headerValue
     */
    public static function sendDownload(string $fileName = null, ?string $contentType = 'text/xml', ?string $cacheControl = 'no-cache', int $contentLength = -1, array $additionalHeaders = [])
    {
        // CORS header
        Cors::sendResponseHeader();
        // base download headers
        if ($fileName !== null) {
            header('Content-Disposition: attachment; filename*=UTF-8\'\'' . $fileName . '; filename=' . $fileName);
        }
        if ($contentType !== null) {
            header('Content-Type: ' . $contentType, true);
        }
        if ($cacheControl !== null) {
            header('Cache-Control: ' . $cacheControl);
        }
        if($contentLength > -1){
            header('Content-Length: '.$contentLength);
        }
        foreach ($additionalHeaders as $name => $value){
            header($name . ': ' . $value);
        }
    }

    /**
     * Sends headers to pseudo-stream a video
     * @param string $extension
     * @param string|null $httpStatus
     * @param string|null $contentRange
     * @param int $contentLength
     */
    public static function pseudoStreamVideo(string $extension, string $httpStatus = null, string $contentRange = null, int $contentLength = -1)
    {
        // CORS header
        Cors::sendResponseHeader();
        if($httpStatus != null){
            header('HTTP/1.1 '.$httpStatus);
        }
        header('Content-type: video/' . $extension);
        // header("Accept-Ranges: 0-$contentLength");
        header('Accept-Ranges: bytes');
        if($contentRange != null){
            header('Content-Range: '.$contentRange);
        }
        if($contentLength > -1){
            header('Content-Length: '.$contentLength);
        }
    }
}