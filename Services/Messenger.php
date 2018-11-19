<?php

namespace HBM\AsyncBundle\Services;

use HBM\AsyncBundle\Async\Job\AbstractAsyncJob;
use HBM\AsyncBundle\Async\Job\Interfaces\AsyncJob;
use Psr\Log\LoggerInterface;

class Messenger {

  /****************************************************************************/
  /* SET                                                                      */
  /****************************************************************************/
  protected const SET_JOBS_DELAYED = 'jobs.delayed';

  /****************************************************************************/
  /* HASH                                                                     */
  /****************************************************************************/
  protected const HASH_WORKER_KILLED = 'worker.killed';
  protected const HASH_WORKER_START  = 'worker.start';
  protected const HASH_WORKER_STATUS = 'worker.status';

  protected const HASH_JOBS         = 'jobs';

  protected const HASH_JOBS_FAILED  = 'jobs.failed';
  protected const HASH_JOBS_RUNNING = 'jobs.running';

  /****************************************************************************/
  /* STATUS                                                                   */
  /****************************************************************************/
  public const STATUS_TIMEOUT  = 'timeout';
  public const STATUS_STOPPED  = 'stopped';
  public const STATUS_STARTED  = 'started';
  public const STATUS_IDLE     = 'idle';
  public const STATUS_RUNNING  = 'running';

  /**
   * @var \Redis
   */
  private $redis;

  /**
   * @var array
   */
  private $config;

  /**
   * Messenger constructor.
   *
   * @param array $config
   * @param \Redis $redis
   * @param LoggerInterface $logger
   */
  public function __construct(array $config, \Redis $redis, LoggerInterface $logger) {
    $this->redis = $redis;
    $this->config = $config;

    try {
      $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
    } catch (\Exception $re) {
      $logger->critical('Redis is not available.');
    }
  }

  /**
   * Checks if redis is available.
   *
   * @return bool
   */
  public function isAvailable() : bool {
    try {
      if ($this->redis && ($this->redis->ping() === '+PONG')) {
        return TRUE;
      }
    } catch (\Exception $re) {
    }

    return FALSE;
  }

  /**
   * Pops a job id from a list and returns the corresponding job.
   *
   * @param string $workerId
   * @param string $queue
   * @param int $timeout
   *
   * @return string|null
   */
  public function popJobId(string $workerId, &$queue = NULL, $timeout = 10) : ?string {
    // Check queues for jobs.
    $jobId = NULL;
    try {
      if ($entry = $this->redis->blPop($this->getQueuesForWorker($workerId), $timeout)) {
        // Index 0 of the array holds which queue was returned.
        $queue = $entry[0] ?? NULL;
        // Index 1 of the array holds the job.
        $jobId = $entry[1] ?? NULL;
      }
    } catch (\Exception $re) {
    }

    return $jobId;
  }

  /**
   * Dispatch an async job to the corresponding (delayed) queue.
   *
   * @param AbstractAsyncJob $job
   *
   * @return bool
   */
  public function dispatchJob(AbstractAsyncJob $job) : bool {
    if (!\in_array($job->getPriority(), $this->getPriorities(), TRUE)) {
      throw new \InvalidArgumentException('Priority is invalid. Use one of the following: '.json_encode($this->getPriorities()));
    }

    $this->redis->hSet(self::HASH_JOBS, $job->getId(), $job);

    if ($job->getDelayed()) {
      return $this->delayJob($job, $job->getDelayed()->getTimestamp());
    }

    return $this->enqueueJob($job);
  }

  /**
   * Expedites a delayed job.
   *
   * @param AbstractAsyncJob $job
   *
   * @return bool
   */
  public function expediteJob(AbstractAsyncJob $job) : bool {
    if ($this->redis->zRank(self::SET_JOBS_DELAYED, $job->getId())) {
      return $this->enqueueJob($job);
    }

    return FALSE;
  }

  /**
   * Expedites a delayed job by id.
   *
   * @param string $jobId
   *
   * @return bool
   */
  public function expediteJobById(string $jobId) : bool {
    if ($job = $this->getJob($jobId)) {
      return $this->expediteJob($job);
    }

    return FALSE;
  }

