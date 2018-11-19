<?php

namespace HBM\AsyncBundle\Async\Job;


use HBM\AsyncBundle\Async\Job\Interfaces\AsyncJob;

/**
 * Class AbstractAsyncJob.
 */
abstract class AbstractAsyncJob implements AsyncJob {

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
  private $workerDesired;

  /**
   * @var string
   */
  private $workerExecuting;

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
  private $cancelled;

  /**
   * @var \DateTime
   */
  private $delayed;

  /**
   * @var string
   */
  private $state;

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
   * @var array
   */
  protected $data = [];

  /**
   * AbstractAsyncJob constructor.
   *
   * @param string $priority
   * @param string $workerId
   */
  public function __construct(string $priority, string $workerId = NULL) {
    $this->id = uniqid('', TRUE);
    $this->setCreated(new \DateTime('now'));
    $this->setPriority($priority);
    $this->setWorkerDesired($workerId);
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
   * Set desired worker.
   *
   * @param string|NULL $workerDesired
   *
   * @return self
   */
  public function setWorkerDesired(string $workerDesired = NULL) : self {
    $this->workerDesired = $workerDesired;

    return $this;
  }

  /**
   * Get desired worker.
   *
   * @return string|NULL
   */
  public function getWorkerDesired() : ?string {
    return $this->workerDesired;
  }

  /**
   * Set executing worker.
   *
   * @param string|NULL $workerExecuting
   *
   * @return self
   */
  public function setWorkerExecuting(string $workerExecuting = NULL) : self {
    $this->workerExecuting = $workerExecuting;

    return $this;
  }

  /**
   * Get executing worker.
   *
   * @return string|NULL
   */
  public function getWorkerExecuting() : ?string {
    return $this->workerExecuting;
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
   * Set cancelled.
   *
   * @param \DateTime|NULL $cancelled
   *
   * @return self
   */
  public function setCancelled(\DateTime $cancelled = NULL) : self {
    $this->cancelled = $cancelled;

    return $this;
  }

  /**
   * Get cancelled.
   *
   * @return \DateTime|NULL
   */
  public function getCancelled() : ?\DateTime {
    return $this->cancelled;
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

  /**
   * Set data.
   *
   * @param array $data
   *
   * @return self
   */
  public function setData(array $data) : self {
    $this->data = $data;

    return $this;
  }

  /**
   * Get data.
   *
   * @return array
   */
  public function getData() : array {
    $this->data['job'] = $this;

    return $this->data;
  }

  /****************************************************************************/
  /* CUSTOM                                                                   */
  /****************************************************************************/

  /**
   * Set data value.
   *
   * @param $key
   * @param $value
   */
  public function setDataValue($key, $value) : void {
    $this->data[$key] = $value;
  }

  /**
   * Get queue for job.
   *
   * @return string
   */
  public function getQueue() : string {
    if ($this->getWorkerDesired()) {
      return $this->getPriority().'.'.$this->getWorkerDesired();
    }

    return $this->getPriority();
  }

  /**
   * @inheritdoc
   */
  public function getTemplateFolder(): string {
    return 'HBMAsyncBundle:';
  }

}
