<?php

namespace HBM\AsyncWorkerBundle\AsyncWorker\Job;

use HBM\AsyncWorkerBundle\AsyncWorker\Job\Interfaces\Job;

/**
 * Class AbstractJob.
 */
abstract class AbstractJob implements Job {

  /**
   * @var string
   */
  private  $id;

  /**
   * @var string
   */
  private $priority;

  /**
   * @var string
   */
  private $runnerDesired;

  /**
   * @var string
   */
  private $runnerExecuting;

  /**
   * @var \DateTime
   */
  private $created;

  /**
   * @var \DateTime
   */
  private $started;

  /**
   * @var \DateTime
   */
  private $delayed;

  /**
   * @var \DateTime
   */
  private $expires;

  /**
   * @var string
   */
  private $state = JOB::STATE_NEW;

  /**
   * @var string
   */
  private $email;

  /**
   * @var string
   */
  private $message;

  /**
   * @var bool
   */
  private $inform = TRUE;

  /**
   * AbstractJob constructor.
   *
   * @param string $priority
   * @param string|NULL $runnerId
   */
  public function __construct(string $priority, string $runnerId = NULL) {
    $this->id = uniqid('', TRUE);
    try {
      $this->setCreated(new \DateTime('now'));
    } catch (\Exception $e) {
    }
    $this->setPriority($priority);
    $this->setRunnerDesired($runnerId);
  }

  /**
   * Used for array_diff.
   *
   * @return string
   */
  public function __toString() {
    return $this->getId();
  }

  /**
   * Clone async job
   */
  public function __clone() {
    $this->id = uniqid('', TRUE);
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
   * Set priority.
   *
   * @param string $priority
   *
   * @return self
   */
  public function setPriority($priority) : self {
    $this->priority = $priority;

    return $this;
  }

  /**
   * Get priority.
   *
   * @return string
   */
  public function getPriority() : string {
    return $this->priority;
  }

  /**
   * Set desired runner.
   *
   * @param string|NULL $runnerDesired
   *
   * @return self
   */
  public function setRunnerDesired(string $runnerDesired = NULL) : self {
    $this->runnerDesired = $runnerDesired;

    return $this;
  }

  /**
   * Get desired runner.
   *
   * @return string|NULL
   */
  public function getRunnerDesired() : ?string {
    return $this->runnerDesired;
  }

  /**
   * Set executing runner.
   *
   * @param string|NULL $runnerExecuting
   *
   * @return self
   */
  public function setRunnerExecuting(string $runnerExecuting = NULL) : self {
    $this->runnerExecuting = $runnerExecuting;

    return $this;
  }

  /**
   * Get executing runner.
   *
   * @return string|NULL
   */
  public function getRunnerExecuting() : ?string {
    return $this->runnerExecuting;
  }

  /**
   * Set created.
   *
   * @param \DateTime|NULL $created
   *
   * @return self
   */
  public function setCreated(\DateTime $created = NULL) : self {
    $this->created = $created;

    return $this;
  }

  /**
   * Get created.
   *
   * @return \DateTime|NULL
   */
  public function getCreated() : ?\DateTime {
    return $this->created;
  }

  /**
   * Set started.
   *
   * @param \DateTime|NULL $started
   *
   * @return self
   */
  public function setStarted(\DateTime $started = NULL) : self {
    $this->started = $started;

    return $this;
  }

  /**
   * Get started.
   *
   * @return \DateTime|NULL
   */
  public function getStarted() : ?\DateTime {
    return $this->started;
  }

  /**
   * Set delayed.
   *
   * @param \DateTime|NULL $delayed
   *
   * @return self
   */
  public function setDelayed(\DateTime $delayed = NULL) : self {
    $this->delayed = $delayed;

    return $this;
  }

  /**
   * Get delayed.
   *
   * @return \DateTime|NULL
   */
  public function getDelayed() : ?\DateTime {
    return $this->delayed;
  }

  /**
   * Set expires.
   *
   * @param \DateTime|NULL $expires
   *
   * @return self
   */
  public function setExpires(\DateTime $expires = NULL) : self {
    $this->expires = $expires;

    return $this;
  }

  /**
   * Get expires.
   *
   * @return \DateTime|NULL
   */
  public function getExpires() : ?\DateTime {
    return $this->expires;
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
   * Set email.
   *
   * @param string|NULL $email
   *
   * @return self
   */
  public function setEmail(string $email = NULL) : self {
    $this->email = $email;

    return $this;
  }

  /**
   * Get email.
   *
   * @return string|null
   */
  public function getEmail() : ?string {
    return $this->email;
  }

  /**
   * Set message.
   *
   * @param string|NULL $message
   *
   * @return self
   */
  public function setMessage(string $message = NULL) : self {
    $this->message = $message;

    return $this;
  }

  /**
   * Get message.
   *
   * @return string|null
   */
  public function getMessage() : ?string {
    return $this->message;
  }

  /**
   * Set inform.
   *
   * @param bool $inform
   *
   * @return self
   */
  public function setInform(bool $inform) : self {
    $this->inform = $inform;

    return $this;
  }

  /**
   * Get inform.
   *
   * @return bool
   */
  public function getInform() : bool {
    return $this->inform;
  }

  /****************************************************************************/
  /* CUSTOM                                                                   */
  /****************************************************************************/

  /**
   * @inheritdoc
   */
  public function getTemplateFolder(): string {
    return '@HBMAsyncWorker';
  }

}
