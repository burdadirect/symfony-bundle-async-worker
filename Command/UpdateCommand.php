<?php

namespace HBM\AsyncWorkerBundle\Command;

use HBM\AsyncWorkerBundle\Services\Messenger;
use HBM\AsyncWorkerBundle\Services\ConsoleLogger;
use HBM\AsyncWorkerBundle\Traits\ConsoleLoggerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

class UpdateCommand extends Command {

  use ConsoleLoggerTrait;

  /**
   * @var string
   */
  public const NAME = 'hbm:async-worker:update';

  /**
   * @var Messenger
   */
  private $messenger;

  /**
   * UpdateCommand constructor.
   *
   * @param Messenger $messenger
   * @param ConsoleLogger $consoleLogger
   */
  public function __construct(Messenger $messenger, ConsoleLogger $consoleLogger) {
    $this->messenger = $messenger;
    $this->consoleLogger = $consoleLogger;

    parent::__construct();
  }

  /**
   * @inheritdoc
   */
  protected function configure() {
    $this
      ->setName(self::NAME)
      ->setDescription('Check delayed and expired jobs.');

    $this->configureCommand($this);
  }

  /**
   * @inheritdoc
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->initializeCommand($input, $output);
  }

  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->outputAndOrLog('Updating delayed/expiring queues.', 'info');

    $result = $this->messenger->updateQueues();

    if ($result['delayed'] > 0) {
      $this->outputAndOrLog('Enqueuing '.$result['delayed'].' delayed job(s).', 'info');
    }
    if ($result['expired'] > 0) {
      $this->outputAndOrLog('Expired '.$result['expired'].' job(s).', 'info');
    }
    if (array_sum($result) === 0) {
      $this->outputAndOrLog('Nothing to enqueue or expire.', 'info');
    }
  }

}
