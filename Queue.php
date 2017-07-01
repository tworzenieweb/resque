<?php

namespace ResqueBundle\Resque;

use ResqueBundle\Resque\Factory\JobFactory;

/**
 * Class Queue
 * @package ResqueBundle\Resque
 */
class Queue
{
    /**
     * @var string The queue name
     */
    private $name;

    /**
     * @var JobFactory
     */
    private $jobFactory;

    public function __construct($name, JobFactory $jobFactory)
    {
        $this->name = $name;
        $this->jobFactory = $jobFactory;
    }

    /**
     * @return int
     */
    public function getSize()
    {
        return \Resque::size($this->name);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param int $start
     * @param int $stop
     * @return array
     */
    public function getJobs($start = 0, $stop = -1)
    {
        $jobs = \Resque::redis()->lrange('queue:' . $this->name, $start, $stop);

        $result = [];
        foreach ($jobs as $job) {
            $result[] = $this->jobFactory->create(\json_decode($job, TRUE), $this->name);
        }

        return $result;
    }

    public function remove()
    {
        \Resque::redis()->srem('queues', $this->name);
    }

    /**
     * @return int
     */
    public function clear()
    {
        $length = \Resque::redis()->llen('queue:' . $this->name);
        \Resque::redis()->del('queue:' . $this->name);
        return $length;
    }
}
