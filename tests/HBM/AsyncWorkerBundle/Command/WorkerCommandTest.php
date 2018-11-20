<?php

namespace Tests\HBM\AsyncWorkerBundle\Command;

use HBM\AsyncWorkerBundle\AsyncWorker\Job\Command as AsyncCommand;
use HBM\AsyncWorkerBundle\Command\RunnerCommand;
use HBM\AsyncWorkerBundle\Services\Messenger;
use LongRunning\Core\Cleaner;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Templating\EngineInterface;

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

  /**
   * @inheritdoc
   */
  public function setUp() {
    parent::setUp();

    $config = [];

    /** @var \Redis $redis */
    $redis = $this->getMockBuilder(\Redis::class)
      ->disableOriginalConstructor()
      ->getMock();

    /** @var LoggerInterface $logger */
    $logger = $this->getMockBuilder(LoggerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    /** @var Cleaner $cleaner */
    $cleaner = $this->getMockBuilder(Cleaner::class)
      ->disableOriginalConstructor()
      ->getMock();

    /** @var \Swift_Mailer $mailer */
    $mailer = $this->getMockBuilder(\Swift_Mailer::class)
      ->disableOriginalConstructor()
      ->getMock();

    /** @var EngineInterface $templating */
    $templating = $this->getMockBuilder(EngineInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->messenger = new Messenger($config, $redis, $logger);
    $this->runnerCommand = new RunnerCommand($config, $this->messenger, $cleaner, $logger, $mailer, $templating);

    $this->application = new Application();
    $this->application->add($this->runnerCommand);
  }

  public function testRunnerKill() : void {
    $commandTester = new CommandTester($this->runnerCommand);
    $commandTester->execute([
      'command' => $this->runnerCommand->getName(),
      'runner'  => 1,
      'action'  => 'kill',
    ]);

    $commandDisplay = $commandTester->getDisplay();
    $commandString = $this->removeAnsiEscapeSequences($commandDisplay);

    $this->assertContains('Sent kill request to runner with ID 1.', $commandString, 'Output should contain "Sent kill request to runner with ID 1.".');
  }

  public function testRunnerSingle() : void {
    $queue = 'normal';
    $runner = 'john';
    $runnerLog = '(runner ID "john")';

    /**************************************************************************/
    /* DUMMY COMMAND                                                          */
    /**************************************************************************/

    /** @var Command $dummyCommand */
    $dummyCommand = $this->getMockBuilder(Command::class)
      ->disableOriginalConstructor()
      ->getMock()
      ->method('execute')
      ->willReturn('This is a dummy command.');

    $dummyCommand->setName('hbm:async:dummy');

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

    $this->assertContains('Running a single job '.$runnerLog.'.', $commandString, 'Output should contain "Running a single job...".');
    $this->assertContains('Found job ID '.$job->getId().' in queue "'.$queue.'" '.$runnerLog.'.', $commandString, 'Output should contain "Found job ... in queue ... .".');
    $this->assertContains('Informing development@playboy.de about job ID '.$job->getId().' '.$runnerLog.'.', $commandString, 'Output should contain "Informing ... about job ... .".');
    $this->assertContains('Job ID '.$job->getId().' successful '.$runnerLog.'.', $commandString, 'Output should contain "Job ... successful.".');
  }

}
