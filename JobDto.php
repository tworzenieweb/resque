<?php
namespace ResqueBundle\Resque;


class JobDto
{
    /** @var string */
    private $jobName;

    /** @var array */
    private $args;

    /** @var  string */
    private $queue;


    /**
     * JobDto constructor.
     * @param $jobName
     * @param $args
     * @param $queue
     */
    public function __construct($jobName, $args, $queue)
    {
        $this->jobName = $jobName;
        $this->args = $args;
        $this->queue = $queue;
    }

    public static function create($jobName, array $args = [], $queue = 'default')
    {
        return new self($jobName, $args, $queue);
    }

    /**
     * @return string
     */
    public function getJobName()
    {
        return $this->jobName;
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->args;
    }

    /**
     * @return string
     */
    public function getQueue()
    {
        return $this->queue;
    }
}