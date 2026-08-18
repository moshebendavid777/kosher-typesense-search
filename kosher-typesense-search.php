<?php
/*
Plugin Name: Kosher TypeSense Search
Description: A plugin that adds a search form with TypeSense multisearch and autocomplete for Kosher.com.
Version: 3.1.0
Author: Kosher
Text Domain: kosher-typesense-search
*/

/**
 * File: kosher-typesense-search.php
 * Description: Main plugin bootstrap, asset enqueueing, Typesense proxy, and shortcode registration.
 * Author: Kosher Dev Team
 */


if (! defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

$kosher_typesense_autoload = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($kosher_typesense_autoload)) {
  require_once $kosher_typesense_autoload;
}

define('KOSHER_TYPESENSE_SEARCH_VERSION', '3.1.0');
define('KOSHER_TYPESENSE_SEARCH_FILE', __FILE__);

$kosher_typesense_analytics = plugin_dir_path(__FILE__) . 'includes/analytics.php';
if (file_exists($kosher_typesense_analytics)) {
  require_once $kosher_typesense_analytics;
}

register_activation_hook(__FILE__, 'kosher_typesense_install_analytics_tables');

$kosher_typesense_simple_search_context = plugin_dir_path(__FILE__) . 'includes/simple-search-context.php';
if (file_exists($kosher_typesense_simple_search_context)) {
  require_once $kosher_typesense_simple_search_context;
}

function kosher_typesense_raw_config_value($key, $default = '')
{
  if (defined($key)) {
    return constant($key);
  }

  $env_value = getenv($key);
  if ($env_value !== false && $env_value !== '') {
    return $env_value;
  }

  return $default;
}

function kosher_typesense_site_settings_value($key)
{
  if (!function_exists('get_option')) {
    return null;
  }

  $settings = get_option('kayco_ai_settings', array());
  if (!is_array($settings)) {
    $settings = array();
  }

  if (function_exists('kayco_get_option')) {
    $theme_map = array(
      'KOSHER_TYPESENSE_HOST'           => 'typesense.host',
      'TYPESENSE_API_HOST'              => 'typesense.host',
      'KOSHER_TYPESENSE_SEARCH_API_KEY' => 'typesense.api_key',
      'TYPESENSE_SEARCH_ONLY_API_KEY'   => 'typesense.api_key',
      'TYPESENSE_API_KEY'               => 'typesense.api_key',
      'KOSHER_TYPESENSE_ADMIN_API_KEY'  => 'typesense.admin_api_key',
      'TYPESENSE_ADMIN_KEY'             => 'typesense.admin_api_key',
      'KOSHER_TYPESENSE_COLLECTION_ENV' => 'typesense.collection_env',
    );

    if (isset($theme_map[$key])) {
      $theme_value = kayco_get_option($theme_map[$key], null);

      if ($theme_value !== null && $theme_value !== false && $theme_value !== '') {
        return $theme_value;
      }
    }
  }

  $theme_options = get_option('kayco_theme_options', array());
  if (is_array($theme_options) && isset($theme_options['typesense']) && is_array($theme_options['typesense'])) {
    $direct_map = array(
      'KOSHER_TYPESENSE_HOST'           => 'host',
      'TYPESENSE_API_HOST'              => 'host',
      'KOSHER_TYPESENSE_SEARCH_API_KEY' => 'api_key',
      'TYPESENSE_SEARCH_ONLY_API_KEY'   => 'api_key',
      'TYPESENSE_API_KEY'               => 'api_key',
      'KOSHER_TYPESENSE_ADMIN_API_KEY'  => 'admin_api_key',
      'TYPESENSE_ADMIN_KEY'             => 'admin_api_key',
      'KOSHER_TYPESENSE_COLLECTION_ENV' => 'collection_env',
    );

    if (isset($direct_map[$key], $theme_options['typesense'][$direct_map[$key]]) && $theme_options['typesense'][$direct_map[$key]] !== '') {
      return $theme_options['typesense'][$direct_map[$key]];
    }
  }

  $map = array(
    'KOSHER_TYPESENSE_HOST'           => 'typesense_host',
    'TYPESENSE_API_HOST'              => 'typesense_host',
    'KOSHER_TYPESENSE_PROTOCOL'       => 'typesense_protocol',
    'TYPESENSE_API_PROTOCOL'          => 'typesense_protocol',
    'KOSHER_TYPESENSE_PORT'           => 'typesense_port',
    'TYPESENSE_API_PORT'              => 'typesense_port',
    'KOSHER_TYPESENSE_SEARCH_API_KEY' => 'typesense_search_api_key',
    'TYPESENSE_SEARCH_ONLY_API_KEY'   => 'typesense_search_api_key',
    'TYPESENSE_API_KEY'               => 'typesense_search_api_key',
    'KOSHER_TYPESENSE_ADMIN_API_KEY'  => 'typesense_admin_api_key',
    'TYPESENSE_ADMIN_KEY'             => 'typesense_admin_api_key',
    'KOSHER_TYPESENSE_COLLECTION_ENV' => 'typesense_collection_env',
  );

  if ('KOSHER_TYPESENSE_COLLECTION_PREFIX' === $key || 'TYPESENSE_COLLECTION_PREFIX' === $key) {
    $environment = !empty($settings['typesense_collection_env']) ? sanitize_key((string) $settings['typesense_collection_env']) : 'production';
    $prefix_key = 'staging' === $environment ? 'typesense_staging_collection_prefix' : 'typesense_production_collection_prefix';

    if (!empty($settings[$prefix_key])) {
      return $settings[$prefix_key];
    }

    if (!empty($settings['typesense_collection_prefix'])) {
      return $settings['typesense_collection_prefix'];
    }

    return null;
  }

  if (!isset($map[$key]) || empty($settings[$map[$key]])) {
    return null;
  }

  return $settings[$map[$key]];
}

if (!function_exists('env')) {
  if (!defined('KOSHER_TYPESENSE_ENV_FALLBACK')) {
    define('KOSHER_TYPESENSE_ENV_FALLBACK', true);
  }

  function env($key, $default = null)
  {
    return kosher_typesense_raw_config_value($key, $default);
  }
}

function kosher_typesense_config_value($keys, $default = '')
{
  foreach ((array) $keys as $key) {
    $value = kosher_typesense_raw_config_value($key, null);
    if ($value !== null && $value !== '') {
      return $value;
    }

    if (function_exists('env') && !defined('KOSHER_TYPESENSE_ENV_FALLBACK')) {
      $value = env($key);
      if ($value !== null && $value !== false && $value !== '') {
        return $value;
      }
    }

    $value = kosher_typesense_site_settings_value($key);
    if ($value !== null && $value !== false && $value !== '') {
      return $value;
    }
  }

  return $default;
}

function kosher_typesense_host()
{
  $host = kosher_typesense_config_value(
    array('KOSHER_TYPESENSE_HOST', 'TYPESENSE_API_HOST'),
    'pxuy5ezorfl4btw2p-1.a1.typesense.net'
  );

  $host = preg_replace('#^https?://#', '', trim((string) $host));
  return rtrim($host, '/');
}

function kosher_typesense_protocol()
{
  return kosher_typesense_config_value(
    array('KOSHER_TYPESENSE_PROTOCOL', 'TYPESENSE_API_PROTOCOL'),
    'https'
  ) === 'http' ? 'http' : 'https';
}

function kosher_typesense_port()
{
  return (string) kosher_typesense_config_value(
    array('KOSHER_TYPESENSE_PORT', 'TYPESENSE_API_PORT'),
    ''
  );
}

function kosher_typesense_collection_prefix()
{
  $settings_prefix = kosher_typesense_site_settings_value('KOSHER_TYPESENSE_COLLECTION_PREFIX');

  if ($settings_prefix !== null && $settings_prefix !== false && $settings_prefix !== '') {
    return (string) $settings_prefix;
  }

  return (string) kosher_typesense_config_value(
    array('KOSHER_TYPESENSE_COLLECTION_PREFIX', 'TYPESENSE_COLLECTION_PREFIX'),
    'live_'
  );
}

function kosher_typesense_collection_environment()
{
  $environment = kosher_typesense_site_settings_value('KOSHER_TYPESENSE_COLLECTION_ENV');
  $environment = $environment ? sanitize_key((string) $environment) : 'production';

  return 'staging' === $environment ? 'staging' : 'production';
}

function kosher_typesense_collection_name($collection_slug)
{
  return kosher_typesense_collection_prefix() . sanitize_key((string) $collection_slug);
}

function kosher_typesense_search_api_key()
{
  return (string) kosher_typesense_config_value(array(
    'KOSHER_TYPESENSE_SEARCH_API_KEY',
    'TYPESENSE_SEARCH_ONLY_API_KEY',
    'TYPESENSE_API_KEY',
  ), 'ipJSwqkldtmmDBxQYwPAMbhoQIBDfQBK');
}

function kosher_typesense_admin_api_key()
{
  $settings_key = kosher_typesense_site_settings_value('KOSHER_TYPESENSE_ADMIN_API_KEY');

  if ($settings_key !== null && $settings_key !== false && $settings_key !== '') {
    return (string) $settings_key;
  }

  return (string) kosher_typesense_config_value(array(
    'KOSHER_TYPESENSE_ADMIN_API_KEY',
    'TYPESENSE_ADMIN_KEY',
  ));
}

function kosher_typesense_url($path = '')
{
  $host = kosher_typesense_host();
  $port = kosher_typesense_port();
  $base = kosher_typesense_protocol() . '://' . $host;

  if ($port !== '' && !in_array($port, array('80', '443'), true)) {
    $base .= ':' . $port;
  }

  return $base . '/' . ltrim($path, '/');
}

function kosher_typesense_asset_version($relative_path)
{
  $path = plugin_dir_path(__FILE__) . ltrim($relative_path, '/');
  return file_exists($path) ? (string) filemtime($path) : KOSHER_TYPESENSE_SEARCH_VERSION;
}

/**
 * Write debug logs when KOSHER_TYPESENSE_DEBUG is enabled.
 *
 * @param string $message Log message.
 * @param array  $context Optional context.
 * @return void
 */
