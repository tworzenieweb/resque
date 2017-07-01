<?php

namespace ResqueBundle\Resque;

/**
 * Interface EnqueueInterface
 */
interface EnqueueInterface
{
    /**
     * @param JobDto $job
     * @param bool $trackStatus
     * @return mixed
     */
    public function enqueue(JobDto $job, $trackStatus = FALSE);

    /**
     * @param JobDto $job
     * @param bool $trackStatus
     * @return mixed
     */
    public function enqueueOnce(JobDto $job, $trackStatus = FALSE);

    /**
     * @param $at
     * @param JobDto $job
     * @return mixed
     */
    public function enqueueAt($at, JobDto $job);

    /**
     * @param $in
     * @param JobDto $job
     * @return mixed
     */
    public function enqueueIn($in, JobDto $job);
}