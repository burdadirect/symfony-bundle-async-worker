<?php

namespace Tests\HBM\AsyncWorkerBundle\Command;

use PHPUnit\Framework\TestCase;

abstract class AbstractCommandTestCase extends TestCase {

  protected function removeAnsiEscapeSequences($subject) {
    $subject = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '', $subject);
    $subject = preg_replace('/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/', '', $subject);
    $subject = preg_replace('/[\x03|\x1a]/', '', $subject);

    return $subject;
  }

}
