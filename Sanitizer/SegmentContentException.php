<?php

namespace MittagQI\ZfExtended\Sanitizer;

use ZfExtended_ErrorCodeException;

class SegmentContentException extends ZfExtended_ErrorCodeException
{
    protected $domain = 'input';

    protected static $localErrorCodes = [
        'E1764' => 'Invalid elements detected and removed',
    ];
}
