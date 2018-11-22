<?php

namespace Tests\HBM\AsyncWorkerBundle\Command;

use HBM\AsyncWorkerBundle\Command\InfoCommand;

class InfoCommandTest extends AbstractCommandTestCase {

  /**
   * @var InfoCommand
   */
  protected $commandToTest;

  /**
   * @inheritdoc
   */
  protected function initServices(array $configs = []) : array {
    $config = parent::initServices($configs);

    $this->commandToTest = new InfoCommand($this->messenger);

    $this->application->add($this->commandToTest);

    return $config;
  }

  public function testInfoEmpty() : void {
    $this->initServices();

    // Test command.
    $commandString = $this->executeCommandToTest();

    $this->assertOutputContains($commandString, '"id": "main",');
    $this->assertOutputContains($commandString, '"jobs": [],');
  }

  public function testInfoFilled() : void {
    $specialConfig = [
      'runner' => ['ids' => ['john', 'mary']],
    ];
    $this->initServices([$specialConfig]);

    // Adjust runner.
    $this->messenger->updateRunner($this->getRunner('john')->addJobId('TEST-JOB-ID'));

    // Test command.
    $commandString = $this->executeCommandToTest();

    $this->assertOutputContains($commandString, '"id": "john",');
    $this->assertOutputContains($commandString, '"TEST-JOB-ID": {');
  }

}
