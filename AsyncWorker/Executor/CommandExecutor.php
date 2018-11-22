<?php

namespace HBM\AsyncWorkerBundle\AsyncWorker\Executor;

use HBM\AsyncWorkerBundle\AsyncWorker\Job\AbstractJob;
use HBM\AsyncWorkerBundle\AsyncWorker\Job\Command;
use HBM\AsyncWorkerBundle\Output\BufferedStreamOutput;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Class CommandExecutor.
 */
class CommandExecutor extends AbstractExecutor {

  /**
   * @inheritdoc
   */
  public function executeInternal(AbstractJob $job, BufferedStreamOutput $output) : void {
    if (!$job instanceof Command) {
      throw new \LogicException('Job is of wrong type.');
    }

    // Find command.
    $command = $this->application->find($job->getCommand());
    $this->setReturnDataValue('command', $command);

    // Assemble arguments.
    $arguments = $job->getArguments();
    $arguments['command'] = $command;

    // Run command.
    $this->setReturnCode($command->run(new ArrayInput($arguments), $output));
    $this->setReturnDataValue('output', $output->getBufferedOutput()->fetch());
  }

}
