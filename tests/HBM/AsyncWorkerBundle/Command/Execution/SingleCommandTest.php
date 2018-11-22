<?php

namespace Tests\HBM\AsyncWorkerBundle\Command\Execution;

use HBM\AsyncWorkerBundle\AsyncWorker\Job\AbstractJob;
use HBM\AsyncWorkerBundle\AsyncWorker\Runner\Runner;
use HBM\AsyncWorkerBundle\Command\Execution\SingleCommand;
use HBM\AsyncWorkerBundle\Services\Informer;
use Tests\HBM\AsyncWorkerBundle\Command\AbstractCommandTestCase;

class SingleCommandTest extends AbstractCommandTestCase {

  /**
   * @var SingleCommand
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

    $this->commandToTest = new SingleCommand($config, $this->messenger, $informer, $this->consoleLogger);

    $this->application->add($this->commandToTest);

    return $config;
  }

  public function testSingle() : void {
    $specialConfig = [
      'runner' => ['ids' => ['john']],
      'queue' => ['priorities' => ['important']]
    ];

    $this->initServices([$specialConfig]);

    $queue = $specialConfig['queue']['priorities'][0];
    $runner = $specialConfig['runner']['ids'][0];

    // Dummy command job
    $job = $this->createDummyCommandJob($queue);
    $job->setRunnerDesired($runner);
    $this->messenger->dispatchJob($job);

    // Test command
    $commandString = $this->executeCommandToTest(['runner' => $job->getRunnerDesired()]);

    // Assert successful job
    $this->assertOutputContainsSuccessfulSingleJob($commandString, $job, $runner);
  }

  public function testSinglePassthruFlag() : void {
    $this->initServices();

    // Dummy command job
    $job = $this->createDummyCommandJob('normal');
    $this->messenger->dispatchJob($job);

    // Test command
    $commandString = $this->executeCommandToTest([
      'runner' => 'main',
      '--passthru' => TRUE,
    ]);

    // Assert successful job and passed thru job output
    $this->assertOutputContainsSuccessfulSingleJob($commandString, $job);
    $this->assertOutputContains($commandString, 'This is a dummy command.');
  }

  public function testSingleForceFlag() : void {
    $this->initServices();

    $this->messenger->updateRunner($this->getRunner('main')->setState(Runner::STATE_LISTENING));

    // Dummy command job
    $job = $this->createDummyCommandJob('normal');
    $this->messenger->dispatchJob($job);

    // Test command
    $commandString = $this->executeCommandToTest([
      'runner' => 'main',
      '--force' => TRUE,
    ]);

    // Assert successful job
    $this->assertOutputContainsSuccessfulSingleJob($commandString, $job);
  }

  public function testSingleBusy() : void {
    $this->initServices();

    $this->messenger->updateRunner($this->getRunner('main')->setState(Runner::STATE_LISTENING));

    // Dummy command job
    $job = $this->createDummyCommandJob('normal');
    $this->messenger->dispatchJob($job);

    // Test command
    $commandString = $this->executeCommandToTest([
      'runner' => 'main',
    ]);

    // Assert busy runner
    $this->assertOutputContains($commandString, 'Runner is currently listening (runner ID "main").');
  }

  private function assertOutputContainsSuccessfulSingleJob(string $output, AbstractJob $job, string $runner = 'main') : void {
    $this->assertOutputContains($output, 'Running a single job (runner ID "'.$runner.'").');
    $this->assertOutputContains($output, 'Found job ID '.$job->getId().' in queue "'.$this->messenger->getQueue($job).'" (runner ID "'.$runner.'").');
    $this->assertOutputContains($output, 'Job ID '.$job->getId().' successful (runner ID "'.$runner.'").');
  }

}
