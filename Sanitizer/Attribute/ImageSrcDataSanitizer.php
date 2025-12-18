<?php

/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library

 Copyright (c) 2013 - 2024 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

 Contact:  http://www.MittagQI.com/  /  service (ATT) MittagQI.com

 This file may be used under the terms of the GNU LESSER GENERAL PUBLIC LICENSE version 3
 as published by the Free Software Foundation and appearing in the file lgpl3-license.txt
 included in the packaging of this file.  Please review the following information
 to ensure the GNU LESSER GENERAL PUBLIC LICENSE version 3.0 requirements will be met:
https://www.gnu.org/licenses/lgpl-3.0.txt

@copyright  Marc Mittag, MittagQI - Quality Informatics
@author     Marc Mittag, MittagQI - Quality Informatics
@license    GNU LESSER GENERAL PUBLIC LICENSE version 3
            https://www.gnu.org/licenses/lgpl-3.0.txt

END LICENSE AND COPYRIGHT
*/

declare(strict_types=1);

namespace MittagQI\ZfExtended\Sanitizer\Attribute;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Allows only data:image/* base64 URIs on img src, rejects everything else (e.g. data:text/html payloads).
 */
final class ImageSrcDataSanitizer implements AttributeSanitizerInterface
{
    private const string SAFE_DATA_URI_PATTERN = '#^data:image/[a-z0-9.+-]+;base64,[a-z0-9+/]+=*$#i';

    private const int MAX_SRC_LENGTH = 4096;

    public function getSupportedElements(): ?array
    {
        return ['img'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['src'];
    }

    public function sanitizeAttribute(
        string $element,
        string $attribute,
        string $value,
        HtmlSanitizerConfig $config
    ): ?string {
        $lower = strtolower($value);

        // Drop obvious payload attempts embedded into the URL
        if (str_contains($value, '<') || str_contains($value, '>')) {
            return null;
        }

        if (str_starts_with($lower, 'data:')) {
            return preg_match(self::SAFE_DATA_URI_PATTERN, $value) === 1 ? $value : null;
        }

        if (strlen($value) > self::MAX_SRC_LENGTH) {
            return null;
        }

        $decoded = rawurldecode($value);
        $decodedLower = strtolower($decoded);

        // block encoded tags / javascript pseudo-protocols after decoding
        if (str_contains($decoded, '<')
            || str_contains($decoded, '>')
            || str_starts_with($decodedLower, 'javascript:')) {
            return null;
        }

        // block svg uploads and traversals
        if (preg_match('~.svg(?:$|[?#])~i', $decoded)) {
            return null;
        }
        if (str_contains($decoded, '/../')) {
            return null;
        }

        return $value;
    }
}
