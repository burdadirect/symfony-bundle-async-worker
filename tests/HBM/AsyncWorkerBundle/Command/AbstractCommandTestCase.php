<?php

namespace Tests\HBM\AsyncWorkerBundle\Command;

use HBM\AsyncWorkerBundle\AsyncWorker\Job\Command as AsyncCommand;
use HBM\AsyncWorkerBundle\AsyncWorker\Runner\Runner;
use HBM\AsyncWorkerBundle\DependencyInjection\Configuration;
use HBM\AsyncWorkerBundle\Service\ConsoleLogger;
use HBM\AsyncWorkerBundle\Service\Messenger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

abstract class AbstractCommandTestCase extends TestCase {

  /**
   * @var \Redis
   */
  protected $redis;

  /**
   * @var Application
   */
  protected $application;

  /**
   * @var ConsoleLogger
   */
  protected $consoleLogger;

  /**
   * @var Messenger
   */
  protected $messenger;

  /**
   * @var Command
   */
  protected $commandToTest;

  /**
   * @var Command
   */
  protected $dummyCommand;

  /**
   * Setup test.
   */
  protected function setUp() {
    parent::setUp();

    $this->redis = new \Redis();
    $this->redis->connect('127.0.0.1', 6379);

    // Dummy command
    $this->dummyCommand = new Command('hbm:async:dummy');
    $this->dummyCommand->addArgument('attribute', InputArgument::OPTIONAL, 'Pass an attribute to the dummy command.', 'dummy');
    $this->dummyCommand->setCode(function(InputInterface $input, OutputInterface $output) {
      $output->writeln('This is a '.$input->getArgument('attribute').' command.');
    });
  }

  /**
   * Init services.
   *
   * @param array $configs
   *
   * @return array $config
   */
  protected function initServices(array $configs = []) : array {
    $configuration = new Configuration();
    $processor = new Processor();

    $config = $processor->processConfiguration($configuration, $configs);

    $this->consoleLogger = new ConsoleLogger($config);

    $this->messenger = new Messenger($config, $this->redis, $this->consoleLogger);
    $this->messenger->purge();

    $this->application = new Application();
    $this->application->add($this->dummyCommand);

    return $config;
  }

  /**
   * Test command.
   *
   * @param array $arguments
   * @param Command $command
   *
   * @return string
   */
  protected function executeCommandToTest(array $arguments = [], Command $command = NULL) : string {
    if ($command === NULL) {
      $command = $this->commandToTest;
    }

    $arguments['command'] = $command->getName();

    $commandTester = new CommandTester($command);
    $commandTester->execute($arguments);

    $commandDisplay = $commandTester->getDisplay();

    return $this->removeAnsiEscapeSequences($commandDisplay);
  }

  /**
   * Make command output testable by removing ansi escape sequences.
   *
   * @param $subject
   *
   * @return null|string|string[]
   */
  protected function removeAnsiEscapeSequences($subject) {
    $subject = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '', $subject);
    $subject = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '', $subject);
    $subject = preg_replace('/[\x03|\x1a]/', '', $subject);

    return $subject;
  }

  /**
   * Creates a dummy command async job.
   *
   * @param $queue
   *
   * @return AsyncCommand
   */
  protected function createDummyCommandJob($queue) : AsyncCommand {
    $job = new AsyncCommand($queue);
    $job->setCommand($this->dummyCommand->getName());

    return $job;
  }

  /**
   * Get runner (fresh from redis).
   *
   * @param string $runnerId
   *
   * @return Runner
   */
  protected function getRunner(string $runnerId) : Runner {
    return $this->messenger->getRunner($runnerId);
  }

  /****************************************************************************/
  /* ASSERTS                                                                  */
  /****************************************************************************/

  protected function assertOutputContains(string $output, string $message) : void {
    $this->assertContains($message, $output, 'Output should contain: '.$message);
  }

  protected function assertOutputNotContains(string $output, string $message) : void {
    $this->assertNotContains($message, $output, 'Output should NOT contain: '.$message);
  }

}
