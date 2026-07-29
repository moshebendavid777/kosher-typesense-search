<?php
/**
 * File: analytics.php
 * Description: Search analytics storage, tracking, dashboard reporting, and CSV export.
 * Author: Kosher Dev Team
 */

if (! defined('ABSPATH')) {
  exit;
}

/**
 * Return the analytics table name.
 *
 * @return string
 */
function kosher_typesense_analytics_table()
{
  global $wpdb;

  return $wpdb->prefix . 'kosher_search_analytics';
}

/**
 * Create or update analytics tables.
 *
 * @return void
 */
function kosher_typesense_install_analytics_tables()
{
  global $wpdb;

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';

  $charset_collate = $wpdb->get_charset_collate();
  $table_name      = kosher_typesense_analytics_table();

  $sql = "CREATE TABLE {$table_name} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(24) NOT NULL DEFAULT 'search',
    query_text VARCHAR(255) NOT NULL DEFAULT '',
    result_count INT UNSIGNED NOT NULL DEFAULT 0,
    post_id BIGINT UNSIGNED NULL,
    result_position SMALLINT UNSIGNED NULL,
    result_type VARCHAR(32) NOT NULL DEFAULT '',
    tab VARCHAR(32) NOT NULL DEFAULT '',
    filters_hash CHAR(32) NOT NULL DEFAULT '',
    filters_json LONGTEXT NULL,
    url TEXT NULL,
    user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    session_hash CHAR(64) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    PRIMARY KEY  (id),
    KEY event_created (event_type, created_at),
    KEY query_created (query_text(120), created_at),
    KEY post_created (post_id, created_at),
    KEY filters_created (filters_hash, created_at)
  ) {$charset_collate};";

  dbDelta($sql);
}

/**
 * Ensure analytics schema exists after plugin updates.
 *
 * @return void
 */
function kosher_typesense_maybe_install_analytics_tables()
{
  $installed_version = get_option('kosher_typesense_analytics_db_version');

  if ($installed_version === KOSHER_TYPESENSE_SEARCH_VERSION) {
    return;
  }

  kosher_typesense_install_analytics_tables();
  update_option('kosher_typesense_analytics_db_version', KOSHER_TYPESENSE_SEARCH_VERSION, false);
}
add_action('init', 'kosher_typesense_maybe_install_analytics_tables', 5);

/**
 * Get an anonymized session hash.
 *
 * @return string
 */
function kosher_typesense_analytics_session_hash()
{
  $ip      = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
  $agent   = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
  $salt    = wp_salt('nonce');
  $user_id = get_current_user_id();

  return hash('sha256', $ip . '|' . $agent . '|' . $user_id . '|' . $salt);
}

/**
 * Normalize analytics filter values.
 *
 * @param mixed $filters Raw filter payload.
 * @return array
 */
function kosher_typesense_normalize_analytics_filters($filters)
{
  if (! is_array($filters)) {
    return array();
  }

  $normalized = array();

  foreach ($filters as $key => $value) {
    $clean_key = sanitize_key($key);

    if ($clean_key === '') {
      continue;
    }

    if (is_array($value)) {
      $normalized[$clean_key] = array_map('kosher_typesense_sanitize_scalar', $value);
    } else {
      $normalized[$clean_key] = kosher_typesense_sanitize_scalar($value);
    }
  }

  ksort($normalized);

  return $normalized;
}

/**
 * Insert an analytics event.
 *
 * @param array $event Analytics event.
 * @return void
 */
