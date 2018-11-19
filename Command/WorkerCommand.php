<?php

namespace HBM\AsyncBundle\Command;

use HBM\AsyncBundle\Async\Executor\AbstractExecutor;
use HBM\AsyncBundle\Async\Job\AbstractAsyncJob;
use HBM\AsyncBundle\Services\Messenger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Templating\EngineInterface;

class WorkerCommand extends Command {

  /**
   * @var string
   */
  public const NAME = 'hbm:async:worker';

  /**
   * @var array
   */
  private $config;

  /**
   * @var Messenger
   */
  private $messenger;

  /**
   * @var LoggerInterface
   */
  private $logger;

  /**
   * @var \Swift_Mailer
   */
  private $mailer;

  /**
   * @var EngineInterface
   */
  private $templating;

  /**
   * @var string
   */
  private $workerId;

  /**
   * @var OutputInterface
   */
  private $output;

  /**
   * @var InputInterface
   */
  private $input;

  public function __construct(array $config, Messenger $messenger, LoggerInterface $logger, \Swift_Mailer $mailer = NULL, EngineInterface $templating = NULL) {
    $this->config = $config;

    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->mailer = $mailer;
    $this->templating = $templating;

    parent::__construct();
  }

  protected function configure() {
    $this
      ->setName(self::NAME)
      ->addArgument('worker-id', InputArgument::REQUIRED, 'The ID of the worker. Could be any integer/string. Just to identify this worker.')
      ->addArgument('action', InputArgument::OPTIONAL, 'The action to perform. Possible values are: start, kill, force. Default: "start"')
      ->addOption('log', NULL, InputOption::VALUE_NONE, 'Log to channel "cc2_worker" instead of writing to console output.')
      ->addOption('console', NULL, InputOption::VALUE_NONE, 'Output command output to worker console.')
      ->setDescription('Run the worker.');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->workerId = $input->getArgument('worker-id');

    $this->input = $input;
    $this->output = $output;

    $this->prettifyOutput();

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
    /* CHECK REDIS                                                            */
    /**************************************************************************/
    if (!$this->messenger->isAvailable()) {
      $this->outputAndLog('Redis is not available.', 'critical');
      return;
    }

    /**************************************************************************/
    /* KILL WORKER                                                            */
    /**************************************************************************/
    if ($input->getArgument('action') === 'kill') {
      $this->messenger->setWorkerKilled($this->workerId, TRUE);
      $this->outputAndLog('Sent kill request to worker with ID '.$this->workerId.'.', 'info');
      return;
    }

    /**************************************************************************/
    /* RUN SINGLE COMMAND                                                     */
    /**************************************************************************/
    if ($input->getArgument('action') === 'single') {
      $this->outputAndLog('Running a single job using worker with ID '.$this->workerId.'.', 'info');
      $this->executeOne();
      return;
    }

    /**************************************************************************/
    /* START TIME                                                             */
    /**************************************************************************/
    /*
     * We'll set our base time, which is one hour (in seconds).
     * Once we have our base time, we'll add anywhere between 0
     * to 10 minutes randomly, so all workers won't stop at the
     * same time.
     */
    $time_limit = $this->config['worker']['runtime']; // Minimum running time
    $time_limit += random_int(0, $this->config['worker']['runtime']); // Adding additional time

    // Set the start time
    $start_time = time();

    /**************************************************************************/
    /* CHECK IF WORKER HAS TIMED OUT                                          */
    /**************************************************************************/
    if ($this->hasWorkerTimedOut($time_limit)) {
      return;
    }

    /**************************************************************************/
    /* CHECK IF WORKER IS ALREADY STARTED/RUNNING/IDLE                        */
    /**************************************************************************/
    if ($this->hasWorkerAlreadyBeenStarted()) {
      return;
    }

    /**************************************************************************/
    /* CHECK IF WORKER HAS BEEN KILLED                                        */
    /**************************************************************************/
    if ($this->hasWorkerBeenKilled()) {
      return;
    }

    /**************************************************************************/
    /* START WORKER                                                           */
    /**************************************************************************/
    $this->messenger->setWorkerStatusToStarted($this->workerId);
    $this->outputAndLog('Worker started (worker ID '.$this->workerId.').', 'info');

    // Set the last time this worker checked in, use this to
    // help determine when scripts die
    $this->messenger->setWorkerStart($this->workerId, time());

    /**************************************************************************/
    /* RUN WORKER                                                             */
    /**************************************************************************/
    while (time() < $start_time + $time_limit) {
      // Execute queued job.
      $this->executeOne();

      // Check if worker has been killed.
      if ($this->hasWorkerBeenKilled()) {
        return;
      }

      // Setting worker status to idle
      $this->messenger->setWorkerStatusToIdle($this->workerId);

      // Enqueue delayed jobs which are now due.
      if ($numOfEnqueuedJobs = $this->messenger->enqueueDelayedJobs()) {
        $this->outputAndLog('Enqueuing '.$numOfEnqueuedJobs.' delayed jobs (worker ID '.$this->workerId.').', 'info');
      }
    }

    // Setting the worker status to started
    $this->messenger->setWorkerStatusToStopped($this->workerId);
    $this->outputAndLog('Planned shutdown (worker ID '.$this->workerId.')! Waiting for restart...', 'info');
  }

