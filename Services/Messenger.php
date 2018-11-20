<?php

namespace HBM\AsyncWorkerBundle\Services;

use HBM\AsyncWorkerBundle\AsyncWorker\Job\AbstractJob;
use HBM\AsyncWorkerBundle\AsyncWorker\Job\Interfaces\Job;
use HBM\AsyncWorkerBundle\Traits\LoggerTrait;

class Messenger {

  use LoggerTrait;

  /****************************************************************************/
  /* SET                                                                      */
  /****************************************************************************/
  protected const SET_JOBS_DELAYED  = 'jobs.delayed';
  protected const SET_JOBS_EXPIRING = 'jobs.expiring';

  /****************************************************************************/
  /* HASH                                                                     */
  /****************************************************************************/
  protected const HASH_RUNNER_KILLED = 'runner.killed';
  protected const HASH_RUNNER_START  = 'runner.start';
  protected const HASH_RUNNER_STATUS = 'runner.status';

  protected const HASH_JOBS         = 'jobs';

  protected const HASH_JOBS_FAILED  = 'jobs.failed';
  protected const HASH_JOBS_EXPIRED = 'jobs.expired';
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
   * @var array
   */
  private $config;

  /**
   * @var \Redis
   */
  private $redis;

  /**
   * Messenger constructor.
   *
   * @param array $config
   * @param \Redis $redis
   * @param Logger|NULL $logger
   */
  public function __construct(array $config, \Redis $redis, Logger $logger = NULL) {
    $this->config = $config;
    $this->redis = $redis;

    $this->setLogger($logger);

    try {
      $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
    } catch (\Exception $re) {
      $this->outputAndOrLog('Redis is not available.', 'critical');
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
      $this->outputAndOrLog('Redis is not available.', 'critical');
    }

    return FALSE;
  }