  /**
   * Push the job on an empty spot of the delay list.
   *
   * @param AbstractAsyncJob $job
   * @param int $score
   *
   * @return bool
   */
  protected function delayJob(AbstractAsyncJob $job, int $score) : bool {
    if ($this->redis->zCount(self::SET_JOBS_DELAYED, $score, $score) === 0) {
      return (bool) $this->redis->zAdd(self::SET_JOBS_DELAYED, $score, $job->getId());
    }

    return $this->delayJob($job, $score + 1);
  }

  /**
   * Dispatch an async job to the corresponding (delayed) queue.
   *
   * @param AbstractAsyncJob $job
   *
   * @return bool
   */
  protected function enqueueJob(AbstractAsyncJob $job) : bool {
    // Make sure queued job is not longer in delayed set.
    $this->redis->zRem(self::SET_JOBS_DELAYED, $job->getId());

    return (bool) $this->redis->rPush($job->getQueue(), $job->getId());
  }

  /**
   * Requeue an async job (for example after it has failed).
   *
   * @param AbstractAsyncJob $job
   *
   * @return bool
   */
  public function requeueJob(AbstractAsyncJob $job) : bool {
    $this->resetJob($job);

    return $this->dispatchJob($job);
  }

  /**
   * Requeue a job by id.
   *
   * @param string $jobId
   *
   * @return bool
   */
  public function requeueJobById(string $jobId) : bool {
    if ($job = $this->getJob($jobId)) {
      return $this->requeueJob($job);
    }

    return FALSE;
  }

  /**
   * Enqueue due jobs.
   *
   * @return int
   */
  public function enqueueDelayedJobs() : int {
    $dueJobs = $this->getDueJobs();

    foreach ($dueJobs as $dueJob) {
      $this->enqueueJob($dueJob);
    }

    return \count($dueJobs);
  }

  /**
   * Get job.
   *
   * @param string $jobId
   *
   * @return string|AbstractAsyncJob|NULL
   */
  public function getJob(string $jobId) {
    return $this->redis->hGet(self::HASH_JOBS, $jobId) ?: NULL;
  }

  /**
   * Get all jobs.
   *
   * @return array|AbstractAsyncJob[]
   */
  public function getJobs() : array {
    return $this->redis->hGetAll(self::HASH_JOBS);
  }

  /**
   * Get all running jobs.
   *
   * @return array|AbstractAsyncJob[]
   */
  public function getJobsRunning() : array {
    return $this->getJobsById(array_keys($this->redis->hGetAll(self::HASH_JOBS_RUNNING)));
  }

  /**
   * Count all running jobs.
   *
   * @return int
   */
  public function countJobsRunning() : int {
    return $this->redis->hLen(self::HASH_JOBS_RUNNING);
  }

  /**
   * Get all failed jobs.
   *
   * @return array|AbstractAsyncJob[]
   */
  public function getJobsFailed() : array {
    return $this->getJobsById(array_keys($this->redis->hGetAll(self::HASH_JOBS_FAILED)));
  }

  /**
   * Count all running jobs.
   *
   * @return int
   */
  public function countJobsFailed() : int {
    return $this->redis->hLen(self::HASH_JOBS_FAILED);
  }

  /**
   * Get jobs by id.
   *
   * @param array $jobIds
   *
   * @return array|AbstractAsyncJob
   */
  protected function getJobsById(array $jobIds) : array {
    // May return NULL values.
    if ($jobs = $this->redis->hMGet(self::HASH_JOBS, $jobIds)) {
      return array_diff($jobs, [NULL, FALSE]);
    }

    return [];
  }

  /**
   * Update job.
   *
   * @param AbstractAsyncJob $job
   */
  protected function updateJob(AbstractAsyncJob $job) : void {
    $res = $this->redis->hSet(self::HASH_JOBS, $job->getId(), $job);

    // Not an update. AsyncJob must have been altered in the meantime.
    if ($res === 1) {
      // Delete job to prevent double execution.
      $this->redis->hDel(self::HASH_JOBS, $job->getId());
    }
  }

  /****************************************************************************/
  /* QUEUES                                                                   */
  /****************************************************************************/

  /**
   * Return the names of the priority queues.
   *
   * @return array
   */
  public function getPriorities() : array {
    return $this->config['priorities'];
  }

