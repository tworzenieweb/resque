<?php

namespace ResqueBundle\Resque\Factory;

use Resque_Worker;
use ResqueBundle\Resque\Worker;

/**
 * Class WorkerFactory
 * @package ResqueBundle\Resque\Factory
 */
class WorkerFactory
{
    /**
     * WorkerFactory constructor.
     * @param JobFactory $jobFactory
     */
    public function __construct(JobFactory $jobFactory)
    {
        $this->jobFactory = $jobFactory;
    }

    /**
     * @param Resque_Worker $worker
     * @return Worker
     */
    public function create(Resque_Worker $worker)
    {
        return new Worker($worker, $this->jobFactory);
    }
}