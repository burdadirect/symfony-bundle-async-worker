<?php

namespace HBM\AsyncWorkerBundle\Service;

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
    $this->setLogger($logger);
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
   * @param string|array $message
   * @param string $level
   */
  public function outputAndOrLog($message, string $level = NULL) : void {
    $parts = ['PREFIX', 'JOB', 'LOG', 'RUNNER', 'POSTFIX'];

    $data = array_fill_keys($parts, NULL);
    if (\is_array($message)) {
      $data = array_merge($data, $message);
    } else {
      $data['LOG'] = $message;
    }

    if (isset($data['RUNNER_ID']) || isset($this->replacements['RUNNER_ID'])) {
      $data['RUNNER'] = $this->config['logger']['runner'];
    }
    if (isset($data['JOB_ID']) || isset($this->replacements['JOB_ID'])) {
      $data['JOB'] = $this->config['logger']['job'];
    }

    // Fill up missing replacements.
    foreach ($this->replacements as $key => $value) {
      if (!isset($data[$key])) {
        $data[$key] = $value;
      }
    }

    // Replace in format.
    if ($data['LOG'] === NULL) {
      $formattedMessage = '';
    } else {
      $formattedMessage = $this->config['logger']['format'];
      foreach ($data as $key => $value) {
        $delimitedKey = $this->config['logger']['delimiter'].$key.$this->config['logger']['delimiter'];
        $formattedMessage = str_replace($delimitedKey, $value, $formattedMessage);
      }
    }


    if ($this->loggerActive && $this->logger) {
      $this->logger->log($level ?: 'debug', $formattedMessage);
    }

    if ($this->outputActive && $this->output) {
      if ($level) {
        $this->output->writeln('<hbm_async_worker_'.$level.'>'.$formattedMessage.'</hbm_async_worker_'.$level.'>');
      } else {
        $this->output->writeln($formattedMessage);
      }
    }
  }

}