function kosher_typesense_record_analytics_event($event)
{
  global $wpdb;

  $event_type = isset($event['event_type']) ? sanitize_key($event['event_type']) : 'search';

  if (! in_array($event_type, array('search', 'click', 'zero_result'), true)) {
    return;
  }

  $filters      = isset($event['filters']) ? kosher_typesense_normalize_analytics_filters($event['filters']) : array();
  $filters_json = ! empty($filters) ? wp_json_encode($filters) : null;
  $result_count = isset($event['result_count']) ? absint($event['result_count']) : 0;

  $wpdb->insert(
    kosher_typesense_analytics_table(),
    array(
      'event_type'      => $event_type,
      'query_text'      => isset($event['query_text']) ? kosher_typesense_sanitize_scalar($event['query_text'], 255) : '',
      'result_count'    => $result_count,
      'post_id'         => isset($event['post_id']) ? absint($event['post_id']) : null,
      'result_position' => isset($event['result_position']) ? absint($event['result_position']) : null,
      'result_type'     => isset($event['result_type']) ? kosher_typesense_sanitize_scalar($event['result_type'], 32) : '',
      'tab'             => isset($event['tab']) ? kosher_typesense_sanitize_scalar($event['tab'], 32) : '',
      'filters_hash'    => $filters_json ? md5($filters_json) : '',
      'filters_json'    => $filters_json,
      'url'             => isset($event['url']) ? esc_url_raw($event['url']) : '',
      'user_id'         => get_current_user_id(),
      'session_hash'    => kosher_typesense_analytics_session_hash(),
      'created_at'      => current_time('mysql', true),
    ),
    array('%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
  );
}

/**
 * Track search analytics from the server response path.
 *
 * @param array $payload Typesense request payload.
 * @param array $response Typesense response.
 * @return void
 */
function kosher_typesense_track_search_response($payload, $response)
{
  if (empty($payload['searches'][0])) {
    return;
  }

  $first_search = $payload['searches'][0];
  $query        = isset($first_search['q']) ? kosher_typesense_sanitize_scalar($first_search['q'], 255) : '';
  $filters      = array();
  $result_count = 0;

  foreach ($payload['searches'] as $index => $search) {
    if (! empty($search['filter_by'])) {
      $filters['search_' . $index] = $search['filter_by'];
    }
  }

  if ($query === '' && empty($filters)) {
    return;
  }

  if (! empty($response['results']) && is_array($response['results'])) {
    foreach ($response['results'] as $index => $result) {
      if ($index > 2) {
        continue;
      }

      $result_count += isset($result['found']) ? absint($result['found']) : 0;
    }
  }

  $event_type = $result_count > 0 ? 'search' : 'zero_result';
  $throttle_key = 'kosher_search_event_' . md5(
    kosher_typesense_analytics_session_hash() . '|' . $event_type . '|' . $query . '|' . wp_json_encode($filters)
  );

  if (get_transient($throttle_key)) {
    return;
  }

  set_transient($throttle_key, 1, 20);

  kosher_typesense_record_analytics_event(
    array(
      'event_type'   => $event_type,
      'query_text'   => $query,
      'result_count' => $result_count,
      'filters'      => $filters,
    )
  );
}

/**
 * Track click events from the browser.
 *
 * @return void
 */
function kosher_typesense_track_event_ajax()
{
  $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

  if (! wp_verify_nonce($nonce, 'kosher_typesense_analytics_nonce')) {
    wp_send_json_error(array('message' => 'Invalid analytics token.'), 403);
  }

  $filters = array();

  if (! empty($_POST['filters'])) {
    $decoded_filters = json_decode(wp_unslash($_POST['filters']), true);
    $filters = is_array($decoded_filters) ? $decoded_filters : array();
  }

  kosher_typesense_record_analytics_event(
    array(
      'event_type'      => isset($_POST['event_type']) ? sanitize_key(wp_unslash($_POST['event_type'])) : 'click',
      'query_text'      => isset($_POST['query_text']) ? wp_unslash($_POST['query_text']) : '',
      'result_count'    => isset($_POST['result_count']) ? absint($_POST['result_count']) : 0,
      'post_id'         => isset($_POST['post_id']) ? absint($_POST['post_id']) : 0,
      'result_position' => isset($_POST['result_position']) ? absint($_POST['result_position']) : 0,
      'result_type'     => isset($_POST['result_type']) ? wp_unslash($_POST['result_type']) : '',
      'tab'             => isset($_POST['tab']) ? wp_unslash($_POST['tab']) : '',
      'filters'         => $filters,
      'url'             => isset($_POST['url']) ? wp_unslash($_POST['url']) : '',
    )
  );

  wp_send_json_success(array('tracked' => true));
}
add_action('wp_ajax_kosher_search_track_event', 'kosher_typesense_track_event_ajax');
add_action('wp_ajax_nopriv_kosher_search_track_event', 'kosher_typesense_track_event_ajax');

/**
 * Convert a dashboard range key to SQL dates.
 *
 * @return array
 */
function kosher_typesense_analytics_date_range()
{
  $range = isset($_GET['range']) ? sanitize_key(wp_unslash($_GET['range'])) : '7days';
  $from  = gmdate('Y-m-d 00:00:00', strtotime('-6 days'));
  $to    = gmdate('Y-m-d 23:59:59');

  switch ($range) {
    case 'today':
      $from = gmdate('Y-m-d 00:00:00');
      break;
    case '30days':
      $from = gmdate('Y-m-d 00:00:00', strtotime('-29 days'));
      break;
    case 'year':
      $from = gmdate('Y-m-d 00:00:00', strtotime('-1 year'));
      break;
    case 'custom':
      $from_input = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '';
      $to_input   = isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '';

      if ($from_input) {
        $from = gmdate('Y-m-d 00:00:00', strtotime($from_input));
      }

      if ($to_input) {
        $to = gmdate('Y-m-d 23:59:59', strtotime($to_input));
      }
      break;
  }

  return array($range, $from, $to);
}

/**
 * Fetch dashboard analytics.
 *
 * @return array
 */
function kosher_typesense_get_analytics_dashboard_data()
{
  global $wpdb;

  list($range, $from, $to) = kosher_typesense_analytics_date_range();
  $table = kosher_typesense_analytics_table();

  $totals = $wpdb->get_row(
    $wpdb->prepare(
      "SELECT
        SUM(event_type IN ('search', 'zero_result')) AS searches,
        SUM(event_type = 'click') AS clicks,
        SUM(event_type = 'zero_result') AS zero_results
      FROM {$table}
      WHERE created_at BETWEEN %s AND %s",
      $from,
      $to
    ),
    ARRAY_A
  );

  $trend = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT DATE(created_at) AS day, COUNT(*) AS total
      FROM {$table}
      WHERE event_type IN ('search', 'zero_result') AND created_at BETWEEN %s AND %s
      GROUP BY DATE(created_at)
      ORDER BY day ASC",
      $from,
      $to
    ),
    ARRAY_A
  );

  $top_queries = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT query_text, COUNT(*) AS total, AVG(result_count) AS avg_results
      FROM {$table}
      WHERE event_type IN ('search', 'zero_result') AND query_text != '' AND created_at BETWEEN %s AND %s
      GROUP BY query_text
      ORDER BY total DESC
      LIMIT 12",
      $from,
      $to
    ),
    ARRAY_A
  );

  $zero_queries = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT query_text, COUNT(*) AS total
      FROM {$table}
      WHERE event_type = 'zero_result' AND query_text != '' AND created_at BETWEEN %s AND %s
      GROUP BY query_text
      ORDER BY total DESC
      LIMIT 20",
      $from,
      $to
    ),
    ARRAY_A
  );

  $recent = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT event_type, query_text, result_count, post_id, result_position, result_type, tab, created_at
      FROM {$table}
      WHERE created_at BETWEEN %s AND %s
      ORDER BY created_at DESC
      LIMIT 100",
      $from,
      $to
    ),
    ARRAY_A
  );

  return array(
    'range'        => $range,
    'from'         => $from,
    'to'           => $to,
    'totals'       => array(
      'searches'     => isset($totals['searches']) ? absint($totals['searches']) : 0,
      'clicks'       => isset($totals['clicks']) ? absint($totals['clicks']) : 0,
      'zero_results' => isset($totals['zero_results']) ? absint($totals['zero_results']) : 0,
    ),
    'trend'        => $trend,
    'top_queries'  => $top_queries,
    'zero_queries' => $zero_queries,
    'recent'       => $recent,
  );
}

