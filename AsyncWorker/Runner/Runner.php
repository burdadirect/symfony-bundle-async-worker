<?php

namespace HBM\AsyncWorkerBundle\AsyncWorker\Runner;

/**
 * Class Runner.
 */
class Runner {

  /****************************************************************************/
  /* STATES                                                                   */
  /****************************************************************************/

  public const STATE_NEW       = 'new';
  public const STATE_LISTENING = 'listening';
  public const STATE_STOPPED   = 'stopped';
  public const STATE_TIMEOUT   = 'timeout';

  /****************************************************************************/
  /* COUNTER                                                                  */
  /****************************************************************************/

  public const COUNTER_JOBS        = 'jobs';
  public const COUNTER_STARTS      = 'starts';
  public const COUNTER_STOPS       = 'stops';
  public const COUNTER_TIMEOUTS    = 'timeouts';
  public const COUNTER_SHUTDOWNS   = 'shutdowns';
  public const COUNTER_AUTORECOVER = 'autorecover';

  /****************************************************************************/
  /* GENERAL                                                                  */
  /****************************************************************************/

  /**
   * @var string
   */
  private  $id;

  /**
   * @var string
   */
  private $state = self::STATE_NEW;

  /****************************************************************************/
  /* LONG TERM                                                                */
  /****************************************************************************/

  /**
   * @var \DateTime
   */
  private $created;

  /**
   * @var array
   */
  private $counter = [];

  /****************************************************************************/
  /* RUN SPECIFIC                                                             */
  /****************************************************************************/

  /**
   * @var string
   */
  private $runPid;

  /**
   * @var \DateTime
   */
  private $runStarted;

  /**
   * @var \DateTime
   */
  private $runShutdown;

  /**
   * @var \DateTime
   */
  private $runTimeout;

  /**
   * @var array
   */
  private $runJobIds = [];

  /**
   * Runner constructor.
   *
   * @param $id
   */
  public function __construct($id) {
    $this->id = $id;
    $this->created = new \DateTime();
  }

  /**
   * Get id.
   *
   * @return string
   */
  public function getId() : string {
    return $this->id;
  }

  /**
   * Set state.
   *
   * @param string $state
   *
   * @return self
   */
  public function setState(string $state) : self {
    $this->state = $state;

    return $this;
  }

  /**
   * Get state.
   *
   * @return string|null
   */
  public function getState() : ?string {
    return $this->state;
  }

  /**
   * Set created.
   *
   * @param \DateTime $created
   *
   * @return self
   */
  public function setCreated(\DateTime $created) : self {
    $this->created = $created;

    return $this;
  }

  /**
   * Get created.
   *
   * @return \DateTime
   */
  public function getCreated() : \DateTime {
    return $this->created;
  }

  /**
   * Set counter.
   *
   * @param array $counter
   *
   * @return self
   */
  public function setCounter(array $counter) : self {
    $this->counter = $counter;

    return $this;
  }

  /**
   * Get counter.
   *
   * @return array
   */
  public function getCounter() : array {
    return $this->counter;
  }

  /**
   * Set runPid.
   *
   * @param string $runPid
   *
   * @return self
   */
  public function setRunPid($runPid) : self {
    $this->runPid = $runPid;

    return $this;
  }

  /**
   * Get runPid.
   *
   * @return string|null
   */
  public function getRunPid() : ?string {
    return $this->runPid;
  }

  /**
   * Set runStarted.
   *
   * @param \DateTime|NULL $runStarted
   *
   * @return self
   */
  public function setRunStarted(\DateTime $runStarted = NULL) : self {
    $this->runStarted = $runStarted;

    return $this;
  }

  /**
   * Get runStarted.
   *
   * @return \DateTime|NULL
   */
  public function getRunStarted() : ?\DateTime {
    return $this->runStarted;
  }

  /**
   * Set runShutdown.
   *
   * @param \DateTime|NULL $runShutdown
   *
   * @return self
   */
  public function setRunShutdown(\DateTime $runShutdown = NULL) : self {
    $this->runShutdown = $runShutdown;

    return $this;
  }

  /**
   * Get runShutdown.
   *
   * @return \DateTime|null
   */
  public function getRunShutdown() : ?\DateTime {
    return $this->runShutdown;
  }

  /**
   * Set runTimeout.
   *
   * @param \DateTime|NULL $runTimeout
   *
   * @return self
   */
  public function setRunTimeout(\DateTime $runTimeout = NULL) : self {
    $this->runTimeout = $runTimeout;

    return $this;
  }

  /**
   * Get runTimeout.
   *
   * @return \DateTime|null
   */
  public function getRunTimeout() : ?\DateTime {
    return $this->runTimeout;
  }


  /**
   * Set runJobIds.
   *
   * @param array $runJobIds
   *
   * @return self
   */
  public function setRunJobIds(array $runJobIds) : self {
    $this->runJobIds = $runJobIds;

    return $this;
  }