function kosher_typesense_debug_log($message, $context = array())
{
  if (!defined('KOSHER_TYPESENSE_DEBUG') || !KOSHER_TYPESENSE_DEBUG) {
    return;
  }

  $suffix = !empty($context) ? ' ' . wp_json_encode($context) : '';
  error_log('[Kosher Typesense] ' . $message . $suffix); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

function kosher_typesense_runtime_available()
{
  return class_exists('\Typesense\Client') && kosher_typesense_search_api_key() !== '';
}

if (!defined('KOSHER_TYPESENSE_RECIPE_DEFAULT_IMAGE_URL')) {
  define('KOSHER_TYPESENSE_RECIPE_DEFAULT_IMAGE_URL', 'https://images.kosher.com/uploads/no-image-recipe.png');
}

if (!defined('KOSHER_TYPESENSE_RECIPE_LEGACY_MEMBER_IMAGE_URL')) {
  define('KOSHER_TYPESENSE_RECIPE_LEGACY_MEMBER_IMAGE_URL', 'https://images.kosher.com/uploads/Member_recipes_image.webp');
}

function kosher_typesense_admin_request($method, $path, $args = array())
{
  $api_key = kosher_typesense_admin_api_key();

  if ($api_key === '') {
    return new WP_Error('kosher_typesense_missing_admin_key', __('Typesense admin API key is missing.', 'kosher-typesense-search'));
  }

  $request = wp_parse_args(
    $args,
    array(
      'method'  => strtoupper($method),
      'headers' => array(),
      'timeout' => 30,
    )
  );

  $request['headers']['X-TYPESENSE-API-KEY'] = $api_key;
  $request['headers']['Content-Type'] = 'application/json';

  return wp_remote_request(kosher_typesense_url($path), $request);
}

function kosher_typesense_admin_json_request($method, $path, $body = null)
{
  $args = array();

  if ($body !== null) {
    $args['body'] = wp_json_encode($body);
  }

  $response = kosher_typesense_admin_request($method, $path, $args);

  if (is_wp_error($response)) {
    return $response;
  }

  $code = wp_remote_retrieve_response_code($response);
  $raw = wp_remote_retrieve_body($response);
  $data = json_decode($raw, true);

  if ($code < 200 || $code >= 300) {
    return new WP_Error(
      'kosher_typesense_request_failed',
      $data['message'] ?? sprintf(__('Typesense request failed with status %d.', 'kosher-typesense-search'), $code),
      array('status' => $code, 'body' => $raw)
    );
  }

  return is_array($data) ? $data : array();
}

/**
 * Return the Typesense NLS model used by the temporary /search-ai experience.
 * If no model ID was configured, provision one from the site's existing OpenAI
 * key. The secret is sent server-to-server to Typesense and is never localized
 * into the browser.
 *
 * @return string
 */
function kosher_typesense_nl_model_id()
{
  $configured_id = (string) apply_filters(
    'kosher_typesense_nl_model_id',
    kosher_typesense_config_value(array('KOSHER_TYPESENSE_NL_MODEL_ID', 'TYPESENSE_NL_MODEL_ID'), '')
  );

  if ($configured_id !== '') {
    return sanitize_key($configured_id);
  }

  $model_id = 'kosher-natural-language-search';
  $model_version = '4';
  $ready_value = $model_id . ':' . $model_version;
  $system_prompt = implode(' ', array(
    'Interpret every request as a structured search of Kosher.com recipes, menus, articles, or shows.',
    'Preserve the main food, recipe, article, or show subject as q, but always generate filter_by and sort_by parameters for constraints expressed by the user.',
    'For recipes use these exact filter mappings: holidays and Jewish occasions use occasions:=`Value`; Dairy, Meat, or Parve use preference:=`Value`; diets use diets:=`Value`; allergens or allergen-free constraints use contains_allergents:!=`Value`; difficulty uses difficulty:=`Value`; sources or publications use sources:=`Value`; recipe categories use recipe_category:=`Value`; cuisines use cuisine:=`Value`; included ingredients use ingredients:=`Value`; excluded ingredients use ingredients:!=`Value`; chefs use chefs:=`Value`.',
    'For article searches use author:=`Value` for authors and article_sub_category:=`Value` for categories.',
    'For menu searches use author_name:=`Value` for authors, categories:=`Value` for categories, holidays:=`Value` for holidays, sections_count for section-count constraints, cards_count for recipe-count constraints, and always preserve privacy:=`public`.',
    'For episode or show searches use chef:=`Value` for chefs and show:=`Value` for show names.',
    'Recognize likely human names as chef or author entities even when lowercase, unfamiliar, Hebrew-derived, Yiddish-derived, or Jewish names; never require the name to be present in your pretrained knowledge.',
    'In recipe intent, a standalone likely person name or a name after chef, by, from, recipes by, or recipes from must become chefs:=`Title Cased Name`. Example: esty wolbe or recipes by esty wolbe becomes q:* and chefs:=`Esty Wolbe`.',
    'In episode intent, the same chef-name pattern uses chef:=`Title Cased Name`. In article intent, by followed by a person name uses author:=`Title Cased Name`. In menu intent it uses author_name:=`Title Cased Name`.',
    'A standalone person name defaults to recipe chef intent; only apply article, menu, or episode person filters when the query explicitly indicates that content type.',
    'Do not treat ordinary dish names, ingredients, holidays, cuisines, diets, or cooking techniques as people.',
    'Translate without, no, omit, exclude, avoiding, or free of an ingredient to ingredients:!=`Ingredient`; translate with, containing, includes, or made with an ingredient to ingredients:=`Ingredient`.',
    'Dairy-free means contains_allergents:!=`Dairy`, gluten-free means contains_allergents:!=`Gluten`, nut-free means the appropriate contains_allergents exclusions, and other allergen-free phrases follow the same rule. Do not translate Dairy, Meat, or Parve into allergens when the user is asking for the recipe type; those values use preference.',
    'When multiple acceptable values are requested, join values in the same filter group with ||. Join independent requirements and multiple exclusions with &&.',
    'Translate maximum preparation-time requests to cook_time:<N where N is minutes.',
    'Translate newest or latest to date:desc, oldest to date:asc, highest rated or best rated to rating:desc, and most liked or popular to likes:desc when those sortable fields exist.',
    'Do not leave exclusion words such as without, no, omit, or exclude in q after creating the corresponding filter.',
    'Correct obvious food-word misspellings when forming q or filter values.',
    'Only use fields present in the supplied collection schema.',
    'Never infer or recommend non-kosher ingredients or meat-and-dairy combinations.',
  ));

  if (get_option('kosher_typesense_nl_model_ready') === $ready_value) {
    return $model_id;
  }

  $existing = kosher_typesense_admin_json_request('GET', 'nl_search_models/' . rawurlencode($model_id));
  if (!is_wp_error($existing)) {
    $updated = kosher_typesense_admin_json_request(
      'PUT',
      'nl_search_models/' . rawurlencode($model_id),
      array(
        'temperature' => 0.0,
        'system_prompt' => $system_prompt,
      )
    );

    if (!is_wp_error($updated)) {
      update_option('kosher_typesense_nl_model_ready', $ready_value, false);
      return $model_id;
    }

    kosher_typesense_debug_log('Typesense natural-language model update failed.', array('error' => $updated->get_error_message()));
  }

  $openai_api_key = kosher_typesense_openai_api_key();

  if ($openai_api_key === '') {
    kosher_typesense_debug_log('Natural-language model was not provisioned because the OpenAI API key is missing.');
    return '';
  }

  $created = kosher_typesense_admin_json_request(
    'POST',
    'nl_search_models',
    array(
      'id' => $model_id,
      'model_name' => 'openai/gpt-4.1-mini',
      'api_key' => $openai_api_key,
      'max_bytes' => 16000,
      'temperature' => 0.0,
      'system_prompt' => $system_prompt,
    )
  );

  if (is_wp_error($created)) {
    kosher_typesense_debug_log('Typesense natural-language model provisioning failed.', array('error' => $created->get_error_message()));
    return '';
  }

  update_option('kosher_typesense_nl_model_ready', $ready_value, false);
  return $model_id;
}

/**
 * Resolve the OpenAI key from the settings formats used by the site's AI
 * integrations. The value is only used in server-to-server requests.
 *
 * @return string
 */
function kosher_typesense_openai_api_key()
{
  $settings = get_option('kayco_ai_settings', array());
  $candidates = array(
    is_array($settings) ? ($settings['openai_api_key'] ?? '') : '',
    get_option('kayco_ai_openai_api_key', ''),
    get_option('kosher_ai_openai_key', ''),
    kosher_typesense_raw_config_value('OPENAI_API_KEY', ''),
    kosher_typesense_raw_config_value('KOSHER_OPENAI_API_KEY', ''),
  );

  foreach ($candidates as $candidate) {
    $candidate = trim((string) $candidate);
    if ($candidate !== '') {
      return (string) apply_filters('kosher_typesense_openai_api_key', $candidate);
    }
  }

  return (string) apply_filters('kosher_typesense_openai_api_key', '');
}

function kosher_typesense_recipe_ranking_fields_available()
{
  $collection = kosher_typesense_collection_name('recipes');
  $cache_key = 'kosher_ts_recipe_ranking_fields_' . md5($collection);
  $cached = get_transient($cache_key);

  if ($cached === 'yes' || $cached === 'no') {
    return $cached === 'yes';
  }

  $schema = kosher_typesense_admin_json_request('GET', 'collections/' . rawurlencode($collection));
  $field_names = array();

  if (!is_wp_error($schema) && !empty($schema['fields']) && is_array($schema['fields'])) {
    $field_names = array_column($schema['fields'], 'name');
  }

  $available = in_array('main_dish', $field_names, true) && in_array('search_priority', $field_names, true);
  set_transient($cache_key, $available ? 'yes' : 'no', 5 * MINUTE_IN_SECONDS);

  return $available;
}

function kosher_typesense_recipe_placeholder_count()
{
  $collection = kosher_typesense_collection_name('recipes');
  $query = add_query_arg(
    array(
      'q' => '*',
      'query_by' => 'title',
      'filter_by' => 'image:=' . KOSHER_TYPESENSE_RECIPE_LEGACY_MEMBER_IMAGE_URL,
      'per_page' => 1,
    ),
    'collections/' . rawurlencode($collection) . '/documents/search'
  );
  $result = kosher_typesense_admin_json_request('GET', $query);

  if (is_wp_error($result)) {
    return $result;
  }

  return isset($result['found']) ? absint($result['found']) : 0;
}

function kosher_typesense_recipe_placeholder_batch($limit = 50)
{
  $collection = kosher_typesense_collection_name('recipes');
  $limit = max(1, min(250, absint($limit)));
  $query = add_query_arg(
    array(
      'q' => '*',
      'query_by' => 'title',
      'filter_by' => 'image:=' . KOSHER_TYPESENSE_RECIPE_LEGACY_MEMBER_IMAGE_URL,
      'per_page' => $limit,
    ),
    'collections/' . rawurlencode($collection) . '/documents/search'
  );
  $search = kosher_typesense_admin_json_request('GET', $query);

  if (is_wp_error($search)) {
    return $search;
  }

  $result = array(
    'found' => isset($search['found']) ? absint($search['found']) : 0,
    'processed' => 0,
    'updated' => 0,
    'failed' => 0,
    'complete' => true,
  );

  if (empty($search['hits']) || !is_array($search['hits'])) {
    return $result;
  }

  foreach ($search['hits'] as $hit) {
    $document = isset($hit['document']) && is_array($hit['document']) ? $hit['document'] : array();
    $document_id = isset($document['id']) ? (string) $document['id'] : '';

    if ($document_id === '') {
      $result['failed']++;
      continue;
    }

    $update = kosher_typesense_admin_json_request(
      'PATCH',
      'collections/' . rawurlencode($collection) . '/documents/' . rawurlencode($document_id),
      array('image' => KOSHER_TYPESENSE_RECIPE_DEFAULT_IMAGE_URL)
    );

    $result['processed']++;

    if (is_wp_error($update)) {
      $result['failed']++;
    } else {
      $result['updated']++;
    }
  }

  $result['complete'] = $result['found'] <= $limit;

  return $result;
}

function kosher_typesense_recipe_placeholder_list($page = 1, $per_page = 20)
{
  $collection = kosher_typesense_collection_name('recipes');
  $page = max(1, absint($page));
  $per_page = max(1, min(100, absint($per_page)));
  $query = add_query_arg(
    array(
      'q' => '*',
      'query_by' => 'title',
      'filter_by' => 'image:=' . KOSHER_TYPESENSE_RECIPE_LEGACY_MEMBER_IMAGE_URL,
      'per_page' => $per_page,
      'page' => $page,
    ),
    'collections/' . rawurlencode($collection) . '/documents/search'
  );
  $search = kosher_typesense_admin_json_request('GET', $query);

  if (is_wp_error($search)) {
    return $search;
  }

  $items = array();

  if (!empty($search['hits']) && is_array($search['hits'])) {
    foreach ($search['hits'] as $hit) {
      $document = isset($hit['document']) && is_array($hit['document']) ? $hit['document'] : array();
      $id = isset($document['postID']) ? (string) $document['postID'] : (string) ($document['id'] ?? '');

      $items[] = array(
        'id' => $id,
        'title' => isset($document['title']) ? (string) $document['title'] : '',
        'url' => isset($document['url']) ? (string) $document['url'] : '',
      );
    }
  }

  $total = isset($search['found']) ? absint($search['found']) : 0;

  return array(
    'items' => $items,
    'total' => $total,
    'page' => $page,
    'per_page' => $per_page,
    'total_pages' => max(1, (int) ceil($total / $per_page)),
  );
}

function kosher_typesense_recipe_image_ajax()
{
  if (!current_user_can('manage_options')) {
    wp_send_json_error(array('message' => __('You are not allowed to run this repair.', 'kosher-typesense-search')), 403);
  }

  check_ajax_referer('kosher_typesense_recipe_images', 'nonce');

  $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'count';

  if ($mode === 'replace') {
    $result = kosher_typesense_recipe_placeholder_batch(isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 50);
  } elseif ($mode === 'list') {
    $result = kosher_typesense_recipe_placeholder_list(
      isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1,
      isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 20
    );
  } else {
    $count = kosher_typesense_recipe_placeholder_count();
    $result = is_wp_error($count) ? $count : array('count' => $count);
  }

  if (is_wp_error($result)) {
    wp_send_json_error(array('message' => $result->get_error_message()), 500);
  }

  wp_send_json_success($result);
}
add_action('wp_ajax_kosher_typesense_recipe_images', 'kosher_typesense_recipe_image_ajax');

function kosher_typesense_register_recipe_image_admin_page()
{
  add_management_page(
    __('Typesense Recipe Images', 'kosher-typesense-search'),
    __('Typesense Recipe Images', 'kosher-typesense-search'),
    'manage_options',
    'kosher-typesense-recipe-images',
    'kosher_typesense_render_recipe_image_admin_page'
  );
}
add_action('admin_menu', 'kosher_typesense_register_recipe_image_admin_page');

function kosher_typesense_render_recipe_image_admin_page()
{
  if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You are not allowed to run this repair.', 'kosher-typesense-search'));
  }

  $nonce = wp_create_nonce('kosher_typesense_recipe_images');
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('Typesense Recipe Images', 'kosher-typesense-search'); ?></h1>
    <p><?php esc_html_e('Count and replace recipe documents in Typesense that still use the old member recipe placeholder image.', 'kosher-typesense-search'); ?></p>
    <p><strong><?php esc_html_e('Old image:', 'kosher-typesense-search'); ?></strong> <code><?php echo esc_html(KOSHER_TYPESENSE_RECIPE_LEGACY_MEMBER_IMAGE_URL); ?></code></p>
    <p><strong><?php esc_html_e('New image:', 'kosher-typesense-search'); ?></strong> <code><?php echo esc_html(KOSHER_TYPESENSE_RECIPE_DEFAULT_IMAGE_URL); ?></code></p>
    <div class="card" style="max-width:760px;">
      <p>
        <label for="kosher-typesense-recipe-image-limit"><?php esc_html_e('Batch size', 'kosher-typesense-search'); ?></label>
        <input id="kosher-typesense-recipe-image-limit" type="number" min="1" max="250" value="50" class="small-text" />
      </p>
      <p>
        <button type="button" class="button" data-kosher-typesense-recipe-images="count"><?php esc_html_e('Load Count', 'kosher-typesense-search'); ?></button>
        <button type="button" class="button button-primary" data-kosher-typesense-recipe-images="replace"><?php esc_html_e('Replace in Typesense', 'kosher-typesense-search'); ?></button>
      </p>
      <div data-kosher-typesense-recipe-image-progress>
        <p><?php esc_html_e('No task is running.', 'kosher-typesense-search'); ?></p>
      </div>
    </div>
    <div class="card" style="max-width:960px;">
      <h2><?php esc_html_e('Recipes Found', 'kosher-typesense-search'); ?></h2>
      <p><?php esc_html_e('Typesense recipe documents currently using the old member recipe placeholder.', 'kosher-typesense-search'); ?></p>
      <div data-kosher-typesense-recipe-image-results>
        <p><?php esc_html_e('Loading recipes...', 'kosher-typesense-search'); ?></p>
      </div>
    </div>
  </div>
  <script>
    (function () {
      const ajaxUrl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
      const nonce = <?php echo wp_json_encode($nonce); ?>;
      const buttons = document.querySelectorAll('[data-kosher-typesense-recipe-images]');
      const progress = document.querySelector('[data-kosher-typesense-recipe-image-progress]');
      const results = document.querySelector('[data-kosher-typesense-recipe-image-results]');
      const limitInput = document.getElementById('kosher-typesense-recipe-image-limit');

      function setButtons(disabled) {
        buttons.forEach(function (button) {
          button.disabled = disabled;
        });
      }

      function render(summary, running) {
        const remaining = summary.remaining || 0;
        const initial = summary.initial || remaining || 0;
        const done = Math.max(0, initial - remaining);
        const percent = initial ? Math.min(100, Math.round((done / initial) * 100)) : 100;
        progress.innerHTML =
          '<p><strong>' + (running ? '<?php echo esc_js(__('Running', 'kosher-typesense-search')); ?>' : '<?php echo esc_js(__('Ready', 'kosher-typesense-search')); ?>') + '</strong> ' + percent + '%</p>' +
          '<progress max="100" value="' + percent + '" style="width:100%;height:18px;"></progress>' +
          '<ul>' +
          '<li><?php echo esc_js(__('Initial count', 'kosher-typesense-search')); ?>: ' + initial + '</li>' +
          '<li><?php echo esc_js(__('Remaining', 'kosher-typesense-search')); ?>: ' + remaining + '</li>' +
          '<li><?php echo esc_js(__('Processed', 'kosher-typesense-search')); ?>: ' + (summary.processed || 0) + '</li>' +
          '<li><?php echo esc_js(__('Updated', 'kosher-typesense-search')); ?>: ' + (summary.updated || 0) + '</li>' +
          '<li><?php echo esc_js(__('Failed', 'kosher-typesense-search')); ?>: ' + (summary.failed || 0) + '</li>' +
          '</ul>';
      }

      function request(mode, extra) {
        const body = new URLSearchParams();
        body.append('action', 'kosher_typesense_recipe_images');
        body.append('nonce', nonce);
        body.append('mode', mode);
        body.append('limit', Math.max(1, Math.min(250, parseInt(limitInput.value || '50', 10))));
        Object.keys(extra || {}).forEach(function (key) {
          body.append(key, extra[key]);
        });

        return fetch(ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString()
        }).then(function (response) {
          return response.json();
        }).then(function (response) {
          if (!response.success) {
            throw new Error(response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('The request failed.', 'kosher-typesense-search')); ?>');
          }
          return response.data;
        });
      }

      function loadCount() {
        setButtons(true);
        progress.innerHTML = '<p><?php echo esc_js(__('Loading count...', 'kosher-typesense-search')); ?></p>';
        request('count').then(function (data) {
          render({ initial: data.count || 0, remaining: data.count || 0 }, false);
          setButtons(false);
          loadResults(1);
        }).catch(function (error) {
          progress.innerHTML = '<p style="color:#b32d2e;"><strong><?php echo esc_js(__('Error:', 'kosher-typesense-search')); ?></strong> ' + error.message + '</p>';
          setButtons(false);
        });
      }

      function replaceBatch() {
        setButtons(true);
        const summary = { initial: 0, remaining: 0, processed: 0, updated: 0, failed: 0 };
        progress.innerHTML = '<p><?php echo esc_js(__('Starting replacement...', 'kosher-typesense-search')); ?></p>';

        function next() {
          request('replace').then(function (data) {
            if (!summary.initial) {
              summary.initial = data.found || 0;
            }

            summary.remaining = Math.max(0, (data.found || 0) - (data.updated || 0));
            summary.processed += data.processed || 0;
            summary.updated += data.updated || 0;
            summary.failed += data.failed || 0;
            render(summary, !data.complete);

            if (!data.complete) {
              next();
              return;
            }

            setButtons(false);
            render(summary, false);
            loadResults(1);
          }).catch(function (error) {
            progress.innerHTML += '<p style="color:#b32d2e;"><strong><?php echo esc_js(__('Error:', 'kosher-typesense-search')); ?></strong> ' + error.message + '</p>';
            setButtons(false);
          });
        }

        next();
      }

      function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
      }

      function loadResults(page) {
        results.innerHTML = '<p><?php echo esc_js(__('Loading recipes...', 'kosher-typesense-search')); ?></p>';
        request('list', {
          page: page || 1,
          per_page: 20
        }).then(function (data) {
          const rows = (data.items || []).map(function (item) {
            const link = item.url
              ? '<a href="' + escapeHtml(item.url) + '" target="_blank" rel="noopener"><?php echo esc_js(__('View', 'kosher-typesense-search')); ?></a>'
              : '';
            return '<tr>' +
              '<td>' + escapeHtml(item.id) + '</td>' +
              '<td>' + escapeHtml(item.title || '<?php echo esc_js(__('Untitled', 'kosher-typesense-search')); ?>') + '</td>' +
              '<td>' + link + '</td>' +
            '</tr>';
          }).join('');
          const prevDisabled = data.page <= 1 ? ' disabled' : '';
          const nextDisabled = data.page >= data.total_pages ? ' disabled' : '';

          results.innerHTML =
            '<p><strong>' + escapeHtml(data.total || 0) + '</strong> <?php echo esc_js(__('recipes found', 'kosher-typesense-search')); ?></p>' +
            '<table class="widefat striped"><thead><tr><th><?php echo esc_js(__('ID', 'kosher-typesense-search')); ?></th><th><?php echo esc_js(__('Recipe Title', 'kosher-typesense-search')); ?></th><th><?php echo esc_js(__('Link', 'kosher-typesense-search')); ?></th></tr></thead><tbody>' +
            (rows || '<tr><td colspan="3"><?php echo esc_js(__('No recipes found.', 'kosher-typesense-search')); ?></td></tr>') +
            '</tbody></table>' +
            '<p><button type="button" class="button" data-page="' + (data.page - 1) + '"' + prevDisabled + '><?php echo esc_js(__('Previous', 'kosher-typesense-search')); ?></button> ' +
            '<span><?php echo esc_js(__('Page', 'kosher-typesense-search')); ?> ' + escapeHtml(data.page) + ' / ' + escapeHtml(data.total_pages) + '</span> ' +
            '<button type="button" class="button" data-page="' + (data.page + 1) + '"' + nextDisabled + '><?php echo esc_js(__('Next', 'kosher-typesense-search')); ?></button></p>';

          results.querySelectorAll('button[data-page]').forEach(function (button) {
            button.addEventListener('click', function () {
              loadResults(parseInt(button.getAttribute('data-page'), 10));
            });
          });
        }).catch(function (error) {
          results.innerHTML = '<p style="color:#b32d2e;"><strong><?php echo esc_js(__('Error:', 'kosher-typesense-search')); ?></strong> ' + escapeHtml(error.message) + '</p>';
        });
      }

      buttons.forEach(function (button) {
        button.addEventListener('click', function () {
          if (button.getAttribute('data-kosher-typesense-recipe-images') === 'replace') {
            replaceBatch();
          } else {
            loadCount();
          }
        });
      });

      loadResults(1);
    }());
  </script>
  <?php
}

