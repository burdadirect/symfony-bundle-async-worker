<?php

namespace Tests\HBM\AsyncBundle\Command;

use HBM\AsyncBundle\Async\Job\AsyncCommand;
use HBM\AsyncBundle\Command\WorkerCommand;
use HBM\AsyncBundle\Services\Messenger;
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
   * @var WorkerCommand
   */
  private $workerCommand;

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

    /** @var \Swift_Mailer $mailer */
    $mailer = $this->getMockBuilder(\Swift_Mailer::class)
      ->disableOriginalConstructor()
      ->getMock();
    /** @var EngineInterface $templating */
    $templating = $this->getMockBuilder(EngineInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->messenger = new Messenger($config, $redis, $logger);
    $this->workerCommand = new WorkerCommand($config, $this->messenger, $logger, $mailer, $templating);

    $this->application = new Application();
    $this->application->add($this->workerCommand);
  }

  public function testWorkerKill() : void {
    $commandTester = new CommandTester($this->workerCommand);
    $commandTester->execute([
      'command'   => $this->workerCommand->getName(),
      'worker-id' => 1,
      'action'    => 'kill',
    ]);

    $commandDisplay = $commandTester->getDisplay();
    $commandString = $this->removeAnsiEscapeSequences($commandDisplay);

    $this->assertContains('Sent kill request to worker with ID 1.', $commandString, 'Output should contain "Sent kill request to worker with ID 1.".');
  }

  public function testWorkerSingle() : void {
    $queue = 'normal';
    $workerId = 'john';

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
    $job->setWorkerDesired($workerId);
    $job->setCommand($dummyCommand->getName());

    $this->messenger->dispatchJob($job);

    /**************************************************************************/
    /* WORKER                                                                 */
    /**************************************************************************/

    $this->application->add($dummyCommand);

    $commandTester = new CommandTester($this->workerCommand);
    $commandTester->execute([
      'command'   => $this->workerCommand->getName(),
      'worker-id' => $job->getWorkerDesired(),
      'action'    => 'single',
    ]);

    $commandDisplay = $commandTester->getDisplay();
    $commandString = $this->removeAnsiEscapeSequences($commandDisplay);

    $this->assertContains('Running a single job using worker with ID '.$workerId.'.', $commandString, 'Output should contain "Running a single job...".');
    $this->assertContains('Found job ID '.$job->getId().' in queue "'.$queue.'" (worker ID '.$workerId.').', $commandString, 'Output should contain "Found job ... in queue ... .".');
    $this->assertContains('Informing development@playboy.de about job ID '.$job->getId().' (worker ID '.$workerId.').', $commandString, 'Output should contain "Informing ... about job ... .".');
    $this->assertContains('Job ID '.$job->getId().' successful (worker ID '.$workerId.').', $commandString, 'Output should contain "Job ... successful.".');
  }

}
