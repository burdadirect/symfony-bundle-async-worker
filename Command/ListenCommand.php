<?php

namespace HBM\AsyncWorkerBundle\Command;

use HBM\AsyncWorkerBundle\AsyncWorker\Executor\AbstractExecutor;
use HBM\AsyncWorkerBundle\AsyncWorker\Job\AbstractJob;
use HBM\AsyncWorkerBundle\Output\BufferedConsoleOutput;
use HBM\AsyncWorkerBundle\Services\Informer;
use HBM\AsyncWorkerBundle\Services\Messenger;
use HBM\AsyncWorkerBundle\Traits\LoggerTrait;
use LongRunning\Core\Cleaner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ListenCommand extends Command {

  use LoggerTrait;

  /**
   * @var string
   */
  public const NAME = 'hbm:async_worker:listen';

  /**
   * @var array
   */
  private $config;

  /**
   * @var Messenger
   */
  private $messenger;

  /**
   * @var Informer
   */
  private $informer;

  /**
   * @var Cleaner
   */
  private $cleaner;

  /**
   * @var string
   */
  private $runnerId;

  /**
   * @var InputInterface
   */
  private $input;

  public function __construct(array $config, Messenger $messenger, Informer $informer, Cleaner $cleaner) {
    $this->config = $config;

    $this->messenger = $messenger;
    $this->informer = $informer;
    $this->cleaner = $cleaner;

    parent::__construct();
  }

  protected function configure() {
    $this
      ->setName(self::NAME)
      ->addArgument('runner', InputArgument::REQUIRED, 'The ID of the runner. Could be any integer/string. Just to identify this runner.')
      ->addArgument('action', InputArgument::OPTIONAL, 'The action to perform. Possible values are: start, force, update, kill. Default: "start"')
      ->addOption('log', NULL, InputOption::VALUE_NONE, 'Log to channel instead of writing to console output.')
      ->addOption('console', NULL, InputOption::VALUE_NONE, 'Output command output to runner console.')
      ->setDescription('Make the runner listening.');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->runnerId = $input->getArgument('runner');
    $this->input = $input;

   /**************************************************************************/
    /* PREPARE LOGGING                                                        */
    /**************************************************************************/

    $this->setLogChannel($this->input->getOption('log'));
    $this->setLogOutput($output);
    $this->setLogReplacement('%RUNNER_ID%', '(runner ID "'.$this->runnerId.'")');

    $this->informer->setLogger($this->logger);

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
    /**************************************************************************/
    /* PREPARE OUTPUT                                                         */
    /**************************************************************************/
    $bufferedConsoleOutput = new BufferedConsoleOutput(new BufferedOutput());
    if (($output instanceof ConsoleOutput) && $input->getOption('console')) {
      $bufferedConsoleOutput->setConsoleOutput($output);
    }

    /**************************************************************************/
    /* CHECK REDIS                                                            */
    /**************************************************************************/
    if (!$this->messenger->isAvailable()) {
      $this->outputAndOrLog('Redis is not available %RUNNER_ID%.', 'critical');
      return;
    }

    /**************************************************************************/
    /* KILL RUNNER                                                            */
    /**************************************************************************/
    if ($input->getArgument('action') === 'kill') {
      $this->messenger->setRunnerKilled($this->runnerId, TRUE);
      $this->outputAndOrLog('Sent kill request %RUNNER_ID%.', 'notice');
      return;
    }

    /**************************************************************************/
    /* RUN SINGLE COMMAND                                                     */
    /**************************************************************************/
    if ($input->getArgument('action') === 'single') {
      $this->outputAndOrLog('Running a single job %RUNNER_ID%.', 'notice');
      $this->executeOne($bufferedConsoleOutput);
      return;
    }

    /**************************************************************************/
    /* UPDATE QUEUES                                                          */
    /**************************************************************************/
    if ($input->getArgument('action') === 'update') {
      $this->outputAndOrLog('Updating queues %RUNNER_ID%.', 'notice');
      $this->updateQueues();
      return;
    }

    /**************************************************************************/
    /* START TIME                                                             */
    /**************************************************************************/
    /*
     * We'll set our base time, which is one hour (in seconds).
     * Once we have our base time, we'll add anywhere between 0
     * to 10 minutes randomly, so all runners won't stop at the
     * same time.
     */
    $time_limit = $this->config['runner']['runtime']; // Minimum running time
    $time_limit += random_int(0, $this->config['runner']['runtime']); // Adding additional time

    // Set the start time
    $start_time = time();

    /**************************************************************************/
    /* CHECK IF RUNNER HAS TIMED OUT                                          */
    /**************************************************************************/
    if ($this->hasRunnerTimedOut($time_limit)) {
      return;
    }

    /**************************************************************************/
    /* CHECK IF RUNNER IS ALREADY STARTED/RUNNING/IDLE                        */
    /**************************************************************************/
    if ($this->hasRunnerAlreadyBeenStarted()) {
      return;
    }

    /**************************************************************************/
    /* CHECK IF RUNNER HAS BEEN KILLED                                        */
    /**************************************************************************/
    if ($this->hasRunnerBeenKilled()) {
      return;
    }

    /**************************************************************************/
    /* START RUNNER                                                           */
    /**************************************************************************/
    $this->messenger->setRunnerStatusToStarted($this->runnerId);
    $this->outputAndOrLog('Runner started %RUNNER_ID%.', 'notice');

    // Set the last time this runner checked in, use this to
    // help determine when scripts die
    $this->messenger->setRunnerStart($this->runnerId, time());

    /**************************************************************************/
    /* POLLING                                                                */
    /**************************************************************************/
    while (time() < $start_time + $time_limit) {
      // Execute queued job.
      $this->executeOne($bufferedConsoleOutput);

      // Check if runner has been killed.
      if ($this->hasRunnerBeenKilled()) {
        return;
      }

      // Setting runner status to idle
      $this->messenger->setRunnerStatusToIdle($this->runnerId);

      // Enqueue delayed jobs, discard expired jobs
      $this->updateQueues();
    }

    // Setting the runner status to started
    $this->messenger->setRunnerStatusToStopped($this->runnerId);
    $this->outputAndOrLog('Planned shutdown %RUNNER_ID%! Waiting for restart...', 'notice');
  }

  /**
   * Pops an item from the beginning of the queue (blocking) and runs the
   * underlying command.
   *
   * @param BufferedConsoleOutput $bufferedConsoleOutput
   */
  private function executeOne(BufferedConsoleOutput $bufferedConsoleOutput) : void {
    if ($jobId = $this->messenger->popJobId($this->runnerId, $queue, $this->config['runner']['block'])) {
      $this->outputAndOrLog('');

      /************************************************************************/
      /* CHECK IF JOB IS DISCARDED                                            */
      /************************************************************************/
      if (!$job = $this->messenger->getJob($jobId)) {
        $this->outputAndOrLog('Job ID '.$job->getId().' discarded (missing) %RUNNER_ID%.', 'info');
        return;
      }

      /************************************************************************/
      /* SETTING RUNNER STATUS TO RUNNING                                     */
      /************************************************************************/
      $this->messenger->setRunnerStatusToRunning($this->runnerId);

      $this->outputAndOrLog('Found job ID '.$job->getId().' in queue "'.$queue.'" %RUNNER_ID%.', 'debug');

      /************************************************************************/
      /* CHECK IF JOB IS CANCELLED                                            */
      /************************************************************************/
      if ($job->getCancelled()) {
        $this->outputAndOrLog('Cancelled job ID '.$job->getId().' discarded (cancelled) %RUNNER_ID%.', 'info');
        $this->messenger->discardJob($job);
      }

      /************************************************************************/
      /* EXECUTE JOB USING CORRESPONDING EXECUTOR                             */
      /************************************************************************/

      $executor = $this->getExecutorForJob($job);
      try {
        // Save async job if anything fails during execution
        $this->messenger->markJobAsRunning($job, $this->runnerId);

        $executor->execute($job, $bufferedConsoleOutput);

        // Delete async job if everything went fine
        $this->messenger->discardJob($job);
      } catch (\Exception $e) {
        $this->outputAndOrLog('Job ID '.$job->getId().' failed %RUNNER_ID%. Message: '.$e->getMessage(), 'error');
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
        $this->outputAndOrLog('Job ID '.$job->getId().' invalid %RUNNER_ID%.', 'alert');
      } elseif ($executor->getReturnCode() === 0) {
        $this->outputAndOrLog('Job ID '.$job->getId().' successful %RUNNER_ID%.', 'info');
      } else {
        $this->outputAndOrLog('Job ID '.$job->getId().' erroneous %RUNNER_ID%.', 'error');
      }

      $this->outputAndOrLog('');

      /************************************************************************/
      /* CLEANUP (doctrine_orm, doctrine_dbal, monolog, swift_mailer spool)   */
      /************************************************************************/
      $this->cleaner->cleanUp();
    }
  }

  /**
   * Check if runner has already been started.
   *
   * @return bool
   */
  private function hasRunnerAlreadyBeenStarted() : bool {
    if (!\in_array($this->messenger->getRunnerStatus($this->runnerId), [Messenger::STATE_STOPPED, Messenger::STATE_TIMEOUT], TRUE)) {
      if ($this->input->getArgument('action') !== 'force') {
        $this->outputAndOrLog('Runner is already active %RUNNER_ID%.', 'debug');
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check if runner has timed out.
   *
   * @param int $time_limit
   *
   * @return bool
   */
  private function hasRunnerTimedOut(int $time_limit) : bool {
    if ($this->messenger->getRunnerStatus($this->runnerId) === Messenger::STATE_TIMEOUT) {
      $this->messenger->setRunnerStatusToStopped($this->runnerId);
      $this->outputAndOrLog('Runner reset after timeout %RUNNER_ID%.', 'info');
    }

    if ($this->messenger->getRunnerStatus($this->runnerId) !== Messenger::STATE_STOPPED) {
      $start = $this->messenger->getRunnerStart($this->runnerId);
      if ($start && ($start->getTimestamp() < time() - $this->config['runner']['timeout'] * $time_limit)) {
        if ($this->input->getArgument('action') !== 'force') {
          $this->messenger->setRunnerStatusToTimeout($this->runnerId);
          $this->outputAndOrLog('Runner has timed out %RUNNER_ID%.', 'alert');
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Check if runner has been killed.
   *
   * @return bool
   */
  private function hasRunnerBeenKilled() : bool {
    if ($this->messenger->isRunnerKilled($this->runnerId))	{
      // Make sure to unset the kill request before exiting, or
      // your runner will just keep restarting.
      $this->messenger->setRunnerKilled($this->runnerId, FALSE);
      $this->messenger->setRunnerStatusToStopped($this->runnerId);

      $this->outputAndOrLog('Kill request detected %RUNNER_ID%! Waiting for restart...', 'notice');

      return TRUE;
    }

    return FALSE;
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

  /**
   * Enqueue delayed jobs. Discard expired jobs.
   */
  private function updateQueues() : void {
    // Enqueue delayed jobs which are now due.
    if ($numOfEnqueuedJobs = $this->messenger->enqueueDelayedJobs()) {
      $this->outputAndOrLog('Enqueuing '.$numOfEnqueuedJobs.' delayed jobs %RUNNER_ID%.', 'notice');
    }

    // Remove waiting jobs which are expired now.
    if ($numOfExpiredJobs = $this->messenger->expireJobs()) {
      $this->outputAndOrLog('Expired '.$numOfExpiredJobs.' jobs %RUNNER_ID%.', 'notice');
    }
  }

}
