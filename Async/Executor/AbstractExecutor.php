<?php

namespace HBM\AsyncBundle\Async\Executor;

use HBM\AsyncBundle\Async\Job\AbstractAsyncJob;
use Symfony\Component\Console\Application;

/**
 * Class AbstractExecutor.
 */
abstract class AbstractExecutor  {

  /**
   * @var Application
   */
  protected $application;

  /**
   * @var array
   */
  protected $config;

  /**
   * @var int
   */
  protected $returnCode;

  /**
   * AbstractExecutor constructor.
   *
   * @param Application $application
   * @param array $config
   */
  public function __construct(Application $application, array $config) {
    $this->application = $application;
    $this->config = $config;
  }

  /**
   * Set returnCode.
   *
   * @param int $returnCode
   *
   * @return self
   */
  public function setReturnCode($returnCode) : self {
    $this->returnCode = $returnCode;

    return $this;
  }

  /**
   * Get returnCode.
   *
   * @return int|null
   */
  public function getReturnCode() : ?int {
    return $this->returnCode;
  }

  /****************************************************************************/
  /* CUSTOM                                                                   */
  /****************************************************************************/

  /**
   * Execute async job.
   *
   * @param AbstractAsyncJob $job
   *
   * @throws \Exception
   */
  public function execute(AbstractAsyncJob $job) : void {
    $this->executeInternal($job);
  }

  /****************************************************************************/
  /* ABSTRACT                                                                 */
  /****************************************************************************/

  /**
   * Execute a async job. Populate data with job specific information.
   *
   * @param AbstractAsyncJob $job
   *
   * @throws \Exception
   */
  abstract protected function executeInternal(AbstractAsyncJob $job) : void;

}