function kosher_typesense_load_integrated_runtime()
{
  if (!function_exists('typesense_post_to_record')) {
    $kosher_typesense_functions = plugin_dir_path(__FILE__) . 'includes/typesense-functions.php';
    if (file_exists($kosher_typesense_functions)) {
      require_once $kosher_typesense_functions;
    }
  }

  if (!isset($GLOBALS['typesense']) && function_exists('ap_typesense')) {
    $GLOBALS['typesense'] = ap_typesense();
  }

  if (defined('WP_CLI') && WP_CLI) {
    $kosher_typesense_cli = plugin_dir_path(__FILE__) . 'includes/typesense-wp-cli.php';
    if (file_exists($kosher_typesense_cli)) {
      require_once $kosher_typesense_cli;
    }
  }
}
add_action('plugins_loaded', 'kosher_typesense_load_integrated_runtime', 20);

// Register the shortcode 
function kosher_typesense_search_shortcode($atts = array())
{
    $atts = shortcode_atts(array(
        'template'    => 'default',
        'placeholder' => 'What do you want to make?...',
        'target'      => '',
    ), $atts, 'kosher_typesense_search');

    $template_file = 'search-template.php';

    if ($atts['template'] === 'header') {
        $template_file = 'header-search-template.php';
    }

    if ($atts['template'] === 'filter') {
        $template_file = 'search-filter-template.php';
    }

    $placeholder = $atts['placeholder'];
    $target      = $atts['target'];

    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/' . $template_file;
    return ob_get_clean();
}
add_shortcode('kosher_typesense_search', 'kosher_typesense_search_shortcode');
add_shortcode('kayco_typesense_search', 'kosher_typesense_search_shortcode');

