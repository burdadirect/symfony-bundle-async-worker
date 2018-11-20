<?php

namespace HBM\AsyncWorkerBundle\Output;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\Output;

class BufferedConsoleOutput extends Output {

  /**
   * @var BufferedOutput
   */
  private $bufferedOutput;

  /**
   * @var ConsoleOutput
   */
  private $consoleOutput;

  /**
   * BufferedConsoleOutput constructor.
   *
   * @param BufferedOutput $bufferedOutput
   * @param ConsoleOutput|NULL $consoleOutput
   */
  public function __construct(BufferedOutput $bufferedOutput, ConsoleOutput $consoleOutput = NULL) {
    parent::__construct(self::VERBOSITY_NORMAL, FALSE, NULL);

    $this->bufferedOutput = $bufferedOutput;
    $this->consoleOutput = $consoleOutput;
  }

  /**
   * Set bufferedOutput.
   *
   * @param BufferedOutput $bufferedOutput
   *
   * @return self
   */
  public function setBufferedOutput(BufferedOutput $bufferedOutput) : self {
    $this->bufferedOutput = $bufferedOutput;

    return $this;
  }

  /**
   * Get bufferedOutput.
   *
   * @return BufferedOutput
   */
  public function getBufferedOutput() : BufferedOutput {
    return $this->bufferedOutput;
  }

  /**
   * Set consoleOutput.
   *
   * @param ConsoleOutput|NULL $consoleOutput
   *
   * @return self
   */
  public function setConsoleOutput(ConsoleOutput $consoleOutput = NULL) : self {
    $this->consoleOutput = $consoleOutput;

    return $this;
  }

  /**
   * Get consoleOutput.
   *
   * @return ConsoleOutput|null
   */
  public function getConsoleOutput() : ?ConsoleOutput {
    return $this->consoleOutput;
  }

  /**
   * @param string $message
   * @param bool $newline
   */
  protected function doWrite($message, $newline) {
    if ($this->bufferedOutput) {
      $this->bufferedOutput->write($message, $newline);
    }
    if ($this->consoleOutput) {
      $this->consoleOutput->write($message, $newline);
    }
  }

}
