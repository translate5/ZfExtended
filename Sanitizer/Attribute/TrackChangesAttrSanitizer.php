<?php

/*
START LICENSE AND COPYRIGHT

 This file is part of ZfExtended library

 Copyright (c) 2013 - 2022 Marc Mittag; MittagQI - Quality Informatics;  All rights reserved.

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
 * Validates track-changes attributes so values stay intact (e.g. timezone offsets) while blocking invalid data.
 */
final class TrackChangesAttrSanitizer implements AttributeSanitizerInterface
{
    private const ATTR_PATTERNS = [
        'data-timestamp' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:[+-]\d{2}:\d{2}|Z)$/',
        'data-usertrackingid' => '/^\d+$/',
        'data-usercssnr' => '/^[\w-]+$/',
        'data-workflowstep' => '/^[\s\w-]+$/',
        'data-userguid' => '/^(?:\{[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\}|[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12})$/i',
        'data-username' => '/^(?!.*\\p{C})[^<>"\']{1,200}$/u',
    ];

    public function getSupportedElements(): ?array
    {
        return ['ins', 'del'];
    }

    public function getSupportedAttributes(): ?array
    {
        return array_keys(self::ATTR_PATTERNS);
    }

    public function sanitizeAttribute(
        string $element,
        string $attribute,
        string $value,
        HtmlSanitizerConfig $config
    ): ?string {
        $pattern = self::ATTR_PATTERNS[$attribute] ?? null;

        if ($pattern === null) {
            return null;
        }

        if (preg_match($pattern, $value) === 1) {
            return $value;
        }

        return null; // strip invalid values
    }
}