/**
 * Identify the temporary natural-language search route. This supports both a
 * real WordPress page with the search-ai slug and the plugin's virtual fallback
 * route used in local/testing environments.
 *
 * @return bool
 */
function kosher_typesense_is_natural_language_search_page()
{
  if (is_page('search-ai')) {
    return true;
  }

  $request_path = isset($_SERVER['REQUEST_URI'])
    ? (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH)
    : '';

  return untrailingslashit($request_path) === '/search-ai';
}

/**
 * Render /search-ai even when no WordPress Page has been created yet.
 */
function kosher_typesense_render_natural_language_search_page()
{
  if (!kosher_typesense_is_natural_language_search_page() || is_page('search-ai')) {
    return;
  }

  global $wp_query;
  if ($wp_query instanceof WP_Query) {
    $wp_query->is_404 = false;
  }

  status_header(200);
  nocache_headers();
  add_filter('pre_get_document_title', function () {
    return __('AI Search', 'kosher-typesense-search');
  });

  get_header();
  echo do_shortcode('[kosher_typesense_search template="filter"]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
  get_footer();
  exit;
}
add_action('template_redirect', 'kosher_typesense_render_natural_language_search_page', 0);





function kosher_typesense_get_approved_comments_count_ajax()
{
  if (!isset($_POST['post_id'])) {
    wp_send_json_error('No post ID provided');
  }

  $post_id = intval($_POST['post_id']);
  $comments_count = wp_count_comments($post_id)->approved;

  wp_send_json_success($comments_count);
}
add_action('wp_ajax_get_comments_count', 'kosher_typesense_get_approved_comments_count_ajax');
add_action('wp_ajax_nopriv_get_comments_count', 'kosher_typesense_get_approved_comments_count_ajax');


add_action('wp_ajax_fetch_taxonomy_image', 'kosher_typesense_fetch_taxonomy_image');
add_action('wp_ajax_nopriv_fetch_taxonomy_image', 'kosher_typesense_fetch_taxonomy_image');

function kosher_typesense_fetch_taxonomy_image()
{
  $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

  if (!$post_id) {
    wp_send_json_error('Invalid post ID');
  }

  // Get the taxonomy ID associated with this post
  $taxonomy_id = get_post_meta($post_id, 'sources', true);

  if (!$taxonomy_id) {
    wp_send_json_error('No taxonomy ID found');
  }

  // Get the image ID associated with the taxonomy
  $image_id = get_term_meta($taxonomy_id, 'image', true);

  if (!$image_id) {
    wp_send_json_error('No image found for this taxonomy');
  }

  // Get the image URL
  $image_url = wp_get_attachment_url($image_id);

  if (!$image_url) {
    wp_send_json_error('No image URL found');
  }

  // Return the image URL as a JSON response
  wp_send_json_success(['image_url' => $image_url]);
}


function kosher_typesense_search_enqueue_fontawesome()
{
  if (!wp_style_is('font-awesome', 'enqueued')) {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css', array(), '6.0.0-beta3');
    wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css', array(), '1.11.3');
  }
}
add_action('wp_enqueue_scripts', 'kosher_typesense_search_enqueue_fontawesome');

function kosher_typesense_search_scripts()
{
  wp_enqueue_script('mobile-detect', 'https://cdnjs.cloudflare.com/ajax/libs/mobile-detect/1.4.5/mobile-detect.min.js', array(), '1.4.5', false);
  wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', array(), '11');
  wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', array(), '11', false);
  wp_enqueue_style('tom-select', 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css', array(), '2.3.1');
  wp_enqueue_script('tom-select', 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js', array(), '2.3.1', true);

  $filter_dependencies = array('jquery', 'tom-select', 'swiper-js', 'mobile-detect');

  if (kosher_typesense_is_natural_language_search_page()) {
    wp_enqueue_script(
      'kosher-typesense-search-filter-natural-language',
      plugin_dir_url(__FILE__) . 'js/kosher-typesense-search-filter-natural-language.js',
      array(),
      kosher_typesense_asset_version('js/kosher-typesense-search-filter-natural-language.js'),
      true
    );

    wp_localize_script(
      'kosher-typesense-search-filter-natural-language',
      'kosherNaturalLanguageConfig',
      array(
        'searchAction' => 'kosher_typesense_search',
        'translateAction' => 'kosher_typesense_nl_translate',
        'searchNonce' => wp_create_nonce('kosher_typesense_search_nonce'),
        'modelId' => kosher_typesense_nl_model_id(),
        'recipeRankingFieldsAvailable' => kosher_typesense_recipe_ranking_fields_available(),
        'debugEnabled' => current_user_can('manage_options'),
      )
    );

    $filter_dependencies[] = 'kosher-typesense-search-filter-natural-language';
  }

  wp_enqueue_script('kosher-typesense-search-filter', plugin_dir_url(__FILE__) . 'js/kosher-typesense-search-filter.js', $filter_dependencies, kosher_typesense_asset_version('js/kosher-typesense-search-filter.js'), true);
  wp_enqueue_script('kosher-typesense-search', plugin_dir_url(__FILE__) . 'js/kosher-typesense-search.js', array('jquery'), kosher_typesense_asset_version('js/kosher-typesense-search.js'), true);
  wp_enqueue_script('kosher-media-playback-guard', plugin_dir_url(__FILE__) . 'js/kosher-media-playback-guard.js', array(), kosher_typesense_asset_version('js/kosher-media-playback-guard.js'), true);
  wp_enqueue_style('kosher-typesense-search-styles', plugin_dir_url(__FILE__) . 'css/main.css', array(), kosher_typesense_asset_version('css/main.css'));
  wp_enqueue_style('kosher-typesense-search-styles-extra', plugin_dir_url(__FILE__) . 'css/style-search.css', array(), kosher_typesense_asset_version('css/style-search.css'));

  $no_results_suggestions = function_exists('get_field') ? (string) get_field('no_results_search_suggestions', 'option') : '';
  $no_results_suggestions_articles = function_exists('get_field') ? (string) get_field('no_results_search_suggestions_articles', 'option') : '';
  $no_results_suggestions_shows = function_exists('get_field') ? (string) get_field('no_results_search_suggestions_shows', 'option') : '';
  $suggestions_array = explode(',', $no_results_suggestions); // Convert comma-separated string to array
  $suggestions_array['recipes'] = explode(',', $no_results_suggestions);
  $suggestions_array['menus'] = explode(',', $no_results_suggestions);
  $suggestions_array['articles'] = explode(',', $no_results_suggestions_articles);
  $suggestions_array['shows'] = explode(',', $no_results_suggestions_shows);

  $shared_config = array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'ajax_url' => admin_url('admin-ajax.php'),
    'searchAction' => 'kosher_typesense_search',
    'legacySearchAction' => 'kayco_typesense_search',
    'searchNonce' => wp_create_nonce('kosher_typesense_search_nonce'),
    'legacySearchNonce' => wp_create_nonce('kayco_typesense_search_nonce'),
    'noResultsSuggestions' => $suggestions_array,
    'security' => wp_create_nonce('update_like_nonce'),
	    'analyticsAction' => 'kosher_search_track_event',
	    'analyticsNonce' => wp_create_nonce('kosher_typesense_analytics_nonce'),
	    'isAuthenticated' => is_user_logged_in(),
	    'collectionPrefix' => kosher_typesense_collection_prefix(),
	    'collectionEnvironment' => kosher_typesense_collection_environment(),
	    'submitSearchOnEnterOnly' => kosher_typesense_is_natural_language_search_page(),
	    'collections' => array(
	      'recipes' => kosher_typesense_collection_name('recipes'),
	      'articles' => kosher_typesense_collection_name('articles'),
	      'episodes' => kosher_typesense_collection_name('episodes'),
	      'shows' => kosher_typesense_collection_name('shows'),
	      'menus' => kosher_typesense_collection_name('menus'),
	    ),
	    'pluginDirUrl' => plugin_dir_url(__FILE__)
	  );

  wp_localize_script('kosher-typesense-search-filter', 'typeSenseConfig', $shared_config);
  wp_localize_script('kosher-typesense-search', 'typeSenseConfig', $shared_config);
}
add_action('wp_enqueue_scripts', 'kosher_typesense_search_scripts');

/**
 * Resolve a possible recipe-chef name against the actual Typesense index.
 * This scales independently of the number of chefs and preserves stored casing.
 *
 * @param string $query Natural-language query.
 * @return array|null
 */
function kosher_typesense_resolve_recipe_chef($query)
{
  $query = trim((string) $query);
  $candidate = $query;
  $subject = '';

  if (preg_match('/^(.*?)(?:\brecipes?\s+by\b|\bby\b|\bfrom\b|\bchef\b)\s+(.+)$/iu', $query, $matches)) {
    $subject = trim((string) $matches[1]);
    $candidate = trim((string) $matches[2]);
  }

  $subject = trim((string) preg_replace('/\b(recipes?|dishes|food)\b/iu', ' ', $subject));
  if ($candidate === '' || count(preg_split('/\s+/u', $candidate)) > 5) {
    return null;
  }

  $params = array(
    'q' => $candidate,
    'query_by' => 'chefs',
    'per_page' => 10,
    'include_fields' => 'chefs',
    'num_typos' => 2,
  );
  $url = add_query_arg($params, kosher_typesense_url(
    'collections/' . rawurlencode(kosher_typesense_collection_name('recipes')) . '/documents/search'
  ));
  $response = wp_remote_get($url, array(
    'headers' => array('X-TYPESENSE-API-KEY' => kosher_typesense_search_api_key()),
    'timeout' => 8,
  ));

  if (is_wp_error($response) || wp_remote_retrieve_response_code($response) < 200 || wp_remote_retrieve_response_code($response) >= 300) {
    return null;
  }

  $data = json_decode(wp_remote_retrieve_body($response), true);
  $candidate_normalized = strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $candidate)));
  $partial_matches = array();

  foreach (($data['hits'] ?? array()) as $hit) {
    foreach (($hit['document']['chefs'] ?? array()) as $chef) {
      $chef_normalized = strtolower(trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', (string) $chef)));
      if ($chef_normalized === $candidate_normalized) {
        return array(
          'name' => (string) $chef,
          'q' => $subject !== '' ? $subject : '*',
          'match_type' => 'exact',
          'standalone' => $subject === '',
        );
      }

      if (preg_match('/(^|\s)' . preg_quote($candidate_normalized, '/') . '(\s|$)/u', $chef_normalized)) {
        $partial_matches[(string) $chef] = true;
      }
    }
  }

  if (!empty($partial_matches)) {
    return array(
      'name' => $candidate,
      'q' => $subject !== '' ? trim($subject . ' ' . $candidate) : $candidate,
      'match_type' => 'partial',
      'matches' => array_slice(array_keys($partial_matches), 0, 10),
      'standalone' => $subject === '',
    );
  }

  return null;
}

