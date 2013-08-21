<?php

/**
 * @file
 * Contains \Drupal\globalredirect\GlobalredirectSettingsForm.
 */

namespace Drupal\globalredirect;

use Drupal\system\SystemConfigFormBase;

/**
 * Configure globalredirect settings for this site.
 */
class GlobalredirectSettingsForm extends SystemConfigFormBase {

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormID() {
    return 'globalredirect_admin_settings';
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::buildForm().
   */
  public function buildForm(array $form, array &$form_state) {
    $config = $this->configFactory->get('globalredirect.settings');

    $form['deslash'] = array(
      '#type' => 'checkbox',
      '#title' => t('Deslash'),
      '#description' => t('If enabled, this option will remove the trailing slash from requests. This stops requests such as <code>example.com/node/1/</code> failing to match the corresponding alias and can cause duplicate content. On the other hand, if you require certain requests to have a trailing slash, this feature can cause problems so may need to be disabled.'),
      '#default_value' => $config->get('deslash'),
    );

    $form['nonclean_to_clean'] = array(
      '#type' => 'checkbox',
      '#title' => t('Non-clean to Clean'),
      '#description' => t('If enabled, this option will redirect from non-clean to clean URL (if Clean URL\'s are enabled). This will stop, for example, node 1  existing on both <code>example.com/node/1</code> AND <code>example.com?q=node/1</code>.'),
      '#default_value' => $config->get('nonclean_to_clean'),
    );

    $form['trailing_zero'] = array(
      '#type' => 'radios',
      '#title' => t('Remove Trailing Zero Argument'),
      '#description' => t('If enabled, any instance of "/0" will be trimmed from the right of the URL. This stops duplicate pages such as "taxonomy/term/1" and "taxonomy/term/1/0" where 0 is the default depth. There is an option of limiting this feature to taxonomy term pages ONLY or allowing it to effect any page. <strong>By default this feature is disabled to avoid any unexpected behavior. Also of note, the trailing /0 "depth modifier" was removed from Drupal 7.</strong>'),
      '#options' => array(
        0 => t('Disabled'),
        1 => t('Enabled for all pages'),
        2 => t('Enabled for taxonomy term pages only'),
      ),
      '#default_value' => $config->get('trailing_zero'),
    );

    $form['menu_check'] = array(
      '#type' => 'checkbox',
      '#title' => t('Menu Access Checking'),
      '#description' => t('If enabled, the module will check the user has access to the page before redirecting. This helps to stop redirection on protected pages and avoids giving away <em>secret</em> URL\'s. <strong>By default this feature is disabled to avoid any unexpected behavior</strong>'),
      '#default_value' => $config->get('menu_check'),
    );

    $form['case_sensitive_urls'] = array(
      '#type' => 'checkbox',
      '#title' => t('Case Sensitive URL Checking'),
      '#description' => t('If enabled, the module will compare the current URL to the alias stored in the system. If there are any differences in case then the user will be redirected to the correct URL.'),
      '#default_value' => $config->get('case_sensitive_urls'),
    );

    $form['language_redirect'] = array(
      '#type' => 'checkbox',
      '#title' => t('Language Path Checking'),
      '#description' => t('If enabled, the module will check that the page being viewed matches the language in the URL or the system default. For example, viewing a French node while the site is in English will cause a redirect to the English node.'),
      '#default_value' => $config->get('language_redirect'),
    );

    $form['canonical'] = array(
      '#type' => 'checkbox',
      '#title' => t('Add Canonical Link'),
      '#description' => t('If enabled, will add a <a href="!canonical">canonical link</a> to each page.', array('!canonical' => 'http://googlewebmastercentral.blogspot.com/2009/02/specify-your-canonical.html')),
      '#default_value' => $config->get('canonical'),
    );

    $form['content_location_header'] = array(
      '#type' => 'checkbox',
      '#title' => t('Set Content Location Header'),
      '#description' => t('If enabled, will add a <a href="!canonical">Content-Location</a> header.', array('!canonical' => 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.14')),
      '#default_value' => $config->get('content_location_header'),
    );

    $form['term_path_handler'] = array(
      '#type' => 'checkbox',
      '#title' => t('Taxonomy Term Path Handler'),
      '#description' => t('If enabled, any request to a taxonomy/term/[tid] page will check that the correct path is being used for the term\'s vocabulary.'),
      '#default_value' => $config->get('term_path_handler'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::submitForm().
   */
  public function submitForm(array &$form, array &$form_state) {
    $this->configFactory->get('globalredirect.settings')
      ->set('deslash', $form_state['values']['deslash'])
      ->set('nonclean_to_clean', $form_state['values']['nonclean_to_clean'])
      ->set('trailing_zero', $form_state['values']['trailing_zero'])
      ->set('menu_check', $form_state['values']['menu_check'])
      ->set('case_sensitive_urls', $form_state['values']['case_sensitive_urls'])
      ->set('language_redirect', $form_state['values']['language_redirect'])
      ->set('canonical', $form_state['values']['canonical'])
      ->set('content_location_header', $form_state['values']['content_location_header'])
      ->set('term_path_handler', $form_state['values']['term_path_handler'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}