  /**
   * Pops a job id from a list and returns the corresponding job.
   *
   * @param string $runnerId
   * @param string $queue
   * @param int $timeout
   *
   * @return string|null
   */
  public function popJobId(string $runnerId, &$queue = NULL, $timeout = 10) : ?string {
    // Check queues for jobs.
    $jobId = NULL;
    try {
      if ($entry = $this->redis->blPop($this->getQueuesForRunner($runnerId), $timeout)) {
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
   * @param AbstractJob $job
   *
   * @return bool
   */
  public function dispatchJob(AbstractJob $job) : bool {
    if (!\in_array($job->getPriority(), $this->getPriorities(), TRUE)) {
      throw new \InvalidArgumentException('Priority is invalid. Use one of the following: '.json_encode($this->getPriorities()));
    }

    $this->redis->hSet(self::HASH_JOBS, $job->getId(), $job);

    if ($job->getExpires()) {
      $this->redis->zAdd(self::SET_JOBS_EXPIRING, $job->getExpires()->getTimestamp(), $job->getId());
    }

    if ($job->getDelayed()) {
      return $this->delayJob($job, $job->getDelayed()->getTimestamp());
    }

    return $this->enqueueJob($job);
  }

  /**
   * Expedites a delayed job.
   *
   * @param AbstractJob $job
   *
   * @return bool
   */
  public function expediteJob(AbstractJob $job) : bool {
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
   * @param AbstractJob $job
   * @param int $score
   *
   * @return bool
   */
  protected function delayJob(AbstractJob $job, int $score) : bool {
    if ($this->redis->zCount(self::SET_JOBS_DELAYED, $score, $score) === 0) {
      return (bool) $this->redis->zAdd(self::SET_JOBS_DELAYED, $score, $job->getId());
    }

    return $this->delayJob($job, $score + 1);
  }

  /**
   * Dispatch an async job to the corresponding (delayed) queue.
   *
   * @param AbstractJob $job
   *
   * @return bool
   */
  protected function enqueueJob(AbstractJob $job) : bool {
    // Make sure queued job is not longer in delayed set.
    $this->redis->zRem(self::SET_JOBS_DELAYED, $job->getId());

    return (bool) $this->redis->rPush($job->getQueue(), $job->getId());
  }

  /**
   * Requeue an async job (for example after it has failed).
   *
   * @param AbstractJob $job
   *
   * @return bool
   */
  public function requeueJob(AbstractJob $job) : bool {
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
   * Enqueue due jobs.
   *
   * @return int
   */
  public function expireJobs() : int {
    $expiredJobs = $this->getExpiredJobs();

    foreach ($expiredJobs as $expiredJob) {
      $this->markJobAsExpired($expiredJob);
    }

    return \count($expiredJobs);
  }

  /**
   * Get job.
   *
   * @param string $jobId
   *
   * @return string|AbstractJob|NULL
   */
  public function getJob(string $jobId) {
    return $this->redis->hGet(self::HASH_JOBS, $jobId) ?: NULL;
  }

  /**
   * Get all jobs.
   *
   * @return array|AbstractJob[]
   */
  public function getJobs() : array {
    return $this->redis->hGetAll(self::HASH_JOBS);
  }

  /**
   * Count all jobs.
   *
   * @return int
   */
  public function countJobs() : int {
    return $this->redis->hLen(self::HASH_JOBS);
  }

  /**
   * Get all running jobs.
   *
   * @return array|AbstractJob[]
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
   * @return array|AbstractJob[]
   */
  public function getJobsFailed() : array {
    return $this->getJobsById(array_keys($this->redis->hGetAll(self::HASH_JOBS_FAILED)));
  }

  /**
   * Count all failed jobs.
   *
   * @return int
   */
  public function countJobsFailed() : int {
    return $this->redis->hLen(self::HASH_JOBS_FAILED);
  }

  /**
   * Get all expired jobs.
   *
   * @return array|AbstractJob[]
   */
  public function getJobsExpired() : array {
    return $this->getJobsById(array_keys($this->redis->hGetAll(self::HASH_JOBS_EXPIRED)));
  }

  /**
   * Count all expired jobs.
   *
   * @return int
   */
  public function countJobsExpired() : int {
    return $this->redis->hLen(self::HASH_JOBS_EXPIRED);
  }

  /**
   * Get jobs by id.
   *
   * @param array $jobIds
   *
   * @return array|AbstractJob
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
   * @param AbstractJob $job
   */
  protected function updateJob(AbstractJob $job) : void {
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
  public function getRunnerIds() : array {
    return $this->config['runner']['ids'];
  }

  /**
   * Return runner specific queues.
   *
   * @param string $runnerId
   *
   * @return array
   */
  public function getQueuesForRunner(string $runnerId) : array {
    // Do runner specific jobs first.
    $runnerQueues = [];
    foreach ($this->config['priorities'] as $queue) {
      $runnerQueues[] = $queue.'.'.$runnerId;
    }
    foreach ($this->config['priorities'] as $queue) {
      $runnerQueues[] = $queue;
    }

    return $runnerQueues;
  }

  /****************************************************************************/
  /* RUNNER STATUS                                                            */
  /****************************************************************************/

  /**
   * @param string $runnerId
   * @param string $status
   *
   * @return bool|int
   */
  protected function setRunnerStatus(string $runnerId, string $status) {
    return $this->redis->hSet(self::HASH_RUNNER_STATUS, $runnerId, $status);
  }

  public function setRunnerStatusToRunning(string $runnerId) {
    return $this->setRunnerStatus($runnerId, self::STATUS_RUNNING);
  }

  public function setRunnerStatusToIdle(string $runnerId) {
    return $this->setRunnerStatus($runnerId, self::STATUS_IDLE);
  }

  public function setRunnerStatusToTimeout(string $runnerId) {
    return $this->setRunnerStatus($runnerId, self::STATUS_TIMEOUT);
  }

  public function setRunnerStatusToStopped(string $runnerId) {
    return $this->setRunnerStatus($runnerId, self::STATUS_STOPPED);
  }

  public function setRunnerStatusToStarted(string $runnerId) {
    return $this->setRunnerStatus($runnerId, self::STATUS_STARTED);
  }

  /**
   * @param string $runnerId
   *
   * @return string
   */
  public function getRunnerStatus(string $runnerId) : string {
    return $this->redis->hGet(self::HASH_RUNNER_STATUS, $runnerId);
  }

  /****************************************************************************/
  /* RUNNER START                                                             */
  /****************************************************************************/

  /**
   * @param string $runnerId
   * @param int $start
   *
   * @return bool|int
   */
  public function setRunnerStart(string $runnerId, int $start) {
    return $this->redis->hSet(self::HASH_RUNNER_START, $runnerId, $start);
  }

  /**
   * @param $runnerId
   *
   * @return \DateTime|null
   */
  public function getRunnerStart(string $runnerId) : ?\DateTime {
    $start = $this->redis->hGet(self::HASH_RUNNER_START, $runnerId);

    return $start ? new \DateTime('@'.$start) : NULL;
  }

  /****************************************************************************/
  /* RUNNER KILLED                                                            */
  /****************************************************************************/

  /**
   * @param string $runnerId
   * @param bool $flag
   */
  public function setRunnerKilled(string $runnerId, bool $flag) : void {
    if ($flag === TRUE) {
      $this->redis->hSet(self::HASH_RUNNER_KILLED, $runnerId, 'yes');
    }
    if ($flag === FALSE) {
      $this->redis->hDel(self::HASH_RUNNER_KILLED, $runnerId);
    }
  }

  /**
   * @param string $runnerId
   *
   * @return bool
   */
  public function isRunnerKilled(string $runnerId) : bool {
    return $this->redis->hGet(self::HASH_RUNNER_KILLED, $runnerId) === 'yes';
  }

  /****************************************************************************/
  /* JOBS QUEUED                                                              */
  /****************************************************************************/

  /**
   * Get queued jobs for priority (and runner).
   *
   * @param string|NULL $priority
   * @param string|NULL $runnerId
   *
   * @return array|AbstractJob[]
   */
  public function getJobsQueued(string $priority, string $runnerId = NULL) : array {
    $queue = $priority;
    if ($runnerId) {
      $queue .= '.'.$runnerId;
    }

    return $this->getJobsById($this->redis->lRange($queue, 0, -1));
  }

  /**
   * Count queued jobs for priority (and runner).
   *
   * @param array|string|NULL $priorities
   * @param array|string|NULL $runnerIds
   *
   * @return int
   */
  public function countJobsQueued($priorities = NULL, $runnerIds = NULL) : int {
    if ($priorities === NULL) {
      $priorities = $this->getPriorities();
    } elseif (!\is_array($priorities)) {
      $priorities = [$priorities];
    }

    if ($runnerIds === NULL) {
      $runnerIds = $this->getRunnerIds();
    } elseif (!\is_array($runnerIds)) {
      $runnerIds = [$runnerIds];
    }

    $count = 0;
    foreach ($priorities as $priority) {
      $count += $this->redis->lLen($priority);
      foreach ($runnerIds as $runnerId) {
        $count += $this->redis->lLen($priority.'.'.$runnerId);
      }
    }
    return $count;
  }

  /****************************************************************************/
  /* JOBS EXPIRING                                                            */
  /****************************************************************************/

  /**
   * Get expired jobs (and optionally remove them in a transaction).
   *
   * @param bool $remove
   *
   * @return array|AbstractJob[]
   */
  public function getExpiredJobs($remove = TRUE) : array {
    /** @var \Redis $redis */
    $redis = $this->redis->multi();

    $ts = time();
    $redis->zRangeByScore(self::SET_JOBS_EXPIRING, 0, $ts);
    if ($remove) {
      $redis->zRemRangeByScore(self::SET_JOBS_EXPIRING, 0, $ts);
    }

    $res = $redis->exec();

    return $this->getJobsById($res[0]);
  }

  /**
   * Get all expiring jobs.
   *
   * @return array|AbstractJob[]
   */
  public function getJobsExpiring() : array {
    return $this->getJobsById($this->redis->zRange(self::SET_JOBS_EXPIRING, 0, -1));
  }

  /**
   * Count all expiring jobs.
   *
   * @return int
   */
  public function countJobsExpiring() : int {
    return $this->redis->zCount(self::SET_JOBS_EXPIRING, 0, '+inf');
  }

  /****************************************************************************/
  /* JOBS DELAYED                                                             */
  /****************************************************************************/

  /**
   * Get due jobs (and optionally remove them in a transaction).
   *
   * @param bool $remove
   *
   * @return array|AbstractJob[]
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
   * @return array|AbstractJob[]
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
   * @param AbstractJob $job
   */
  protected function resetJob(AbstractJob $job) : void {
    $this->redis->hDel(self::HASH_JOBS_RUNNING, $job->getId());
    $this->redis->hDel(self::HASH_JOBS_FAILED, $job->getId());
    $this->redis->hDel(self::HASH_JOBS_EXPIRED, $job->getId());

    $this->redis->lRem($job->getQueue(), $job->getId(), 0);
    $this->redis->zRem(self::SET_JOBS_DELAYED, $job);
    $this->redis->zRem(self::SET_JOBS_EXPIRING, $job);
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
   * @param AbstractJob $job
   */
  public function markJobAsCancelled(AbstractJob $job) : void {
    $job->setCancelled(new \DateTime('now'));
    $job->setState(Job::STATUS_CANCELLED);
    $this->updateJob($job);
  }

  /**
   * @param AbstractJob $job
   * @param string|NULL $runnerId
   */
  public function markJobAsRunning(AbstractJob $job, string $runnerId) : void {
    $job->setStarted(new \DateTime('now'));
    $job->setState(Job::STATUS_RUNNING);
    if ($runnerId) {
      $job->setRunnerExecuting($runnerId);
    }
    $this->updateJob($job);

    $this->resetJob($job);
    $this->redis->hSet(self::HASH_JOBS_RUNNING, $job->getId(), TRUE);
  }

  /**
   * @param AbstractJob $job
   */
  public function markJobAsFailed(AbstractJob $job) : void {
    $job->setState(Job::STATUS_FAILED);
    $this->updateJob($job);

    $this->resetJob($job);
    $this->redis->hSet(self::HASH_JOBS_FAILED, $job->getId(), TRUE);
  }

  /**
   * @param AbstractJob $job
   */
  public function markJobAsExpired(AbstractJob $job) : void {
    $job->setState(Job::STATUS_EXPIRED);
    $this->updateJob($job);

    $this->resetJob($job);
    $this->redis->hSet(self::HASH_JOBS_EXPIRED, $job->getId(), TRUE);
  }

  /**
   * @param AbstractJob $job
   *
   * @return bool|int
   */
  public function discardJob(AbstractJob $job) {
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