  /**
   * Pops an item from the beginning of the queue (blocking) and runs the
   * underlying command.
   */
  private function executeOne() : void {
    if ($asyncJobId = $this->messenger->popJobId($this->workerId, $queue, $this->config['worker']['block'])) {
      $this->output->writeln('');

      if (!$asyncJob = $this->messenger->getJob($asyncJobId)) {
        $this->outputAndLog('Job ID '.$asyncJob->getId().' discarded (missing) (worker ID '.$this->workerId.').', 'info');
        return;
      }

      /************************************************************************/
      /* SETTING WORKER STATUS TO RUNNING                                     */
      /************************************************************************/
      $this->messenger->setWorkerStatusToRunning($this->workerId);

      $this->outputAndLog('Found job ID '.$asyncJob->getId().' in queue "'.$queue.'" (worker ID '.$this->workerId.').', 'debug');

      /************************************************************************/
      /* CHECK IF JOB IS CANCELLED                                            */
      /************************************************************************/
      if ($asyncJob->getCancelled()) {
        $this->outputAndLog('Cancelled job ID '.$asyncJob->getId().' discarded (cancelled) (worker ID '.$this->workerId.').', 'info');
        $this->messenger->discardJob($asyncJob);
      }

      /************************************************************************/
      /* EXECUTE JOB USING CORRESPONDING EXECUTOR                             */
      /************************************************************************/

      $executor = $this->getExecutorForJob($asyncJob);
      try {
        // Save async job if anything fails during execution
        $this->messenger->markJobAsRunning($asyncJob, $this->workerId);

        $executor->execute($asyncJob);

        // Delete async job if everything went fine
        $this->messenger->discardJob($asyncJob);
      } catch (\Exception $e) {
        $this->outputAndLog('Job ID '.$asyncJob->getId().' failed (worker ID '.$this->workerId.'). Message: '.$e->getMessage(), 'error');
        $this->messenger->markJobAsFailed($asyncJob);
      }

      /************************************************************************/
      /* SEND INFORMER MAIL                                                   */
      /************************************************************************/
      $this->informAboutJob($asyncJob, $executor->getReturnData());

      /************************************************************************/
      /* OUTPUT RESULT                                                        */
      /************************************************************************/
      if ($executor->getReturnCode() === NULL) {
        $this->outputAndLog('Job ID '.$asyncJob->getId().' invalid (worker ID '.$this->workerId.').', 'alert');
      } elseif ($executor->getReturnCode() === 0) {
        $this->outputAndLog('Job ID '.$asyncJob->getId().' successful (worker ID '.$this->workerId.').', 'info');
      } else {
        $this->outputAndLog('Job ID '.$asyncJob->getId().' erroneous (worker ID '.$this->workerId.').', 'error');
      }

      $this->output->writeln('');
    }
  }

  /**
   * Inform about job execution via email.
   *
   * @param AbstractAsyncJob $asyncJob
   * @param array $returnData
   *
   * @return bool
   */
  private function informAboutJob(AbstractAsyncJob $asyncJob, array $returnData) : bool {
    $email = $this->config['mail']['to'];
    if ($asyncJob->getEmail()) {
      $email = $asyncJob->getEmail();
    }

    // Check if email should be sent.
    if ($email && $this->mailer && $this->config['mail']['fromAddress'] && $asyncJob->getInform()) {
      $message = new \Swift_Message();
      $message->setTo($email);
      $message->setFrom($this->config['mail']['fromAddress'], $this->config['mail']['fromName']);

      // Render subject.
      $subject = $this->renderTemplateChain([
        $asyncJob->getTemplateFolder().':body.text.twig',
        'HBMAsyncBundle:subject.text.twig',
      ], $returnData);
      $message->setSubject($subject);

      // Render text body.
      $body = $this->renderTemplateChain([
        $asyncJob->getTemplateFolder().':body.text.twig',
        'HBMAsyncBundle:body.text.twig',
      ], $returnData);
      $message->setBody($body, 'text/plain');

      // Render html body.
      $body = $this->renderTemplateChain([
        $asyncJob->getTemplateFolder().':body.html.twig',
        'HBMAsyncBundle:body.html.twig',
      ], $returnData);
      if ($body) {
        $message->setBody($body, 'text/html');
      }

      $this->mailer->send($message);

      $this->outputAndLog('Informing '.$email.' about job ID '.$asyncJob->getId().' (worker ID '.$this->workerId.').', 'info');

      return FALSE;
    }

    return FALSE;
  }

