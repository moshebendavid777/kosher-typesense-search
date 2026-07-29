<?php
$kayco_simple_archive_filter_field = '';
$kayco_simple_archive_filter_value = '';
$kayco_simple_show_archive_filters = false;
$kayco_simple_archive_taxonomy_map = array(
    'recipe_category' => 'recipe_category',
    'recipe_categories' => 'recipe_category',
    'Holiday' => 'occasions',
    'holiday' => 'occasions',
    'holidays' => 'occasions',
);
$kayco_simple_term_taxonomies = array_keys($kayco_simple_archive_taxonomy_map);
$kayco_simple_enable_term_archive = static function ($term) use (&$kayco_simple_show_archive_filters, &$kayco_simple_archive_filter_value, &$kayco_simple_archive_filter_field, $kayco_simple_archive_taxonomy_map) {
    if (!$term instanceof WP_Term || !isset($kayco_simple_archive_taxonomy_map[$term->taxonomy])) {
        return false;
    }

    $kayco_simple_show_archive_filters = true;
    $kayco_simple_archive_filter_value = $term->name;
    $kayco_simple_archive_filter_field = $kayco_simple_archive_taxonomy_map[$term->taxonomy];

    return true;
};

$kayco_simple_current_term = get_queried_object();

if ($kayco_simple_current_term instanceof WP_Term) {
    $kayco_simple_enable_term_archive($kayco_simple_current_term);
}

if (!$kayco_simple_show_archive_filters && is_tax($kayco_simple_term_taxonomies)) {
    $kayco_simple_current_term = get_queried_object();

    $kayco_simple_enable_term_archive($kayco_simple_current_term);
}

if (!$kayco_simple_show_archive_filters && !empty($_SERVER['REQUEST_URI'])) {
    $kayco_simple_request_path = trim((string) wp_parse_url(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH), '/');

    if (preg_match('#(?:^|/)recipes/category/([^/]+)(?:/page/[0-9]+)?/?$#', $kayco_simple_request_path, $kayco_simple_matches)) {
        $kayco_simple_archive_slug = sanitize_title($kayco_simple_matches[1]);
        $kayco_simple_rewrite_term = null;

        foreach (array('recipe_category', 'recipe_categories') as $kayco_simple_taxonomy_name) {
            $kayco_simple_rewrite_term = get_term_by('slug', $kayco_simple_archive_slug, $kayco_simple_taxonomy_name);

            if ($kayco_simple_rewrite_term instanceof WP_Term) {
                break;
            }
        }

        if (!$kayco_simple_enable_term_archive($kayco_simple_rewrite_term)) {
            $kayco_simple_show_archive_filters = true;
            $kayco_simple_archive_filter_value = ucwords(str_replace('-', ' ', $kayco_simple_archive_slug));
            $kayco_simple_archive_filter_field = 'recipe_category';
        }
    } elseif (preg_match('#(?:^|/)recipes/(?!category/)([^/]+)(?:/page/[0-9]+)?/?$#', $kayco_simple_request_path, $kayco_simple_matches)) {
        $kayco_simple_archive_slug = sanitize_title($kayco_simple_matches[1]);
        $kayco_simple_rewrite_term = null;

        foreach (array('Holiday', 'holiday', 'holidays') as $kayco_simple_taxonomy_name) {
            $kayco_simple_rewrite_term = get_term_by('slug', $kayco_simple_archive_slug, $kayco_simple_taxonomy_name);

            if ($kayco_simple_rewrite_term instanceof WP_Term) {
                break;
            }
        }

        if (!$kayco_simple_enable_term_archive($kayco_simple_rewrite_term)) {
            $kayco_simple_show_archive_filters = true;
            $kayco_simple_archive_filter_value = ucwords(str_replace('-', ' ', $kayco_simple_archive_slug));
            $kayco_simple_archive_filter_field = 'occasions';
        }
    }
}

$kayco_simple_filter_groups = array();
$kayco_simple_diets = get_terms(array(
    'taxonomy' => 'diets',
    'hide_empty' => false,
));

$kayco_simple_allergens = get_terms(array(
    'taxonomy' => 'allergens',
    'hide_empty' => false,
));

$kayco_simple_cuisines = get_terms(array(
    'taxonomy' => 'cuisnesses',
    'hide_empty' => false,
));

