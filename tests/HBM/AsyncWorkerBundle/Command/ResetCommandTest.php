<?php

namespace Tests\HBM\AsyncWorkerBundle\Command;

use HBM\AsyncWorkerBundle\Command\ResetCommand;

class ResetCommandTest extends AbstractCommandTestCase {

  /**
   * @var ResetCommand
   */
  protected $commandToTest;

  /**
   * @inheritdoc
   */
  protected function initServices(array $configs = []) : array {
    $config = parent::initServices($configs);

    $this->commandToTest = new ResetCommand($this->messenger, $this->consoleLogger);

    $this->application->add($this->commandToTest);

    return $config;
  }

  public function testReset() : void {
    $this->initServices();

    // Adjust runner.
    $this->messenger->updateRunner($this->getRunner('main')->addJobId('TEST-JOB-ID'));

    // Test command.
    $commandString = $this->executeCommandToTest();

    $this->assertOutputContains($commandString, 'Forced reset (runner ID "main").');
    $this->assertCount(0, $this->getRunner('main')->getRunJobIds(), 'The runner should not contain any jobs.');
  }

}