  /**
   * Get runJobIds.
   *
   * @return array
   */
  public function getRunJobIds() : array {
    return $this->runJobIds;
  }

  /****************************************************************************/
  /* CUSTOM                                                                   */
  /****************************************************************************/

  /**
   * Increment run counter.
   *
   * @param string $counter
   * @param int $increment
   *
   * @return self
   */
  public function incr(string $counter, int $increment = 1) : self {
    $current = $this->counter[$counter] ?? 0;

    $this->counter[$counter] = $current + $increment;

    return $this;
  }

  /**
   * @return Runner
   */
  public function incrAutorecover() : self {
    return $this->incr(self::COUNTER_AUTORECOVER);
  }

  /**
   * @return int
   */
  public function numAutorecover() : int {
    return $this->counter[self::COUNTER_AUTORECOVER] ?? 0;
  }

  /**
   * @return Runner
   */
  public function incrJobs() : self {
    return $this->incr(self::COUNTER_JOBS);
  }

  /**
   * @return int
   */
  public function numJobs() : int {
    return $this->counter[self::COUNTER_JOBS] ?? 0;
  }

  /**
   * @return Runner
   */
  public function incrStarts() : self {
    return $this->incr(self::COUNTER_STARTS);
  }

  /**
   * @return int
   */
  public function numStarts() : int {
    return $this->counter[self::COUNTER_STARTS] ?? 0;
  }

  /**
   * @return Runner
   */
  public function incrStops() : self {
    return $this->incr(self::COUNTER_STOPS);
  }

  /**
   * @return int
   */
  public function numStops() : int {
    return $this->counter[self::COUNTER_STOPS] ?? 0;
  }

  /**
   * @return Runner
   */
  public function incrShutdowns() : self {
    return $this->incr(self::COUNTER_SHUTDOWNS);
  }

  /**
   * @return int
   */
  public function numShutdowns() : int {
    return $this->counter[self::COUNTER_SHUTDOWNS] ?? 0;
  }

  /**
   * @return Runner
   */
  public function incrTimeouts() : self {
    return $this->incr(self::COUNTER_TIMEOUTS);
  }

  /**
   * @return int
   */
  public function numTimeouts() : int {
    return $this->counter[self::COUNTER_TIMEOUTS] ?? 0;
  }

  /****************************************************************************/

  /**
   * Add job id.
   *
   * @param string $jobId
   *
   * @return self
   */
  public function addJobId(string $jobId) : self {
    $this->runJobIds[$jobId] = new \DateTime();

    return $this;
  }

  /**
   * Remove job id.
   *
   * @param string $jobId
   *
   * @return self
   */
  public function removeJobId(string $jobId) : self {
    unset($this->runJobIds[$jobId]);

    return $this;
  }

  /****************************************************************************/

  /**
   * Checks if runner is listening.
   *
   * @return bool
   */
  public function isListening() : bool {
    return $this->getState() === self::STATE_LISTENING;
  }

  /**
   * Checks if runner is currently working on jobs.
   *
   * @return bool
   */
  public function isBusy() : bool {
    return \count($this->getRunJobIds()) > 0;
  }

  /**
   * Checks if runner is timed out.
   *
   * @return bool
   */
  public function isTimedOut() : bool {
    return ($timeout = $this->getRunTimeout()) && (time() > $timeout->getTimestamp());
  }

  /****************************************************************************/

  /**
   * Send shutdown signal to runner.
   *
   * @return self
   */
  public function sendShutdownSignal() : self {
    $this->setRunShutdown(new \DateTime());

    return $this;
  }

  /**
   * Reset counter.
   *
   * @return $this
   */
  public function resetCounter() : self {
    $this->setCounter([]);

    return $this;
  }

  /**
   * Reset run variables.
   *
   * @return $this
   */
  public function reset() : self {
    // Set run specific variables.
    $this->setRunPid(NULL);
    $this->setRunStarted(NULL);
    $this->setRunTimeout(NULL);
    $this->setRunShutdown(NULL);
    $this->setRunJobIds([]);

    // Set general variables.
    $this->setState(self::STATE_STOPPED);

    return $this;
  }

  /**
   * Explain runner.
   *
   * @param bool $detailed
   * @param bool $counter
   *
   * @return array
   */
  public function info(bool $detailed = FALSE, bool $counter = FALSE) : array {
    $data = [
      'id' => $this->getId(),
      'created' => $this->getCreated(),
      'state' => $this->getState(),
    ];

    if ($detailed) {
      $data['run'] = [
        'pid' => $this->getRunPid(),
        'jobs' => $this->getRunJobIds(),
        'started' => $this->getRunStarted(),
        'shutdown' => $this->getRunShutdown(),
        'timeout' => $this->getRunTimeout(),
      ];
    }

    if ($counter) {
      $data['counter'] = $this->getCounter();
    }

    return $data;
  }

}
