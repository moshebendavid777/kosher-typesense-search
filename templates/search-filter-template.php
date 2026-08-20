<?php
/**
 * File: search-filter-template.php
 * Description: Renders the Typesense search layout, filters, tabs, controls, and results containers.
 * Author: Kosher Dev Team
 */

global $wpdb;
$holidays = get_terms(array(
  'taxonomy' => 'Holiday',
  'hide_empty' => false,
));

$diets = get_terms(array(
  'taxonomy' => 'diets',
  'hide_empty' => false,
));

$allergens = get_terms(array(
  'taxonomy' => 'allergens',
  'hide_empty' => false,
));

$category = get_terms(array(
  'taxonomy' => 'recipe_category',
  'hide_empty' => false,
));

$article_categories = get_terms(array(
    'taxonomy'   => 'articles_categories',
    'hide_empty' => false,
));

$menu_categories = get_terms(array(
    'taxonomy'   => 'menus_categories',
    'hide_empty' => false,
));

$menu_author_names = array();
$menu_author_ids = get_posts(array(
    'post_type'      => 'menus',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
));

foreach ($menu_author_ids as $menu_post_id) {
    $menu_author = get_user_by('ID', (int) get_post_field('post_author', $menu_post_id));
    if ($menu_author && $menu_author->display_name) {
        $menu_author_names[] = $menu_author->display_name;
    }
}

$menu_author_names = array_values(array_unique(array_filter($menu_author_names)));
sort($menu_author_names, SORT_NATURAL | SORT_FLAG_CASE);

