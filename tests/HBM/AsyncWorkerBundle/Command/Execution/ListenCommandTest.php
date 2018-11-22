<?php

namespace Tests\HBM\AsyncWorkerBundle\Command\Execution;

use HBM\AsyncWorkerBundle\Command\Execution\ListenCommand;
use HBM\AsyncWorkerBundle\Command\ShutdownCommand;
use HBM\AsyncWorkerBundle\Services\Informer;
use LongRunning\Core\Cleaner;
use Tests\HBM\AsyncWorkerBundle\Command\AbstractCommandTestCase;

class ListenCommandTest extends AbstractCommandTestCase {

  /**
   * @var ListenCommand
   */
  protected $commandToTest;

  /**
   * @inheritdoc
   */
  protected function initServices(array $configs = []) : array {
    $config = parent::initServices($configs);

    /** @var Informer $informer */
    $informer = $this->getMockBuilder(Informer::class)
      ->disableOriginalConstructor()
      ->getMock();

    /** @var Cleaner $cleaner */
    $cleaner = $this->getMockBuilder(Cleaner::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->commandToTest = new ListenCommand($config, $this->messenger, $informer, $cleaner, $this->consoleLogger);

    $this->application->add($this->commandToTest);

    return $config;
  }

  public function testListen() : void {
    $specialConfig = [
      'runner' => [
        'block' => 1,
        'runtime' => 1,
        'fuzz' => 0,
      ],
    ];
    $this->initServices([$specialConfig]);

    // Test command
    $commandString = $this->executeCommandToTest(['runner' => 'main']);

    // Assert successful job
    $this->assertOutputContains($commandString, 'Runner started (runner ID "main")! Listening for jobs...');
    $this->assertOutputContains($commandString, 'Planned shutdown (runner ID "main")! Waiting for restart...');
  }

  public function testListenWithJob() : void {
    $specialConfig = [
      'runner' => [
        'block' => 1,
        'runtime' => 1,
        'fuzz' => 0,
      ],
    ];
    $this->initServices([$specialConfig]);

    // Dummy command job
    $job = $this->createDummyCommandJob('normal');
    $this->messenger->dispatchJob($job);

    // Test command
    $commandString = $this->executeCommandToTest(['runner' => 'main']);

    // Assert successful job
    $this->assertOutputContains($commandString, 'Runner started (runner ID "main")! Listening for jobs...');
    $this->assertOutputContains($commandString, 'Found job ID '.$job->getId().' in queue "queue.normal" (runner ID "main").');
    $this->assertOutputContains($commandString, 'Job ID '.$job->getId().' successful (runner ID "main").');
    $this->assertOutputContains($commandString, 'Planned shutdown (runner ID "main")! Waiting for restart...');
  }

}
