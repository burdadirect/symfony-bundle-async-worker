<?php

namespace HBM\AsyncWorkerBundle\Output;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Output\Output;

class BufferedStreamOutput extends Output {

  /**
   * @var BufferedOutput
   */
  private $bufferedOutput;

  /**
   * @var StreamOutput
   */
  private $streamOutput;

  /**
   * BufferedStreamOutput constructor.
   *
   * @param BufferedOutput $bufferedOutput
   * @param StreamOutput|NULL $streamOutput
   */
  public function __construct(BufferedOutput $bufferedOutput, StreamOutput $streamOutput = NULL) {
    parent::__construct(self::VERBOSITY_NORMAL, FALSE, NULL);

    $this->bufferedOutput = $bufferedOutput;
    $this->streamOutput = $streamOutput;
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
   * Set streamOutput.
   *
   * @param StreamOutput|NULL $streamOutput
   *
   * @return self
   */
  public function setStreamOutput(StreamOutput $streamOutput = NULL) : self {
    $this->streamOutput = $streamOutput;

    return $this;
  }

  /**
   * Get consoleOutput.
   *
   * @return StreamOutput|null
   */
  public function getStreamOutput() : ?StreamOutput {
    return $this->streamOutput;
  }

  /**
   * @param string $message
   * @param bool $newline
   */
  protected function doWrite($message, $newline) {
    if ($this->bufferedOutput) {
      $this->bufferedOutput->write($message, $newline);
    }
    if ($this->streamOutput) {
      $this->streamOutput->write($message, $newline);
    }
  }

}