  /**
   * Return the names of the priority queues.
   *
   * @return array
   */
  public function getWorkerIds() : array {
    return $this->config['worker']['ids'];
  }

  /**
   * Return worker specific queues.
   *
   * @param string $workerId
   *
   * @return array
   */
  public function getQueuesForWorker(string $workerId) : array {
    // Do worker specific jobs first.
    $workerQueues = [];
    foreach ($this->config['priorities'] as $queue) {
      $workerQueues[] = $queue.'.'.$workerId;
    }
    foreach ($this->config['priorities'] as $queue) {
      $workerQueues[] = $queue;
    }

    return $workerQueues;
  }

  /****************************************************************************/
  /* WORKER STATUS                                                            */
  /****************************************************************************/

  /**
   * @param string $workerId
   * @param string $status
   *
   * @return bool|int
   */
  protected function setWorkerStatus(string $workerId, string $status) {
    return $this->redis->hSet(self::HASH_WORKER_STATUS, $workerId, $status);
  }

  public function setWorkerStatusToRunning(string $workerId) {
    return $this->setWorkerStatus($workerId, self::STATUS_RUNNING);
  }

  public function setWorkerStatusToIdle(string $workerId) {
    return $this->setWorkerStatus($workerId, self::STATUS_IDLE);
  }

  public function setWorkerStatusToTimeout(string $workerId) {
    return $this->setWorkerStatus($workerId, self::STATUS_TIMEOUT);
  }

  public function setWorkerStatusToStopped(string $workerId) {
    return $this->setWorkerStatus($workerId, self::STATUS_STOPPED);
  }

  public function setWorkerStatusToStarted(string $workerId) {
    return $this->setWorkerStatus($workerId, self::STATUS_STARTED);
  }

  /**
   * @param string $workerId
   *
   * @return string
   */
  public function getWorkerStatus(string $workerId) : string {
    return $this->redis->hGet(self::HASH_WORKER_STATUS, $workerId);
  }

  /****************************************************************************/
  /* WORKER START                                                             */
  /****************************************************************************/

  /**
   * @param string $workerId
   * @param int $start
   *
   * @return bool|int
   */
  public function setWorkerStart(string $workerId, int $start) {
    return $this->redis->hSet(self::HASH_WORKER_START, $workerId, $start);
  }

  /**
   * @param $workerId
   *
   * @return \DateTime|null
   */
  public function getWorkerStart(string $workerId) : ?\DateTime {
    $start = $this->redis->hGet(self::HASH_WORKER_START, $workerId);

    return $start ? new \DateTime('@'.$start) : NULL;
  }

  /****************************************************************************/
  /* WORKER KILLED                                                            */
  /****************************************************************************/

  /**
   * @param string $workerId
   * @param bool $flag
   */
  public function setWorkerKilled(string $workerId, bool $flag) : void {
    if ($flag === TRUE) {
      $this->redis->hSet(self::HASH_WORKER_KILLED, $workerId, 'yes');
    }
    if ($flag === FALSE) {
      $this->redis->hDel(self::HASH_WORKER_KILLED, $workerId);
    }
  }

  /**
   * @param string $workerId
   *
   * @return bool
   */
  public function isWorkerKilled(string $workerId) : bool {
    return $this->redis->hGet(self::HASH_WORKER_KILLED, $workerId) === 'yes';
  }

  /****************************************************************************/
  /* JOBS QUEUED                                                              */
  /****************************************************************************/

  /**
   * Get queued jobs for priority (and worker).
   *
   * @param string|NULL $priority
   * @param string|NULL $workerId
   *
   * @return array|AbstractAsyncJob[]
   */
  public function getJobsQueued(string $priority, string $workerId = NULL) : array {
    $queue = $priority;
    if ($workerId) {
      $queue .= '.'.$workerId;
    }

    return $this->getJobsById($this->redis->lRange($queue, 0, -1));
  }

