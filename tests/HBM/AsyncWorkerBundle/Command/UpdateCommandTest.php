<?php

namespace Tests\HBM\AsyncWorkerBundle\Command;

use HBM\AsyncWorkerBundle\Command\UpdateCommand;

class UpdateCommandTest extends AbstractCommandTestCase {

  /**
   * @var UpdateCommand
   */
  protected $commandToTest;

  /**
   * @inheritdoc
   */
  protected function initServices(array $configs = []) : array {
    $config = parent::initServices($configs);

    $this->commandToTest = new UpdateCommand($this->messenger, $this->consoleLogger);

    $this->application->add($this->commandToTest);

    return $config;
  }

  public function testUpdate() : void {
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
    $commandString = $this->executeCommandToTest();
    $this->assertOutputContains($commandString, 'Updating delayed/expiring queues.');
    $this->assertOutputContains($commandString, 'Expired 1 job(s).');

    $this->assertSame(2, $this->messenger->countJobsExpiring(), 'There should be 2 expiring jobs.');
    $this->assertSame(2, $this->messenger->countJobsQueued(), 'There should be 2 waiting jobs.');
    $this->assertSame(3, $this->messenger->countJobs(), 'There should be 3 jobs.');

    sleep(2);

    // Test command.
    $commandString = $this->executeCommandToTest();
    $this->assertOutputContains($commandString, 'Updating delayed/expiring queues.');
    $this->assertOutputContains($commandString, 'Expired 1 job(s).');

    $this->assertSame(1, $this->messenger->countJobsExpiring(), 'There should be 1 expiring job.');
    $this->assertSame(1, $this->messenger->countJobsQueued(), 'There should be 1 waiting job.');
    $this->assertSame(3, $this->messenger->countJobs(), 'There should be 3 jobs.');

    sleep(2);

    // Test command.
    $commandString = $this->executeCommandToTest();
    $this->assertOutputContains($commandString, 'Updating delayed/expiring queues.');
    $this->assertOutputContains($commandString, 'Expired 1 job(s).');

    $this->assertSame(0, $this->messenger->countJobsExpiring(), 'There should be 0 expiring jobs.');
    $this->assertSame(0, $this->messenger->countJobsQueued(), 'There should be 0 waiting job.');
    $this->assertSame(3, $this->messenger->countJobs(), 'There should be 3 jobs.');

    // Test command.
    $commandString = $this->executeCommandToTest();
    $this->assertOutputContains($commandString, 'Updating delayed/expiring queues.');
    $this->assertOutputContains($commandString, 'Nothing to enqueue or expire.');
  }

}
