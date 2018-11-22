<?php

namespace HBM\AsyncWorkerBundle\Services;

use HBM\AsyncWorkerBundle\AsyncWorker\Job\AbstractJob;
use HBM\AsyncWorkerBundle\Traits\ConsoleLoggerTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Templating\EngineInterface;

class Informer {

  use ConsoleLoggerTrait;

  /**
   * @var array
   */
  private $config;

  /**
   * @var \Swift_Mailer
   */
  private $mailer;

  /**
   * @var EngineInterface
   */
  private $templating;

  /**
   * Messenger constructor.
   *
   * @param array $config
   * @param LoggerInterface|NULL $logger
   */

  /**
   * Informer constructor.
   *
   * @param array $config
   * @param \Swift_Mailer $mailer
   * @param EngineInterface $templating
   * @param ConsoleLogger $consoleLogger
   */
  public function __construct(array $config, \Swift_Mailer $mailer, EngineInterface $templating, ConsoleLogger $consoleLogger) {
    $this->config = $config;
    $this->mailer = $mailer;
    $this->templating = $templating;
    $this->consoleLogger = $consoleLogger;
  }

  /**
   * Inform about job execution via email.
   *
   * @param AbstractJob $job
   * @param array $returnData
   *
   * @return bool
   */
  public function informAboutJob(AbstractJob $job, array $returnData) : bool {
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
        '@HBMAsyncWorker/subject.text.twig',
      ], $returnData);
      $message->setSubject($subject ?: $this->config['mail']['subject']);

      // Render text body.
      $bodyText = $this->renderTemplateChain([
        $job->getTemplateFolder().'body.text.twig',
        '@HBMAsyncWorker/body.text.twig',
      ], $returnData);
      $message->setBody($bodyText, 'text/plain');

      // Render html body.
      $bodyHtml = $this->renderTemplateChain([
        $job->getTemplateFolder().'body.html.twig',
        '@HBMAsyncWorker/body.html.twig',
      ], $returnData);

      // Fallback to nl2br of the text version.
      if (!$bodyHtml && $this->config['mail']['text2html']) {
        $bodyHtml = nl2br($bodyText);
      }
      if ($bodyHtml) {
        $message->addPart($bodyHtml, 'text/html');
      }

      $this->mailer->send($message);

      $this->outputAndOrLog('Informing '.$email.' about job ID '.$job->getId().' %RUNNER_ID%.', 'info');

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

}