/**
 * Translate a free-form search with the site's existing OpenAI connection when
 * the Typesense cluster does not have an NLS model configured.
 */
function kosher_typesense_nl_translate()
{
  $nonce = isset($_SERVER['HTTP_X_KOSHER_TYPESENSE_NONCE'])
    ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_KOSHER_TYPESENSE_NONCE']))
    : '';

  if (!wp_verify_nonce($nonce, 'kosher_typesense_search_nonce')) {
    wp_send_json(array('error' => 'Invalid request token.'), 403);
  }

  $body = kosher_typesense_read_json_request();
  $query = isset($body['q']) ? sanitize_text_field((string) $body['q']) : '';
  if ($query === '') {
    wp_send_json(array('translations' => array()));
  }

  $settings = get_option('kayco_ai_settings', array());
  $api_key = kosher_typesense_openai_api_key();
  $model = is_array($settings) && !empty($settings['model']) ? sanitize_text_field((string) $settings['model']) : 'gpt-4o-mini';

  if ($api_key === '') {
    wp_send_json(array('error' => 'OpenAI API key is not configured.'), 500);
  }

  $instructions = 'Convert the user request into Typesense search parameters for each collection. Return JSON only with a top-level translations object containing recipes, menus, articles, and episodes. Each must contain q, filter_by, sort_by, and primary_dish strings. primary_dish is the dish category the user wants, such as chicken thighs, chicken breasts, brisket, steak, salmon, potato kugel, cheesecake, cookies, challah, soup, or lasagna. A protein, cut of meat, poultry part, fish, baked good, or established preparation can itself be a finished dish category; NEVER turn a standalone dish request such as "chicken thighs", "brisket", "salmon", or "potato kugel" into an ingredients filter. For those examples keep the phrase in q, set the same phrase in primary_dish, and leave ingredients out of filter_by. Use ingredients:= only when the user explicitly expresses ingredient composition with wording such as "with", "containing", "made with", "using", or "that has". Use ingredients:!= for explicit exclusions such as "without". Keep the main subject in q and remove only words consumed by real filters. Use only these mappings. Recipes: holidays=occasions:=`Value`; Dairy/Meat/Parve=preference:=`Value`; diets=diets:=`Value`; allergen-free or without an allergen=contains_allergents:!=`Value`; difficulty=difficulty:=`Value`; sources=sources:=`Value`; categories=recipe_category:=`Value`; cuisines=cuisine:=`Value`; included ingredients=ingredients:=`Value`; excluded ingredients=ingredients:!=`Value`; chefs=chefs:=`Value`; time limit=cook_time:<minutes. Articles: authors=author:=`Value`; categories=article_sub_category:=`Value`. Menus: authors=author_name:=`Value`; categories=categories:=`Value`; holidays=holidays:=`Value`. Episodes: chefs=chef:=`Value`; shows=show:=`Value`. Recognize likely human names even when lowercase, unfamiliar, Hebrew-derived, Yiddish-derived, or Jewish; do not require prior knowledge of the person. For recipe intent, a standalone likely person name or a name after chef, by, from, recipes by, or recipes from uses chefs:=`Title Cased Name`; for example esty wolbe and recipes by esty wolbe both use q:* and chefs:=`Esty Wolbe`. For episodes use chef:=`Title Cased Name`; for articles use author:=`Title Cased Name`; for menus use author_name:=`Title Cased Name`. A standalone person name defaults to recipe chef intent; only apply article, menu, or episode person filters when that content type is explicit. Do not classify dish names, ingredients, holidays, cuisines, diets, or techniques as people. Join alternatives with || and independent constraints with &&. Sort newest date:desc, oldest date:asc, highest rated rating:desc, popular likes:desc where supported. If a constraint does not apply to a collection, leave it out of that collection and keep only the relevant subject in q. Correct obvious food misspellings. Never invent filters outside these mappings.';

  $instructions_file = plugin_dir_path(__FILE__) . 'prompts/natural-language-search.txt';
  if (is_readable($instructions_file)) {
    $file_instructions = file_get_contents($instructions_file);
    if (is_string($file_instructions) && trim($file_instructions) !== '') {
      $instructions = trim($file_instructions);
    }
  }

  $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
    'headers' => array(
      'Authorization' => 'Bearer ' . $api_key,
      'Content-Type' => 'application/json',
    ),
    'timeout' => 10,
    'body' => wp_json_encode(array(
      'model' => $model,
      'temperature' => 0,
      'response_format' => array('type' => 'json_object'),
      'messages' => array(
        array('role' => 'system', 'content' => $instructions),
        array('role' => 'user', 'content' => $query),
      ),
    )),
  ));

  if (is_wp_error($response)) {
    wp_send_json(array('error' => $response->get_error_message()), 502);
  }

  $status = wp_remote_retrieve_response_code($response);
  $decoded = json_decode(wp_remote_retrieve_body($response), true);
  $content = $decoded['choices'][0]['message']['content'] ?? '';
  $translated = json_decode($content, true);

  if ($status < 200 || $status >= 300 || !is_array($translated)) {
    $message = $decoded['error']['message'] ?? 'OpenAI returned an invalid translation.';
    wp_send_json(array('error' => $message), $status >= 400 ? $status : 502);
  }

  $translations = array();
  foreach (array('recipes', 'menus', 'articles', 'episodes') as $collection) {
    $params = isset($translated['translations'][$collection]) && is_array($translated['translations'][$collection])
      ? $translated['translations'][$collection]
      : array();
    $translations[$collection] = array(
      'q' => kosher_typesense_sanitize_scalar($params['q'] ?? $query, 500),
      'filter_by' => kosher_typesense_normalize_ai_filter($params['filter_by'] ?? '', $collection),
      'sort_by' => kosher_typesense_sanitize_sort($params['sort_by'] ?? ''),
      'primary_dish' => kosher_typesense_sanitize_scalar($params['primary_dish'] ?? '', 100),
    );
  }

  // Guard against a recurring model error: the subject of a standalone dish
  // search must not also become an ingredient constraint. This correction is
  // generic, so it covers poultry parts, meat cuts, fish and named dishes
  // without maintaining a fragile hard-coded food list.
  $recipe_query = trim((string) $translations['recipes']['q']);
  $recipe_filter = trim((string) $translations['recipes']['filter_by']);
  $normalize_intent = static function ($value) {
    $value = strtolower(remove_accents(wp_strip_all_tags((string) $value)));
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $value));
  };
  $normalized_recipe_query = $normalize_intent($recipe_query);
  $standalone_request = !preg_match('/\b(with|containing|made with|using|that has|without|excluding|except)\b/i', $query);

  if ($standalone_request && $normalized_recipe_query !== '' && $normalized_recipe_query !== '*') {
    $recipe_filter = preg_replace_callback(
      '/\(?\s*ingredients\s*:=\s*(`([^`]*)`|"([^"]*)"|([^&|()]+))\s*\)?/i',
      static function ($matches) use ($normalize_intent, $normalized_recipe_query) {
        $value = $matches[2] !== '' ? $matches[2] : ($matches[3] !== '' ? $matches[3] : $matches[4]);
        return $normalize_intent($value) === $normalized_recipe_query ? '' : $matches[0];
      },
      $recipe_filter
    );
    $recipe_filter = preg_replace('/^\s*(&&|\|\|)\s*|\s*(&&|\|\|)\s*$/', '', (string) $recipe_filter);
    $recipe_filter = preg_replace('/\s*(&&|\|\|)\s*(?=&&|\|\|)/', ' ', (string) $recipe_filter);
    $translations['recipes']['filter_by'] = trim((string) $recipe_filter, " \t\n\r\0\x0B()");

    if ($translations['recipes']['primary_dish'] === '') {
      $translations['recipes']['primary_dish'] = $recipe_query;
    }
  }

  $resolved_chef = kosher_typesense_resolve_recipe_chef($query);
  if (is_array($resolved_chef) && !empty($resolved_chef['name'])) {
    $existing_filter = trim((string) $translations['recipes']['filter_by']);
    $translations['recipes']['q'] = (string) $resolved_chef['q'];

    if (($resolved_chef['match_type'] ?? '') === 'exact') {
      $chef_filter = 'chefs:=`' . str_replace('`', '\\`', $resolved_chef['name']) . '`';
      $translations['recipes']['filter_by'] = $existing_filter !== ''
        ? '(' . $existing_filter . ') && (' . $chef_filter . ')'
        : $chef_filter;
    } elseif (!empty($resolved_chef['standalone'])) {
      // A first-name-only lookup is intentionally a text search within the
      // chefs field, not an exact filter against a possibly incomplete name.
      $translations['recipes']['filter_by'] = '';
    }

    $translations['recipes']['resolved_entities'] = array(
      'chef' => $resolved_chef['name'],
      'source' => 'typesense_recipes.chefs',
      'match_type' => $resolved_chef['match_type'] ?? 'exact',
      'candidate_matches' => $resolved_chef['matches'] ?? array($resolved_chef['name']),
    );
  }

  wp_send_json(array('translations' => $translations));
}
add_action('wp_ajax_kosher_typesense_nl_translate', 'kosher_typesense_nl_translate');
add_action('wp_ajax_nopriv_kosher_typesense_nl_translate', 'kosher_typesense_nl_translate');

