<?php

namespace Tests\HBM\AsyncWorkerBundle\Command;

use HBM\AsyncWorkerBundle\Command\ShutdownCommand;

class ShutdownCommandTest extends AbstractCommandTestCase {

  /**
   * @var ShutdownCommand
   */
  protected $commandToTest;

  /**
   * @inheritdoc
   */
  protected function initServices(array $configs = []) : array {
    $config = parent::initServices($configs);

    $this->commandToTest = new ShutdownCommand($this->messenger, $this->consoleLogger);

    $this->application->add($this->commandToTest);

    return $config;
  }

  public function testShutdownOne() : void {
    $this->initServices();

    $commandString = $this->executeCommandToTest();

    $this->assertOutputContains($commandString, 'Sent shutdown request (runner ID "main").');
  }

  public function testShutdownMultiple() : void {
    $specialConfig = [
      'runner' => ['ids' => ['john', 'mary']],
    ];
    $this->initServices([$specialConfig]);

    $commandString = $this->executeCommandToTest();

    $this->assertOutputContains($commandString, 'Sent shutdown request (runner ID "john").');
    $this->assertOutputContains($commandString, 'Sent shutdown request (runner ID "mary").');
  }

  public function testShutdownSingle() : void {
    $specialConfig = [
      'runner' => ['ids' => ['1', '2', '3']],
    ];
    $this->initServices([$specialConfig]);

    $commandString = $this->executeCommandToTest(['runner' => '3']);

    $this->assertOutputNotContains($commandString, 'Sent shutdown request (runner ID "1").');
    $this->assertOutputNotContains($commandString, 'Sent shutdown request (runner ID "2").');
    $this->assertOutputContains($commandString, 'Sent shutdown request (runner ID "3").');
  }

}
