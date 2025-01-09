<?php

namespace MittagQI\ZfExtended\Worker\Exception;

use ZfExtended_ErrorCodeException;

class MaxDelaysException extends ZfExtended_ErrorCodeException
{
    protected $domain = 'worker';

    protected static $localErrorCodes = [
        'E1613' => 'The worker "{worker}" was too often delayed because the service "{service}" still malfunctions',
        'E1639' => 'The worker "{worker}" was delayed for too long',
    ];
}