/**
 * Correct common model field aliases and quote generated string values before
 * the filter reaches Typesense.
 *
 * @param string $filter Generated Typesense filter.
 * @param string $collection Logical collection slug.
 * @return string
 */
function kosher_typesense_normalize_ai_filter($filter, $collection)
{
  $filter = kosher_typesense_sanitize_filter_by($filter, 2000);
  if ($filter === '') {
    return '';
  }

  $aliases = array(
    'recipes' => array(
      'holiday' => 'occasions',
      'holidays' => 'occasions',
      'occasion' => 'occasions',
      'allergens' => 'contains_allergents',
      'allergies' => 'contains_allergents',
      'category' => 'recipe_category',
      'categories' => 'recipe_category',
      'chef' => 'chefs',
      'diet' => 'diets',
      'source' => 'sources',
      'ingredient' => 'ingredients',
    ),
    'articles' => array('authors' => 'author', 'category' => 'article_sub_category', 'categories' => 'article_sub_category'),
    'menus' => array('author' => 'author_name', 'category' => 'categories', 'occasion' => 'holidays', 'occasions' => 'holidays'),
    'episodes' => array('chefs' => 'chef', 'shows' => 'show'),
  );

  foreach (($aliases[$collection] ?? array()) as $from => $to) {
    $filter = preg_replace('/\b' . preg_quote($from, '/') . '\s*:/i', $to . ':', $filter);
  }

  $filter = preg_replace_callback(
    '/([A-Za-z0-9_-]+)\s*(:!=|:=|:<=|:>=|:<|:>)\s*([^&|()]+)/',
    function ($matches) {
      $value = trim((string) $matches[3]);
      $is_quoted = strlen($value) >= 2 && (
        ($value[0] === '`' && substr($value, -1) === '`') ||
        ($value[0] === '"' && substr($value, -1) === '"')
      );
      $is_scalar = is_numeric($value) || in_array(strtolower($value), array('true', 'false'), true);

      if (!$is_quoted && !$is_scalar) {
        $value = '`' . str_replace('`', '\\`', $value) . '`';
      }

      return $matches[1] . $matches[2] . $value;
    },
    $filter
  );

  return kosher_typesense_sanitize_filter_by($filter, 2000);
}

function kosher_typesense_register_sources_rest_route()
{
  register_rest_route('custom/v1', '/source/(?P<id>\d+)', array(
    'methods' => 'GET',
    'callback' => 'kosher_typesense_get_source_image',
    'permission_callback' => '__return_true',
  ));
}

function kosher_typesense_get_source_image($data)
{
  $source_id = absint($data['id']);
  $source_img = function_exists('get_field') ? get_field('source_image', 'sources_' . $source_id) : '';

  if ($source_img) {
    return [
      'image' => $source_img
    ];
  } else {
    return new WP_Error('no_image', 'No image found', array('status' => 404));
  }
}

add_action('rest_api_init', 'kosher_typesense_register_sources_rest_route');


add_action('wp_ajax_get_ingredients_terms', 'kosher_typesense_get_ingredients_terms');
add_action('wp_ajax_nopriv_get_ingredients_terms', 'kosher_typesense_get_ingredients_terms'); // If this should be available to non-logged-in users as well


function kosher_typesense_get_ingredients_terms()
{
  $search_term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

  if (strlen($search_term) < 3) {
    wp_send_json(array());
  }

  $terms = get_terms(array(
    'taxonomy' => 'ingredients_single',
    'hide_empty' => false,
    'search' => $search_term,
    'number' => 10, // Limit results for performance
  ));

  $results = array();

  if (is_wp_error($terms)) {
    wp_send_json($results);
  }

  foreach ($terms as $term) {
    $results[] = array(
      'id' => $term->term_id,
      'name' => $term->name,
    );
  }

  wp_send_json($results);
}

add_action('wp_ajax_get_chefs', 'kosher_typesense_get_chefs');
add_action('wp_ajax_nopriv_get_chefs', 'kosher_typesense_get_chefs'); // If this should be available to non-logged-in users as well

function kosher_typesense_get_chefs()
{
  // Get the search query from the AJAX request
  $search_term = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';

  if (strlen($search_term) < 3) {
    wp_send_json(array());
  }

  // Query users with the role 'chef'
  $args = array(
    'role' => 'chef',  // The role we are filtering by
    'search' => '*' . esc_attr($search_term) . '*',  // Search by display name
    'search_columns' => array('display_name'),
    'number' => 10,  // Limit the number of results for performance
  );
  $user_query = new WP_User_Query($args);

  $results = array();

  // Loop through the users and prepare the result
  if (!empty($user_query->get_results())) {
    foreach ($user_query->get_results() as $user) {
      $results[] = array(
        'id' => $user->ID,  // The user ID
        'name' => $user->display_name,  // The display name to be shown in the dropdown
      );
    }
  }

  // Send the results back as JSON
  wp_send_json($results);
}

function kosher_typesense_add_type_attribute_to_typesense_script($tag, $handle, $src) {
    // Add the module type to the specific handle
    if ('kosher-typesense-search-filter' === $handle) {
        $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
    }
    return $tag;
}
add_filter('script_loader_tag', 'kosher_typesense_add_type_attribute_to_typesense_script', 10, 3);

add_action('wp_ajax_kosher_typesense_search', 'kosher_typesense_search');
add_action('wp_ajax_nopriv_kosher_typesense_search', 'kosher_typesense_search');
add_action('wp_ajax_kayco_typesense_search', 'kosher_typesense_search');
add_action('wp_ajax_nopriv_kayco_typesense_search', 'kosher_typesense_search');
add_action('wp_ajax_kosher_typesense_toggle_favorite', 'kosher_typesense_toggle_favorite');
add_action('wp_ajax_nopriv_kosher_typesense_toggle_favorite', 'kosher_typesense_toggle_favorite');

function kosher_typesense_read_json_request()
{
  $body = json_decode(file_get_contents('php://input'), true);
  return is_array($body) ? $body : array();
}

/**
 * Toggle a search-result favorite using the current account folder system.
 */
