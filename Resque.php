<?php

namespace ResqueBundle\Resque;

use Psr\Log\NullLogger;
use ResqueBundle\Resque\Factory\QueueFactory;
use ResqueBundle\Resque\Factory\WorkerFactory;

/**
 * Class Resque
 * @package ResqueBundle\Resque
 */
class Resque implements EnqueueInterface
{
    /**
     * @var array
     */
    private $kernelOptions;

    /**
     * @var array
     */
    private $redisConfiguration;

    /**
     * @var array
     */
    private $globalRetryStrategy = [];

    /**
     * @var array
     */
    private $jobRetryStrategy = [];

    /**
     * @var WorkerFactory
     */
    private $workerFactory;

    /**
     * @var QueueFactory
     */
    private $queueFactory;

    /**
     * Resque constructor.
     * @param array $kernelOptions
     * @param WorkerFactory $workerFactory
     * @param QueueFactory $queueFactory
     */
    public function __construct(array $kernelOptions, WorkerFactory $workerFactory, QueueFactory $queueFactory)
    {
        $this->kernelOptions = $kernelOptions;
        $this->workerFactory = $workerFactory;
        $this->queueFactory = $queueFactory;
    }

    /**
     * @param $prefix
     */
    public function setPrefix($prefix)
    {
        \Resque_Redis::prefix($prefix);
    }

    /**
     * @param $strategy
     */
    public function setGlobalRetryStrategy($strategy)
    {
        $this->globalRetryStrategy = $strategy;
    }

    /**
     * @param $strategy
     */
    public function setJobRetryStrategy($strategy)
    {
        $this->jobRetryStrategy = $strategy;
    }

    /**
     * @return array
     */
    public function getRedisConfiguration()
    {
        return $this->redisConfiguration;
    }

    /**
     * @param $host
     * @param $port
     * @param $database
     */
    public function setRedisConfiguration($host, $port, $database)
    {
        $this->redisConfiguration = [
            'host'     => $host,
            'port'     => $port,
            'database' => $database,
        ];
        $host = substr($host, 0, 1) == '/' ? $host : $host . ':' . $port;

        \Resque::setBackend($host, $database);
    }

    /**
     * @param JobDto $job
     * @param bool $trackStatus
     * @return null|\Resque_Job_Status
     */
    public function enqueueOnce(JobDto $job, $trackStatus = FALSE)
    {
        $queue = $this->queueFactory->create($job->getQueue());
        $jobs = $queue->getJobs();

        foreach ($jobs AS $j) {
            if ($j->job->payload['class'] == get_class($job)) {
                if (count(array_intersect($j->args, $job->getArguments())) == count($job->getArguments())) {
                    return ($trackStatus) ? $j->job->payload['id'] : NULL;
                }
            }
        }

        return $this->enqueue($job, $trackStatus);
    }

    /**
     * @param JobDto $job
     * @param bool $trackStatus
     * @return null|\Resque_Job_Status
     */
    public function enqueue(JobDto $job, $trackStatus = FALSE)
    {
        $this->attachRetryStrategy($job);

        $result = \Resque::enqueue($job->getQueue(), $job->getJobName(), $job->getArguments(), $trackStatus);

        if ($trackStatus && $result !== FALSE) {
            return new \Resque_Job_Status($result);
        }

        return NULL;
    }

    /**
     * Attach any applicable retry strategy to the job.
     *
     * @param JobDto $job
     */
    protected function attachRetryStrategy(JobDto $job)
    {
        $class = $job->getJobName();
        $arguments = $job->getArguments();

        if (isset($this->jobRetryStrategy[$class])) {
            if (count($this->jobRetryStrategy[$class])) {
                $arguments['resque.retry_strategy'] = $this->jobRetryStrategy[$class];
            }
            $arguments['resque.retry_strategy'] = $this->jobRetryStrategy[$class];
        } elseif (count($this->globalRetryStrategy)) {
            $arguments['resque.retry_strategy'] = $this->globalRetryStrategy;
        }
    }

    /**
     * @param $at
     * @param JobDto $job
     * @return null
     */
    public function enqueueAt($at, JobDto $job)
    {
        $this->attachRetryStrategy($job);

        \ResqueScheduler::enqueueAt($at, $job->getQueue(), $job->getJobName(), $job->getArguments());

        return NULL;
    }

    /**
     * @param $in
     * @param JobDto $job
     * @return null
     */
    public function enqueueIn($in, JobDto $job)
    {
        $this->attachRetryStrategy($job);

        \ResqueScheduler::enqueueIn($in, $job->getQueue(), $job->getJobName(), $job->getArguments());

        return NULL;
    }

