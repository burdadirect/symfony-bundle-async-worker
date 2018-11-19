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
   * @var array
   */
  protected $returnData = [];

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
   * Set returnData.
   *
   * @param array $returnData
   *
   * @return self
   */
  public function setReturnData(array $returnData) : self {
    $this->returnData = $returnData;

    return $this;
  }

  /**
   * Get returnData.
   *
   * @return array
   */
  public function getReturnData() : array {
    return $this->returnData;
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
   * Set data value.
   *
   * @param $key
   * @param $value
   */
  public function setReturnDataValue($key, $value) : void {
    $this->returnData[$key] = $value;
  }

  /**
   * Execute async job.
   *
   * @param AbstractAsyncJob $job
   *
   * @throws \Exception
   */
  public function execute(AbstractAsyncJob $job) : void {
    $this->setReturnDataValue('job', $job);
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
