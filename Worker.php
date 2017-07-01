<?php

namespace ResqueBundle\Resque;

use Resque_Worker;
use ResqueBundle\Resque\Factory\JobFactory;

/**
 * Class Worker
 * @package ResqueBundle\Resque
 */
class Worker
{
    /**
     * @var Resque_Worker
     */
    protected $worker;

    /**
     * @var JobFactory
     */
    private $jobFactory;

    /**
     * Worker constructor.
     * @param Resque_Worker $worker
     * @param JobFactory $jobFactory
     */
    public function __construct(Resque_Worker $worker, JobFactory $jobFactory)
    {
        $this->worker = $worker;
        $this->jobFactory = $jobFactory;
    }

    /**
     * @return bool
     */
    public function stop()
    {
        $parts = \explode(':', $this->getId());

        return \posix_kill($parts[1], 3);
    }

    /**
     * @return string
     */
    public function getId()
    {
        return (string)$this->worker;
    }

    /**
     * @return array
     */
    public function getQueues()
    {
        return \array_map(function($queue) {
            return new Queue($queue);
        }, $this->worker->queues());
    }

    /**
     * @return integer
     */
    public function getProcessedCount()
    {
        return $this->worker->getStat('processed');
    }

    /**
     * @return integer
     */
    public function getFailedCount()
    {
        return $this->worker->getStat('failed');
    }

    /**
     * @return \DateTime|null
     */
    public function getCurrentJobStart()
    {
        $job = $this->worker->job();

        if (!$job) {
            return NULL;
        }

        return new \DateTime($job['run_at']);
    }

    /**
     * @return null
     */
    public function getCurrentJob()
    {
        $job = $this->worker->job();

        if (!$job) {
            return NULL;
        }

        return $this->jobFactory->create($job['queue'], $job['payload']);
    }

    /**
     * @return Resque_Worker
     */
    public function getWorker()
    {
        return $this->worker;
    }
}
