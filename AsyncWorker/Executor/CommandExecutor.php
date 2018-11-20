<?php

namespace HBM\AsyncWorkerBundle\AsyncWorker\Executor;

use HBM\AsyncWorkerBundle\AsyncWorker\Job\AbstractJob;
use HBM\AsyncWorkerBundle\AsyncWorker\Job\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Class CommandExecutor.
 */
class CommandExecutor extends AbstractExecutor {

  /**
   * @inheritdoc
   */
  public function executeInternal(AbstractJob $job) : void {
    if (!$job instanceof Command) {
      throw new \LogicException('Job is of wrong type.');
    }

    // Find command.
    $command = $this->application->find($job->getCommand());
    $this->setReturnDataValue('command', $command);

    // Assemble arguments.
    $arguments = $job->getArguments();
    $arguments['command'] = $command;

    // Buffer output.
    $bufferedOutput = new BufferedOutput();

    // Run command.
    $this->setReturnCode($command->run(new ArrayInput($arguments), $bufferedOutput));
    $this->setReturnDataValue('output', $bufferedOutput);
  }

}
