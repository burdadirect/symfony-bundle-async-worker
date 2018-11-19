<?php

namespace HBM\AsyncBundle\Async\Executor;

use HBM\AsyncBundle\Async\Job\AbstractAsyncJob;
use HBM\AsyncBundle\Async\Job\AsyncCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Class CommandExecutor.
 */
class CommandExecutor extends AbstractExecutor {

  /**
   * @inheritdoc
   */
  public function executeInternal(AbstractAsyncJob $job) : void {
    if (!$job instanceof AsyncCommand) {
      throw new \LogicException('Job is of wrong type.');
    }

    // Find command.
    $command = $this->application->find($job->getCommand());
    $job->setDataValue('command', $command);

    // Assemble arguments.
    $arguments = $job->getArguments();
    $arguments['command'] = $command;

    // Buffer output.
    $bufferedOutput = new BufferedOutput();

    // Run command.
    $this->setReturnCode($command->run(new ArrayInput($arguments), $bufferedOutput));
    $job->setDataValue('output', $bufferedOutput);
  }

}
