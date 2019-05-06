<?php

namespace HBM\AsyncWorkerBundle\AsyncWorker\Job;

use HBM\AsyncWorkerBundle\AsyncWorker\Executor\CommandExecutor;

/**
 * Class AsyncCommand.
 */
class Command extends AbstractJob {

  /**
   * @var string
   */
  private $command;

  /**
   * @var array
   */
  private $arguments;

  /**
   * Set command.
   *
   * @param string $command
   *
   * @return self
   */
  public function setCommand(string$command) : self {
    $this->command = $command;

    return $this;
  }

  /**
   * Get command.
   *
   * @return string|null
   */
  public function getCommand() : ?string {
    return $this->command;
  }

  /**
   * Set arguments.
   *
   * @param array $arguments
   *
   * @return self
   */
  public function setArguments(array $arguments) : self {
    $this->arguments = $arguments;

    return $this;
  }

  /**
   * Get arguments.
   *
   * @return array|null
   */
  public function getArguments() : ?array {
    return $this->arguments;
  }

  /****************************************************************************/
  /* INTERFACE                                                                */
  /****************************************************************************/

  /**
   * @inheritdoc
   */
  public function getExecutorClass(): string {
    return CommandExecutor::class;
  }

  /**
   * @inheritdoc
   */
  public function getIdentifier(): string {
    return $this->getCommand();
  }

  /**
   * @inheritdoc
   */
  public function getTemplateFolder(): string {
    return '@HBMAsyncWorker/Informer/Command/';
  }

}
