<?php

namespace HBM\AsyncWorkerBundle\Command\Execution;

use HBM\AsyncWorkerBundle\AsyncWorker\Executor\AbstractExecutor;
use HBM\AsyncWorkerBundle\AsyncWorker\Job\AbstractJob;
use HBM\AsyncWorkerBundle\AsyncWorker\Runner\Runner;
use HBM\AsyncWorkerBundle\Output\BufferedStreamOutput;
use HBM\AsyncWorkerBundle\Service\Informer;
use HBM\AsyncWorkerBundle\Service\Messenger;
use HBM\AsyncWorkerBundle\Service\ConsoleLogger;
use HBM\AsyncWorkerBundle\Traits\ConsoleLoggerTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\StreamOutput;

abstract class AbstractExecutionCommand extends Command {

  use ConsoleLoggerTrait;

  /**
   * @var array
   */
  protected $config;

  /**
   * @var Messenger
   */
  protected $messenger;

  /**
   * @var Informer
   */
  private $informer;

  /**
   * @var string
   */
  protected $runner;

  /**
   * @var InputInterface
   */
  protected $input;

  /**
   * AbstractExecutionCommand constructor.
   *
   * @param array $config
   * @param Messenger $messenger
   * @param Informer $informer
   * @param ConsoleLogger $consoleLogger
   */
  public function __construct(array $config, Messenger $messenger, Informer $informer, ConsoleLogger $consoleLogger) {
    $this->config = $config;

    $this->messenger = $messenger;
    $this->informer = $informer;
    $this->consoleLogger = $consoleLogger;

    parent::__construct();
  }

  /**
   * @inheritdoc
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->runner = $input->getArgument('runner');
    $this->input = $input;

    /***************************************************************************/
    /* PREPARE CONSOLE LOGGER                                                 */
    /**************************************************************************/

    $this->initializeCommand($input, $output);
    $this->consoleLogger->setReplacement('RUNNER_ID', $this->runner);

    /**************************************************************************/
    /* PREPARE EXECUTION                                                      */
    /**************************************************************************/

    ini_set('log_errors', (int) $this->config['error']['log']);
    ini_set('error_log', $this->config['error']['file']);

  }

  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->executeLogic($input, $output);
  }

  /**
   * @param InputInterface $input
   * @param OutputInterface $output
   *
   * @throws \Exception
   */
  abstract protected function executeLogic(InputInterface $input, OutputInterface $output) : void;

  /**
   * Pops an item from the beginning of the queue (blocking) and runs the
   * underlying command.
   *
   * @param OutputInterface $output
   *
   * @return bool
   */
  protected function executeOne(OutputInterface $output) : bool {
    $this->consoleLogger->unsetReplacement('JOB_ID');
    if ($jobId = $this->messenger->popJobId($this->runner, $queue, $this->config['runner']['block'])) {
      $this->consoleLogger->setReplacement('JOB_ID', $jobId);

      $this->outputAndOrLog(NULL);

      /************************************************************************/
      /* CHECK IF JOB IS DISCARDED                                            */
      /************************************************************************/
      if (!$job = $this->messenger->getJob($jobId)) {
        $this->outputAndOrLog('Job discarded (missing).', 'notice');
        return FALSE;
      }

      /************************************************************************/
      /* SETTING RUNNER STATUS TO RUNNING                                     */
      /************************************************************************/
      $this->outputAndOrLog('Job found in queue "'.$queue.'".', 'info');
      $this->messenger->updateRunner($this->getRunner()->addJobId($jobId));

      /**************************************************************************/
      /* PREPARE OUTPUT                                                         */
      /**************************************************************************/
      $bufferedStreamOutput = new BufferedStreamOutput(new BufferedOutput());
      if (($output instanceof StreamOutput) && $this->input->getOption('passthru')) {
        $bufferedStreamOutput->setStreamOutput($output);
      }

      /************************************************************************/
      /* EXECUTE JOB USING CORRESPONDING EXECUTOR                             */
      /************************************************************************/
      $executor = $this->getExecutorForJob($job);
      try {
        // Save async job if anything fails during execution
        $this->messenger->markJobAsRunning($job, $this->runner);

        $executor->execute($job, $bufferedStreamOutput);

        // Delete async job if everything went fine
        $this->messenger->discardJob($job);
      } catch (\Exception $e) {
        $this->outputAndOrLog(['LOG' => 'Job failed.', 'POSTFIX' => ' Message: '.$e->getMessage()], 'critical');
        $this->messenger->markJobAsFailed($job);
      }

      /************************************************************************/
      /* SEND INFORMER MAIL                                                   */
      /************************************************************************/
      $this->informer->informAboutJob($job, $executor->getReturnData());

      /************************************************************************/
      /* OUTPUT RESULT                                                        */
      /************************************************************************/
      if ($executor->getReturnCode() === NULL) {
        $this->outputAndOrLog('Job invalid.', 'error');
      } elseif ($executor->getReturnCode() === 0) {
        $this->outputAndOrLog('Job successful.', 'info');
      } else {
        $this->outputAndOrLog('Job erroneous.', 'error');
      }

      $this->outputAndOrLog(NULL);

      $this->messenger->updateRunner($this->getRunner()->incrJobs()->removeJobId($jobId));

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get runner (fresh from redis).
   *
   * @return Runner
   */
  protected function getRunner() : Runner {
    return $this->messenger->getRunner($this->runner);
  }

  /**
   * Create Executor instance.
   *
   * @param AbstractJob $job
   *
   * @return AbstractExecutor
   */
  private function getExecutorForJob(AbstractJob $job) : AbstractExecutor {
    $executorClass = $job->getExecutorClass();

    return new $executorClass($this->getApplication(), $this->config);
  }

}
