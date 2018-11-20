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
   * @var RunnerCommand
   */
  private $runnerCommand;

  private function initServices(array $configs = []) {
    $configuration = new Configuration();
    $processor = new Processor();

    $config = $processor->processConfiguration($configuration, $configs);

    /** @var \Redis $redis */
    $redis = new \Redis();
    $redis->connect('127.0.0.1', 6379);

    /** @var Messenger $messenger */
    $messenger = new Messenger($config, $redis);
    $messenger->setOptions();

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

    $this->runnerCommand = new RunnerCommand($config, $messenger, $informer, $cleaner);
    $this->runnerCommand->setLogger($logger);

    $this->application = new Application();
    $this->application->add($this->runnerCommand);
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

  public function testRunnerSingle() : void {
    $specialConfig = [
      'runner' => ['ids' => ['john']],
      'priorities' => ['important']
    ];

    $this->initServices([$specialConfig]);

    $queue = $specialConfig['priorities'][0];
    $runner = $specialConfig['runner']['ids'][0];
    $log = '(runner ID "'.$runner.'")';

    /**************************************************************************/
    /* DUMMY COMMAND                                                          */
    /**************************************************************************/

    $dummyCommand = new Command('hbm:async:dummy');
    $dummyCommand->setCode(function() {
      return 'This is a dummy command.';
    });

    /**************************************************************************/
    /* ASYNC JOB                                                              */
    /**************************************************************************/

    $job = new AsyncCommand($queue);
    $job->setRunnerDesired($runner);
    $job->setCommand($dummyCommand->getName());

    $this->messenger->dispatchJob($job);

    /**************************************************************************/
    /* RUNNER                                                                 */
    /**************************************************************************/

    $this->application->add($dummyCommand);

    $commandTester = new CommandTester($this->runnerCommand);
    $commandTester->execute([
      'command' => $this->runnerCommand->getName(),
      'runner'  => $job->getRunnerDesired(),
      'action'  => 'single',
    ]);

    $commandDisplay = $commandTester->getDisplay();
    $commandString = $this->removeAnsiEscapeSequences($commandDisplay);

    $this->assertContains('Running a single job '.$log.'.', $commandString, 'Output should contain "Running a single job...".');
    $this->assertContains('Found job ID '.$job->getId().' in queue "'.$queue.'.'.$runner.'" '.$log.'.', $commandString, 'Output should contain "Found job ... in queue ... .".');
    $this->assertContains('Job ID '.$job->getId().' successful '.$log.'.', $commandString, 'Output should contain "Job ... successful.".');
  }

}
