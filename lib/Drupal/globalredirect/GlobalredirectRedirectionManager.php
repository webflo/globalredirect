<?php

/**
 * @file
 * Definition of Drupal\globalredirect\GlobalredirectRedirectionManager.
 */

namespace Drupal\globalredirect;

use Drupal\Core\Path\AliasManagerInterface;
use Drupal\Component\Utility\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Globalredirect Redirection manager.
 */
class GlobalredirectRedirectionManager {

  /**
   * The path alias manager.
   *
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs a GlobalredirectRedirectionManager object.
   *
   * @param \Drupal\Core\Path\AliasManagerInterface $alias_manager
   *   The path alias manager.
   */
  public function __construct(AliasManagerInterface $alias_manager) {
    $this->aliasManager = $alias_manager;
  }

  /**
   * Returns whether Globalredirect is active.
   *
   * @return bool
   *   TRUE if global redirect is active, FALSE otherwise.
   */
  public function isActive() {
    /**
    * We need to do a test to make sure we only clean up URL's for the main
    * request. This stops modules such as the Ad Module which had its own script
    * in its folder doing a bootstrap which invoked hook_init() and caused some
    * banners to get "cleaned up"
    *
    * @see http://drupal.org/node/205810
    * @see http://drupal.org/node/278615
    */
    if ($_SERVER['SCRIPT_NAME'] != $GLOBALS['base_path'] . 'index.php') {
      return FALSE;
    }

    /**
     * If this is a command line request (Drush, etc), skip processing.
     */
    if (drupal_is_cli()) {
      return FALSE;
    }

    /**
     * Check if the request is an attempted url mask
     */
    if (strpos(request_uri(), '://') !== FALSE) {
      return FALSE;
    }

    /**
     * If the site is in offline mode there is little point doing any of this as
     * you might end up redirecting to a 503.
     */
    if (\Drupal::config('system.maintenance')->get('enabled')) {
      return FALSE;
    }

    /**
     * If there is something posted, GlobalRedirect is not active
     */
    if (!empty($_POST)) {
      return FALSE;
    }

    /**
     * If alias manager isn't preset, GlobalRedirect is not active
     */
    if (!$this->aliasManager) {
      return FALSE;
    }

    $settings = $this->getSettings();

    /**
     * If menu_check is enabled AND the menu_get_item function is missing, GlobalRedirect is disabled
     */
    if ($settings['menu_check'] && !function_exists('menu_get_item')) {
      return FALSE;
    }

    /**
     * We seem to have passed all the tests - let say we're active
     */
    return TRUE;
  }

  /**
   * Return the settings with any defaults mapped over the top.
   *
   * @return array
   *   Array key/value with settings names and its values.
   */
  public function getSettings() {
    $defaults = array(
      'deslash' => 1,
      'nonclean_to_clean' => 1,
      'trailing_zero' => 0,
      'menu_check' => 0,
      'case_sensitive_urls' => 1,
      'language_redirect' => 0,
      'canonical' => 0,
      'content_location_header' => 0,
      'term_path_handler' => 1,
    );
    $config = config('globalredirect.settings')->get();
    return $config + $defaults;
  }