function kosher_typesense_toggle_favorite()
{
  $request = kosher_typesense_read_json_request();
  $nonce   = isset($request['nonce']) ? sanitize_text_field((string) $request['nonce']) : '';

  if (!wp_verify_nonce($nonce, 'update_like_nonce')) {
    wp_send_json_error(array('message' => 'Invalid request token.'), 403);
  }

  if (!is_user_logged_in()) {
    wp_send_json_error(
      array(
        'message'        => 'Please log in to save favorites.',
        'login_required' => true,
        'login_url'      => wp_login_url(wp_get_referer() ?: home_url('/')),
      ),
      403
    );
  }

  $post_id = isset($request['id']) ? absint($request['id']) : 0;
  $user_id = get_current_user_id();

  if (!$post_id || !get_post($post_id)) {
    wp_send_json_error(array('message' => 'Content not found.'), 404);
  }

  $required_functions = array(
    'kayco_get_folder_memberships_for_post',
    'kayco_remove_post_from_account_folders',
    'kayco_move_post_to_account_folder',
    'kayco_get_account_default_folder_id',
    'kayco_update_account_user_folders',
    'kayco_get_account_favorite_post_ids',
    'kayco_get_post_favorite_count',
  );

  foreach ($required_functions as $required_function) {
    if (!function_exists($required_function)) {
      wp_send_json_error(array('message' => 'Favorites are temporarily unavailable.'), 503);
    }
  }

  $was_favorite = !empty(kayco_get_folder_memberships_for_post($user_id, $post_id));
  $folders      = $was_favorite
    ? kayco_remove_post_from_account_folders($user_id, $post_id)
    : kayco_move_post_to_account_folder($user_id, $post_id, kayco_get_account_default_folder_id());

  kayco_update_account_user_folders($user_id, $folders);
  update_user_meta($user_id, 'kayco_favorite_posts', kayco_get_account_favorite_post_ids($user_id));

  $is_favorite = !empty(kayco_get_folder_memberships_for_post($user_id, $post_id));
  $count_delta = $is_favorite === $was_favorite ? 0 : ($is_favorite ? 1 : -1);
  $total_likes = $count_delta && function_exists('kayco_adjust_post_folder_favorite_count')
    ? kayco_adjust_post_folder_favorite_count($post_id, $count_delta)
    : kayco_get_post_favorite_count($post_id);

  if (function_exists('typesense_likes_to_record')) {
    typesense_likes_to_record($post_id);
  }

  wp_send_json_success(
    array(
      'action'      => $is_favorite ? 'insert' : 'delete',
      'exists'      => $is_favorite,
      'total_likes' => max(0, (int) $total_likes),
    )
  );
}

function kosher_typesense_sanitize_scalar($value, $max_length = 500)
{
  if (is_bool($value)) {
    return $value;
  }

  $value = sanitize_text_field(wp_check_invalid_utf8((string) $value));
  return substr($value, 0, $max_length);
}

/**
 * Sanitize a Typesense filter expression without corrupting comparison operators.
 *
 * sanitize_text_field() applies HTML-oriented less-than handling, which changes
 * expressions such as "cook_time:<60". JSON decoding has already removed any
 * transport escaping, so only invalid UTF-8 and control characters need to be
 * removed here.
 */
function kosher_typesense_sanitize_filter_by($value, $max_length = 2000)
{
  $value = wp_check_invalid_utf8((string) $value);
  $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);

  if (!is_string($value)) {
    return '';
  }

  return substr($value, 0, $max_length);
}

function kosher_typesense_allowed_collections()
{
  return array(
    kosher_typesense_collection_name('recipes'),
    kosher_typesense_collection_name('articles'),
    kosher_typesense_collection_name('episodes'),
    kosher_typesense_collection_name('shows'),
    kosher_typesense_collection_name('menus'),
  );
}

function kosher_typesense_collection_slug_map()
{
  return array(
    'recipes' => 'recipes',
    'articles' => 'articles',
    'episodes' => 'episodes',
    'shows' => 'shows',
    'menus' => 'menus',
  );
}

function kosher_typesense_normalize_collection($collection)
{
  $collection = kosher_typesense_sanitize_scalar($collection, 80);
  $slug_map = kosher_typesense_collection_slug_map();

  if (isset($slug_map[$collection])) {
    return kosher_typesense_collection_name($slug_map[$collection]);
  }

  foreach ($slug_map as $slug) {
    if (preg_match('/(^|_)(recipes|articles|episodes|shows|menus)$/', $collection, $matches) && $matches[2] === $slug) {
      return kosher_typesense_collection_name($slug);
    }
  }

  return $collection;
}

function kosher_typesense_allowed_presets()
{
  return array('recipes_search', 'articles_search', 'shows_search', 'menus_search');
}

function kosher_typesense_sanitize_sort($sort_by)
{
  $sort_by = kosher_typesense_sanitize_scalar($sort_by, 120);
  if ($sort_by === '') {
    return '';
  }

  $allowed_fields = array('_text_match', 'date', 'title', 'title_sort', 'rating', 'likes', 'cook_time', 'search_priority', 'sections_count', 'cards_count');
  $parts = array();

  foreach (explode(',', $sort_by) as $part) {
    $part = trim($part);
    if (!preg_match('/^([A-Za-z0-9_]+):(asc|desc)$/', $part, $matches)) {
      continue;
    }

    if (in_array($matches[1], $allowed_fields, true)) {
      $parts[] = $matches[1] . ':' . $matches[2];
    }
  }

  return implode(',', $parts);
}

function kosher_typesense_sanitize_search_request($search)
{
  if (!is_array($search)) {
    return null;
  }

  $allowed_keys = array(
    'collection',
    'preset',
    'q',
    'query_by',
    'query_by_weights',
    'include_fields',
    'filter_by',
    'page',
	    'limit',
	    'per_page',
    'sort_by',
    'prioritize_exact_match',
    'prioritize_token_position',
    'text_match_type',
    'num_typos',
    'drop_tokens_threshold',
    'limit_hits',
    'typo_tokens_threshold',
    'use_synonyms',
    'enable_overrides',
    'nl_query',
    'nl_query_debug',
    'nl_model_id',
  );

  $clean = array();

  foreach ($allowed_keys as $key) {
    if (!array_key_exists($key, $search)) {
      continue;
    }

    switch ($key) {
      case 'collection':
        $collection = kosher_typesense_normalize_collection($search[$key]);
        if (!in_array($collection, kosher_typesense_allowed_collections(), true)) {
          return null;
        }
        $clean[$key] = $collection;
        break;

      case 'preset':
        $preset = kosher_typesense_sanitize_scalar($search[$key], 80);
        if (!in_array($preset, kosher_typesense_allowed_presets(), true)) {
          return null;
        }
        $clean[$key] = $preset;
        break;

      case 'page':
        $clean[$key] = max(1, min(200, absint($search[$key])));
        break;

	      case 'limit':
	      case 'per_page':
	        $clean[$key] = max(1, min(45, absint($search[$key])));
	        break;

	      case 'sort_by':
        $sort_by = kosher_typesense_sanitize_sort($search[$key]);
        if ($sort_by !== '') {
          $clean[$key] = $sort_by;
        }
        break;

      case 'prioritize_exact_match':
      case 'prioritize_token_position':
      case 'use_synonyms':
      case 'enable_overrides':
      case 'nl_query':
      case 'nl_query_debug':
        $clean[$key] = (bool) $search[$key];
        break;

      case 'drop_tokens_threshold':
      case 'typo_tokens_threshold':
        $clean[$key] = max(0, min(200, absint($search[$key])));
        break;

      case 'limit_hits':
        $clean[$key] = max(1, min(10000, absint($search[$key])));
        break;

      default:
        $clean[$key] = $key === 'filter_by'
          ? kosher_typesense_sanitize_filter_by($search[$key], 2000)
          : kosher_typesense_sanitize_scalar($search[$key], 500);
        break;
    }
  }

  if (!isset($clean['collection']) && !isset($clean['preset'])) {
    return null;
  }

  if (!isset($clean['q'])) {
    $clean['q'] = '';
  }

  return $clean;
}

function kosher_typesense_sanitize_multi_search_payload($body)
{
  $payload = isset($body['payload']) && is_array($body['payload']) ? $body['payload'] : $body;

  if (!isset($payload['searches']) || !is_array($payload['searches'])) {
    return new WP_Error('invalid_payload', 'Invalid search payload.');
  }

  $clean_searches = array();
  foreach (array_slice($payload['searches'], 0, 6) as $search) {
    $clean_search = kosher_typesense_sanitize_search_request($search);
    if ($clean_search !== null) {
      $clean_searches[] = $clean_search;
    }
  }

  if (empty($clean_searches)) {
    return new WP_Error('invalid_searches', 'No allowed searches were requested.');
  }

  return array('searches' => $clean_searches);
}

function kosher_typesense_payload_uses_title_sort($payload)
{
  if (empty($payload['searches']) || !is_array($payload['searches'])) {
    return false;
  }

  foreach ($payload['searches'] as $search) {
    if (!empty($search['sort_by']) && false !== strpos((string) $search['sort_by'], 'title_sort:')) {
      return true;
    }
  }

  return false;
}

function kosher_typesense_title_sort_fallback_payload($payload)
{
  if (empty($payload['searches']) || !is_array($payload['searches'])) {
    return $payload;
  }

  foreach ($payload['searches'] as $index => $search) {
    if (!empty($search['sort_by'])) {
      $payload['searches'][$index]['sort_by'] = str_replace('title_sort:', 'title:', (string) $search['sort_by']);
    }
  }

  return $payload;
}

function kosher_typesense_response_has_title_sort_error($decoded)
{
  if (!is_array($decoded)) {
    return false;
  }

  $messages = array();

  if (!empty($decoded['message'])) {
    $messages[] = (string) $decoded['message'];
  }

  if (!empty($decoded['error'])) {
    $messages[] = is_string($decoded['error']) ? $decoded['error'] : wp_json_encode($decoded['error']);
  }

  if (!empty($decoded['results']) && is_array($decoded['results'])) {
    foreach ($decoded['results'] as $result) {
      if (!is_array($result)) {
        continue;
      }

      if (!empty($result['error'])) {
        $messages[] = is_string($result['error']) ? $result['error'] : wp_json_encode($result['error']);
      }

      if (!empty($result['message'])) {
        $messages[] = (string) $result['message'];
      }
    }
  }

  foreach ($messages as $message) {
    if (false !== stripos($message, 'title_sort')) {
      return true;
    }
  }

  return false;
}

