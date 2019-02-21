<?php

namespace HBM\AsyncWorkerBundle\AsyncWorker\Job\Interfaces;

interface Job {

  public const STATE_NEW     = 'new';
  public const STATE_MANUAL  = 'manual';
  public const STATE_RUNNING = 'running';
  public const STATE_FAILED  = 'failed';
  public const STATE_EXPIRED = 'expired';
  public const STATE_PARKED  = 'parked';

  /**
   * Return the class of the executor.
   *
   * @return string
   */
  public function getExecutorClass() : string;

  /**
   * Return a string identifying this job. Used for example in the subject of
   * the informer mail.
   *
   * @return string
   */
  public function getIdentifier() : string;

  /**
   * Returns the name of the template folder.
   *
   * @return string
   */
  public function getTemplateFolder() : string;

}
