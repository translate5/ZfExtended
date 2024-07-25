<?php

namespace MittagQI\ZfExtended\Worker\Exception;

use Exception;

/**
 * A marker-exception that will trigger setting a worker to delayed
 */
final class SetDelayedException extends Exception
{
    public function __construct(
        private string $serviceId,
        private ?string $workerName = null
    ) {
        parent::__construct('Set worker/service delayed: ' . ($workerName ?? $serviceId));
    }

    public function getServiceId(): string
    {
        return $this->serviceId;
    }

    public function getWorkerName(): ?string
    {
        return $this->workerName;
    }
}