function kosher_typesense_filter_primary_dish_hits($decoded, $primary_dish)
{
  if ($primary_dish === '' || empty($decoded['results'][0]['hits']) || !is_array($decoded['results'][0]['hits'])) {
    return $decoded;
  }

  $normalize = static function ($value) {
    $value = strtolower(remove_accents(wp_strip_all_tags((string) $value)));
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    return trim(preg_replace('/\s+/', ' ', (string) $value));
  };
  $dish = $normalize($primary_dish);
  $derivative_dishes = array(
    'pizza', 'pizzas', 'sandwich', 'sandwiches', 'burger', 'burgers',
    'taco', 'tacos', 'pie', 'pies', 'ice cream', 'milkshake', 'smoothie',
    'mousse', 'cannoli', 'cannolis', 'muffin', 'muffins', 'cup', 'cups',
    'popsicle', 'popsicles', 'dip', 'sauce', 'salad', 'soup', 'egg roll',
  );
  $scored_hits = array();

  foreach ($decoded['results'][0]['hits'] as $position => $hit) {
    $title = $normalize($hit['document']['title'] ?? '');
    $indexed_main_dish = $normalize(
      $hit['document']['main_dish']
        ?? $hit['document']['kosher_main_dish']
        ?? ''
    );
    $search_priority = max(0, min(1000, (int) (
      $hit['document']['search_priority']
        ?? $hit['document']['kosher_search_priority']
        ?? 0
    )));
    $score = 0;

    if ($dish !== '' && $indexed_main_dish !== '' && $indexed_main_dish === $dish) {
      $score = 2000;
    } elseif ($dish !== '' && $title === $dish) {
      $score = 1000;
    } elseif ($dish !== '' && preg_match('/(^| )' . preg_quote($dish, '/') . '( |$)/', $title)) {
      $score = str_ends_with($title, $dish) ? 800 : (str_starts_with($title, $dish) ? 700 : 600);
      $remainder = trim(str_replace($dish, ' ', $title));

      foreach ($derivative_dishes as $derivative) {
        if ($derivative !== $dish && preg_match('/(^| )' . preg_quote($derivative, '/') . '( |$)/', $remainder)) {
          $score -= 650;
          break;
        }
      }
    }

    $scored_hits[] = array(
      'hit' => $hit,
      'score' => $score,
      'priority' => $search_priority,
      'position' => $position,
    );
  }

  usort($scored_hits, static function ($left, $right) {
    return $right['score'] <=> $left['score']
      ?: $right['priority'] <=> $left['priority']
      ?: $left['position'] <=> $right['position'];
  });
  $decoded['results'][0]['hits'] = array_column($scored_hits, 'hit');
  $decoded['results'][0]['primary_dish_rerank'] = array(
    'requested_dish' => $primary_dish,
    'provider' => 'deterministic',
    'ranked' => count($scored_hits),
    'uses_main_dish' => (bool) array_filter($scored_hits, static function ($item) {
      return $item['score'] >= 2000;
    }),
    'uses_search_priority' => (bool) array_filter($scored_hits, static function ($item) {
      return $item['priority'] > 0;
    }),
  );

  $api_key = kosher_typesense_openai_api_key();
  if ($api_key === '') {
    return $decoded;
  }

  $candidates = array();
  foreach ($decoded['results'][0]['hits'] as $hit) {
    if (!empty($hit['document']['postID']) && !empty($hit['document']['title'])) {
      $candidates[] = array(
        'id' => (string) $hit['document']['postID'],
        'title' => (string) $hit['document']['title'],
      );
    }
  }

  if (empty($candidates)) {
    return $decoded;
  }

  $settings = get_option('kayco_ai_settings', array());
  $model = is_array($settings) && !empty($settings['model']) ? sanitize_text_field((string) $settings['model']) : 'gpt-4o-mini';
  $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
    'headers' => array('Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'),
    'timeout' => 10,
    'body' => wp_json_encode(array(
      'model' => $model,
      'temperature' => 0,
      'response_format' => array('type' => 'json_object'),
      'messages' => array(
        array(
          'role' => 'system',
          'content' => 'Return JSON with keep_ids and rejected arrays. Keep a recipe only when its primary finished dish is the requested dish. Reject titles where the requested term is merely a flavor, filling, layer, component, or modifier of a different dish. Example: for cheesecake, keep Classic Cheesecake and Strawberry Cheesecake, but reject Cheesecake Mousse Cannolis, Cheesecake Cookies, Cheesecake Milkshake, and Cheesecake Popsicles.',
        ),
        array('role' => 'user', 'content' => wp_json_encode(array('requested_dish' => $primary_dish, 'candidates' => $candidates))),
      ),
    )),
  ));

  if (is_wp_error($response) || wp_remote_retrieve_response_code($response) < 200 || wp_remote_retrieve_response_code($response) >= 300) {
    return $decoded;
  }

  $body = json_decode(wp_remote_retrieve_body($response), true);
  $classification = json_decode($body['choices'][0]['message']['content'] ?? '', true);
  if (!is_array($classification) || !array_key_exists('keep_ids', $classification) || !is_array($classification['keep_ids'])) {
    return $decoded;
  }

  $keep_ids = isset($classification['keep_ids']) && is_array($classification['keep_ids'])
    ? array_map('strval', $classification['keep_ids'])
    : array();

  // AI-confirmed canonical dishes lead; derivative recipes remain available
  // later in the result set instead of disappearing from pagination.
  usort($decoded['results'][0]['hits'], static function ($left, $right) use ($keep_ids) {
    $left_id = (string) ($left['document']['postID'] ?? '');
    $right_id = (string) ($right['document']['postID'] ?? '');
    return (int) in_array($right_id, $keep_ids, true) <=> (int) in_array($left_id, $keep_ids, true);
  });
  $decoded['results'][0]['primary_dish_rerank'] = array(
    'requested_dish' => $primary_dish,
    'provider' => 'openai',
    'canonical' => count($keep_ids),
    'rejected' => $classification['rejected'] ?? array(),
  );

  return $decoded;
}

function kosher_typesense_search() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_send_json(array('error' => 'Invalid request method.'), 405);
    }

    $nonce = isset($_SERVER['HTTP_X_KOSHER_TYPESENSE_NONCE'])
      ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_KOSHER_TYPESENSE_NONCE']))
      : '';

    if ($nonce === '' && isset($_SERVER['HTTP_X_KAYCO_TYPESENSE_NONCE'])) {
      $nonce = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_KAYCO_TYPESENSE_NONCE']));
    }

    $valid_nonce = wp_verify_nonce($nonce, 'kosher_typesense_search_nonce') || wp_verify_nonce($nonce, 'kayco_typesense_search_nonce');

    if (!$valid_nonce) {
        wp_send_json(array('error' => 'Invalid request token.'), 403);
    }

    // Scoped browser/search keys can embed a low `limit_hits` value (commonly
    // 200). That makes `found` report the full total while pages crossing the
    // embedded ceiling return no hits. This request is already server-side,
    // nonce-protected, read-only, and strictly sanitized, so use the private
    // server key when available to keep pagination aligned with `found`.
    $api_key = kosher_typesense_admin_api_key();

    if ($api_key === '') {
        $api_key = kosher_typesense_search_api_key();
    }

    if ($api_key === '') {
        wp_send_json(array('error' => 'Typesense search key is not configured.'), 500);
    }

    $payload = kosher_typesense_sanitize_multi_search_payload(kosher_typesense_read_json_request());

    if (is_wp_error($payload)) {
        wp_send_json(array('error' => $payload->get_error_message()), 400);
    }

    $is_suggestion_request = isset($_SERVER['HTTP_X_KOSHER_TYPESENSE_SUGGESTIONS'])
      && '1' === sanitize_text_field(wp_unslash($_SERVER['HTTP_X_KOSHER_TYPESENSE_SUGGESTIONS']));
    $primary_dish = isset($_SERVER['HTTP_X_KOSHER_PRIMARY_DISH'])
      ? kosher_typesense_sanitize_scalar(wp_unslash($_SERVER['HTTP_X_KOSHER_PRIMARY_DISH']), 100)
      : '';

    // Version the cache so responses produced under the previous scoped key
    // (including empty later pages) are not reused.
    $cache_key = 'kosher_ts_v6_' . md5(wp_json_encode($payload) . '|' . $primary_dish);
    $cache_ttl = (int) apply_filters('kosher_typesense_search_cache_ttl', 60, $payload);
    $cached = $cache_ttl > 0 ? get_transient($cache_key) : false;

    if ($cached !== false) {
        if (!$is_suggestion_request && function_exists('kosher_typesense_track_search_response')) {
            kosher_typesense_track_search_response($payload, $cached);
        }
        wp_send_json($cached);
    }

    $response = wp_remote_post(kosher_typesense_url('multi_search'), [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-TYPESENSE-API-KEY' => $api_key,
        ],
        'body' => wp_json_encode($payload),
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        kosher_typesense_debug_log('Typesense request failed.', array('error' => $response->get_error_message()));
        wp_send_json(array('error' => $response->get_error_message()), 502);
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if (!$status_code) {
        $status_code = 502;
    }

    $response_body = wp_remote_retrieve_body($response);
    $decoded = json_decode($response_body, true);

    if (!is_array($decoded)) {
        kosher_typesense_debug_log('Typesense returned invalid JSON.', array('status' => $status_code));
        wp_send_json(array('error' => 'Typesense returned an invalid response.'), 502);
    }

    if (kosher_typesense_payload_uses_title_sort($payload) && kosher_typesense_response_has_title_sort_error($decoded)) {
        $fallback_payload = kosher_typesense_title_sort_fallback_payload($payload);
        $fallback_response = wp_remote_post(kosher_typesense_url('multi_search'), [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-TYPESENSE-API-KEY' => $api_key,
            ],
            'body' => wp_json_encode($fallback_payload),
            'timeout' => 15,
        ]);

        if (!is_wp_error($fallback_response)) {
            $fallback_status_code = wp_remote_retrieve_response_code($fallback_response);
            $fallback_body = wp_remote_retrieve_body($fallback_response);
            $fallback_decoded = json_decode($fallback_body, true);

            if ($fallback_status_code >= 200 && $fallback_status_code < 300 && is_array($fallback_decoded)) {
                kosher_typesense_debug_log('Typesense title_sort fallback used.', array('payload' => $fallback_payload));
                $status_code = $fallback_status_code;
                $decoded = $fallback_decoded;
            }
        }
    }

    if ($status_code < 200 || $status_code >= 300) {
        wp_send_json($decoded, $status_code);
    }

    if ($cache_ttl > 0) {
        $decoded = kosher_typesense_filter_primary_dish_hits($decoded, $primary_dish);
        set_transient($cache_key, $decoded, $cache_ttl);
    } elseif ($primary_dish !== '') {
        $decoded = kosher_typesense_filter_primary_dish_hits($decoded, $primary_dish);
    }

    if (!$is_suggestion_request && function_exists('kosher_typesense_track_search_response')) {
        kosher_typesense_track_search_response($payload, $decoded);
    }

    wp_send_json($decoded);
}
