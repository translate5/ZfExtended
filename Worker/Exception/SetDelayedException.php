<?php

namespace MittagQI\ZfExtended\Worker\Exception;

use Exception;

/**
 * A marker-exception that will trigger setting a worker to delayed
 * A worker can be delayed in two ways:
 *
 * a) A (service)-worker will be delayed hoping when the service is unavailable hoping the service will be online
 * again after a while. This delay will increase with each attempt and end after maxDelays with an exception
 *
 * b) A segment-processor/looper waiting for locked segments to become available again. This delay has a fixed length
 * and will result in an exception after the overall max delay-time is reached
 */
final class SetDelayedException extends Exception
{
    /**
     * @param string $serviceId Must be given, the Service-ID of the causing service being unavailable
     * @param string|null $workerName Optional, defaults to the worker-classname
     * @param int $singleDelay  Optional, if given, the worker is only delayed once with the given time in seconds.
     *                          Normally, a worker waits multiple times with the delay as configured in the worker-class
     */
    public function __construct(
        private string $serviceId,
        private ?string $workerName = null,
        private int $singleDelay = -1
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

    public function getSingleDelay(): int
    {
        return $this->singleDelay;
    }
}
