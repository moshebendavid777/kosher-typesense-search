<?php
/**
 * Typesense-backed pagination for the theme's simple archive search.
 *
 * The theme's fallback callback gathers every matching WordPress ID before it
 * paginates. Large recipe terms can exhaust PHP memory, particularly when an
 * ACF-backed filter is active. This handler runs first and asks Typesense for
 * only the requested page, then hands those IDs to the existing card renderer.
 */

if (!defined('ABSPATH')) {
  exit;
}

function kosher_typesense_simple_search_filter_value($value)
{
  $value = sanitize_text_field((string) $value);
  $value = str_replace(array('\\', '`'), array('\\\\', '\\`'), $value);

  return '`' . $value . '`';
}

function kosher_typesense_simple_search_filters($raw_filters)
{
  $allowed_fields = array(
    'holidays', 'preference', 'diets', 'contains_allergents', 'difficulty',
    'chefs', 'sources', 'recipe_category', 'cuisine', 'ingredients', 'cook_time',
  );
  $grouped = array();

  foreach ((array) $raw_filters as $raw_filter) {
    if (is_string($raw_filter)) {
      $raw_filter = json_decode(wp_unslash($raw_filter), true);
    }

    if (!is_array($raw_filter)) {
      continue;
    }

    $field = sanitize_key($raw_filter['field'] ?? '');
    $mode = sanitize_key($raw_filter['mode'] ?? 'include_any');
    $raw_value = wp_unslash((string) ($raw_filter['value'] ?? ''));
    // sanitize_text_field() treats values beginning with "<" as HTML and can
    // erase the cooking-time operator. Preserve only the four UI-owned ranges.
    $value = $field === 'cook_time' && in_array($raw_value, array('<30', '<60', '<120', '>120'), true)
      ? $raw_value
      : sanitize_text_field($raw_value);

    if (!in_array($field, $allowed_fields, true) || $value === '') {
      continue;
    }

    if ($field === 'cook_time') {
      if (in_array($value, array('<30', '<60', '<120', '>120'), true)) {
        $grouped[$field][] = 'cook_time:' . $value;
      }
      continue;
    }

    $operator = $mode === 'exclude_all' ? ':!=' : ':=';
    $grouped[$field][] = $field . $operator . kosher_typesense_simple_search_filter_value($value);
    $grouped[$field . '_mode'] = $mode;
  }

  $clauses = array();

  foreach ($grouped as $field => $filters) {
    if (substr($field, -5) === '_mode' || !is_array($filters) || !$filters) {
      continue;
    }

    $mode = $grouped[$field . '_mode'] ?? 'include_any';
    $joiner = $mode === 'include_any' || $field === 'cook_time' ? ' || ' : ' && ';
    $clauses[] = count($filters) > 1 ? '(' . implode($joiner, $filters) . ')' : $filters[0];
  }

  return $clauses;
}

