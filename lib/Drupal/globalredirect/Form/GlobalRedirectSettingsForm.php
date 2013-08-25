<?php
/**
 * @file
 * This is the GlobalRedirect admin include which provides an interface to global redirect to change some of the default settings
 * Contains \Drupal\globalredirect\Form\GlobalRedirectSettingsForm.
 */

namespace Drupal\globalredirect\Form;

use Drupal\globalredirect\Controller\GlobalRedirectController;
use Drupal\system\SystemConfigFormBase;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form to configure module settings.
 */
class GlobalRedirectSettingsForm extends SystemConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'globalredirect_settings';
  }

}