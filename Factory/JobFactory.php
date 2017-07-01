<?php

namespace ResqueBundle\Resque\Factory;


use Psr\Container\ContainerInterface;
use Resque_Exception;
use Resque_Job;
use Resque_JobInterface;

class JobFactory
{
    /** @var ContainerInterface */
    private $container;

    /**
     * JobFactory constructor.
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param array $payload
     * @param $queue
     * @return Resque_JobInterface
     * @throws Resque_Exception
     */
    public function create($payload, $queue)
    {
        $resqueJob = new Resque_Job($queue, $payload);
        $className = $payload['class'];

        if (!$this->container->has($className)) {
            throw new Resque_Exception(
                'Could not find job class ' . $className . '.'
            );
        }

        /** @var Resque_JobInterface $job */
        $job = $this->container->get($className);

        if (!method_exists($className, 'perform')) {
            throw new Resque_Exception(
                'Job class ' . $className . ' does not contain a perform method.'
            );
        }

        $job->queue = $queue;
        $job->args = $payload;
        $job->job = $resqueJob;

        return $job;
    }
}