function kosher_typesense_simple_search_document_card($document, $index, $post_type)
{
  $title = sanitize_text_field((string) ($document['title'] ?? ''));
  $url = esc_url((string) ($document['url'] ?? '#'));
  $image = esc_url((string) ($document['image'] ?? ''));
  $chefs = isset($document['chefs']) ? (array) $document['chefs'] : array();
  $author = sanitize_text_field((string) ($chefs[0] ?? $document['author_name'] ?? $document['author'] ?? ''));
  $author_url = esc_url((string) ($document['chef_url'] ?? $document['user_url'] ?? ''));
  $cook_minutes = max(0, absint($document['cook_time'] ?? 0));
  $cook_time = $cook_minutes >= 60
    ? floor($cook_minutes / 60) . 'h' . ($cook_minutes % 60 ? ' ' . ($cook_minutes % 60) . 'm' : '')
    : ($cook_minutes ? $cook_minutes . 'm' : '');
  $difficulty_values = isset($document['difficulty']) ? (array) $document['difficulty'] : array();
  $difficulty = sanitize_text_field((string) ($difficulty_values[0] ?? ''));
  $likes = max(0, absint($document['likes'] ?? 0));
  $post_id = absint($document['postID'] ?? 0);

  if ($title === '') {
    return '';
  }

  $item_class = $post_type === 'episodes' ? 'kayco-show-episodes__item' : 'kayco-holiday-recipes__item';
  $value_class = $post_type === 'episodes' ? 'kayco-show-episodes__value' : 'kayco-holiday-recipes__value';
  $html = '<article class="' . esc_attr($item_class) . '">';
  $html .= '<span class="' . esc_attr($value_class) . ' recipe-title">' . esc_html($title) . '</span>';
  $html .= '<span class="' . esc_attr($value_class) . ' order">' . esc_html($index) . '</span>';
  $html .= '<article class="kayco-card kayco-card--standard kayco-card--' . esc_attr($post_type) . '">';
  $html .= '<div class="kayco-card__media"><a class="kayco-card__image-link" href="' . $url . '">';
  $html .= $image !== ''
    ? '<img class="kayco-card__image" src="' . $image . '" alt="' . esc_attr($title) . '" loading="lazy">'
    : '<span class="kayco-card__image kayco-card__image--placeholder" aria-hidden="true"></span>';
  $html .= '</a>';
  if ($likes || $url !== '') {
    $html .= '<div class="kayco-card__media-actions">';
    if ($likes) {
      $html .= '<button type="button" class="kayco-card__like like-button" data-post="' . esc_attr($post_id) . '" aria-label="' . esc_attr($likes . ' saves') . '"><i class="bi bi-heart-fill" aria-hidden="true"></i><span class="like-count">' . esc_html($likes) . '</span></button>';
    }
    $html .= '<button type="button" class="kayco-card__share js-kayco-share-trigger" data-share-url="' . $url . '" data-share-title="' . esc_attr($title) . '" aria-label="' . esc_attr('Share ' . $title) . '"><i class="bi bi-share" aria-hidden="true"></i></button>';
    $html .= '</div>';
  }
  if ($difficulty !== '') {
    $html .= '<span class="kayco-card__badge kayco-card__badge--primary">' . esc_html($difficulty) . '</span>';
  }
  $html .= '</div><div class="kayco-card__body">';
  if ($author !== '' || $cook_time !== '') {
    $html .= '<div class="kayco-card__meta">';
    if ($author !== '') {
      $html .= $author_url !== ''
        ? '<a href="' . $author_url . '" class="kayco-card__author">' . esc_html($author) . '</a>'
        : '<span class="kayco-card__author">' . esc_html($author) . '</span>';
    }
    if ($cook_time !== '') {
      $html .= '<span class="kayco-card__text">' . esc_html($cook_time) . '</span>';
    }
    $html .= '</div>';
  }
  $html .= '<h3 class="kayco-card__title"><a href="' . $url . '" title="' . esc_attr($title) . '">' . esc_html($title) . '</a></h3>';
  $html .= '</div></article></article>';

  return $html;
}

function kosher_typesense_simple_search_resolve_post_id($id, $title, $url, $post_type)
{
  $id = absint($id);
  $post_type = sanitize_key($post_type);

  if ($id && get_post_type($id) === $post_type && get_post_status($id) === 'publish') {
    return $id;
  }

  if ($post_type === 'recipes' && function_exists('kayco_resolve_legacy_recipe_post_id')) {
    $resolved_id = kayco_resolve_legacy_recipe_post_id($id, $title);
    if ($resolved_id && get_post_type($resolved_id) === $post_type && get_post_status($resolved_id) === 'publish') {
      return $resolved_id;
    }
  }

  $path = (string) wp_parse_url((string) $url, PHP_URL_PATH);
  $slug = sanitize_title(basename(untrailingslashit($path)));
  if ($slug !== '') {
    $post = get_page_by_path($slug, OBJECT, $post_type);
    if ($post instanceof WP_Post && $post->post_status === 'publish') {
      return (int) $post->ID;
    }
  }

  return 0;
}

