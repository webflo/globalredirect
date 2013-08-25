<?php

/**
 * @file
 * Definition of Drupal\globalredirect\EventSubscriber\GlobalRedirectSubscriber.
 */

namespace Drupal\globalredirect\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * KernelEvents::REQUEST subscriber for redirecting q=path/to/page requests.
 */
class GlobalRedirectSubscriber implements EventSubscriberInterface {

  /**
   * Detects a q=path/to/page style request and performs a redirect.
   *
   * @param Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The Event to process.
   */
  public function GlobalRedirectCleanUrls(GetResponseEvent $event) {
    global $base_url;
    $request = $event->getRequest();
    $query = $request->query->all();
    if (!empty($query['q'])) {
      $legacy_path = $query['q'];
      unset($query['q']);
      // Do not use url(), because we want to redirect to the exact q value
      // without invoking hooks or adjusting for aliases or language.
      $url = $base_url . '/index.php/' . drupal_encode_path($legacy_path);
      if ($query) {
        $url .= '?' . drupal_http_build_query($query);
      }
      $event->setResponse(new RedirectResponse($url, 301));
    }
  }

  /**
   * Detects a url with an ending slash (/) and removes it
   *
   * @param Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The Event to process.
   */
  public function GlobalRedirectDeslash(GetResponseEvent $event) {
    global $base_url;
    $request = $event->getRequest();
    $query = $request->query->all();

    $pathInfo = $request->getPathInfo();
    // paths would commonly begin with a forward slash (/) so we'll trim it
    // if it's there as we already have it in the new url we are building
    if (substr($pathInfo, 0, 1) === '/')
      $pathInfo = substr($pathInfo, 1);

    if (substr($pathInfo, -1, 1) === '/') {
      $pathDeslashed = substr($pathInfo, 0, -1);
      $url = $base_url . '/index.php/' . drupal_encode_path($pathDeslashed);
      if ($query) {
        $url .= '?' . drupal_http_build_query($query);
      }
      $event->setResponse(new RedirectResponse($url, 301));
    }
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    // Run earlier than all the listeners in
    // Drupal\Core\EventSubscriber\PathSubscriber, because there is no need
    // to decode the incoming path, resolve language, etc. if the real path
    // information is in the query string.
    $events[KernelEvents::REQUEST][] = array('GlobalRedirectCleanUrls', 500);
    $events[KernelEvents::REQUEST][] = array('GlobalRedirectDeslash', 500);
    return $events;
  }
}
