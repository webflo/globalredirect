<?php

/**
 * @file
 * Definition of Drupal\globalredirect\EventSubscriber\GlobalredirectSubscriber.
 */

namespace Drupal\globalredirect\EventSubscriber;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use Drupal\globalredirect\GlobalredirectRedirectionManager;

/**
 * Redirects for controller requests.
 */
class GlobalredirectSubscriber implements EventSubscriberInterface {

  /**
   * The manager used to check the redictions.
   *
   * @var Drupal\globalredirect\GlobalredirectRedirectionManager
   */
  protected $manager;

  /**
   * Construct the GlobalredirectSubscriber.
   *
   * @param Drupal\globalredirect\GlobalredirectRedirectionManager $manager
   *   The manager used to check the redirections.
   */
  public function __construct(GlobalredirectRedirectionManager $manager) {
    $this->manager = $manager;
  }

  /**
   * Redirection magic.
   *
   * @param Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The Event to process.
   */
  public function onKernelRequestRedirectionCheck(GetResponseEvent $event) {
    if ($this->manager->isActive()) {
      $redirection = $this->manager->getRedirection();
      if ($redirection) {
        return $redirection->send();
      }
    }
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('onKernelRequestRedirectionCheck', 10);
    return $events;
  }

}
