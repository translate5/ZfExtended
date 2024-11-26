<?php

namespace MittagQI\ZfExtended\Worker\Exception;

use ZfExtended_ErrorCodeException;

class EmulatedBlockingException extends ZfExtended_ErrorCodeException
{
    protected $domain = 'worker';

    protected static array $localErrorCodes = [
        'E1640' => 'Worker {worker} is defunct!',
        'E1641' => 'Worker {worker} was queued blocking and timed out!',
    ];
}
