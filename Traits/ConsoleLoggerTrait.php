<?php

namespace HBM\AsyncWorkerBundle\Traits;

use HBM\AsyncWorkerBundle\Services\ConsoleLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait ConsoleLoggerTrait {

  /**
   * @var ConsoleLogger
   */
  protected $consoleLogger;

  /**
   * Output and or log message. NULL for empty line.
   *
   * @param string|array|NULL $message
   * @param string $level
   */
  public function outputAndOrLog($message, string $level = NULL) : void {
    $this->consoleLogger->outputAndOrLog($message, $level);
  }

  /**
   * Configure command.
   *
   * @param Command $command
   */
  public function configureCommand(Command $command) : void {
    $command->addOption('output', NULL, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Write infos to logger.', ['console', 'logger']);
  }

  /**
   * Initialize command.
   *
   * @param InputInterface $input
   * @param OutputInterface $output
   */
  public function initializeCommand(InputInterface $input, OutputInterface $output) : void {
    $targets = $input->getOption('output');
    $this->consoleLogger->setLoggerActive(\in_array('logger', $targets, TRUE));
    $this->consoleLogger->setOutputActive(\in_array('console', $targets, TRUE));
    $this->consoleLogger->setOutput($output);
  }

}