  // TO-DO: Documentar
  public function getRedirection() {

    $settings = $this->getSettings();

    // If menu checking is enabled, do the check. Note: Feature disabled by default.
    if ($settings['menu_check']) {
      // Check the access on the current path, return FALSE if access not
      // allowed. This stops redirection for paths without access permission.
      $item = menu_get_item();
      if (!$item['access']) {
        return FALSE;
      }
    }

    // Store the destination from the $_REQUEST as it breaks things if we leave
    // it in - restore it at the end...
    if (isset($_GET['destination'])) {
      $destination = $_GET['destination'];
      unset($_GET['destination']);
    }

    // Get the Query String (minus the 'q'). If none set, set to NULL
    $query_string = Url::filterQueryParameters($_GET, array('q'));
    if (empty($query_string)) {
      $query_string = NULL;
    }

    // Establish the language prefix that should be used, ie. the one that
    // url() would use
    $options = array(
      'fragment' => '',
      'query' => $query_string,
      'absolute' => FALSE,
      'alias' => FALSE,
      'prefix' => '',
      'external' => FALSE,
    );

    $request_uri = request_path();
    $path = current_path();

    if (function_exists('language_url_rewrite_session')) {
      // Note 1 : the language_url_rewrite_session() takes path (by reference) as the
      //          first argument but does not use it at all
      language_url_rewrite_session($path, $options);
    }
    $prefix = rtrim($options['prefix'], '/');

    // Do a check if this is a front page
    if (drupal_is_front_page()) {
      // Redirect if the current request does not refer to the front page in the
      // configured fashion (with or without a prefix)
      if ($request_uri) {
        return new RedirectResponse(url(''), 301);
      }
      // If we've got to this point then we're on a front page with a VALID
      // request path (such as a language-prefix front page such as '/de')
      return;
    }

    // TO-DO: Test D8
    // Trim any trailing slash off the end (eg, 'node/1/' to 'node/1')
    $request = $settings['deslash'] ? trim($path, '/') : $path;
    // Optional stripping of "/0". Disabled by default.
    switch ($settings['trailing_zero']) {
      case 2 :
        // If 'taxonomy/term/*' only. If not, break out.
        if (drupal_substr($request, 0, 14) != 'taxonomy/term/') {
          break;
        }
        // If it is, fall through to general trailing zero method
      case 1 :
        // If last 2 characters of URL are /0 then trim them off
        if (drupal_substr($request, -2) == '/0') {
          $request = rtrim($request, '/0');
        }
    }

    // TO-DO: Test D8
    // If the feature is enabled, check and redirect taxonomy/term/* requests to their proper handler defined by hook_term_path().
    if ($settings['term_path_handler'] && module_exists('taxonomy') && preg_match('/taxonomy\/term\/([0-9]+)$/', $request, $matches)) {
      // So the feature is enabled, as is taxonomy module and the current request is a taxonomy term page.
      // NOTE: This will only match taxonomy term pages WITHOUT a depth modifier
      $term = taxonomy_term_load($matches[1]);

      // Get the term path for this term (handler is defined in the vocab table under module). If it differs from the request, then redirect.
      if (($term_path = entity_uri('taxonomy_term', $term)) && $term_path['path'] != $request) {
        $request = $term_path['path'];
      }
    }

    // TO-DO: Test D8
    // If Content Translation module is enabled then check the path is correct.
    if ($settings['language_redirect'] && module_exists('translation') && (arg(0) == 'node') && is_numeric(arg(1)) && (arg(2) == '')) {
      switch (variable_get('language_negotiation', LANGUAGE_NEGOTIATION_NONE)) {
        case LANGUAGE_NEGOTIATION_PATH_DEFAULT:
        case LANGUAGE_NEGOTIATION_PATH:
          // Check if there's a translation for the current language of the requested node...
          $node_translations = translation_path_get_translations('node/' . arg(1));
          // If there is, go to the translation.
          if (!empty($node_translations[$language->language]) && $node_translations[$language->language] != 'node/' . arg(1)) {
            drupal_goto($node_translations[$language->language], $options, 301);
          }
          // If there is no translation, change the language to fit the content!
          else {
            $node = node_load(arg(1));
            if (!empty($node->language) && $node->language != $language->language) {
              $all_languages = language_list();
              // Change the global $language's prefix, to make drupal_goto()
              // follow the proper prefix
              $language = $all_languages[$node->language];
              drupal_goto('node/' . $node->nid, $options, 301);
            }
          }
          break;

        case LANGUAGE_NEGOTIATION_DOMAIN:
          // Let's check is there other languages on site.
          $all_languages = language_list();
          if (count($all_languages) > 1) {
            foreach ($all_languages as $l => $lang) {
              // Only test for languages other than the current one.
              if ($lang->language != $language->language) {
                $alias = drupal_get_path_alias($request, $lang->language);
                // There is a matching language for this alias
                if ($alias != $request) {
                  if (isset($lang->domain)) {
                    drupal_goto($lang->domain . '/' . $alias, $options, 301);
                  }
                  break;
                }
              }
            }
          }
          break;
      }
    }

    // Find an alias (if any) for the request
    $langcode = isset($options['language']->language) ? $options['language']->language : '';
    $alias = $this->aliasManager->getPathAlias($request, $langcode);

    // TODO: This looks wrong for D7... maybe a hook?
    if (function_exists('custom_url_rewrite_outbound')) {
      // Modules may alter outbound links by reference.
      custom_url_rewrite_outbound($alias, $options, $request);
    }
    if ($prefix && $alias) {
      $prefix .= '/';
    }

    // TO-DO: Test D8
    // Alias case sensitivity check.
    // NOTE: This has changed. In D6 the $alias matched the request (in terms of case), however in D7 $alias is already a true alias (accurate in case), and therefore not the "true" request...
    // So, if the alias and the request path are case-insensitive the same then, if Case Senitive URL's are enabled, the alias SHOULD be the accurate $alias from above, otherwise it should be the request_path()...
    // TODO: Test if this breaks the language checking above!
    if (strcasecmp($alias, request_path()) == 0) {
      // The alias and the request are identical (case insensitive)... Therefore...
      $alias = $settings['case_sensitive_urls'] ? $alias : request_path();
    }

    // Compare the request to the alias. This also works as a 'deslashing'
    // agent. If we have a language prefix then prefix the alias
    if ($request_uri != $prefix . $alias) {
      // If it's not just a slash or user has deslash on, redirect
      if (str_replace($prefix . $request_uri, '', $path) != '/' || $settings['deslash']) {
        return new RedirectResponse(url($alias, $options), '301');
      }
    }

    // TO-DO: Test D8
    // If no alias was returned, the final check is to direct non-clean to
    // clean - if clean is enabled
    if ($settings['nonclean_to_clean'] && ((bool)variable_get('clean_url', 0)) && strpos(request_uri(), '?q=')) {
      drupal_goto($request, $options, 301);
    }

    // Restore the destination from earlier so its available in other places.
    if (isset($destination)) {
      $_GET['destination'] = $destination;
    }

    // Add the canonical link to the head of the document if desired.
    // TODO - The Canonical already gets set by Core for node page views... See http://api.drupal.org/api/function/node_page_view/7
    // TO-DO: Test D8
    if ($settings['canonical']) {
       drupal_add_html_head_link(array(
        'rel' => 'canonical',
        'href' => url(drupal_is_front_page() ? '<front>' : $_REQUEST['q'], array('absolute' => TRUE, 'query' => $query_string)),
      ));
    }

    // Add the Content-Location header to the page
    // TO-DO: Test D8
    if ($settings['content_location_header']) {
      drupal_add_http_header('Content-Location', url(drupal_is_front_page() ? '<front>' : $_REQUEST['q'], array('absolute' => TRUE, 'query' => $query_string)));
    }
  }

}
