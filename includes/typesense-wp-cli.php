<?php

require_once __DIR__ . '/typesense-functions.php';

if (!(defined('WP_CLI') && WP_CLI)) {
    return;
}

class Kosher_Typesense_Command
{
    public $types = [
        'recipes' => [
            /*
            [
                'name' => '.*',
                'type' => 'string*'
            ],
            */
            [
                'name' => 'postID',
                'type' => 'int32'
            ],
            [
                'name' => 'title',
                'type' => 'string',
                "infix" => false,
                'facet' => false,
                "sort" => true,
                "exact" => true,
                'stem' => true,
            ],
            [
                'name' => 'title_sort',
                'type' => 'string',
                'facet' => false,
                'sort' => true,
                'optional' => true,
            ],
            [
                'name' => 'title_words_length',
                'type' => 'int32',
                'sort' => true
            ],
            [
                'name' => 'main_dish',
                'type' => 'string',
                'facet' => true,
                'sort' => true,
                'optional' => true,
            ],
            [
                'name' => 'search_priority',
                'type' => 'int32',
                'sort' => true,
                'optional' => true,
            ],
            [
                'name' => 'likes',
                'type' => 'int32',
                'sort' => true
            ],
            [
                'name' => 'chef_url',
                'type' => 'string',
                'facet' => false,
                "sort" => true,
                "exact" => true,
            ],
            [
                'name' => 'type',
                'type' => 'auto',
                'facet' => true,
            ],
            [
                'name' => 'chefs',
                'type' => 'string[]',
                'facet' => false,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true,
                'stem' => true,
            ],
            [
                'name' => 'contains_allergents',
                'type' => 'string[]',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                "name" => "rating",
                "type" => "float",
                "facet" => false,
                "optional" => true,
                "sort" => true,
            ],
            [
                'name' => 'cook_time',
                'type' => 'int64',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'diets',
                'type' => 'string[]',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'cuisine',
                'type' => 'string[]',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'difficulty',
                'type' => 'string[]',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'hours',
                'type' => 'int64',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'minutes',
                'type' => 'int64',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'ingredients',
                'type' => 'string[]',
                'facet' => false,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true,
                'infix' => false,
                'stem' => true
            ],
            [
                'name' => 'occasions',
                'type' => 'string[]',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'preference',
                'type' => 'string*',         
                'facet' => true,         
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'featured_recipe',
                'type' => 'int32',
                'optional' => true,
                "sort" => true,
                'facet' => false
            ],
            [
                'name' => 'recipe_category',
                'type' => 'string[]',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'serving',
                'type' => 'auto',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'tags',
                'type' => 'string[]',
                'facet' => false,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true,
                'infix' => false,
            ],
            [
                'name' => 'sources',
                'type' => 'string*',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'date',
                'type' => 'int64'
            ],
            [
                'name' => 'community-recipe',
                'type' => 'bool',
                'facet' => true,
                'optional' => true
            ],
            [
                'name' => 'user_consent_public',
                'type' => 'bool',
                'facet' => true,
                'optional' => true
            ],
        ],
        'articles' => [
            /*
            [
                'name' => '.*',
                'type' => 'string*'
            ],
            */
            [
                'name' => 'title',
                'type' => 'string',
                'facet' => false,
                "sort" => true,
                "exact" => true,
                'stem' => true,
            ],
            [
                'name' => 'title_sort',
                'type' => 'string',
                'facet' => false,
                'sort' => true,
                'optional' => true,
            ],
            [
                'name' => 'author',
                'type' => 'string',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                "sort" => true,
                'optional' => true
            ],
            [
                'name' => 'postID',
                'type' => 'int64'
            ],
            [
                'name' => 'tags',
                'type' => 'string[]',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                "name" => "rating",
                "type" => "float",
                "facet" => false,
                "optional" => true,
                "sort" => true,
            ],
            [
                'name' => 'article_sub_category',
                'type' => 'string[]',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'date',
                'type' => 'int64'
            ],
        ],
        'episodes' => [
            /*
            [
                'name' => '.*',
                'type' => 'string*'
            ],
            */
            [
                'name' => 'title',
                'type' => 'string',
                'facet' => false,
                "sort" => true,
                "exact" => true,
                'stem' => true,
            ],
            [
                'name' => 'title_sort',
                'type' => 'string',
                'facet' => false,
                'sort' => true,
                'optional' => true,
            ],
            [
                "name" => "rating",
                "type" => "float",
                "facet" => false,
                "optional" => true,
                "sort" => true,
            ],
            [
                'name' => 'chef',
                'type' => 'string[]',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true,
                'stem' => true,
            ],
            [
                'name' => 'chef_id',
                'type' => 'string',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'postID',
                'type' => 'int64'
            ],
            [
                'name' => 'show',
                'type' => 'string',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'tags',
                'type' => 'string[]',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
            [
                'name' => 'date',
                'type' => 'int64'
            ],

        ],
        'shows' => [
            /*
            [
                'name' => '.*',
                'type' => 'string*'
            ],
            */
            [
                'name' => 'title',
                'type' => 'string',
                'facet' => false,
                "sort" => true,
            ],
            [
                'name' => 'title_sort',
                'type' => 'string',
                'facet' => false,
                'sort' => true,
                'optional' => true,
            ],
            [
                'name' => 'postID',
                'type' => 'int64'
            ],
            [
                'name' => 'short_description',
                'type' => 'string[]',
                'facet' => false,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
                    /*
            [
                'name' => 'tags',
                'type' => 'string[]',
                'facet' => true,
                'dirty_values' => 'coerce_or_drop',
                'optional' => true
            ],
                     */
            [
                'name' => 'date',
                'type' => 'int64'
            ],
        ]
        ,
        'menus' => [
            [
                'name' => 'postID',
                'type' => 'int64',
            ],
            [
                'name' => 'title',
                'type' => 'string',
                'facet' => false,
                'sort' => true,
                'exact' => true,
                'stem' => true,
            ],
            [
                'name' => 'title_sort',
                'type' => 'string',
                'facet' => false,
                'sort' => true,
                'optional' => true,
            ],
            [
                'name' => 'description',
                'type' => 'string',
                'facet' => false,
                'optional' => true,
                'stem' => true,
            ],
            [
                'name' => 'author_name',
                'type' => 'string',
                'facet' => true,
                'optional' => true,
                'sort' => true,
            ],
            [
                'name' => 'categories',
                'type' => 'string[]',
                'facet' => true,
                'optional' => true,
                'dirty_values' => 'coerce_or_drop',
            ],
            [
                'name' => 'menus_categories',
                'type' => 'string[]',
                'facet' => true,
                'optional' => true,
                'dirty_values' => 'coerce_or_drop',
            ],
            [
                'name' => 'holidays',
                'type' => 'string[]',
                'facet' => true,
                'optional' => true,
                'dirty_values' => 'coerce_or_drop',
            ],
            [
                'name' => 'section_titles',
                'type' => 'string[]',
                'facet' => false,
                'optional' => true,
                'dirty_values' => 'coerce_or_drop',
                'stem' => true,
            ],
            [
                'name' => 'recipe_titles',
                'type' => 'string[]',
                'facet' => false,
                'optional' => true,
                'dirty_values' => 'coerce_or_drop',
                'stem' => true,
            ],
            [
                'name' => 'card_text',
                'type' => 'string[]',
                'facet' => false,
                'optional' => true,
                'dirty_values' => 'coerce_or_drop',
                'stem' => true,
            ],
            [
                'name' => 'privacy',
                'type' => 'string',
                'facet' => true,
                'optional' => true,
            ],
            [
                'name' => 'sections_count',
                'type' => 'int32',
                'facet' => true,
                'sort' => true,
            ],
            [
                'name' => 'cards_count',
                'type' => 'int32',
                'facet' => true,
                'sort' => true,
            ],
            [
                'name' => 'date',
                'type' => 'int64',
                'sort' => true,
            ],
        ]

    ];




    private function get_collection_name($type)
    {
        return function_exists('kosher_typesense_collection_name')
            ? kosher_typesense_collection_name($type)
            : env('TYPESENSE_COLLECTION_PREFIX') . $type;
    }

    private function get_collection_prefix()
    {
        return function_exists('kosher_typesense_collection_prefix')
            ? kosher_typesense_collection_prefix()
            : env('TYPESENSE_COLLECTION_PREFIX');
    }

    private function get_collection_environment()
    {
        return function_exists('kosher_typesense_collection_environment')
            ? kosher_typesense_collection_environment()
            : 'production';
    }

    private function get_admin_client()
    {
        if (function_exists('ap_typesense_synonyms')) {
            $client = ap_typesense_synonyms();

            if (is_object($client)) {
                return $client;
            }
        }

        return null;
    }

    /**
     * Show the active Typesense collection targets without writing anything.
     *
     * ## OPTIONS
     *
     * [--c=<post_type>]
     * : Limit the output to one post type collection.
     *
     * ## EXAMPLES
     *
     *     wp typesense collection-plan
     *     wp kosher-typesense collection-plan --c=recipes
     *
     * @subcommand collection-plan
     */
    public function collection_plan($args = array(), $assoc_args = array())
    {
        $only_type = is_array($assoc_args) && isset($assoc_args['c']) ? $assoc_args['c'] : null;
        $environment = $this->get_collection_environment();
        $prefix = $this->get_collection_prefix();
        $collections = array();

        foreach ($this->types as $type => $fields) {
            if ($only_type && $type !== $only_type) {
                continue;
            }

            $collections[$type] = $this->get_collection_name($type);
        }

        if (empty($collections)) {
            WP_CLI::error("No Typesense collection configured for '{$only_type}'.");
            return;
        }

        WP_CLI::line('The site is using ' . ucfirst($environment) . ' Typesense collections.');
        WP_CLI::line('Active collection prefix: ' . $prefix);
        WP_CLI::line('');
        WP_CLI::line('The schema commands will create or update these collections:');

        foreach ($collections as $type => $collection) {
            WP_CLI::line('- ' . $collection . ' (' . $type . ')');
        }

        WP_CLI::line('');
        WP_CLI::line('Post-save sync will save published posts into:');

        foreach ($collections as $type => $collection) {
            WP_CLI::line('- ' . $type . ' -> ' . $collection);
        }

        WP_CLI::line('');
        WP_CLI::line('Synonyms will be attached to:');

        foreach ($collections as $collection) {
            WP_CLI::line('- ' . $collection);
        }

        WP_CLI::line('');
        WP_CLI::success('Read-only check complete. No Typesense data was changed.');
    }

    public function plan($args = array(), $assoc_args = array())
    {
        $specified_post_type = is_array($assoc_args) && isset($assoc_args['c']) ? sanitize_key((string) $assoc_args['c']) : null;

        if ($specified_post_type && !isset($this->types[$specified_post_type])) {
            WP_CLI::error("No Typesense schema is configured for '{$specified_post_type}'.");
            return;
        }

        $env = function_exists('kosher_typesense_collection_prefix')
            ? kosher_typesense_collection_prefix()
            : env('TYPESENSE_COLLECTION_PREFIX');

        $existing_collections = array();
        $typesense = $this->get_admin_client();
        $typesense_available = is_object($typesense);

        if ($typesense_available) {
            try {
                $collections = $typesense->collections->retrieve();

                if (is_array($collections)) {
                    foreach ($collections as $collection) {
                        if (!is_array($collection) || empty($collection['name'])) {
                            continue;
                        }

                        $existing_collections[$collection['name']] = $collection;
                    }
                }
            } catch (Exception $e) {
                $typesense_available = false;
                WP_CLI::warning('Could not reach Typesense to inspect existing collections: ' . $e->getMessage());
            }
        } else {
            WP_CLI::warning('Typesense is not initialized. Showing the local schema plan only.');
        }

        WP_CLI::line('Typesense collection plan');
        WP_CLI::line('Active collection prefix: ' . $env);
        WP_CLI::line('This is a read-only preview. No collections, schemas, documents, or synonyms will be changed.');
        WP_CLI::line('');

        foreach ($this->types as $type => $fields) {
            if ($specified_post_type && $type !== $specified_post_type) {
                continue;
            }

            $collection_name = $env . $type;
            $collection_exists = isset($existing_collections[$collection_name]);
            $document_count = 'Unavailable';

            if ($collection_exists && isset($existing_collections[$collection_name]['num_documents'])) {
                $document_count = (string) $existing_collections[$collection_name]['num_documents'];
            }

            WP_CLI::line('Post type: ' . $type);
            WP_CLI::line('Collection: ' . $collection_name);
            WP_CLI::line('Planned fields: ' . count($fields));
            WP_CLI::line('Default sorting field: date');
            WP_CLI::line('Collection exists: ' . ($typesense_available ? ($collection_exists ? 'Yes' : 'No') : 'Unknown'));
            WP_CLI::line('Current document count: ' . $document_count);
            WP_CLI::line('Planned field list:');

            foreach ($fields as $field) {
                if (!is_array($field) || empty($field['name'])) {
                    continue;
                }

                $flags = array();

                foreach (array('facet', 'sort', 'optional', 'stem', 'exact', 'infix') as $flag) {
                    if (array_key_exists($flag, $field)) {
                        $flags[] = $flag . '=' . ($field[$flag] ? 'true' : 'false');
                    }
                }

                if (!empty($field['dirty_values'])) {
                    $flags[] = 'dirty_values=' . $field['dirty_values'];
                }

                $line = '  - ' . $field['name'] . ' (' . ($field['type'] ?? 'auto') . ')';

                if (!empty($flags)) {
                    $line .= ' [' . implode(', ', $flags) . ']';
                }

                WP_CLI::line($line);
            }

            WP_CLI::line('');
        }

        WP_CLI::line('Write target summary:');
        WP_CLI::line('If you run reindex, it will reindex to these collections:');

        foreach ($this->types as $type => $fields) {
            if ($specified_post_type && $type !== $specified_post_type) {
                continue;
            }

            WP_CLI::line('- ' . $type . ' -> ' . $env . $type);
        }

        WP_CLI::line('');
        WP_CLI::line('If you save a post, post-save sync will save it to:');

        foreach ($this->types as $type => $fields) {
            if ($specified_post_type && $type !== $specified_post_type) {
                continue;
            }

            WP_CLI::line('- ' . $type . ' post -> ' . $env . $type);
        }

        WP_CLI::line('');
        WP_CLI::line('If you save synonyms, they will go to:');

        foreach ($this->types as $type => $fields) {
            if ($specified_post_type && $type !== $specified_post_type) {
                continue;
            }

            WP_CLI::line('- ' . $env . $type);
        }

        WP_CLI::line('');

        WP_CLI::success('Typesense plan complete. No Typesense data was changed.');
    }

    public function init_collections($args = array(), $assoc_args = array())
    {
        $typesense = $this->get_admin_client();

        if (!is_object($typesense)) {
            WP_CLI::error('Typesense admin client is not configured. Add the Typesense Admin API Key before running init_collections.');
            return;
        }

        $only_type = is_array($assoc_args) && isset($assoc_args['c']) ? $assoc_args['c'] : null;
        $existing_collections = array();

        try {
            $collections = $typesense->collections->retrieve();

            if (is_array($collections)) {
                $existing_collections = array_column($collections, 'name');
            }
        } catch (Exception $e) {
            WP_CLI::error('Could not retrieve Typesense collections. Check the Typesense Admin API Key. ' . $e->getMessage());
            return;
        }

        foreach ($this->types as $type => $fields) {
            if ($only_type && $type !== $only_type) {
                continue;
            }

            WP_CLI::line('Init Index ' . $type);
            $collection = $this->get_collection_name($type);

            if (in_array($collection, $existing_collections, true)) {
                WP_CLI::success("Collection already exists: {$collection}");
                continue;
            }

            $schema = [
                'name' => $collection,
                'fields' => $fields,
                'default_sorting_field' => 'date',

                // Improve tokenization for hyphenated words
                'token_separators' => ['-', '/', '+'],
                'symbols_to_index' => ['-', '/'],
            ];

            try {
                $typesense->collections->create($schema);
                WP_CLI::success("Created collection: {$collection}");
            } catch (Exception $e) {
                WP_CLI::warning("Could not create {$collection}: " . $e->getMessage());
            }
        }

    }





    public function synonym_update($args, $assoc_args) {
        $collection = $assoc_args['collection'] ?? null;
        WP_CLI::line($collection);

        if (!$collection) {
            WP_CLI::error("You must specify a collection using --collection=<collection>");
            return;
        }

        $this->update_synonyms($this->get_collection_name($collection));
    }
    
    public function update_synonyms($collection) {
        $typesense = $this->get_admin_client();
    
        if (!function_exists('get_field')) {
            WP_CLI::error("ACF is not installed or activated.");
            return;
        }
    
        // Ensure Typesense is initialized
        if (!isset($typesense) || empty($typesense)) {
            WP_CLI::error("Typesense is not properly initialized.");
            return;
        }
    
        try {
            // Retrieve available collections
            $collections = $typesense->collections->retrieve();
            $collection_names = array_column($collections, 'name');
    
            // Ensure the given collection exists
            if (!in_array($collection, $collection_names, true)) {
                WP_CLI::error("The specified collection '{$collection}' does not exist in Typesense. Available collections: " . implode(', ', $collection_names));
                return;
            }
        } catch (Exception $e) {
            WP_CLI::error("Failed to retrieve collections from Typesense: " . $e->getMessage());
            return;
        }
    
        // Get synonyms from ACF options page
        $acf_synonyms = get_field('synonyms', 'option');

    
        if (!is_array($acf_synonyms) || empty($acf_synonyms)) {
            WP_CLI::line("No synonyms found in ACF.");
            return;
        }
    
        $synonym_mappings = [];
    
        foreach ($acf_synonyms as $synonym_entry) {
            // Ensure 'Synonym' key exists
            if (!isset($synonym_entry['Synonym']) || empty(trim($synonym_entry['Synonym']))) {
                continue;
            }
    
            // Convert comma-separated string into an array
            $synonyms = array_map('trim', explode(',', $synonym_entry['Synonym']));
    
            if (count($synonyms) < 2) {
                // Ignore entries that don't have at least two synonyms
                continue;
            }
    
            // Generate a unique ID from the first synonym
            $id = strtolower(str_replace(' ', '_', $synonyms[0]));
    
            // Create a synonym mapping
            $synonym_mappings[] = [
                "id" => $id,
                "root" => '', // First synonym is the root
                "synonyms" => $synonyms, // Full list of synonyms
            ];
        }
    
        if (empty($synonym_mappings)) {
            WP_CLI::line("No valid synonyms to upload.");
            return;
        }
    
        WP_CLI::line("Uploading synonyms to Typesense...");
    
        foreach ($synonym_mappings as $synonym) {
            try {
                $typesense->collections[$collection]->synonyms->upsert($synonym['id'], $synonym);
                WP_CLI::success("Synonym added: " . implode(', ', $synonym['synonyms']));
            } catch (Exception $e) {
                WP_CLI::warning("Failed to add synonym: " . $synonym['root'] . " - " . $e->getMessage());
            }
        }
    }

    public function reindex($args, $assoc_args)
{
    $specified_post_type = $assoc_args['c'] ?? null;
    $this->init_collections(array(), array('c' => $specified_post_type));
    $typesense = $this->get_admin_client();

    if (!is_object($typesense)) {
        WP_CLI::error('Typesense admin client is not configured. Add the Typesense Admin API Key before running reindex.');
        return;
    }

    $full_count = 0;

    foreach ($this->types as $type => $fields) {
        if ($specified_post_type && $type !== $specified_post_type) {
            continue;
        }

        $collection = $this->get_collection_name($type);
        $this->update_synonyms($collection);

        $paged = 1;
        $total_sent = 0;
        $batch_size = 100; // Ensure batch size remains consistent
        $start_index = 0; // Initialize batch index for logging

        do {
            // Ensure correct pagination
            $query = new WP_Query([
                'posts_per_page' => $batch_size,
                'offset' => ($paged - 1) * $batch_size, // Explicit offset to avoid duplicates
                'post_type' => $type,
                'post_status' => 'publish',
                'orderby' => 'ID',
                'order' => 'ASC',
                'no_found_rows' => true,
                'suppress_filters' => false, // Ensure no unintended filtering
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);

            $num_posts = count($query->posts);
            if (!$query->have_posts() || $num_posts === 0) {
                WP_CLI::warning("No more posts found for $type at batch $start_index-".($start_index + $batch_size - 1).".");
                break;
            }

            WP_CLI::line("Batch $start_index-" . ($start_index + $batch_size - 1) . " - Page: $paged - Fetched $num_posts posts for indexing.");

            $records = [];
            foreach ($query->posts as $post) {
                $record = function_exists('kosher_typesense_normalize_record_for_index') ? kosher_typesense_normalize_record_for_index(typesense_post_to_record($post)) : typesense_post_to_record($post);

                // Ensure record is not empty
                if (empty($record) || !isset($record['objectID'])) {
                    WP_CLI::warning("Skipping post #{$post->ID} due to missing data.");
                    continue;
                }

                $records[] = $record;
                $full_count++;
            }

            // If no valid records, move to the next batch
            $num_records = count($records);
            if ($num_records === 0) {
                WP_CLI::warning("No valid records to index in batch $start_index-" . ($start_index + $batch_size - 1) . ".");
                $paged++;
                $start_index += $batch_size;
                continue;
            }

            WP_CLI::line("Batch $start_index-" . ($start_index + $batch_size - 1) . " - Sending $num_records records to Typesense...");

            try {
                $resp = $typesense->collections[$collection]->documents->import($records, ['action' => 'upsert']);

                // Count successful imports
                $successful_imports = count(array_filter($resp, fn($r) => isset($r['success']) && $r['success']));
                $total_sent += $successful_imports;

                WP_CLI::success("Batch $start_index-" . ($start_index + $batch_size - 1) . " - Successfully indexed $successful_imports / $num_records records.");
                
                // Display cumulative total sent so far
                WP_CLI::line("Records sent so far: $total_sent");

                // Log failed records
                foreach ($resp as $index => $result) {
                    if (isset($result['success']) && !$result['success']) {
                        WP_CLI::warning("Batch $start_index-" . ($start_index + $batch_size - 1) . " - Failed record: " . json_encode($records[$index]));
                        WP_CLI::warning("Batch $start_index-" . ($start_index + $batch_size - 1) . " - Error: " . json_encode($result));
                    }
                }
            } catch (Exception $e) {
                WP_CLI::error("Batch $start_index-" . ($start_index + $batch_size - 1) . " - Failed to import: " . $e->getMessage());
            }

            // Flush cache every 5 batches to free memory
            if (($start_index / $batch_size) % 5 === 0) {
                wp_cache_flush();
                gc_collect_cycles();
            }

            $paged++;
            $start_index += $batch_size;

        } while ($query->have_posts());

        WP_CLI::success("Total: $total_sent $type entries indexed in Typesense.");
    }

    WP_CLI::success("Reindexing completed. Total records indexed: $full_count");
}

    public function update_schema($args, $assoc_args)
{
    $typesense = $this->get_admin_client();

    if (!is_object($typesense)) {
        WP_CLI::error('Typesense admin client is not configured. Add the Typesense Admin API Key before running update_schema.');
        return;
    }
    $specified_post_type = $assoc_args['c'] ?? null;

    WP_CLI::line('Updating Typesense Schemas (without deleting records)...');

    foreach ($this->types as $type => $fields) {
        if ($specified_post_type && $type !== $specified_post_type) {
            continue;
        }

        $collection = $this->get_collection_name($type);

        $schema_update = [
            'fields' => $fields,
        ];

        try {
            $typesense->collections[$collection]->update($schema_update);
            WP_CLI::success("Schema updated for {$collection}");
        } catch (Exception $e) {
            WP_CLI::warning("Could not update {$collection}: " . $e->getMessage());
        }
    }

    WP_CLI::success('Schema update process complete (no records deleted).');
}

    
}

class Kosher_Typesense_Collection_Plan_Command
{
    /**
     * Show the active Typesense collection targets without writing anything.
     *
     * ## OPTIONS
     *
     * [--c=<post_type>]
     * : Limit the output to one post type collection.
     *
     * ## EXAMPLES
     *
     *     wp typesense-collection-plan
     *     wp typesense-collection-plan --c=recipes
     */
    public function __invoke($args = array(), $assoc_args = array())
    {
        $command = new Kosher_Typesense_Command();
        $command->collection_plan($args, $assoc_args);
    }
}

WP_CLI::add_command('kosher-typesense', 'Kosher_Typesense_Command');
WP_CLI::add_command('typesense', 'Kosher_Typesense_Command');
WP_CLI::add_command('kosher-typesense-collection-plan', 'Kosher_Typesense_Collection_Plan_Command');
WP_CLI::add_command('typesense-collection-plan', 'Kosher_Typesense_Collection_Plan_Command');