$kayco_simple_filter_groups = array(
    array(
        'label' => __('Type', 'kosher-typesense-search'),
        'field' => 'preference',
        'mode' => 'include_any',
        'options' => array('Meat', 'Dairy', 'Parve'),
    ),
    array(
        'label' => __('Diets', 'kosher-typesense-search'),
        'field' => 'diets',
        'mode' => 'include_all',
        'options' => is_array($kayco_simple_diets) ? wp_list_pluck($kayco_simple_diets, 'name') : array(),
    ),
    array(
        'label' => __('Allergens', 'kosher-typesense-search'),
        'field' => 'contains_allergents',
        'mode' => 'exclude_all',
        'options' => is_array($kayco_simple_allergens) ? wp_list_pluck($kayco_simple_allergens, 'name') : array(),
    ),
    array(
        'label' => __('Cuisines', 'kosher-typesense-search'),
        'field' => 'cuisine',
        'mode' => 'include_any',
        'options' => is_array($kayco_simple_cuisines) ? wp_list_pluck($kayco_simple_cuisines, 'name') : array(),
    ),
    array(
        'label' => __('Cooking Time', 'kosher-typesense-search'),
        'field' => 'cook_time',
        'mode' => 'cook_time',
        'options' => array(
            __('Less than 30 minutes', 'kosher-typesense-search') => '<30',
            __('Less than 1 hour', 'kosher-typesense-search') => '<60',
            __('Less than 2 hours', 'kosher-typesense-search') => '<120',
            __('More than 2 hours', 'kosher-typesense-search') => '>120',
        ),
    ),
);