  /**
   * Count queued jobs for priority (and worker).
   *
   * @param array|string|NULL $priorities
   * @param array|string|NULL $workerIds
   *
   * @return int
   */
  public function countJobsQueued($priorities = NULL, $workerIds = NULL) : int {
    if ($priorities === NULL) {
      $priorities = $this->getPriorities();
    } elseif (!\is_array($priorities)) {
      $priorities = [$priorities];
    }

    if ($workerIds === NULL) {
      $workerIds = $this->getWorkerIds();
    } elseif (!\is_array($workerIds)) {
      $workerIds = [$workerIds];
    }

    $count = 0;
    foreach ($priorities as $priority) {
      $count += $this->redis->lLen($priority);
      foreach ($workerIds as $workerId) {
        $count += $this->redis->lLen($priority.'.'.$workerId);
      }
    }
    return $count;
  }

  /****************************************************************************/
  /* JOBS DELAYED                                                             */
  /****************************************************************************/

  /**
   * Get due jobs (and optionally remove them in a transaction).
   *
   * @param bool $remove
   *
   * @return array|AbstractAsyncJob[]
   */
  public function getDueJobs($remove = TRUE) : array {
    /** @var \Redis $redis */
    $redis = $this->redis->multi();

    $ts = time();
    $redis->zRangeByScore(self::SET_JOBS_DELAYED, 0, $ts);
    if ($remove) {
      $redis->zRemRangeByScore(self::SET_JOBS_DELAYED, 0, $ts);
    }

    $res = $redis->exec();

    return $this->getJobsById($res[0]);
  }

  /**
   * Get all delayed jobs.
   *
   * @return array|AbstractAsyncJob[]
   */
  public function getJobsDelayed() : array {
    return $this->getJobsById($this->redis->zRange(self::SET_JOBS_DELAYED, 0, -1));
  }

  /**
   * Count all delayed jobs.
   *
   * @return int
   */
  public function countJobsDelayed() : int {
    return $this->redis->zCount(self::SET_JOBS_DELAYED, 0, '+inf');
  }

  /****************************************************************************/
  /* JOBS STATE CHANGES                                                       */
  /****************************************************************************/

  /**
   * @param AbstractAsyncJob $job
   */
  protected function resetJob(AbstractAsyncJob $job) : void {
    $this->redis->hDel(self::HASH_JOBS_RUNNING, $job->getId());
    $this->redis->hDel(self::HASH_JOBS_FAILED, $job->getId());

    $this->redis->lRem($job->getQueue(), $job->getId(), 0);
    $this->redis->zRem(self::SET_JOBS_DELAYED, $job);
  }

  /**
   * @param string $jobId
   */
  public function markJobAsCancelledById(string $jobId) : void {
    if ($job = $this->getJob($jobId)) {
      $this->markJobAsCancelled($job);
    }
  }

  /**
   * @param AbstractAsyncJob $job
   */
  public function markJobAsCancelled(AbstractAsyncJob $job) : void {
    $job->setCancelled(new \DateTime('now'));
    $job->setState(AsyncJob::STATUS_CANCELLED);
    $this->updateJob($job);
  }

  /**
   * @param AbstractAsyncJob $job
   * @param string|NULL $workerId
   */
  public function markJobAsRunning(AbstractAsyncJob $job, string $workerId) : void {
    $job->setStarted(new \DateTime('now'));
    $job->setState(AsyncJob::STATUS_RUNNING);
    if ($workerId) {
      $job->setWorkerExecuting($workerId);
    }
    $this->updateJob($job);

    $this->resetJob($job);
    $this->redis->hSet(self::HASH_JOBS_RUNNING, $job->getId(), TRUE);
  }

  /**
   * @param AbstractAsyncJob $job
   */
  public function markJobAsFailed(AbstractAsyncJob $job) : void {
    $job->setState(AsyncJob::STATUS_FAILED);
    $this->updateJob($job);

    $this->resetJob($job);
    $this->redis->hSet(self::HASH_JOBS_FAILED, $job->getId(), TRUE);
  }

  /**
   * @param AbstractAsyncJob $job
   *
   * @return bool|int
   */
  public function discardJob(AbstractAsyncJob $job) {
    $this->resetJob($job);
    return $this->redis->hDel(self::HASH_JOBS, $job->getId());
  }

  /**
   * @param string $jobId
   *
   * @return bool|int
   */
  public function discardJobById(string $jobId) {
    if ($job = $this->getJob($jobId)) {
      return $this->discardJob($job);
    }

    return FALSE;
  }

}