function kosher_typesense_simple_search_context_ajax()
{
  check_ajax_referer('kayco_frontend_ajax', 'nonce');

  $post_type = sanitize_key(wp_unslash($_POST['post_type'] ?? 'recipes'));
  $allowed_post_types = array('recipes', 'articles', 'episodes', 'shows', 'menus');

  if (!in_array($post_type, $allowed_post_types, true)) {
    wp_send_json_error(array('message' => 'Unsupported search post type.'), 400);
  }

  $page = max(1, absint(wp_unslash($_POST['page'] ?? 1)));
  $per_page = max(1, min(45, absint(wp_unslash($_POST['per_page'] ?? 12))));
  $query = trim(sanitize_text_field(wp_unslash($_POST['query'] ?? '')));
  $sort = sanitize_key(wp_unslash($_POST['sort'] ?? 'relevance'));
  $filter_field = sanitize_key(wp_unslash($_POST['filter_field'] ?? ''));
  $filter_value = sanitize_text_field(wp_unslash($_POST['filter_value'] ?? ''));
  $card_context = sanitize_key(wp_unslash($_POST['card_context'] ?? ''));
  $allowed_context_fields = array(
    'recipe_category', 'occasions', 'holidays', 'preference', 'diets',
    'contains_allergents', 'difficulty', 'chefs', 'sources', 'cuisine',
    'ingredients', 'show_id', 'profile_user_id', 'privacy',
  );

  // Leave specialized profile/show contexts to the theme's WordPress handler.
  if (in_array($filter_field, array('show_id', 'profile_user_id'), true)) {
    return;
  }

  $filter_clauses = array();
  if ($post_type === 'recipes') {
    $filter_clauses[] = '(community-recipe:=false || (community-recipe:=true && user_consent_public:=true))';
  }

  if ($filter_field !== '' && $filter_value !== '' && in_array($filter_field, $allowed_context_fields, true)) {
    $filter_clauses[] = $filter_field . ':=' . kosher_typesense_simple_search_filter_value($filter_value);
  }

  $raw_filters = isset($_POST['simple_filters']) ? (array) $_POST['simple_filters'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
  $filter_clauses = array_merge($filter_clauses, kosher_typesense_simple_search_filters($raw_filters));

  $query_by = array(
    'recipes' => 'title,diets,chefs,ingredients,occasions',
    'articles' => 'title,author,author_name,article_sub_category',
    'episodes' => 'title,chef,show',
    'shows' => 'title',
    'menus' => 'title,description,categories,holidays',
  );
  $sort_by = '';

  if ($sort === 'az') {
    $sort_by = 'title_sort:asc';
  } elseif ($sort === 'za') {
    $sort_by = 'title_sort:desc';
  } elseif ($sort === 'newest') {
    $sort_by = 'date:desc';
  }

  $search = array(
    'collection' => kosher_typesense_collection_name($post_type),
    'q' => $query === '' ? '*' : $query,
    'query_by' => $query_by[$post_type],
    'include_fields' => 'postID,title,url,image,chefs,author,author_name,chef_url,user_url,cook_time,difficulty,likes',
    'page' => $page,
    'per_page' => $per_page,
  );

  if ($filter_clauses) {
    $search['filter_by'] = implode(' && ', array_map(static function ($clause) {
      return '(' . $clause . ')';
    }, $filter_clauses));
  }

  if ($sort_by !== '') {
    $search['sort_by'] = $sort_by;
  }

  $api_key = kosher_typesense_admin_api_key();
  if ($api_key === '') {
    $api_key = kosher_typesense_search_api_key();
  }

  if ($api_key === '') {
    wp_send_json_error(array('message' => 'Typesense search key is not configured.'), 500);
  }

  $response = wp_remote_post(kosher_typesense_url('multi_search'), array(
    'headers' => array(
      'Content-Type' => 'application/json',
      'X-TYPESENSE-API-KEY' => $api_key,
    ),
    'body' => wp_json_encode(array('searches' => array($search))),
    'timeout' => 15,
  ));

  if (is_wp_error($response)) {
    wp_send_json_error(array('message' => $response->get_error_message()), 502);
  }

  $status = (int) wp_remote_retrieve_response_code($response);
  $decoded = json_decode(wp_remote_retrieve_body($response), true);
  $result = is_array($decoded) && isset($decoded['results'][0]) ? $decoded['results'][0] : null;

  if ($status < 200 || $status >= 300 || !is_array($result) || isset($result['error'])) {
    $message = is_array($result) && !empty($result['error']) ? $result['error'] : 'Typesense returned an invalid response.';
    kosher_typesense_debug_log('Simple search context request failed.', array('search' => $search, 'status' => $status, 'response' => $decoded));
    wp_send_json_error(array('message' => $message), $status >= 400 ? $status : 502);
  }

  $html = '';
  foreach ((array) ($result['hits'] ?? array()) as $index => $hit) {
    $document = is_array($hit['document'] ?? null) ? $hit['document'] : array();
    $post_id = absint($hit['document']['postID'] ?? 0);
    $title = sanitize_text_field((string) ($document['title'] ?? ''));
    $post_id = kosher_typesense_simple_search_resolve_post_id(
      $post_id,
      $title,
      (string) ($document['url'] ?? ''),
      $post_type
    );

    if (!$post_id || get_post_type($post_id) !== $post_type || get_post_status($post_id) !== 'publish') {
      // Staging Typesense and a developer's local database are not always
      // synchronized. Keep the archive usable with the same card CSS and the
      // indexed document data instead of returning an empty result grid.
      $html .= kosher_typesense_simple_search_document_card(
        $document,
        (($page - 1) * $per_page) + $index,
        $post_type
      );
      continue;
    }

    if (function_exists('kayco_render_typesense_simple_search_item_html')) {
      $html .= kayco_render_typesense_simple_search_item_html(
        $post_id,
        (($page - 1) * $per_page) + $index,
        $post_type,
        array('card_context' => $card_context)
      );
    }
  }

  wp_send_json_success(array(
    'html' => $html,
    'total' => absint($result['found'] ?? 0),
  ));
}

/**
 * Render IDs already selected by Typesense without filtering them a second
 * time against potentially stale or differently-shaped WordPress ACF data.
 */
function kosher_typesense_simple_search_results_ajax()
{
  check_ajax_referer('kayco_frontend_ajax', 'nonce');

  $post_type = sanitize_key(wp_unslash($_POST['post_type'] ?? 'recipes'));
  $allowed_post_types = array('recipes', 'articles', 'episodes', 'shows', 'menus');
  if (!in_array($post_type, $allowed_post_types, true)) {
    wp_send_json_error(array('message' => 'Unsupported search post type.'), 400);
  }

  $card_context = sanitize_key(wp_unslash($_POST['card_context'] ?? ''));
  $ids = isset($_POST['ids']) ? array_map('absint', (array) wp_unslash($_POST['ids'])) : array();
  $ids = array_slice(array_values(array_filter(array_unique($ids))), 0, 45);
  $documents = array();
  $missing_ids = array();

  foreach ($ids as $id) {
    $resolved_id = kosher_typesense_simple_search_resolve_post_id($id, '', '', $post_type);

    if (!$resolved_id || get_post_type($resolved_id) !== $post_type || get_post_status($resolved_id) !== 'publish') {
      $missing_ids[] = $id;
    }
  }

  // This fallback matters on staging/local environments whose WordPress DB is
  // behind the Typesense collection. It also guarantees one card per hit.
  if ($missing_ids) {
    $api_key = kosher_typesense_admin_api_key();
    if ($api_key === '') {
      $api_key = kosher_typesense_search_api_key();
    }

    if ($api_key !== '') {
      $document_search = array(
        'searches' => array(array(
          'collection' => kosher_typesense_collection_name($post_type),
          'q' => '*',
          'query_by' => 'title',
          'filter_by' => 'postID:=[' . implode(',', $missing_ids) . ']',
          'include_fields' => 'postID,title,url,image,chefs,author,author_name,chef_url,user_url,cook_time,difficulty,likes',
          'per_page' => count($missing_ids),
        )),
      );
      $response = wp_remote_post(kosher_typesense_url('multi_search'), array(
        'headers' => array('Content-Type' => 'application/json', 'X-TYPESENSE-API-KEY' => $api_key),
        'body' => wp_json_encode($document_search),
        'timeout' => 15,
      ));

      if (!is_wp_error($response)) {
        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        foreach ((array) ($decoded['results'][0]['hits'] ?? array()) as $hit) {
          $document = is_array($hit['document'] ?? null) ? $hit['document'] : array();
          $document_id = absint($document['postID'] ?? 0);
          if ($document_id) {
            $documents[$document_id] = $document;
          }
        }
      }
    }
  }

  $html = '';
  foreach ($ids as $index => $id) {
    $title = sanitize_text_field((string) ($documents[$id]['title'] ?? ''));
    $post_id = kosher_typesense_simple_search_resolve_post_id(
      $id,
      $title,
      (string) ($documents[$id]['url'] ?? ''),
      $post_type
    );

    if ($post_id && get_post_type($post_id) === $post_type && get_post_status($post_id) === 'publish' && function_exists('kayco_render_typesense_simple_search_item_html')) {
      $html .= kayco_render_typesense_simple_search_item_html(
        $post_id,
        $index,
        $post_type,
        array('card_context' => $card_context)
      );
    } elseif (isset($documents[$id])) {
      $html .= kosher_typesense_simple_search_document_card($documents[$id], $index, $post_type);
    }
  }

  wp_send_json_success(array('html' => $html));
}

add_action('wp_ajax_kayco_render_typesense_simple_search_context_page', 'kosher_typesense_simple_search_context_ajax', 1);
add_action('wp_ajax_nopriv_kayco_render_typesense_simple_search_context_page', 'kosher_typesense_simple_search_context_ajax', 1);
add_action('wp_ajax_kayco_render_typesense_simple_search_results', 'kosher_typesense_simple_search_results_ajax', 1);
add_action('wp_ajax_nopriv_kayco_render_typesense_simple_search_results', 'kosher_typesense_simple_search_results_ajax', 1);
