<?php

namespace HBM\AsyncWorkerBundle\Services;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

class Logger {

  /**
   * @var array
   */
  private $config;

  /**
   * @var LoggerInterface
   */
  private $logger;

  /**
   * @var bool
   */
  private $channel = TRUE;

  /**
   * @var OutputInterface
   */
  private $output;

  /**
   * @var array
   */
  private $replacements = [];

  /**
   * Logger constructor.
   *
   * @param array $config
   * @param LoggerInterface|NULL $logger
   */
  public function __construct(array $config, LoggerInterface $logger = NULL) {
    $this->config = $config;
    $this->logger = $logger;
  }

  /**
   * @param bool $channel
   */
  public function setChannel(bool $channel) : void {
    $this->channel = $channel;
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
  public function handle(string $message, string $level = NULL) : void {
    $message = str_replace(array_keys($this->replacements), array_values($this->replacements), $message);

    if ($this->logger && $this->channel && $level) {
      $this->logger->log($level, $message);
    }

    if ($this->output) {
      if ($level) {
        $this->output->writeln('<hbm_async_worker_'.$level.'>'.$message.'</hbm_async_worker_'.$level.'>');
      } else {
        $this->output->writeln($message);
      }
    }
  }

}
