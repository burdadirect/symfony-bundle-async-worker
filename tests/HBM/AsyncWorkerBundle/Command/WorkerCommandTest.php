<?php

namespace Tests\HBM\AsyncWorkerBundle\Command;

use HBM\AsyncWorkerBundle\AsyncWorker\Job\Command as AsyncCommand;
use HBM\AsyncWorkerBundle\Command\RunnerCommand;
use HBM\AsyncWorkerBundle\DependencyInjection\Configuration;
use HBM\AsyncWorkerBundle\Services\Informer;
use HBM\AsyncWorkerBundle\Services\Logger;
use HBM\AsyncWorkerBundle\Services\Messenger;
use LongRunning\Core\Cleaner;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class WorkerCommandTest extends AbstractCommandTestCase {

  /**
   * @var Application
   */
  private $application;

  /**
   * @var Messenger
   */
  private $messenger;

  /**
   * @var Command
   */
  private $dummyCommand;

  /**
   * @var RunnerCommand
   */
  private $runnerCommand;

  protected function setUp() {
    parent::setUp();

    // Dummy command
    $this->dummyCommand = new Command('hbm:async:dummy');
    $this->dummyCommand->setCode(function() {
      return 'This is a dummy command.';
    });
  }


  /**
   * Init services.
   *
   * @param array $configs
   */
  private function initServices(array $configs = []) : void {
    $configuration = new Configuration();
    $processor = new Processor();

    $config = $processor->processConfiguration($configuration, $configs);

    /** @var \Redis $redis */
    $redis = new \Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->flushAll();

    /** @var Logger $logger */
    $logger = new Logger($config);

    /** @var Cleaner $cleaner */
    $cleaner = $this->getMockBuilder(Cleaner::class)
      ->disableOriginalConstructor()
      ->getMock();

    /** @var Informer $informer */
    $informer = $this->getMockBuilder(Informer::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->messenger = new Messenger($config, $redis);
    $this->messenger->setLogger($logger);

    $this->runnerCommand = new RunnerCommand($config, $this->messenger, $informer, $cleaner);
    $this->runnerCommand->setLogger($logger);

    $this->application = new Application();
    $this->application->add($this->runnerCommand);
    $this->application->add($this->dummyCommand);
  }

  public function testRunnerKill() : void {
    $this->initServices();

    $commandTester = new CommandTester($this->runnerCommand);
    $commandTester->execute([
      'command' => $this->runnerCommand->getName(),
      'runner'  => 'main',
      'action'  => 'kill',
    ]);

    $commandDisplay = $commandTester->getDisplay();
    $commandString = $this->removeAnsiEscapeSequences($commandDisplay);

    $this->assertContains('Sent kill request (runner ID "main").', $commandString, 'Output should contain "Sent kill request (runner ID "main").".');
  }

  public function testRunnerUpdate() : void {
    $this->initServices();

    // Dummy command job
    $job = $this->createDummyCommandJob('normal');
    $job->setExpires(new \DateTime('+2sec'));
    $this->messenger->dispatchJob($job);

    $job = $this->createDummyCommandJob('normal');
    $job->setExpires(new \DateTime('+4sec'));
    $this->messenger->dispatchJob($job);

    $job = $this->createDummyCommandJob('normal');
    $job->setExpires(new \DateTime('+6sec'));
    $this->messenger->dispatchJob($job);

    sleep(1);

    $this->assertSame(3, $this->messenger->countJobsExpiring(), 'There should be 3 expiring jobs.');
    $this->assertSame(3, $this->messenger->countJobsQueued(), 'There should be 3 waiting jobs.');
    $this->assertSame(3, $this->messenger->countJobs(), 'There should be 3 jobs.');

    sleep(2);

    // Test command.
    $commandString = $this->testRunnerCommand('main', 'update');
    $this->assertContains('Updating queues (runner ID "main").', $commandString, 'Output should contain "Updating queues (runner ID "main").".');

    $this->assertSame(2, $this->messenger->countJobsExpiring(), 'There should be 2 expiring jobs.');
    $this->assertSame(2, $this->messenger->countJobsQueued(), 'There should be 2 waiting jobs.');
    $this->assertSame(3, $this->messenger->countJobs(), 'There should be 3 jobs.');

    sleep(2);

    // Test command.
    $commandString = $this->testRunnerCommand('main', 'update');
    $this->assertContains('Updating queues (runner ID "main").', $commandString, 'Output should contain "Updating queues (runner ID "main").".');

    $this->assertSame(1, $this->messenger->countJobsExpiring(), 'There should be 1 expiring job.');
    $this->assertSame(1, $this->messenger->countJobsQueued(), 'There should be 1 waiting job.');
    $this->assertSame(3, $this->messenger->countJobs(), 'There should be 3 jobs.');

    sleep(2);

    // Test command.
    $commandString = $this->testRunnerCommand('main', 'update');
    $this->assertContains('Updating queues (runner ID "main").', $commandString, 'Output should contain "Updating queues (runner ID "main").".');

    $this->assertSame(0, $this->messenger->countJobsExpiring(), 'There should be 0 expiring jobs.');
    $this->assertSame(0, $this->messenger->countJobsQueued(), 'There should be 0 waiting job.');
    $this->assertSame(3, $this->messenger->countJobs(), 'There should be 3 jobs.');
  }

  public function testRunnerSingle() : void {
    $specialConfig = [
      'runner' => ['ids' => ['john']],
      'priorities' => ['important']
    ];

    $this->initServices([$specialConfig]);

    $queue = $specialConfig['priorities'][0];
    $runner = $specialConfig['runner']['ids'][0];
    $log = '(runner ID "'.$runner.'")';

    // Dummy command job
    $job = $this->createDummyCommandJob($queue);
    $job->setRunnerDesired($runner);
    $this->messenger->dispatchJob($job);

    // Test command
    $commandString = $this->testRunnerCommand($job->getRunnerDesired(), 'single');

    $this->assertContains('Running a single job '.$log.'.', $commandString, 'Output should contain "Running a single job...".');
    $this->assertContains('Found job ID '.$job->getId().' in queue "'.$queue.'.'.$runner.'" '.$log.'.', $commandString, 'Output should contain "Found job ... in queue ... .".');
    $this->assertContains('Job ID '.$job->getId().' successful '.$log.'.', $commandString, 'Output should contain "Job ... successful.".');
  }

  /**
   * Test RunnerCommand.
   *
   * @param string $runner
   * @param string $action
   *
   * @return string
   */
  private function testRunnerCommand(string $runner, string $action) : string {
    $commandTester = new CommandTester($this->runnerCommand);
    $commandTester->execute([
      'command' => $this->runnerCommand->getName(),
      'runner'  => $runner,
      'action'  => $action,
    ]);

    $commandDisplay = $commandTester->getDisplay();

    return $this->removeAnsiEscapeSequences($commandDisplay);
  }

  /**
   * Creates a dummy command async job.
   *
   * @param $queue
   *
   * @return AsyncCommand
   */
  private function createDummyCommandJob($queue) : AsyncCommand {
    $job = new AsyncCommand($queue);
    $job->setCommand($this->dummyCommand->getName());

    return $job;
  }

}
