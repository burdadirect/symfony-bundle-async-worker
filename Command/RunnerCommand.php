<?php

namespace HBM\AsyncWorkerBundle\Command;

use HBM\AsyncWorkerBundle\AsyncWorker\Executor\AbstractExecutor;
use HBM\AsyncWorkerBundle\AsyncWorker\Job\AbstractJob;
use HBM\AsyncWorkerBundle\Services\Messenger;
use LongRunning\Core\Cleaner;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Templating\EngineInterface;

class RunnerCommand extends ContainerAwareCommand {

  /**
   * @var string
   */
  public const NAME = 'hbm:async_worker:run';

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
   * @var Cleaner
   */
  private $cleaner;

  /**
   * @var string
   */
  private $runnerId;

  /**
   * @var OutputInterface
   */
  private $output;

  /**
   * @var InputInterface
   */
  private $input;

  public function __construct(array $config, Messenger $messenger, Cleaner $cleaner, LoggerInterface $logger, \Swift_Mailer $mailer = NULL, EngineInterface $templating = NULL) {
    $this->config = $config;

    $this->messenger = $messenger;
    $this->cleaner = $cleaner;
    $this->logger = $logger;
    $this->mailer = $mailer;
    $this->templating = $templating;

    parent::__construct();
  }

  protected function configure() {
    $this
      ->setName(self::NAME)
      ->addArgument('runner', InputArgument::REQUIRED, 'The ID of the runner. Could be any integer/string. Just to identify this runner.')
      ->addArgument('action', InputArgument::OPTIONAL, 'The action to perform. Possible values are: start, kill, force. Default: "start"')
      ->addOption('log', NULL, InputOption::VALUE_NONE, 'Log to channel instead of writing to console output.')
      ->addOption('console', NULL, InputOption::VALUE_NONE, 'Output command output to runner console.')
      ->setDescription('Run the runner.');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $this->runnerId = $input->getArgument('runner-id');

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
    /* KILL RUNNER                                                            */
    /**************************************************************************/
    if ($input->getArgument('action') === 'kill') {
      $this->messenger->setRunnerKilled($this->runnerId, TRUE);
      $this->outputAndLog('Sent kill request %RUNNER_ID%.', 'info');
      return;
    }

    /**************************************************************************/
    /* RUN SINGLE COMMAND                                                     */
    /**************************************************************************/
    if ($input->getArgument('action') === 'single') {
      $this->outputAndLog('Running a single job %RUNNER_ID%.', 'info');
      $this->executeOne();
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
    $this->outputAndLog('Runner started %RUNNER_ID%.', 'info');

    // Set the last time this runner checked in, use this to
    // help determine when scripts die
    $this->messenger->setRunnerStart($this->runnerId, time());

    /**************************************************************************/
    /* POLLING                                                                */
    /**************************************************************************/
    while (time() < $start_time + $time_limit) {
      // Execute queued job.
      $this->executeOne();

      // Check if runner has been killed.
      if ($this->hasRunnerBeenKilled()) {
        return;
      }

      // Setting runner status to idle
      $this->messenger->setRunnerStatusToIdle($this->runnerId);

      // Enqueue delayed jobs which are now due.
      if ($numOfEnqueuedJobs = $this->messenger->enqueueDelayedJobs()) {
        $this->outputAndLog('Enqueuing '.$numOfEnqueuedJobs.' delayed jobs %RUNNER_ID%.', 'info');
      }
    }

    // Setting the runner status to started
    $this->messenger->setRunnerStatusToStopped($this->runnerId);
    $this->outputAndLog('Planned shutdown %RUNNER_ID%! Waiting for restart...', 'info');
  }

  /**
   * Pops an item from the beginning of the queue (blocking) and runs the
   * underlying command.
   */
  private function executeOne() : void {
    if ($jobId = $this->messenger->popJobId($this->runnerId, $queue, $this->config['runner']['block'])) {
      $this->output->writeln('');

      if (!$job = $this->messenger->getJob($jobId)) {
        $this->outputAndLog('Job ID '.$job->getId().' discarded (missing) %RUNNER_ID%.', 'info');
        return;
      }

      /************************************************************************/
      /* SETTING RUNNER STATUS TO RUNNING                                     */
      /************************************************************************/
      $this->messenger->setRunnerStatusToRunning($this->runnerId);

      $this->outputAndLog('Found job ID '.$job->getId().' in queue "'.$queue.'" %RUNNER_ID%.', 'debug');

      /************************************************************************/
      /* CHECK IF JOB IS CANCELLED                                            */
      /************************************************************************/
      if ($job->getCancelled()) {
        $this->outputAndLog('Cancelled job ID '.$job->getId().' discarded (cancelled) %RUNNER_ID%.', 'info');
        $this->messenger->discardJob($job);
      }

      /************************************************************************/
      /* EXECUTE JOB USING CORRESPONDING EXECUTOR                             */
      /************************************************************************/

      $executor = $this->getExecutorForJob($job);
      try {
        // Save async job if anything fails during execution
        $this->messenger->markJobAsRunning($job, $this->runnerId);

        $executor->execute($job);

        // Delete async job if everything went fine
        $this->messenger->discardJob($job);
      } catch (\Exception $e) {
        $this->outputAndLog('Job ID '.$job->getId().' failed %RUNNER_ID%. Message: '.$e->getMessage(), 'error');
        $this->messenger->markJobAsFailed($job);
      }

      /************************************************************************/
      /* SEND INFORMER MAIL                                                   */
      /************************************************************************/
      $this->informAboutJob($job, $executor->getReturnData());

      /************************************************************************/
      /* OUTPUT RESULT                                                        */
      /************************************************************************/
      if ($executor->getReturnCode() === NULL) {
        $this->outputAndLog('Job ID '.$job->getId().' invalid %RUNNER_ID%.', 'alert');
      } elseif ($executor->getReturnCode() === 0) {
        $this->outputAndLog('Job ID '.$job->getId().' successful %RUNNER_ID%.', 'info');
      } else {
        $this->outputAndLog('Job ID '.$job->getId().' erroneous %RUNNER_ID%.', 'error');
      }

      $this->output->writeln('');

      /************************************************************************/
      /* CLEANUP (doctrine_orm, doctrine_dbal, monolog, swift_mailer spool)   */
      /************************************************************************/
      $this->cleaner->cleanUp();
    }
  }

  /**
   * Inform about job execution via email.
   *
   * @param AbstractJob $job
   * @param array $returnData
   *
   * @return bool
   */
  private function informAboutJob(AbstractJob $job, array $returnData) : bool {
    $email = $this->config['mail']['to'];
    if ($job->getEmail()) {
      $email = $job->getEmail();
    }

    // Check if email should be sent.
    if ($email && $this->mailer && $this->config['mail']['fromAddress'] && $job->getInform()) {
      $message = new \Swift_Message();
      $message->setTo($email);
      $message->setFrom($this->config['mail']['fromAddress'], $this->config['mail']['fromName']);

      // Render subject.
      $subject = $this->renderTemplateChain([
        $job->getTemplateFolder().'subject.text.twig',
        '@HBMAsync/subject.text.twig',
      ], $returnData);
      $message->setSubject($subject);

      // Render text body.
      $body = $this->renderTemplateChain([
        $job->getTemplateFolder().'body.text.twig',
        '@HBMAsync/body.text.twig',
      ], $returnData);
      $message->setBody($body, 'text/plain');

      // Render html body.
      $body = $this->renderTemplateChain([
        $job->getTemplateFolder().'body.html.twig',
        '@HBMAsync/body.html.twig',
      ], $returnData);
      if ($body) {
        $message->setBody($body, 'text/html');
      }

      $this->mailer->send($message);
      var_dump($message->getSubject());
      var_dump($message->getBody());

      $this->outputAndLog('Informing '.$email.' about job ID '.$job->getId().' %RUNNER_ID%.', 'info');

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
   * Check if runner has already been started.
   *
   * @return bool
   */
  private function hasRunnerAlreadyBeenStarted() : bool {
    if (!\in_array($this->messenger->getRunnerStatus($this->runnerId), [Messenger::STATUS_STOPPED, Messenger::STATUS_TIMEOUT], TRUE)) {
      if ($this->input->getArgument('action') !== 'force') {
        $this->outputAndLog('Runner is already active %RUNNER_ID%.', 'debug');
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
    if ($this->messenger->getRunnerStatus($this->runnerId) === Messenger::STATUS_TIMEOUT) {
      $this->messenger->setRunnerStatusToStopped($this->runnerId);
      $this->outputAndLog('Runner reset after timeout %RUNNER_ID%.', 'info');
    }

    if ($this->messenger->getRunnerStatus($this->runnerId) !== Messenger::STATUS_STOPPED) {
      $start = $this->messenger->getRunnerStart($this->runnerId);
      if ($start && ($start->getTimestamp() < time() - $this->config['runner']['timeout'] * $time_limit)) {
        if ($this->input->getArgument('action') !== 'force') {
          $this->messenger->setRunnerStatusToTimeout($this->runnerId);
          $this->outputAndLog('Runner has timed out %RUNNER_ID%.', 'alert');
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

      $this->outputAndLog('Kill request detected %RUNNER_ID%! Waiting for restart...', 'notice');

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
    $searchReplace = [
      '%RUNNER_ID%' => '(runner id "'.$this->runnerId.'")',
    ];
    $message = str_replace(array_keys($searchReplace), array_values($searchReplace), $message);

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
   * @param AbstractJob $job
   *
   * @return AbstractExecutor
   */
  private function getExecutorForJob(AbstractJob $job) : AbstractExecutor {
    $executorClass = $job->getExecutorClass();

    return new $executorClass($this->getApplication(), $this->config);
  }

}
