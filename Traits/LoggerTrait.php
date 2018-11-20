<?php

namespace HBM\AsyncWorkerBundle\Traits;

use HBM\AsyncWorkerBundle\Services\Logger;
use Symfony\Component\Console\Output\OutputInterface;

trait LoggerTrait {

  /**
   * @var Logger
   */
  private $logger;

  /**
   * Set logger.
   *
   * @param Logger $logger
   */
  public function setLogger(Logger $logger = NULL) : void {
    $this->logger = $logger;
  }

  public function setLogChannel(bool $active) : void {
    if ($this->logger) {
      $this->logger->setChannel($active);
    }
  }

  /**
   * Set log output.
   *
   * @param OutputInterface $output
   */
  public function setLogOutput(OutputInterface $output) : void {
    if ($this->logger) {
      $this->logger->setOutput($output);
    }
  }

  /**
   * Set log replacement.
   *
   * @param string $key
   * @param string $value
   */
  public function setLogReplacement(string $key, string $value) : void {
    if ($this->logger) {
      $this->logger->setReplacement($key, $value);
    }
  }

  /**
   * Set log replacement.
   *
   * @param string $key
   */
  public function unsetLogReplacement(string $key) : void {
    if ($this->logger) {
      $this->logger->unsetReplacement($key);
    }
  }

  /**
   * Output and or log message.
   *
   * @param string $message
   * @param string $level
   */
  public function outputAndOrLog(string $message, string $level = NULL) : void {
    if ($this->logger) {
      $this->logger->handle($message, $level);
    }
  }

}