/**
 * Register dashboard menu.
 *
 * @return void
 */
function kosher_typesense_register_analytics_menu()
{
  add_menu_page(
    __('Search Analytics', 'kosher-typesense-search'),
    __('Search Analytics', 'kosher-typesense-search'),
    'manage_options',
    'kosher-search-analytics',
    'kosher_typesense_render_analytics_page',
    'dashicons-chart-line',
    58
  );
}
add_action('admin_menu', 'kosher_typesense_register_analytics_menu');

/**
 * Export analytics CSV.
 *
 * @return void
 */
function kosher_typesense_export_analytics_csv()
{
  if (! current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have permission to export analytics.', 'kosher-typesense-search'));
  }

  check_admin_referer('kosher_export_search_analytics');

  global $wpdb;

  list(, $from, $to) = kosher_typesense_analytics_date_range();
  $table = kosher_typesense_analytics_table();
  $rows  = $wpdb->get_results(
    $wpdb->prepare(
      "SELECT event_type, query_text, result_count, post_id, result_position, result_type, tab, filters_json, url, created_at
      FROM {$table}
      WHERE created_at BETWEEN %s AND %s
      ORDER BY created_at DESC",
      $from,
      $to
    ),
    ARRAY_A
  );

  nocache_headers();
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=kosher-search-analytics.csv');

  $output = fopen('php://output', 'w');
  fputcsv($output, array('event_type', 'query', 'results', 'post_id', 'position', 'type', 'tab', 'filters', 'url', 'created_at'));

  foreach ($rows as $row) {
    fputcsv($output, $row);
  }

  fclose($output);
  exit;
}
add_action('admin_post_kosher_export_search_analytics', 'kosher_typesense_export_analytics_csv');