  /**
   * Render the first existing template.
   *
   * @param array $templates
   * @param array $data
   * @param string|NULL $default
   *
   * @return null|string
   */
  private function renderTemplateChain(array $templates, array $data, string $default = NULL) : ?string {
    foreach ($templates as $template) {
      try {
        if ($this->templating->exists($template)) {
          return $this->templating->render($template, $data);
        }
      } catch (\Throwable $e) {
      }
    }

    return $default;
  }

  /**
   * Check if worker has already been started.
   *
   * @return bool
   */
  private function hasWorkerAlreadyBeenStarted() : bool {
    if (!\in_array($this->messenger->getWorkerStatus($this->workerId), [Messenger::STATUS_STOPPED, Messenger::STATUS_TIMEOUT], TRUE)) {
      if ($this->input->getArgument('action') !== 'force') {
        $this->outputAndLog('Worker is already running (worker ID '.$this->workerId.').', 'debug');
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check if worker has timed out.
   *
   * @param int $time_limit
   *
   * @return bool
   */
  private function hasWorkerTimedOut(int $time_limit) : bool {
    if ($this->messenger->getWorkerStatus($this->workerId) === Messenger::STATUS_TIMEOUT) {
      $this->messenger->setWorkerStatusToStopped($this->workerId);
      $this->outputAndLog('Worker reset after timeout (worker ID '.$this->workerId.').', 'info');
    }

    if ($this->messenger->getWorkerStatus($this->workerId) !== Messenger::STATUS_STOPPED) {
      if ($this->messenger->getWorkerStart($this->workerId) < time() - $this->config['worker']['timeout'] * $time_limit) {
        if ($this->input->getArgument('action') !== 'force') {
          $this->messenger->setWorkerStatusToTimeout($this->workerId);
          $this->outputAndLog('Worker has timed out (worker ID '.$this->workerId.').', 'alert');
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Check if worker has been killed.
   *
   * @return bool
   */
  private function hasWorkerBeenKilled() : bool {
    if ($this->messenger->isWorkerKilled($this->workerId))	{
      // Make sure to unset the kill request before exiting, or
      // your worker will just keep restarting.
      $this->messenger->setWorkerKilled($this->workerId, FALSE);
      $this->messenger->setWorkerStatusToStopped($this->workerId);

      $this->outputAndLog('Kill request detected (worker ID '.$this->workerId.')! Waiting for restart...', 'notice');

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Output (and log) messages.
   *
   * @param $message
   * @param $level
   */
  private function outputAndLog($message, $level) : void {
    if ($this->input->getOption('log')) {
      $this->logger->log($level, $message);
    }

    $this->output->writeln('<hbm_async_'.$level.'>'.$message.'</hbm_async_'.$level.'>');
  }

  /**
   * Add output styles to log levels.
   */
  private function prettifyOutput() : void {
    $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    foreach ($levels as $level) {
      $fg = $this->config['output']['formats'][$level]['fg'] ?? NULL;
      $bg = $this->config['output']['formats'][$level]['bg'] ?? NULL;
      $options    = $this->config['output']['formats'][$level]['options'] ?? [];

      $style = new OutputFormatterStyle($fg, $bg, $options);
      $this->output->getFormatter()->setStyle('hbm_async_'.$level, $style);
    }
  }

  /**
   * Create Executor instance.
   *
   * @param AbstractAsyncJob $asyncJob
   *
   * @return AbstractExecutor
   */
  private function getExecutorForJob(AbstractAsyncJob $asyncJob) : AbstractExecutor {
    $executorClass = $asyncJob->getExecutorClass();

    return new $executorClass($this->getApplication(), $this->config);
  }

}