    /**
     * @param JobDto $job
     * @return mixed
     */
    public function removedDelayed(JobDto $job)
    {
        $this->attachRetryStrategy($job);

        return \ResqueScheduler::removeDelayed($job->getQueue(), $job->getJobName(), $job->getArguments());
    }

    /**
     * @param $at
     * @param JobDto $job
     * @return mixed
     */
    public function removeFromTimestamp($at, JobDto $job)
    {
        $this->attachRetryStrategy($job);

        return \ResqueScheduler::removeDelayedJobFromTimestamp($at, $job->getQueue(), $job->getJobName(), $job->getArguments());
    }

    /**
     * @return array
     */
    public function getQueues()
    {
        return \array_map(function($queue) {
            return $this->queueFactory->create($queue);
        }, \Resque::queues());
    }

    /**
     * @param $queue
     * @return Queue
     */
    public function getQueue($queue)
    {
        return $this->queueFactory->create($queue);
    }

    /**
     * @return Worker[]
     */
    public function getWorkers()
    {
        $workerFactory = $this->workerFactory;
        return \array_map(function($worker) use ($workerFactory) {
            return $workerFactory->create($worker);
        }, \Resque_Worker::all());
    }

    /**
     * @return Worker[]
     */
    public function getRunningWorkers()
    {
        return array_filter($this->getWorkers(), function (Worker $worker) {
            return $worker->getCurrentJob() !== null;
        });
    }

    /**
     * @param $id
     * @return Worker|null
     */
    public function getWorker($id)
    {
        $worker = \Resque_Worker::find($id);

        if (!$worker) {
            return NULL;
        }

        return $this->workerFactory->create($worker);
    }

    /**
     * @return int
     */
    public function getNumberOfWorkers()
    {
        return \Resque::redis()->scard('workers');
    }

    /**
     * @return int
     */
    public function getNumberOfWorkingWorkers()
    {
        $count = 0;
        foreach ($this->getWorkers() as $worker) {
            if ($worker->getCurrentJob() !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @todo - Clean this up, for now, prune dead workers, just in case
     */
    public function pruneDeadWorkers()
    {
        $worker = new \Resque_Worker('temp');
        $worker->setLogger(new NullLogger());
        $worker->pruneDeadWorkers();
    }

    /**
     * @return array|mixed
     */
    public function getFirstDelayedJobTimestamp()
    {
        $timestamps = $this->getDelayedJobTimestamps();
        if (count($timestamps) > 0) {
            return $timestamps[0];
        }

        return [NULL, 0];
    }

    /**
     * @return array
     */
    public function getDelayedJobTimestamps()
    {
        $timestamps = \Resque::redis()->zrange('delayed_queue_schedule', 0, -1);

        //TODO: find a more efficient way to do this
        $out = [];
        foreach ($timestamps as $timestamp) {
            $out[] = [$timestamp, \Resque::redis()->llen('delayed:' . $timestamp)];
        }

        return $out;
    }

    /**
     * @return mixed
     */
    public function getNumberOfDelayedJobs()
    {
        return \ResqueScheduler::getDelayedQueueScheduleSize();
    }

    /**
     * @param $timestamp
     * @return array
     */
    public function getJobsForTimestamp($timestamp)
    {
        $jobs = \Resque::redis()->lrange('delayed:' . $timestamp, 0, -1);
        $out = [];
        foreach ($jobs as $job) {
            $out[] = json_decode($job, TRUE);
        }

        return $out;
    }

    /**
     * @param $queue
     * @return int
     */
    public function clearQueue($queue)
    {
        return $this->getQueue($queue)->clear();
    }

    /**
     * @param int $start
     * @param int $count
     * @return array
     */
    public function getFailedJobs($start = -100, $count = 100)
    {
        $jobs = \Resque::redis()->lrange('failed', $start, $count);

        $result = [];

        foreach ($jobs as $job) {
            $result[] = new FailedJob(json_decode($job, TRUE));
        }

        return $result;
    }

    /**
     * @return int
     */
    public function getNumberOfFailedJobs()
    {
        return \Resque::redis()->llen('failed');
    }

    /**
     * @param bool $clear
     *
     * @return int
     */
    public function retryFailedJobs($clear = false)
    {
        $jobs = \Resque::redis()->lrange('failed', 0, -1);
        if ($clear) {
            $this->clearFailedJobs();
        }
        foreach ($jobs as $job) {
            $failedJob = new FailedJob(json_decode($job, true));
            \Resque::enqueue($failedJob->getQueueName(), $failedJob->getName(), $failedJob->getArgs()[0]);
        }
        return count($jobs);
    }

    /**
     * @return int
     */
    public function clearFailedJobs()
    {
        $length = \Resque::redis()->llen('failed');
        if ($length > 0) {
            \Resque::redis()->del('failed');
        }

        return $length;
    }
}
