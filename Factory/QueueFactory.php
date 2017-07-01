<?php

namespace ResqueBundle\Resque\Factory;


use ResqueBundle\Resque\Queue;

/**
 * Class QueueFactory
 * @package ResqueBundle\Resque\Factory
 */
class QueueFactory
{
    /**
     * QueueFactory constructor.
     * @param JobFactory $jobFactory
     */
    public function __construct(JobFactory $jobFactory)
    {
        $this->jobFactory = $jobFactory;
    }

    /**
     * @param $name
     * @return Queue
     */
    public function create($name)
    {
        return new Queue($name, $this->jobFactory);
    }
}