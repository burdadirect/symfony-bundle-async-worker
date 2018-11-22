<?php

namespace HBM\AsyncWorkerBundle\Command;

use HBM\AsyncWorkerBundle\Services\Messenger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;

class InfoCommand extends Command {

  /**
   * @var string
   */
  public const NAME = 'hbm:async_worker:info';

  /**
   * @var Messenger
   */
  private $messenger;

  /**
   * InfoCommand constructor.
   *
   * @param Messenger $messenger
   */
  public function __construct(Messenger $messenger) {
    $this->messenger = $messenger;

    parent::__construct();
  }

  /**
   * @inheritdoc
   */
  protected function configure() {
    $this
      ->setName(self::NAME)
      ->addArgument('runner', InputArgument::OPTIONAL, 'The ID of the runner. Could be any integer/string. Just to identify this runner.')
      ->setDescription('Get status of runner(s).');
  }

  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Determine runner ids to shutdown.
    if ($runner = $input->getArgument('runner')) {
      $runnerIds = [$runner];
    } else {
      $runnerIds = $this->messenger->getRunnerIds();
    }

    // Send shutdown request to runners.
    $runners = $this->messenger->getRunnersById($runnerIds);
    foreach ($runners as $runner) {
      $output->writeln(json_encode($runner->info(TRUE, TRUE), JSON_PRETTY_PRINT));
    }
  }

}