$kayco_simple_sort_radio_name = 'kayco-simple-sidebar-sort-' . wp_unique_id();
?>
<section class="kosher-search kosher-search-form kayco-typesense-simple-search"
        data-target="<?php echo esc_attr($target); ?>"
        data-archive-filter-field="<?php echo esc_attr($kayco_simple_archive_filter_field); ?>"
        data-archive-filter-value="<?php echo esc_attr($kayco_simple_archive_filter_value); ?>">
  <div class="container">
    <div class="container__wrapper">
      <div class="kayco-typesense-simple-search__layout">
        <?php if (!empty($kayco_simple_filter_groups)) : ?>
          <aside class="kayco-typesense-simple-search__sidebar search-sidebar" data-simple-search-filters>
            <div class="kayco-typesense-simple-search__filter-header">
              <span class="kayco-typesense-simple-search__filter-title"><?php esc_html_e('Filters', 'kosher-typesense-search'); ?></span>
              <button type="button" class="kayco-typesense-simple-search__clear-filters" data-simple-filter-clear-all hidden><?php esc_html_e('Clear all', 'kosher-typesense-search'); ?></button>
            </div>
            <div class="kayco-typesense-simple-search__sidebar-sort kosher-search-filter-form__sidebar-sort" aria-labelledby="kayco-simple-sidebar-sort-title">
              <div id="kayco-simple-sidebar-sort-title" class="kosher-search-filter-form__sidebar-sort-title">
                <?php esc_html_e('Sort by', 'kosher-typesense-search'); ?>
              </div>
              <label class="kosher-search-filter-form__sidebar-sort-option">
                <input type="radio" name="<?php echo esc_attr($kayco_simple_sort_radio_name); ?>" value="default" data-simple-sort-radio checked>
                <span><?php esc_html_e('Default', 'kosher-typesense-search'); ?></span>
              </label>
              <label class="kosher-search-filter-form__sidebar-sort-option">
                <input type="radio" name="<?php echo esc_attr($kayco_simple_sort_radio_name); ?>" value="title_sort:asc" data-simple-sort-radio>
                <span><?php esc_html_e('A-Z', 'kosher-typesense-search'); ?></span>
              </label>
              <label class="kosher-search-filter-form__sidebar-sort-option">
                <input type="radio" name="<?php echo esc_attr($kayco_simple_sort_radio_name); ?>" value="title_sort:desc" data-simple-sort-radio>
                <span><?php esc_html_e('Z-A', 'kosher-typesense-search'); ?></span>
              </label>
              <label class="kosher-search-filter-form__sidebar-sort-option">
                <input type="radio" name="<?php echo esc_attr($kayco_simple_sort_radio_name); ?>" value="date:desc" data-simple-sort-radio>
                <span><?php esc_html_e('Newest', 'kosher-typesense-search'); ?></span>
              </label>
              <label class="kosher-search-filter-form__sidebar-sort-option">
                <input type="radio" name="<?php echo esc_attr($kayco_simple_sort_radio_name); ?>" value="date:asc" data-simple-sort-radio>
                <span><?php esc_html_e('Oldest', 'kosher-typesense-search'); ?></span>
              </label>
              <label class="kosher-search-filter-form__sidebar-sort-option">
                <input type="radio" name="<?php echo esc_attr($kayco_simple_sort_radio_name); ?>" value="rating:desc" data-simple-sort-radio>
                <span><?php esc_html_e('Most Popular', 'kosher-typesense-search'); ?></span>
              </label>
            </div>
            <div class="kayco-typesense-simple-search__filter-list" aria-label="<?php esc_attr_e('Search filters', 'kosher-typesense-search'); ?>">
              <?php foreach ($kayco_simple_filter_groups as $kayco_simple_group_index => $kayco_simple_group) : ?>
                <?php
                $kayco_simple_options = array_filter((array) $kayco_simple_group['options']);

                if (empty($kayco_simple_options)) {
                    continue;
                }
                ?>
                <details class="kayco-typesense-simple-search__filter" data-simple-filter-group>
                  <summary class="kayco-typesense-simple-search__filter-toggle">
                    <span><?php echo esc_html($kayco_simple_group['label']); ?></span>
                    <span class="kayco-typesense-simple-search__filter-count" data-simple-filter-count hidden>0</span>
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                  </summary>
                  <div class="kayco-typesense-simple-search__filter-menu">
                    <label class="screen-reader-text" for="kayco-simple-filter-search-<?php echo esc_attr($kayco_simple_group_index); ?>"><?php esc_html_e('Search filter options', 'kosher-typesense-search'); ?></label>
                    <input
                      id="kayco-simple-filter-search-<?php echo esc_attr($kayco_simple_group_index); ?>"
                      type="search"
                      class="kayco-typesense-simple-search__filter-search"
                      placeholder="<?php esc_attr_e('Search...', 'kosher-typesense-search'); ?>"
                      data-simple-filter-search
                      autocomplete="off">
                    <?php foreach ($kayco_simple_options as $kayco_simple_option_label => $kayco_simple_option_value) : ?>
                      <?php
                      if (is_int($kayco_simple_option_label)) {
                          $kayco_simple_option_label = $kayco_simple_option_value;
                      }

                      $kayco_simple_input_id = 'kayco-simple-filter-' . $kayco_simple_group_index . '-' . sanitize_title($kayco_simple_option_value);
                      ?>
                      <label class="kayco-typesense-simple-search__filter-option" for="<?php echo esc_attr($kayco_simple_input_id); ?>">
                        <input
                          id="<?php echo esc_attr($kayco_simple_input_id); ?>"
                          type="checkbox"
                          value="<?php echo esc_attr($kayco_simple_option_value); ?>"
                          data-filter-label="<?php echo esc_attr($kayco_simple_option_label); ?>"
                          data-filter-field="<?php echo esc_attr($kayco_simple_group['field']); ?>"
                          data-filter-mode="<?php echo esc_attr($kayco_simple_group['mode']); ?>">
                        <span><?php echo esc_html($kayco_simple_option_label); ?></span>
                      </label>
                    <?php endforeach; ?>
                    <button type="button" class="kayco-typesense-simple-search__filter-clear" data-simple-filter-clear hidden><?php esc_html_e('Clear All', 'kosher-typesense-search'); ?></button>
                  </div>
                </details>
              <?php endforeach; ?>
            </div>
          </aside>
        <?php endif; ?>
        <main class="kayco-typesense-simple-search__main">
          <div class="kayco-typesense-simple-search__toolbar">
            <label class="kayco-typesense-simple-search__search">
              <i class="bi bi-search" aria-hidden="true"></i>
              <input type="search"
                     id="searchInput"
                     class="form-control js-kayco-simple-search-input"
                     value=""
                     placeholder="<?php echo esc_attr($placeholder); ?>">
              <span class="screen-reader-text"><?php esc_html_e('Search this archive', 'kosher-typesense-search'); ?></span>
              <span class="kayco-typesense-simple-search__status js-kayco-simple-search-status" aria-live="polite"></span>
            </label>
          </div>
          <div id="predictionList" class="list-group mt-2 kayco-typesense-simple-search__results"></div>
        </main>
      </div>
    </div>
  </div>
</section>