/**
 * Render analytics dashboard.
 *
 * @return void
 */
function kosher_typesense_render_analytics_page()
{
  if (! current_user_can('manage_options')) {
    return;
  }

  $data       = kosher_typesense_get_analytics_dashboard_data();
  $export_url = wp_nonce_url(
    add_query_arg(
      array(
        'action' => 'kosher_export_search_analytics',
        'range'  => $data['range'],
        'from'   => isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '',
        'to'     => isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : '',
      ),
      admin_url('admin-post.php')
    ),
    'kosher_export_search_analytics'
  );
  ?>
  <div class="kosher-analytics-admin">
    <header class="kosher-analytics-admin__header">
      <div>
        <p class="kosher-analytics-admin__eyebrow"><?php esc_html_e('Kosher.com Search', 'kosher-typesense-search'); ?></p>
        <h1><?php esc_html_e('Search Analytics', 'kosher-typesense-search'); ?></h1>
      </div>
      <a class="kosher-analytics-admin__export" href="<?php echo esc_url($export_url); ?>"><?php esc_html_e('Export CSV', 'kosher-typesense-search'); ?></a>
    </header>

    <form class="kosher-analytics-admin__filters" method="get">
      <input type="hidden" name="page" value="kosher-search-analytics">
      <select name="range">
        <?php
        $ranges = array(
          'today'  => __('Today', 'kosher-typesense-search'),
          '7days'  => __('Last 7 days', 'kosher-typesense-search'),
          '30days' => __('Last 30 days', 'kosher-typesense-search'),
          'year'   => __('Last year', 'kosher-typesense-search'),
          'custom' => __('Custom', 'kosher-typesense-search'),
        );
        foreach ($ranges as $value => $label) :
          ?>
          <option value="<?php echo esc_attr($value); ?>" <?php selected($data['range'], $value); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="from" value="<?php echo esc_attr(isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : ''); ?>">
      <input type="date" name="to" value="<?php echo esc_attr(isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : ''); ?>">
      <button class="button button-primary"><?php esc_html_e('Apply', 'kosher-typesense-search'); ?></button>
    </form>

    <section class="kosher-analytics-admin__cards">
      <article><span><?php esc_html_e('Searches', 'kosher-typesense-search'); ?></span><strong><?php echo esc_html(number_format_i18n($data['totals']['searches'] ?? 0)); ?></strong></article>
      <article><span><?php esc_html_e('Clicks', 'kosher-typesense-search'); ?></span><strong><?php echo esc_html(number_format_i18n($data['totals']['clicks'] ?? 0)); ?></strong></article>
      <article><span><?php esc_html_e('Zero Results', 'kosher-typesense-search'); ?></span><strong><?php echo esc_html(number_format_i18n($data['totals']['zero_results'] ?? 0)); ?></strong></article>
    </section>

    <section class="kosher-analytics-admin__grid">
      <article class="kosher-analytics-admin__panel">
        <h2><?php esc_html_e('Search Trends', 'kosher-typesense-search'); ?></h2>
        <canvas id="kosher-search-trend" height="260"></canvas>
      </article>
      <article class="kosher-analytics-admin__panel">
        <h2><?php esc_html_e('Top Queries', 'kosher-typesense-search'); ?></h2>
        <canvas id="kosher-top-queries" height="260"></canvas>
      </article>
    </section>

    <section class="kosher-analytics-admin__grid">
      <article class="kosher-analytics-admin__panel">
        <h2><?php esc_html_e('Zero Result Searches', 'kosher-typesense-search'); ?></h2>
        <table>
          <thead><tr><th><?php esc_html_e('Query', 'kosher-typesense-search'); ?></th><th><?php esc_html_e('Count', 'kosher-typesense-search'); ?></th></tr></thead>
          <tbody>
            <?php foreach ($data['zero_queries'] as $row) : ?>
              <tr><td><?php echo esc_html($row['query_text']); ?></td><td><?php echo esc_html(number_format_i18n($row['total'])); ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </article>
      <article class="kosher-analytics-admin__panel">
        <h2><?php esc_html_e('Recent Events', 'kosher-typesense-search'); ?></h2>
        <table>
          <thead><tr><th><?php esc_html_e('Event', 'kosher-typesense-search'); ?></th><th><?php esc_html_e('Query', 'kosher-typesense-search'); ?></th><th><?php esc_html_e('Results', 'kosher-typesense-search'); ?></th><th><?php esc_html_e('Time', 'kosher-typesense-search'); ?></th></tr></thead>
          <tbody>
            <?php foreach ($data['recent'] as $row) : ?>
              <tr>
                <td><?php echo esc_html($row['event_type']); ?></td>
                <td><?php echo esc_html($row['query_text']); ?></td>
                <td><?php echo esc_html(number_format_i18n($row['result_count'])); ?></td>
                <td><?php echo esc_html(get_date_from_gmt($row['created_at'], 'M j, Y g:i a')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </article>
    </section>
  </div>
  <script>
    window.kosherSearchAnalyticsData = <?php echo wp_json_encode($data); ?>;
  </script>
  <?php
}

/**
 * Enqueue dashboard assets.
 *
 * @param string $hook Current admin hook.
 * @return void
 */
function kosher_typesense_enqueue_analytics_assets($hook)
{
  if ($hook !== 'toplevel_page_kosher-search-analytics') {
    return;
  }

  wp_enqueue_style(
    'kosher-search-analytics-admin',
    plugin_dir_url(KOSHER_TYPESENSE_SEARCH_FILE) . 'css/admin-analytics.css',
    array(),
    kosher_typesense_asset_version('css/admin-analytics.css')
  );

  wp_enqueue_script(
    'kosher-search-analytics-admin',
    plugin_dir_url(KOSHER_TYPESENSE_SEARCH_FILE) . 'js/kosher-analytics-admin.js',
    array(),
    kosher_typesense_asset_version('js/kosher-analytics-admin.js'),
    true
  );
}
add_action('admin_enqueue_scripts', 'kosher_typesense_enqueue_analytics_assets');
