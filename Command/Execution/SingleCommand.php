<?php

namespace HBM\AsyncWorkerBundle\Command\Execution;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class SingleCommand extends AbstractExecutionCommand {

  /**
   * @var string
   */
  public const NAME = 'hbm:async-worker:single';

  /**
   * @inheritdoc
   */
  protected function configure() {
    $this
      ->setName(self::NAME)
      ->addArgument('runner', InputArgument::REQUIRED, 'The ID of the runner. Could be any integer/string. Just to identify this runner.')
      ->addOption('force', NULL, InputOption::VALUE_NONE, 'Force execution even if runner is currently listening.')
      ->addOption('passthru', NULL, InputOption::VALUE_NONE, 'If this option is provided, the output of the executed jobs is passed thru to the console.')
      ->setDescription('Make the runner try to execute a single job.');

    $this->configureCommand($this);
  }

  /**
   * @inheritdoc
   */
  protected function executeLogic(InputInterface $input, OutputInterface $output) : void {
    /**************************************************************************/
    /* CHECK IF RUNNER IS CURRENTLY LISTENING                                 */
    /**************************************************************************/
    if (!$input->getOption('force') && $this->getRunner()->isListening()) {
      $this->outputAndOrLog('Runner is currently listening.', 'info');
      return;
    }

    /**************************************************************************/
    /* CHECK IF RUNNER IS CURRENTLY BUSY                                      */
    /**************************************************************************/
    if (!$input->getOption('force') && $this->getRunner()->isBusy()) {
      $this->outputAndOrLog('Runner is currently busy.', 'info');
      return;
    }

    /**************************************************************************/
    /* RUN SINGLE COMMAND                                                     */
    /**************************************************************************/
    $this->outputAndOrLog('Running a single job.', 'notice');

    // Execute queued job (if there is any).
    $this->executeOne($output);
  }

}
