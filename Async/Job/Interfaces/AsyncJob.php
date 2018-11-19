<?php

namespace HBM\AsyncBundle\Async\Job\Interfaces;

interface AsyncJob {

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