$shows = new WP_Query(array(
            'post_type'      => 'shows',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

$cuisnesses = get_terms(array(
  'taxonomy' => 'cuisnesses',
  'hide_empty' => false,
));

$sources = get_terms(array(
  'taxonomy' => 'sources',
  'hide_empty' => false,
  'object_type' => array('recipes')
));


$preferences = get_terms(array(
  'taxonomy' => 'preferences',
  'hide_empty' => false,
  'object_type' => array('recipes')
));

$chefs = get_terms(array(
  'taxonomy' => 'sources',
  'hide_empty' => false,
  'object_type' => array('recipes')
));

$ingredients = get_terms(array(
  'taxonomy' => 'ingredients_single',
  'hide_empty' => false,
  'object_type' => array('recipes')
));
?>
<!-- <div style="color: #950000; padding: 20px; margin: 20px; text-align: center; background: #fadfdf; font-size: 16px; font-weight: 600;">We're currently experiencing issues with our search tool and are actively working on a fix. We appreciate your patience and will have it resolved soon. Thank you!</div> --> 
<!-- <div style="color: #950000; padding: 20px; margin: 20px; text-align: center; background: #fadfdf; font-size: 16px; font-weight: 600;">Our search feature is temporarily unavailable as we update our records. We're working hard to restore access as soon as possible. Thank you for your patience understanding.</div> --> 
<section class="kosher-search kosher-search-filter-form">
  <div class="container-fluid">
    <div class="container__wrapper pt-30 pb-10">
      <div class="input-group">
        <input type="text" id="searchInput" class="form-control" placeholder="Search 10,000+ Recipes, Shows & Articles...">
        <span class="input-group-text">
          <i class="fa fa-search"></i>
        </span>
        <span class="input-group-text close-icon" style="display: none;">
          <i class="fa fa-times"></i>
        </span>
      </div>
    </div>
  </div>

  <div class="kosher-search-filter-form__wrap">
    <div class="container-fluid">
      <div class="kosher-search-summary" aria-live="polite">
        <span class="kosher-search-summary__label"><?php esc_html_e('Search Result for:', 'kosher-typesense-search'); ?></span>
        <strong class="kosher-search-summary__query" data-search-summary-query></strong>
      </div>
      <section class="search-filters search-filters--state-controls" aria-hidden="true">
        <div class="search-filters__wrapper">
            <div class="recipe-only">

          <select id="ex-checkbox-holidays" autocomplete="off" multiple>
            <?php if (!empty($holidays) && !is_wp_error($holidays)) : ?>
              <?php foreach ($holidays as $holiday) :
                $holiday_id = $holiday->term_id;
                $holiday_name = $holiday->name;
                $taxonomy_name = $holiday->taxonomy;
              ?>
                <option value="<?php echo esc_attr($holiday_name); ?>"><?php echo esc_html($holiday_name); ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>

          <select id="ex-checkbox-diets" autocomplete="off" multiple>
            <?php if (!empty($diets) && !is_wp_error($diets)) : ?>
              <?php foreach ($diets as $diet) :
                $diet_id = $diet->term_id;
                $diet_name = $diet->name;
                $taxonomy_name = $diet->taxonomy;
              ?>
                <option value="<?php echo esc_attr($diet_name); ?>"><?php echo esc_html($diet_name); ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>

          

          <select id="ex-checkbox-allergens" autocomplete="off" multiple>
            <?php if (!empty($allergens) && !is_wp_error($allergens)) : ?>
              <?php foreach ($allergens as $allergen) :
                $allergen_id = $allergen->term_id;
                $allergen_name = $allergen->name;
                $taxonomy_name = $allergen->taxonomy;
              ?>
                <option value="<?php echo esc_attr($allergen_name); ?>"><?php echo esc_html($allergen_name); ?> Free</option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
          <div class="btn-separator"></div>

            <select id="in-checkbox-category" autocomplete="off" multiple>
            <?php if (!empty($category) && !is_wp_error($category)) : ?>
              <?php foreach ($category as $cat) :
                $category_id = $cat->term_id;
                $category_name = $cat->name;
                $taxonomy_name = $cat->taxonomy;
              ?>
                <option value="<?php echo esc_attr($category_name); ?>"><?php echo esc_html($category_name); ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
          <div class="btn-separator"></div>
          <select id="in-checkbox-preferences" autocomplete="off" multiple>
            <?php if (!empty($preferences) && !is_wp_error($preferences)) : ?>
              <?php foreach ($preferences as $preference) : ?>
                <option value="<?php echo esc_attr($preference->name); ?>"><?php echo esc_html($preference->name); ?></option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>

          <div class="btn-separator"></div>

        <div class="position-relative">


            <div class="new-feature new-feature-badge" data-feature-id="1">
  
  <span class="new-feature-badge-bottom">NEW</span>
</div>
         <select id="in-checkbox-difficulty" autocomplete="off" multiple>
                <option value="Easy">Easy</option>
                <option value="Medium">Medium</option>
                <option value="Hard">Hard</option>

          </select>

        </div>
 
          <div class="btn-separator"></div>

              </div>
        </div>
      </section>
    </div>

    <div class="kosher-search-filter-form-result search-layout">
      <aside class="flex-shrink-0 p-3 bg-white kosher-search-filter-form-result__filter search-sidebar">
        <div class="d-flex align-items-center pb-3 link-dark text-decoration-none kosher-search-filter-form-result__filter__header">
          <span class="kosher-search-filter-form-result__filter__header__title"><?php esc_html_e('Filters', 'kosher-typesense-search'); ?></span>

          <div class="kosher-search-filter-form-result__filter__header-actions">
            <button
              type="button"
              class="clear-all"
              aria-label="<?php esc_attr_e('Clear all filters', 'kosher-typesense-search'); ?>"
              hidden
            >
              <span><?php esc_html_e('Clear all', 'kosher-typesense-search'); ?></span>
              <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
            <div class="close-side-filter"> 
              <span>See Results <i class="bi bi-chevron-right"></i></span>
            </div>
            <button type="button" class="kosher-search-filter-form__mobile-filter-close" aria-label="<?php esc_attr_e('Close filters', 'kosher-typesense-search'); ?>">
              <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
          </div>
        </div>

        <div class="accordion" id="accordionPanelsStayOpenExample">

    <div class="recipes-filters-wrapper filters-group filters-recipes">
          <div class="kosher-search-filter-form__sidebar-sort" aria-labelledby="kosher-sidebar-sort-title">
            <div id="kosher-sidebar-sort-title" class="kosher-search-filter-form__sidebar-sort-title">
              <?php esc_html_e('Sort by', 'kosher-typesense-search'); ?>
            </div>
            <label class="kosher-search-filter-form__sidebar-sort-option">
              <input type="radio" name="kosher-sidebar-sort" value="default" checked>
              <span><?php esc_html_e('Default', 'kosher-typesense-search'); ?></span>
            </label>
            <label class="kosher-search-filter-form__sidebar-sort-option">
              <input type="radio" name="kosher-sidebar-sort" value="title_sort:asc">
              <span><?php esc_html_e('A-Z', 'kosher-typesense-search'); ?></span>
            </label>
            <label class="kosher-search-filter-form__sidebar-sort-option">
              <input type="radio" name="kosher-sidebar-sort" value="title_sort:desc">
              <span><?php esc_html_e('Z-A', 'kosher-typesense-search'); ?></span>
            </label>
            <label class="kosher-search-filter-form__sidebar-sort-option">
              <input type="radio" name="kosher-sidebar-sort" value="date:desc">
              <span><?php esc_html_e('Newest', 'kosher-typesense-search'); ?></span>
            </label>
            <label class="kosher-search-filter-form__sidebar-sort-option">
              <input type="radio" name="kosher-sidebar-sort" value="date:asc">
              <span><?php esc_html_e('Oldest', 'kosher-typesense-search'); ?></span>
            </label>
            <label class="kosher-search-filter-form__sidebar-sort-option">
              <input type="radio" name="kosher-sidebar-sort" value="rating:desc">
              <span><?php esc_html_e('Most Popular', 'kosher-typesense-search'); ?></span>
            </label>
          </div>
            <div class="accordion-item">
                <h2 class="accordion-header" id="panelsStayOpen-headingOne">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseOne" aria-expanded="false" aria-controls="panelsStayOpen-collapseOne">
                    Holidays <div class="counter-selection counter-selection--ex-checkbox-holidays-side"></div>
                </button>
                </h2>
                <div id="panelsStayOpen-collapseOne" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingOne">
                    <div class="accordion-body">
                        <select id="ex-checkbox-holidays-side" autocomplete="off" multiple>
                        <?php if (!empty($holidays) && !is_wp_error($holidays)) : ?>
                            <?php foreach ($holidays as $holiday) :
                            $holiday_id = $holiday->term_id;
                            $holiday_name = $holiday->name;
                            $taxonomy_name = $holiday->taxonomy;
                            ?>
                            <option value="<?php echo esc_attr($holiday_name); ?>"><?php echo esc_html($holiday_name); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="panelsStayOpen-headingPreferences">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapsePreferences" aria-expanded="false" aria-controls="panelsStayOpen-collapsePreferences">
                Dairy | Meat | Parve <div class="counter-selection counter-selection--in-checkbox-preferences"></div>
              </button>
            </h2>
            <div id="panelsStayOpen-collapsePreferences" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingPreferences">
              <div class="accordion-body">
                <select id="in-checkbox-preferences-side" autocomplete="off" multiple>
                  <?php if (!empty($preferences) && !is_wp_error($preferences)) : ?>
                    <?php foreach ($preferences as $preference) : ?>
                      <option value="<?php echo esc_attr($preference->name); ?>"><?php echo esc_html($preference->name); ?></option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
          </div>




          <div class="accordion-item">
            <h2 class="accordion-header" id="panelsStayOpen-headingDiets">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseDiets" aria-expanded="false" aria-controls="panelsStayOpen-collapseDiets">
                Diets <div class="counter-selection counter-selection--ex-checkbox-diets-side"></div>
              </button>
            </h2>
            <div id="panelsStayOpen-collapseDiets" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingDiets">
              <div class="accordion-body">
                <select id="ex-checkbox-diets-side" autocomplete="off" multiple>
                  <?php if (!empty($diets) && !is_wp_error($diets)) : ?>
                    <?php foreach ($diets as $diet) :
                      $diet_id = $diet->term_id;
                      $diet_name = $diet->name;
                      $taxonomy_name = $diet->taxonomy;
                    ?>
                      <option value="<?php echo esc_attr($diet_name); ?>"><?php echo esc_html($diet_name); ?></option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
          </div>



          <div class="accordion-item">
            <h2 class="accordion-header" id="panelsStayOpen-headingAllergens">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseAllergens" aria-expanded="false" aria-controls="panelsStayOpen-collapseAllergens">
                Allergens <div class="counter-selection counter-selection--ex-checkbox-allergens-side"></div>
              </button>
            </h2>
            <div id="panelsStayOpen-collapseAllergens" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingAllergens">
              <div class="accordion-body">
                <select id="ex-checkbox-allergens-side" autocomplete="off" multiple>
                  <?php if (!empty($allergens) && !is_wp_error($allergens)) : ?>
                    <?php foreach ($allergens as $allergen) :
                      $allergen_id = $allergen->term_id;
                      $allergen_name = $allergen->name;
                      $taxonomy_name = $allergen->taxonomy;
                    ?>
                      <option value="<?php echo esc_attr($allergen_name); ?>"><?php echo esc_html($allergen_name); ?> Free</option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
          </div>

                  <div class="accordion-item">
            <h2 class="accordion-header" id="panelsStayOpen-headingDifficulty">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseDifficulty" aria-expanded="false" aria-controls="panelsStayOpen-collapseDifficulty">
                Difficulty <div class="counter-selection counter-selection--in-checkbox-difficulty"></div>
              </button>
            </h2>
            <div id="panelsStayOpen-collapseDifficulty" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingDifficulty">
              <div class="accordion-body">
                <select id="in-checkbox-difficulty-side" autocomplete="off" multiple>
                        <option value="Easy">Easy</option>
                        <option value="Medium">Medium</option>
                        <option value="Hard">Hard</option>
                </select>
              </div>
            </div>
          </div>


          <div class="accordion-item">
            <h2 class="accordion-header" id="panelsStayOpen-headingAuthorsType">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseAuthorsType" aria-expanded="false" aria-controls="panelsStayOpen-collapseAuthorsType">
                Authors <div class="counter-selection counter-selection--author-type"></div>
              </button>
            </h2>
            <div id="panelsStayOpen-collapseAuthorsType" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingAuthorsType">
              <div class="accordion-body">
                <div class="kosher-search-filter-form__author-options" data-author-type-filter>
                  <label class="kosher-search-filter-form__author-option">
                    <span><?php esc_html_e('Kosher.com', 'kosher-typesense-search'); ?> <span class="kosher-search-filter-form__author-count" data-author-type-count="kosher">(0)</span></span>
                    <input type="checkbox" value="kosher" data-author-type-option checked>
                  </label>
                  <label class="kosher-search-filter-form__author-option">
                    <span><?php esc_html_e('Home Cooks', 'kosher-typesense-search'); ?> <span class="kosher-search-filter-form__author-count" data-author-type-count="home">(0)</span></span>
                    <input type="checkbox" value="home" data-author-type-option>
                  </label>
                </div>
              </div>
            </div>
          </div>


          <div class="accordion-item">
            <h2 class="accordion-header" id="panelsStayOpen-headingSources">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseSources" aria-expanded="false" aria-controls="panelsStayOpen-collapseSources">
                Sources <div class="counter-selection counter-selection--in-checkbox-sources"></div>
              </button>
            </h2>
            <div id="panelsStayOpen-collapseSources" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingSources">
              <div class="accordion-body">
                <select id="in-checkbox-sources" autocomplete="off" multiple>
                  <?php if (!empty($sources) && !is_wp_error($sources)) : ?>
                    <?php foreach ($sources as $source) : ?>
                      <option value="<?php echo esc_attr($source->name); ?>"><?php echo esc_html($source->name); ?></option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
          </div>
          <div class="accordion-item">
            <h2 class="accordion-header" id="panelsStayOpen-headingCategories-side">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseCategories-side" aria-expanded="false" aria-controls="panelsStayOpen-collapseCategories-side">
                Categories <div class="counter-selection counter-selection--in-checkbox-category-side"></div>
              </button>
            </h2>
            <div id="panelsStayOpen-collapseCategories-side" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingCategories-side">
              <div class="accordion-body">

                <select id="in-checkbox-category-side" autocomplete="off" multiple>
                  <?php if (!empty($category) && !is_wp_error($category)) : ?>
                    <?php foreach ($category  as $cat) :
                      $cat_id = $cat->term_id;
                      $cat_name = $cat->name;
                      $taxonomy_name = $cat->taxonomy;
                    ?>
                      <option value="<?php echo esc_attr($cat_name); ?>"><?php echo esc_html($cat_name); ?></option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="panelsStayOpen-headingCuisine">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseCuisine" aria-expanded="false" aria-controls="panelsStayOpen-collapseCuisine">
              Cuisines <div class="counter-selection counter-selection--in-checkbox-cuisine"></div>
              </button>
            </h2>
            <div id="panelsStayOpen-collapseCuisine" class="accordion-collapse collapse" aria-labelledby="panelsStayOpen-headingCuisine">
              <div class="accordion-body">

                <select id="in-checkbox-cuisine" autocomplete="off" multiple>
                  <?php if (!empty($cuisnesses) && !is_wp_error($cuisnesses)) : ?>
                    <?php foreach ($cuisnesses  as $cuisine) :
                      $cuisine_id = $cuisine->term_id;
                      $cuisine_name = $cuisine->name;
                      $cuisine_taxonomy_name = $cuisine->taxonomy;
                    ?>
                      <option value="<?php echo esc_attr($cuisine_name); ?>"><?php echo esc_html($cuisine_name); ?></option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
          </div>

          <div class="accordion-item">
            <h2 class="accordion-header" id="panelsStayOpen-headingIngredient">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseIngredient" aria-expanded="false" aria-controls="panelsStayOpen-collapseIngredient">
                Ingredients <div class="counter-selection counter-selection--ingredients-remote"></div>
              </button>
            </h2>
            <div id="panelsStayOpen-collapseIngredient" class="accordion-collapse collapse multiselect__search" aria-labelledby="panelsStayOpen-headingIngredient">
              <div class="accordion-body">
                <small>Write at least 3 letters to search...</small>
                <input type="search" class="kosher-search-filter-form__remote-search" data-remote-filter-search="ingredients-select" placeholder="<?php esc_attr_e('Search ingredients...', 'kosher-typesense-search'); ?>" autocomplete="off">
                <select id="ingredients-select" name="ingredients[]" multiple></select>
                <div id="selected-ingredients-container" class="container-ts-selection"></div>

                <input type="search" class="kosher-search-filter-form__remote-search" data-remote-filter-search="ingredients-exclude-select" placeholder="<?php esc_attr_e('Exclude ingredients...', 'kosher-typesense-search'); ?>" autocomplete="off">
                <select id="ingredients-exclude-select" name="ingredients-exclude[]" multiple></select>
                <div id="selected-exclude-ingredients" class="selected-ingredients-exclude-container">
                </div>
                <div id="selected-ingredients-exclude-container" class="container-ts-selection"></div>
              </div>
            </div>
          </div>

            <div class="accordion-item">
                <h2 class="accordion-header" id="panelsStayOpen-headingChef">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panelsStayOpen-collapseChef" aria-expanded="false" aria-controls="panelsStayOpen-collapseChef">
                    Chefs <div class="counter-selection counter-selection--chef-select"></div>
                </button>
                </h2>
                <div id="panelsStayOpen-collapseChef" class="accordion-collapse collapse multiselect__search" aria-labelledby="panelsStayOpen-headingChef">
                    <div class="accordion-body">
                    <small>Write at least 3 letters to search...</small>  
                    <input type="search" class="kosher-search-filter-form__remote-search" data-remote-filter-search="chef-select" placeholder="<?php esc_attr_e('Search chefs...', 'kosher-typesense-search'); ?>" autocomplete="off">
                    <select id="chef-select" name="chef[]" multiple></select>
                    <div id="selected-chef" class="selected-chef-container"></div>
                    <div id="selected-chef-container" class="container-ts-selection"></div>
                </div>
            </div>
          </div>
     </div>   

          <div class="mobile-filter-panel mobile-filter-panel--articles" data-mobile-filter-panel="articles">
          <div class="articles-filters-wrapper filters-group filters-articles">
    
    <!-- ARTICLE CATEGORIES -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="panelsStayOpen-headingArticleCategory">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#panelsStayOpen-collapseArticleCategory" aria-expanded="false"
                aria-controls="panelsStayOpen-collapseArticleCategory">
                Article Categories <div class="counter-selection counter-selection--article-category"></div>
            </button>
        </h2>

        <div id="panelsStayOpen-collapseArticleCategory" class="accordion-collapse collapse"
            aria-labelledby="panelsStayOpen-headingArticleCategory">
            <div class="accordion-body">
      <select id="in-checkbox-article-category" autocomplete="off" multiple>
                <?php 
                if (!empty($article_categories) && !is_wp_error($article_categories)) :
                    foreach ($article_categories as $cat) :
                        $article_category_id    = $cat->term_id;
                        $article_category_label = $cat->name;
                        $article_category_tax   = $cat->taxonomy;
                ?>
                        <option value="<?php echo esc_attr($cat->name); ?>">
                            <?php echo esc_html($cat->name); ?>
                        </option>
                <?php 
                    endforeach;
                endif;
                ?>
            </select>
            </div>
        </div>
    </div>

    <!-- ARTICLE AUTHORS -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="panelsStayOpen-headingArticleAuthor">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#panelsStayOpen-collapseArticleAuthor" aria-expanded="false"
                aria-controls="panelsStayOpen-collapseArticleAuthor">
                Authors <div class="counter-selection counter-selection--article-author"></div>
            </button>
        </h2>

        <div id="panelsStayOpen-collapseArticleAuthor" class="accordion-collapse collapse"
            aria-labelledby="panelsStayOpen-headingArticleAuthor">
            <div class="accordion-body">
                <small>Write at least 3 letters to search...</small>  
                <input type="search" class="kosher-search-filter-form__remote-search" data-remote-filter-search="author-select" placeholder="<?php esc_attr_e('Search authors...', 'kosher-typesense-search'); ?>" autocomplete="off">
                <select id="author-select" name="chef[]" multiple></select>

                <div id="selected-author" class="selected-author-container"></div>
                <div id="selected-author-container" class="container-ts-selection"></div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="mobile-filter-panel mobile-filter-panel--shows" data-mobile-filter-panel="shows">
<div class="shows-filters-wrapper filters-group filters-shows">
    <!-- SHOW NAME -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="panelsStayOpen-headingShowName">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#panelsStayOpen-collapseShowName" aria-expanded="false"
                aria-controls="panelsStayOpen-collapseShowName">
                Show Name <div class="counter-selection counter-selection--showname"></div>
            </button>
        </h2>

        <div id="panelsStayOpen-collapseShowName" class="accordion-collapse collapse"
            aria-labelledby="panelsStayOpen-headingShowName">
            <div class="accordion-body">
                <select id="show-select" autocomplete="off" multiple>
                <?php if ($shows->have_posts()) : 
                        while ($shows->have_posts()) : 
                            $shows->the_post(); ?>
                            <option value="<?php echo esc_attr(get_the_title()); ?>">
                                <?php echo esc_html(get_the_title()); ?>
                            </option>
                    <?php 
                        endwhile; 
                        wp_reset_postdata();
                    endif;
                    ?>
                </select>
            </div>
        </div>
    </div>

        <!-- SHOW CHEF -->
        <div class="accordion-item">
            <h2 class="accordion-header" id="panelsStayOpen-headingShowChefs">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                    data-bs-target="#panelsStayOpen-collapseShowChefs" aria-expanded="false"
                    aria-controls="panelsStayOpen-collapseShowChefs">
                    Chefs <div class="counter-selection counter-selection--show-chef"></div>
                </button>
            </h2>

            <div id="panelsStayOpen-collapseShowChefs" class="accordion-collapse collapse"
                aria-labelledby="panelsStayOpen-headingShowChefs">
                <div class="accordion-body">
                    <small>Write at least 3 letters to search...</small>  
                    <input type="search" class="kosher-search-filter-form__remote-search" data-remote-filter-search="show-chef-select" placeholder="<?php esc_attr_e('Search chefs...', 'kosher-typesense-search'); ?>" autocomplete="off">
                    <select id="show-chef-select" name="chef[]" multiple></select>

                    <div id="selected-show-chef" class="selected-chef-container"></div>
                    <div id="selected-show-chef-container" class="container-ts-selection"></div>
                </div>
            </div>
        </div>



    <!-- VIDEO LENGHT -->
    <div class="accordion-item d-none">
        <h2 class="accordion-header" id="panelsStayOpen-headingVideoLength">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#panelsStayOpen-collapseVideoLength" aria-expanded="false"
                aria-controls="panelsStayOpen-collapseVideoLength">
                Video Length <div class="counter-selection counter-selection--videolength"></div>
            </button>
        </h2>

        <div id="panelsStayOpen-collapseVideoLength" class="accordion-collapse collapse"
            aria-labelledby="panelsStayOpen-headingVideoLength">
            <div class="accordion-body">
                <select id="video-length-select" autocomplete="off" multiple>
                    <option value="5">&lt; 5 min</option>
                    <option value="10">5–10 min</option>
                    <option value="20">10–20 min</option>
                    <option value="21">21+ min</option>
                </select>
            </div>
        </div>
    </div>


</div>
</div>

<div class="mobile-filter-panel mobile-filter-panel--menus" data-mobile-filter-panel="menus">
<div class="menus-filters-wrapper filters-group filters-menus">
    <div class="accordion-item">
        <h2 class="accordion-header" id="panelsStayOpen-headingMenuCategory">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#panelsStayOpen-collapseMenuCategory" aria-expanded="false"
                aria-controls="panelsStayOpen-collapseMenuCategory">
                Menu Categories <div class="counter-selection counter-selection--menu-category"></div>
            </button>
        </h2>

        <div id="panelsStayOpen-collapseMenuCategory" class="accordion-collapse collapse"
            aria-labelledby="panelsStayOpen-headingMenuCategory">
            <div class="accordion-body">
                <select id="in-checkbox-menu-category" autocomplete="off" multiple>
                    <?php if (!empty($menu_categories) && !is_wp_error($menu_categories)) : ?>
                        <?php foreach ($menu_categories as $cat) : ?>
                            <option value="<?php echo esc_attr($cat->name); ?>"><?php echo esc_html($cat->name); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header" id="panelsStayOpen-headingMenuAuthor">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#panelsStayOpen-collapseMenuAuthor" aria-expanded="false"
                aria-controls="panelsStayOpen-collapseMenuAuthor">
                Creators <div class="counter-selection counter-selection--menu-author"></div>
            </button>
        </h2>
        <div id="panelsStayOpen-collapseMenuAuthor" class="accordion-collapse collapse"
            aria-labelledby="panelsStayOpen-headingMenuAuthor">
            <div class="accordion-body">
                <select id="menu-author-select" autocomplete="off" multiple>
                    <?php foreach ($menu_author_names as $menu_author_name) : ?>
                        <option value="<?php echo esc_attr($menu_author_name); ?>"><?php echo esc_html($menu_author_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header" id="panelsStayOpen-headingMenuSections">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#panelsStayOpen-collapseMenuSections" aria-expanded="false"
                aria-controls="panelsStayOpen-collapseMenuSections">
                Sections <div class="counter-selection counter-selection--menu-sections"></div>
            </button>
        </h2>
        <div id="panelsStayOpen-collapseMenuSections" class="accordion-collapse collapse"
            aria-labelledby="panelsStayOpen-headingMenuSections">
            <div class="accordion-body">
                <select id="menu-sections-select" autocomplete="off" multiple>
                    <option value="1">1 section</option>
                    <option value="2">2 sections</option>
                    <option value="3">3 sections</option>
                    <option value="4">4+ sections</option>
                </select>
            </div>
        </div>
    </div>

    <div class="accordion-item">
        <h2 class="accordion-header" id="panelsStayOpen-headingMenuCards">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#panelsStayOpen-collapseMenuCards" aria-expanded="false"
                aria-controls="panelsStayOpen-collapseMenuCards">
                Menu Items <div class="counter-selection counter-selection--menu-cards"></div>
            </button>
        </h2>
        <div id="panelsStayOpen-collapseMenuCards" class="accordion-collapse collapse"
            aria-labelledby="panelsStayOpen-headingMenuCards">
            <div class="accordion-body">
                <select id="menu-cards-select" autocomplete="off" multiple>
                    <option value="5">Up to 5 items</option>
                    <option value="10">6–10 items</option>
                    <option value="20">11–20 items</option>
                    <option value="21">21+ items</option>
                </select>
            </div>
        </div>
    </div>
</div>
</div>




        </div>
      </aside>

      <main class="container search-filters-nav search-content">
        <section class="header-tabs">
          <div class="header-tabs__inner">
            <ul class="nav nav-tabs" id="searchTabs">
              <li class="nav-item">
                <a class="nav-link active" id="tab-all" href="#all">All (<span id="all-count">0</span>)</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="tab-recipes" href="#recipes">Recipes (<span id="recipes-count">0</span>)</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="tab-menus" href="#menus">Menus (<span id="menus-count">0</span>)</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="tab-articles" href="#articles">Articles (<span id="articles-count">0</span>)</a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="tab-shows" href="#shows">Shows (<span id="shows-count">0</span>)</a>
              </li>
            </ul>
            <div class="search-controls-right header-tabs__controls">
              <button type="button" class="btn btn-kosher btn-kosher--filter common-btn"><i class="bi bi-filter"></i> <?php esc_html_e('Filters', 'kosher-typesense-search'); ?></button>
              <div class="search-filters__sort search-filters__sort--hidden">
                <label for="search-filters__sort__dropdown"><?php esc_html_e('Sort by:', 'kosher-typesense-search'); ?></label>
                <select name="sort-results" id="search-filters__sort__dropdown">
                  <option value="default"><?php esc_html_e('Default', 'kosher-typesense-search'); ?></option>
                  <option value="title_sort:asc"><?php esc_html_e('A-Z', 'kosher-typesense-search'); ?></option>
                  <option value="title_sort:desc"><?php esc_html_e('Z-A', 'kosher-typesense-search'); ?></option>
                  <option value="date:desc"><?php esc_html_e('Newest', 'kosher-typesense-search'); ?></option>
                  <option value="date:asc"><?php esc_html_e('Oldest', 'kosher-typesense-search'); ?></option>
                  <option value="rating:desc"><?php esc_html_e('Most Popular', 'kosher-typesense-search'); ?></option>
                </select>
              </div>
            </div>
          </div>
        </section>
        <div id="results" class="tab-content mt-4">
          <!-- Tab for All -->
          <div class="tab-pane fade show active" id="all">
          <script>
  googletag.cmd.push(function() {
    var mapping1 = googletag.sizeMapping()
                            .addSize([990, 90], [[1440, 270], [ 970, 90], [ 728, 90], [ 1, 1]])
                            .addSize([728, 0], [[970, 90], [ 728, 90], [ 1, 1]])
                            .addSize([300, 0], [[300, 250], [ 300, 50], [ 1, 1]])
                            .addSize([0, 0], [[300, 250], [ 300, 50], [ 1, 1]])
                            .build();

    var mapping2 = googletag.sizeMapping()
                            .addSize([990, 0], [[970, 90], [728, 90], [ 1, 1]])
                            .addSize([728, 0], [[728, 90], [ 1, 1]])
                            .addSize([300, 0], [[300, 250], [ 300, 50], [ 1, 1]])
                            .addSize([0, 0], [[300, 50], [ 1, 1]])
                            .build();

    var mapping3 = googletag.sizeMapping()
                            .addSize([990, 0], [[970, 90], [ 728, 90], [ 1, 1]])
                            .addSize([728, 0], [[970, 90], [ 728, 90], [ 300, 250], [ 1, 1]])
                            .addSize([300, 0], [[300, 250], [ 300, 600], [ 1, 1]])
                            .addSize([0, 0], [[300, 50], [ 1, 1]])
                            .build();

    var mapping4 = googletag.sizeMapping()
                            .addSize([728, 0], [[300, 600], [ 300, 250], [ 1, 1]])
                            .addSize([300, 0], [[300, 250], [ 300, 50], [ 1, 1]])
                            .addSize([0, 0], [[300, 250], [ 300, 50], [ 1, 1]])
                            .build();

    googletag.defineSlot('/21630499774/leaderboard-in-content-ltd', [[1,1],[300,50],[300,250],[300,600],[320,50],[336,280],[728,90]], 'div-gpt-ad-1819084-3')
             .setTargeting('Position', ['incontent'])
             .setTargeting('pagecat', ['homepage'])
             .setTargeting('URL', [siteurl])
             .defineSizeMapping(mapping2)
             .addService(googletag.pubads());
             
    googletag.pubads().enableSingleRequest();
    googletag.pubads().collapseEmptyDivs();
    googletag.pubads().setCentering(true);
    googletag.enableServices();
  });
</script>
<!-- GPT AdSlot 3 for Ad unit 'leaderboard-in-content-ltd' ### Size: [[1,1],[300,50],[300,250],[300,600],[320,50],[336,280],[728,90],[970,90],[970,250]] -->
<div id='div-gpt-ad-1819084-3'>
  <script>
    googletag.cmd.push(function() { googletag.display('div-gpt-ad-1819084-3'); });
  </script>
</div>

<!-- End AdSlot 3 -->
            <div class="list list-all">
              <!-- All results will be dynamically injected here -->
            </div>
          </div>
          <!-- Tab for Recipes -->
          <div class="tab-pane fade" id="recipes">
          <script>
  window.googletag = window.googletag || { cmd: [] };

  googletag.cmd.push(function() {
    var mapping1 = googletag.sizeMapping()
      .addSize([990, 90], [[1440, 270], [970, 90], [728, 90], [1, 1]])
      .addSize([728, 0], [[970, 90], [728, 90], [1, 1]])
      .addSize([300, 0], [[300, 250], [300, 50], [1, 1]])
      .addSize([0, 0], [[300, 250], [300, 50], [1, 1]])
      .build();

    var mapping2 = googletag.sizeMapping()
      .addSize([990, 0], [[970, 90], [728, 90], [1, 1]])
      .addSize([728, 0], [[728, 90], [1, 1]])
      .addSize([300, 0], [[300, 250], [300, 50], [1, 1]])
      .addSize([0, 0], [[300, 50], [1, 1]])
      .build();

    var mapping3 = googletag.sizeMapping()
      .addSize([990, 0], [[970, 90], [728, 90], [1, 1]])
      .addSize([728, 0], [[970, 90], [728, 90], [300, 250], [1, 1]])
      .addSize([300, 0], [[300, 250], [300, 600], [1, 1]])
      .addSize([0, 0], [[300, 50], [1, 1]])
      .build();

    var mapping4 = googletag.sizeMapping()
      .addSize([728, 0], [[300, 600], [300, 250], [1, 1]])
      .addSize([300, 0], [[300, 250], [300, 50], [1, 1]])
      .addSize([0, 0], [[300, 250], [300, 50], [1, 1]])
      .build();

    googletag.defineSlot('/21630499774/leaderboard-in-content-ltd-2', [[1,1],[300,50],[300,250],[300,600],[320,50],[336,280],[728,90]], 'div-gpt-ad-1819084-6')
      .setTargeting('Position', ['incontent2'])
      .setTargeting('pagecat', ['homepage'])
      .setTargeting('URL', [window.location.href]) // Ensuring proper URL setting
      .defineSizeMapping(mapping2)
      .addService(googletag.pubads());

    // ✅ Enable Lazy Load
    googletag.pubads().enableLazyLoad({
      fetchMarginPercent: 2,  // Fetch only when near viewport
      renderMarginPercent: 0,  // Render only when fully inside viewport
      mobileScaling: 2.0       // Increase margin for mobile users
    });

    // 🚀 Register event handlers to observe lazy loading behavior
    googletag.pubads().addEventListener("slotRequested", (event) => {
      updateSlotStatus(event.slot.getSlotElementId(), "fetched");
    });

    googletag.pubads().addEventListener("slotOnload", (event) => {
      updateSlotStatus(event.slot.getSlotElementId(), "rendered");
    });

    // ✅ Enable SRA and services
    googletag.pubads().enableSingleRequest();
    googletag.pubads().collapseEmptyDivs();
    googletag.pubads().setCentering(true);
    googletag.enableServices();
  });

  function updateSlotStatus(slotId, state) {
    const elem = document.getElementById(slotId + "-" + state);
    if (elem) {
      elem.className = "activated";
      elem.setAttribute("data-status", "Yes");
    }
  }
</script>
<span id="div-gpt-ad-1819084-6-fetched" data-status="No"></span>
  <span id="div-gpt-ad-1819084-6-rendered" data-status="No"></span>

<!-- GPT AdSlot 6 for Ad unit 'leaderboard-in-content-ltd-2' -->
<div id="div-gpt-ad-1819084-6">
  <script>
    googletag.cmd.push(function() { 
      googletag.display('div-gpt-ad-1819084-6'); 
    });
  </script>
</div>
<!-- End AdSlot 6 -->

            <ul class="list row">
              <!-- Recipes results will be dynamically injected here -->
            </ul>

            <div class="pagination pagination-recipes"></div>
          </div>
          <!-- Tab for Menus -->
          <div class="tab-pane fade" id="menus">
            <ul class="list row">
              <!-- Menus results will be dynamically injected here -->
            </ul>
            <div class="pagination pagination-menus"></div>
          </div>
          <!-- Tab for Articles -->
          <div class="tab-pane fade" id="articles">
          <script>
  window.googletag = window.googletag || { cmd: [] };

  googletag.cmd.push(function() {
    var mapping1 = googletag.sizeMapping()
      .addSize([990, 90], [[1440, 270], [970, 90], [728, 90], [1, 1]])
      .addSize([728, 0], [[970, 90], [728, 90], [1, 1]])
      .addSize([300, 0], [[300, 250], [300, 50], [1, 1]])
      .addSize([0, 0], [[300, 250], [300, 50], [1, 1]])
      .build();

    var mapping2 = googletag.sizeMapping()
      .addSize([990, 0], [[728, 90], [1, 1]])
      .addSize([728, 0], [[728, 90], [300, 250], [1, 1]])
      .addSize([300, 0], [[300, 250], [300, 50], [1, 1]])
      .addSize([0, 0], [[300, 50], [1, 1]])
      .build();

    var mapping3 = googletag.sizeMapping()
      .addSize([990, 0], [[970, 90], [728, 90], [1, 1]])
      .addSize([728, 0], [[970, 90], [728, 90], [300, 250], [1, 1]])
      .addSize([300, 0], [[300, 250], [300, 600], [1, 1]])
      .addSize([0, 0], [[300, 50], [1, 1]])
      .build();

    var mapping4 = googletag.sizeMapping()
      .addSize([728, 0], [[300, 600], [300, 250], [1, 1]])
      .addSize([300, 0], [[300, 250], [300, 50], [1, 1]])
      .addSize([0, 0], [[300, 250], [300, 50], [1, 1]])
      .build();

    googletag.defineSlot('/21630499774/leaderboard-footer', [[1,1],[300,50],[300,250],[300,600],[320,50],[336,280],[728,90],[970,90],[970,250]], 'div-gpt-ad-1819084-2')
      .setTargeting('Position', ['footer'])
      .setTargeting('pagecat', ['homepage'])
      .setTargeting('URL', [window.location.href]) // Ensuring proper URL setting
      .defineSizeMapping(mapping2)
      .addService(googletag.pubads());

    // ✅ Enable Lazy Load
    googletag.pubads().enableLazyLoad({
      fetchMarginPercent: 2,  // Fetch only when near viewport
      renderMarginPercent: 0,  // Render only when fully inside viewport
      mobileScaling: 2.0       // Increase margin for mobile users
    });

    // 🚀 Register event handlers to observe lazy loading behavior
    googletag.pubads().addEventListener("slotRequested", (event) => {
      updateSlotStatus(event.slot.getSlotElementId(), "fetched");
    });

    googletag.pubads().addEventListener("slotOnload", (event) => {
      updateSlotStatus(event.slot.getSlotElementId(), "rendered");
    });

    // ✅ Enable SRA and services
    googletag.pubads().enableSingleRequest();
    googletag.pubads().collapseEmptyDivs();
    googletag.pubads().setCentering(true);
    googletag.enableServices();
  });

  function updateSlotStatus(slotId, state) {
    const elem = document.getElementById(slotId + "-" + state);
    if (elem) {
      elem.className = "activated";
      elem.setAttribute("data-status", "Yes");
    }
  }
</script>
<span id="div-gpt-ad-1819084-2-fetched" data-status="No"></span>
  <span id="div-gpt-ad-1819084-2-rendered" data-status="No"></span>

<!-- GPT AdSlot 2 for Ad unit 'leaderboard-footer' -->
<div id="div-gpt-ad-1819084-2">
   <script>
    googletag.cmd.push(function() { 
      googletag.display('div-gpt-ad-1819084-2'); 
    });
  </script>
</div>
<!-- End AdSlot 2 -->

            <ul class="list row">
              <!-- Articles results will be dynamically injected here -->
            </ul>
            <div class="pagination pagination-articles"></div>

          </div>

          <!-- Tab for Shows -->
          <div class="tab-pane fade" id="shows">

          <script>
  googletag.cmd.push(function() {
    var mapping1 = googletag.sizeMapping()
                            .addSize([990, 90], [[1440, 270], [ 970, 90], [ 728, 90], [ 1, 1]])
                            .addSize([728, 0], [[970, 90], [ 728, 90], [ 1, 1]])
                            .addSize([300, 0], [[300, 250], [ 300, 50], [ 1, 1]])
                            .addSize([0, 0], [[300, 250], [ 300, 50], [ 1, 1]])
                            .build();

    var mapping2 = googletag.sizeMapping()
                            .addSize([990, 0], [[970, 90], [728, 90], [ 1, 1]])
                            .addSize([728, 0], [[728, 90], [ 1, 1]])
                            .addSize([300, 0], [[300, 250], [ 300, 50], [ 1, 1]])
                            .addSize([0, 0], [[300, 50], [ 1, 1]])
                            .build();

    var mapping3 = googletag.sizeMapping()
                            .addSize([990, 0], [[970, 90], [ 728, 90], [ 1, 1]])
                            .addSize([728, 0], [[970, 90], [ 728, 90], [ 300, 250], [ 1, 1]])
                            .addSize([300, 0], [[300, 250], [ 300, 600], [ 1, 1]])
                            .addSize([0, 0], [[300, 50], [ 1, 1]])
                            .build();

    var mapping4 = googletag.sizeMapping()
                            .addSize([728, 0], [[300, 600], [ 300, 250], [ 1, 1]])
                            .addSize([300, 0], [[300, 250], [ 300, 50], [ 1, 1]])
                            .addSize([0, 0], [[300, 250], [ 300, 50], [ 1, 1]])
                            .build();

    googletag.defineSlot('/21630499774/leaderboard-in-content-ltd-3', [[1,1],[300,50],[300,250],[300,600],[320,50],[336,280],[728,90]], 'div-gpt-ad-1819084-7')
             .setTargeting('Position', ['incontent3'])
             .setTargeting('pagecat', ['articles'])
             .setTargeting('pagesubcat', ['list page'])
             .setTargeting('URL', [siteurl])
             .defineSizeMapping(mapping2)
             .addService(googletag.pubads());
             
    googletag.pubads().enableSingleRequest();
    googletag.pubads().collapseEmptyDivs();
    googletag.pubads().setCentering(true);
    googletag.enableServices();
  });
</script>

<!-- GPT AdSlot 7 for Ad unit 'leaderboard-in-content-ltd-3' ### Size: [[1,1],[300,250],[300x50],[970,90],[728,90]] -->
<div id='div-gpt-ad-1819084-7'>
  <script>
    googletag.cmd.push(function() { googletag.display('div-gpt-ad-1819084-7'); });
  </script>
</div>
<!-- End AdSlot 7 -->

            <ul class="list row">
              <!-- Shows results will be dynamically injected here -->
            </ul>
            <div class="pagination pagination-shows"></div>
          </div>
        </div>
      </main>
    </div>
  </div>
</section>
<button type="button" class="btn-apply common__btn ">See Results <i class="bi bi-chevron-right"></i></button>
