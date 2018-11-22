<?php

namespace HBM\AsyncWorkerBundle\Services;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleLogger {

  /**
   * @var array
   */
  private $config;

  /**
   * @var LoggerInterface
   */
  private $logger;

  /**
   * @var OutputInterface
   */
  private $output;

  /**
   * @var bool
   */
  private $loggerActive;

  /**
   * @var bool
   */
  private $outputActive;

  /**
   * @var array
   */
  private $replacements = [];

  /**
   * Logger constructor.
   *
   * @param array $config
   * @param LoggerInterface $logger
   */
  public function __construct(array $config, LoggerInterface $logger = NULL) {
    $this->config = $config;
  }

  /**
   * @param LoggerInterface $logger
   */
  public function setLogger(LoggerInterface $logger = NULL) : void {
    $this->logger = $logger;
  }

  /**
   * @param bool $flag
   */
  public function setLoggerActive(bool $flag) : void {
    $this->loggerActive = $flag;
  }

  /**
   * @param OutputInterface $output
   */
  public function setOutput(OutputInterface $output) : void {
    $this->output = $output;

    // Add output styles to log levels.
    $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
    foreach ($levels as $level) {
      $fg      = $this->config['output']['formats'][$level]['fg'] ?? NULL;
      $bg      = $this->config['output']['formats'][$level]['bg'] ?? NULL;
      $options = $this->config['output']['formats'][$level]['options'] ?? [];

      $style = new OutputFormatterStyle($fg, $bg, $options);
      $this->output->getFormatter()->setStyle('hbm_async_worker_'.$level, $style);
    }
  }

  /**
   * @param bool $flag
   */
  public function setOutputActive(bool $flag) : void {
    $this->outputActive = $flag;
  }

  /**
   * @param string $key
   * @param string $value
   */
  public function setReplacement(string $key, string $value) : void {
    $this->replacements[$key] = $value;
  }

  /**
   * @param string $key
   */
  public function unsetReplacement(string $key) : void {
    unset($this->replacements[$key]);
  }

  /**
   * Output (and log) messages.
   *
   * @param $message
   * @param $level
   */
  public function outputAndOrLog(string $message, string $level = NULL) : void {
    $message = str_replace(array_keys($this->replacements), array_values($this->replacements), $message);

    if ($this->loggerActive && $this->logger) {
      $this->logger->log($level ?: 'debug', $message);
    }

    if ($this->outputActive && $this->output) {
      if ($level) {
        $this->output->writeln('<hbm_async_worker_'.$level.'>'.$message.'</hbm_async_worker_'.$level.'>');
      } else {
        $this->output->writeln($message);
      }
    }
  }

}
