<?php
function kosher_typesense_get_menu_builder_data($post_id)
{
	$stored_value = get_post_meta($post_id, '_menus_builder_data', true);

	if (is_string($stored_value) && $stored_value !== '') {
		$decoded = json_decode($stored_value, true);
		return is_array($decoded) ? $decoded : array();
	}

	return is_array($stored_value) ? $stored_value : array();
}

function kosher_typesense_get_menu_image_url($builder_data, $post_id)
{
	if (!empty($builder_data['settings']['hero']['image']) && !empty($builder_data['settings']['hero']['type']) && $builder_data['settings']['hero']['type'] === 'upload') {
		$image = $builder_data['settings']['hero']['image'];

		if (is_numeric($image)) {
			$url = wp_get_attachment_image_url(absint($image), 'medium_large');
			if ($url) {
				return $url;
			}
		}

		if (is_string($image) && $image !== '') {
			return esc_url_raw($image);
		}
	}

	if (has_post_thumbnail($post_id)) {
		$url = get_the_post_thumbnail_url($post_id, 'medium_large');
		if ($url) {
			return $url;
		}
	}

	if (empty($builder_data['sections']) || !is_array($builder_data['sections'])) {
		return '';
	}

	foreach ($builder_data['sections'] as $section) {
		if (empty($section['cards']) || !is_array($section['cards'])) {
			continue;
		}

		foreach ($section['cards'] as $card) {
			if (!empty($card['image'])) {
				if (is_numeric($card['image'])) {
					$url = wp_get_attachment_image_url(absint($card['image']), 'medium_large');
					if ($url) {
						return $url;
					}
				} elseif (is_string($card['image'])) {
					return esc_url_raw($card['image']);
				}
			}

			if (!empty($card['post_id'])) {
				$url = get_the_post_thumbnail_url(absint($card['post_id']), 'medium_large');
				if ($url) {
					return $url;
				}
			}
		}
	}

	return '';
}

function kosher_typesense_normalize_title_sort($title)
{
	$title = wp_strip_all_tags((string) $title);
	$charset = get_bloginfo('charset');
	$title = html_entity_decode($title, ENT_QUOTES, $charset ? $charset : 'UTF-8');
	$title = preg_replace('/[0-9"\x{201C}\x{201D}\x{201E}\x{201F}\x{2033}]+/u', '', $title);
	$title = preg_replace('/\s+/', ' ', (string) $title);
	$title = trim((string) $title);

	if (function_exists('mb_strtolower')) {
		return mb_strtolower($title);
	}

	return strtolower($title);
}

function kosher_typesense_menu_to_record(WP_Post $post)
{
	$builder_data = kosher_typesense_get_menu_builder_data($post->ID);
	$settings     = isset($builder_data['settings']) && is_array($builder_data['settings']) ? $builder_data['settings'] : array();
	$sections     = isset($builder_data['sections']) && is_array($builder_data['sections']) ? $builder_data['sections'] : array();

	$title       = !empty($settings['title']) ? (string) $settings['title'] : (string) get_the_title($post);
	$description = !empty($settings['description']) ? (string) $settings['description'] : (string) get_the_excerpt($post);
	$categories  = array();
	$terms       = get_the_terms($post->ID, 'menus_categories');

	if (!empty($terms) && !is_wp_error($terms)) {
		foreach ($terms as $term) {
			$categories[] = $term->name;
		}
	}

	$section_titles = array();
	$recipe_titles  = array();
	$card_text      = array();
	$cards_count    = 0;

	foreach ($sections as $section) {
		if (!is_array($section)) {
			continue;
		}

		if (!empty($section['title'])) {
			$section_titles[] = (string) $section['title'];
		}

		if (!empty($section['description'])) {
			$card_text[] = (string) $section['description'];
		}

		if (empty($section['cards']) || !is_array($section['cards'])) {
			continue;
		}

		$cards_count += count($section['cards']);

		foreach ($section['cards'] as $card) {
			if (!is_array($card)) {
				continue;
			}

			if (!empty($card['title'])) {
				$card_text[] = (string) $card['title'];
			}

			if (!empty($card['content'])) {
				$card_text[] = (string) $card['content'];
			}

			if (!empty($card['post_id'])) {
				$recipe_titles[] = get_the_title(absint($card['post_id']));
			}
		}
	}

	$author = get_user_by('ID', $post->post_author);

	return array(
		'id'                 => (string) $post->ID,
		'postID'             => (int) $post->ID,
		'objectID'           => implode('#', array($post->post_type, $post->ID)),
		'title'              => wp_strip_all_tags($title),
		'title_sort'         => kosher_typesense_normalize_title_sort($title),
		'title_words_length' => mb_strlen(str_replace(' ', '', wp_strip_all_tags($title))),
		'description'        => wp_strip_all_tags($description),
		'type'               => 'menus',
		'author_id'          => (int) $post->post_author,
		'author_name'        => $author ? $author->display_name : '',
		'categories'         => array_values(array_unique(array_filter($categories))),
		'section_titles'     => array_values(array_unique(array_filter(array_map('wp_strip_all_tags', $section_titles)))),
		'recipe_titles'      => array_values(array_unique(array_filter(array_map('wp_strip_all_tags', $recipe_titles)))),
		'card_text'          => array_values(array_unique(array_filter(array_map('wp_strip_all_tags', $card_text)))),
		'privacy'            => !empty($settings['privacy']) ? sanitize_key($settings['privacy']) : 'private',
		'sections_count'     => count($sections),
		'cards_count'        => (int) $cards_count,
		'image'              => kosher_typesense_get_menu_image_url($builder_data, $post->ID),
		'url'                => get_permalink($post),
		'date'               => strtotime($post->post_date_gmt ?: $post->post_date),
	);
}

function typesense_post_to_record($post)
{
	if ($post instanceof WP_Post && $post->post_type === 'menus') {
		return kosher_typesense_menu_to_record($post);
	}

	$tags = array_map(function ($term) {
		return $term->name;
	}, wp_get_post_terms($post->ID, 'post_tag'));

	$comments_count = wp_count_comments($post->ID)->approved;

	$ratings_sum = get_post_meta($post->ID, 'rmp_rating_val_sum', true);
	$vote_count  = get_post_meta($post->ID, 'rmp_vote_count', true);
   
	   if ($ratings_sum >= 1 && $vote_count >= 1) {
		   $rating = round(($ratings_sum / $vote_count), 1);
	   } else {
		   $rating = 0; // Set to 0 if conditions aren't met
	   }


	$total_likes = function_exists('kayco_get_post_favorite_count')
		? (int) kayco_get_post_favorite_count($post->ID)
		: 0;

	$recipe_category_names = [];

	$recipe_categories = get_field('recipe_category', $post->ID);

	if (is_array($recipe_categories)) {
		foreach ($recipe_categories as $recipe_category_id) {
			$term = get_term($recipe_category_id);

			if (!is_wp_error($term) && $term) {
				$recipe_category_names[] = $term->name;
			}
		}
	} else {
		$recipe_category_names = [];
	}

    $article_sub_category = get_field('article_sub_category', $post->ID);

    $article_sub_category_names = [];
    
    if (!empty($article_sub_category) && is_array($article_sub_category)) {
        foreach ($article_sub_category as $term) {
            if (is_object($term) && isset($term->name)) {
                $article_sub_category_names[] = $term->name;
            } elseif (is_numeric($term)) {
                $term_obj = get_term($term);
                if ($term_obj && !is_wp_error($term_obj)) {
                    $article_sub_category_names[] = $term_obj->name;
                }
            } elseif (is_string($term)) {
                $article_sub_category_names[] = $term;
            }
        }
    }
    
    // Replace the original field with a clean array of strings
    $record['article_sub_category'] = $article_sub_category_names;
    
	

	$cuisine_names = [];

	$cuisines = get_field('cuisine', $post->ID); // Fetch the ACF field
	
	if (!empty($cuisines) && is_array($cuisines)) {
		foreach ($cuisines as $cuisine) {
			if (is_object($cuisine)) {
				$cuisine_names[] = $cuisine->name;
			}
		}
	}


	$recipe_allergents_names = [];

	$recipe_allergents = get_field('contains_allergents', $post->ID);

	if (is_array($recipe_allergents)) {
		foreach ($recipe_allergents as $allergent_id) {
			$term = get_term($allergent_id);

			if (!is_wp_error($term) && $term) {
				$recipe_allergents_names[] = $term->name;
			}
		}
	} else {
		$recipe_allergents_names = [];
	}






	$recipe_diets_names = [];

	$recipe_diets = get_field('diets', $post->ID);

	if (is_array($recipe_diets)) {
		foreach ($recipe_diets as $recipe_diets_id) {
			$term = get_term($recipe_diets_id);

			if (!is_wp_error($term) && $term) {
				$recipe_diets_names[] = $term->name;
			}
		}
	} else {
		$recipe_diets_names = [];
	}



	// Initialize a variable to store Holiday taxonomy names
	$holiday_names = [];

	// Retrieve the serialized array from the 'occasions' post meta field
	$occasions = maybe_unserialize(get_post_meta($post->ID, 'occasions', true));

	// Ensure it's an array and not empty
	if (is_array($occasions)) {
		foreach ($occasions as $holiday_id) {
			// Get the term object for each Holiday ID
			$term = get_term($holiday_id);

			// Ensure the term is valid and not an error
			if (!is_wp_error($term) && $term) {
				// Add the term name to the variable
				$holiday_names[] = $term->name;
			}
		}
	} else {
		// If no holidays are found, initialize as an empty array
		$holiday_names = [];
	}

	// Now you can use $holiday_names as needed








	if (!empty(get_field('show_id', $post->ID))) {
		// Retrieve the show ID
		$show = get_field('show_id', $post->ID);

		// Get the chef field (ensure it returns an array)
		$chef_field = get_field('chef', $show);

		if (is_array($chef_field) && !empty($chef_field['ID'])) {
			$chef = $chef_field['ID'];

			// Get the chef's slug or set a default if not found
			$show_chef_slug = get_user_meta($chef, "slug", true) ?: "user";

			// Construct the episode chef URL
			$episode_chef_url = get_home_url() . '/user/' . $show_chef_slug;
		} else {
			// Fallback if the chef is not found
			$episode_chef_url = '';
		}
	} else {
		// Fallback if 'show_id' is not found
		$episode_chef_url = '';
	}





		// Check if the show ID exists
		if (!empty(get_field('show_id', $post->ID))) {
			// Retrieve the show ID
			$show = get_field('show_id', $post->ID);
			// Get the array of chef IDs associated with the show
			$chefs_id = get_field('chef', $show);

			$chefs_id  = $chefs_id;
		}else {
			// Fallback if 'show_id' is not found
			$chefs_id = '';
		}





	$author_article_id = get_field("author", $post->ID);

	// Ensure the field is an array and contains at least one element
	if (!empty($author_article_id)) {
		$user_info  = get_userdata($author_article_id);
		$author_article_slug  = $user_info->user_nicename;

		// Get user data
		$user_roles = $user_info->roles;
	
		// Set the base path based on role
		$base_path = in_array('Chef', $user_roles) ? 'chef' : 'user';
	
		$author_article_url = get_home_url() . '/' . $base_path . '/' . $author_article_slug;
	} else {
		$author_article_url = '';
	}




	(int) @get_post_meta($post->ID, 'cook_time_minutes')[0];


	$chef_field = get_field("chefs", $post->ID);


	// Ensure the field is an array and contains at least one element
	if (is_array($chef_field) && !empty($chef_field)) {
		$chef_id = $chef_field[0];
		$user_info  = get_userdata($chef_id['ID']);
		$chef_slug  = $chef_id['user_nicename'];

		// Get user data
		$user_roles = $user_info->roles;
	
		// Set the base path based on role
		$base_path = in_array('Chef', $user_roles) ? 'chef' : 'user';
	
		$chef_url = get_home_url() . '/' . $base_path . '/' . $chef_slug;
	}else{
		$chef_url = '';
	}



	// Ensure that the user object is valid
	if ($post->post_author) {
		$post_author_id = $post->post_author;
		$user_slug = get_user_meta($post_author_id, "slug", true);
	
		if ($user_slug == '') {
			$user_slug = "user";
		}
	
		// Get user data
		$user_info = get_userdata($post_author_id);
		$user_roles = $user_info->roles ?? [];
	
		// Set the base path based on role
		$base_path = in_array('chef', $user_roles) ? 'chef' : 'user';
	
		$user_url = get_home_url() . '/' . $base_path . '/' . $user_slug;
	} else {
		// Fallback if the user is not found
		$user_url = '';
	}
	

	switch ($post->post_type) {
		case 'shows':
			$fields = array('short_description', 'chef', 'published_at');
			break;
		case 'articles':
			$fields = array('article_sub_category', 'tags', 'author', 'date', 'views', 'shares', "app_published_at", 'published_at');
			break;
		case 'episodes':
			$fields = array('episode_number', 'tags', 'show_id', 'show_url', 'published_at', 'video_duration', 'chefID', 'archived_show');
			break;
		default:
			$fields = array(
				'chefs',
				'recipe_category',
				'cook_time',
				'serving',
				'contains_allergents',
				'difficulty',
				'blessing_type',
				'occasions',
				'diets',
				'sources',
				'ingredients',
				'sources',
				'published_at',
			);
			break;
	}

	foreach ($fields as $field) {

		$elements = get_post_meta($post->ID, $field);

		if ($field === "chefs") {
			if (isset($elements[0]) && (is_object($elements[0]) || is_array($elements[0]) || is_countable($elements[0]))) {
				if (count($elements[0]) == 0) {
					$values[$field][] = "Member recipes";
				}
			}
		}
		//checking if field is array

		if (isset($elements[0]) && is_array($elements[0])) {
			foreach ($elements[0] as $element) {
				switch ($field) {
					case 'chefs':
						if ((count([$element[0]]) >= 1)) {
							$chef               = get_user_by('ID', intval($element));
							$name               = @$chef->display_name;
							$values[$field][] = $name;
						}
					break;

							
					case 'recipe_category':
					$recipe_categories = get_field('recipe_category', $post->ID);
					if (is_array($recipe_categories)) {
						foreach ($recipe_categories as $recipe_category_id) {
							$term = get_term($recipe_category_id);
							if (!is_wp_error($term) && $term) {
								$values[$field][] = $term->name;
							}
						}
					} else {
						$values[$field] = [];
					}
					break;
						

					case 'sources':
						$source                 = get_post_meta($post->ID, 'sources');
						$id                     = @$source[0][0];
						$image                  = get_term_meta($id, 'image');
						$name                   = get_term($id)->name;
						$values['source_image'] = wp_get_attachment_url(@$image[0]);
						$values[$field]       = $name;
						break;


				case 'show_id':
					$show           = get_field('show_id', $post->ID);
					$values['show'] = $show['post_title'];
					$values['show_url'] = get_the_permalink($show['ID']);
					$show_chefs = get_field('chef', $show['ID']);
					$values['chef'] = array();

					if (is_array($show_chefs)) {
						foreach ($show_chefs as $show_chef) {
							if (is_array($show_chef) && isset($show_chef['ID'])) {
								$chef_id = $show_chef['ID'];
							} elseif (is_object($show_chef) && isset($show_chef->ID)) {
								$chef_id = $show_chef->ID;
							} else {
								$chef_id = $show_chef;
							}

							$chef = get_user_by('ID', (int) $chef_id);

							if ($chef && $chef->display_name) {
								$values['chef'][] = $chef->display_name;
							}
						}
					}

					if (empty($values['chef'])) {
						$values['chef'][] = 'Kosher.com';
					}
					break;


	

					case 'published_at':
						$date = get_field('published_at', $post->ID);
						if (!isset($record['date'])) {
							if (is_null($date) || $date === false) {
								$date = get_the_date('c', $post->ID);
							}
						}
						$values['date'] = strtotime($date);
						break;

					case 'chef':
						$id             = @get_field('chef', $post->ID)[0];
						$chef           = get_user_by('ID', $id);
						$values['chef'] = $chef->display_name;
						break;

    
				}
			}
		} else {
			switch ($field) {
				case "chefs":
					$values[$field][] = "Member recipes";
					break;
				case "views":
					$views            = get_field("views", $post->ID);
					$values[$field] = (int) $views;
					break;
				case "shares":
					$shares           = get_field("shares", $post->ID);
					$values[$field] = (int) $shares;
					break;
				case 'published_at':
					$date = get_field('published_at', $post->ID);
					if (!isset($record['date'])) {
						if (is_null($date) || $date === false) {
							$date = get_the_date('c', $post->ID);
						}
					}
					$values['date'] = strtotime($date);
					break;
				
				case  'blessing_type':	
			break;

                case 'tags':
                $values[$field] = $tags;
                break;
                

				case 'cook_time':
					$hours            = get_post_meta($post->ID, 'cook_time_hours');
					$minutes          = get_post_meta($post->ID, 'cook_time_minutes');
					$hours            = (int) @$hours[0] * 60;
					$minutes          = (int) @$minutes[0];
					$values[$field] = (int) $hours + $minutes;
					break;
                    
                    case 'ingredients':
                        $ingredients_group = get_field("ingredients", $post->ID);
                    
                        if (is_array($ingredients_group)) {
                            foreach ($ingredients_group as $ingredient_element) {
                                $ingredients = $ingredient_element['ingredients'] ?? null;
                    
                                if (is_array($ingredients)) {
                                    foreach ($ingredients as $ingredient) {
                                        $ingredient1 = $ingredient['ingredient'] ?? null;
                    
                                        if ($ingredient1 && is_object($ingredient1) && isset($ingredient1->name)) {
                                            // Add only valid names
                                            $values[$field][] = $ingredient1->name;
                                        }
                                    }
                                }
                            }
                        }
                        break;


				case 'published_at':
					$date = get_field('published_at', $post->ID);
					if (!isset($record['date'])) {
						if (is_null($date) || $date === false) {
							$date = get_the_date('c', $post->ID);
						}
					}
					$values['date'] = strtotime($date);
					break;

				case  'author':
					$author           = get_field($field, $post->ID);
					$author           = get_user_by("id", $author);
					$values[$field] = @$author->display_name;
					break;

					/*
				case 'date':
					$date             = $post->post_date;
					$values[$field] = strtotime($date);
					break;
				*/

				case 'episode_number':
					$values[$field] = (int) get_field($field, $post->ID);
					break;


				case 'video_duration':
					$values[$field] = get_field($field, $post->ID);
					break;

		
				case 'chefID':
					// Get the show ID associated with the post
					$show_id = get_field('show', $post->ID);

					// Check if the show ID exists
					if ($show_id) {
						// Get the array of chef IDs associated with the show
						$chefs_id = get_field('chef', $show_id);
						// Ensure it's an array and process each chef ID
						if (is_array($chefs_id)) {
							foreach ($chefs_id as $chef_id) {
								// Store each chef's ID in the 'chef_id' field
								$values['chefID'][] = $chef_id;
							}
						} else {
							// If chef_id is not an array, store the single chef ID
							$values['chefID'][] = $chefs_id;
						}
					}
				break;
					

				case 'show_id':
					$show           = get_field('show_id', $post->ID);
					$values['show'] = get_post($show)->post_title;
					$show_chefs = get_field('chef', get_post($show)->ID);
					$values['chef'] = array();

					if (is_array($show_chefs)) {
						foreach ($show_chefs as $show_chef) {
							if (is_array($show_chef) && isset($show_chef['ID'])) {
								$chef_id = $show_chef['ID'];
							} elseif (is_object($show_chef) && isset($show_chef->ID)) {
								$chef_id = $show_chef->ID;
							} else {
								$chef_id = $show_chef;
							}

							$chef = get_user_by('ID', (int) $chef_id);

							if ($chef && $chef->display_name) {
								$values['chef'][] = $chef->display_name;
							}
						}
					}

					if (empty($values['chef'])) {
						$values['chef'][] = 'Kosher.com';
					}
					break;

				case 'chef':
					$chef_ids = get_field('chef', $post->ID);
					$values['chef'] = array();

					if (is_array($chef_ids)) {
						foreach ($chef_ids as $chef_id) {
							if (is_array($chef_id) && isset($chef_id['ID'])) {
								$chef_id = $chef_id['ID'];
							} elseif (is_object($chef_id) && isset($chef_id->ID)) {
								$chef_id = $chef_id->ID;
							}

							$chef = get_user_by('ID', (int) $chef_id);

							if ($chef && $chef->display_name) {
								$values['chef'][] = $chef->display_name;
							}
						}
					}
					break;


				case "sources":
					$sources                = get_field("sources", $post->ID);
					$values[$field]       = (string) @$sources->name;
					$values['source_image'] = wp_get_attachment_url(@get_term_meta($sources->term_id, "image")[0]);
					break;

				default:
					$values[$field] = $elements;
					break;
			}
		}
	}


	$author_display_name = @get_user_by('ID', $post->post_author)->display_name;
	$preference_term = get_field('preference', $post->ID);
	$preference_name = is_object($preference_term) && isset($preference_term->name) ? (string) $preference_term->name : '';

	$data = [
		'id' => (string) $post->ID,
		'postID' => $post->ID,
		'objectID' => implode('#', [$post->post_type, $post->ID]),
		'title'    => (string) $post->post_title,
		'title_sort' => kosher_typesense_normalize_title_sort($post->post_title),
		'title_words_length' => mb_strlen(str_replace(' ', '', $post->post_title)),
		'type'     => (string) $post->post_type,
		'author'   => $author_display_name ?: '',
		'archived_show' =>  get_field('archived_show', $post->ID),
		'author_id' => $post->post_author,
		'author_name' => $author_display_name ?: '',
		'tags'     => $tags,
		'article_sub_category' => $article_sub_category_names,
		'recipe_category' => $recipe_category_names,
		'cuisine' => $cuisine_names,
		'contains_allergents' => $recipe_allergents_names,
		'preference' => $preference_name,
		'diets' => $recipe_diets_names,
		'occasions' => $holiday_names,
		'image'    => get_the_post_thumbnail_url($post) ?: "https://images.kosher.com/uploads/no-image-recipe.png",
		'comments_total' => $comments_count,
		'likes' => $total_likes,
		'rating'  => $rating, // Add the average rating  to the data
		'author_article_url' => $author_article_url,
		'chef_url'  => 	$chef_url,
		'episode_chef_url' => $episode_chef_url,
		'user_url'  => 	$user_url,
		'chefID' => $chefs_id,
		'featured_recipe' => (int) (!empty(get_field('featured_recipe', $post->ID))) ? 1 : 0,
	];

	$link = get_post_permalink($post->ID);

	$end_point = explode('/' . $post->post_type, $link);
	switch ($post->post_type) {
		case "articles":
			$post_type = "article";
			break;
		case "shows":
			$post_type = "shows";
			break;
		case "recipes":
			$post_type = "recipe";
			break;
		default:
			$post_type = "video";
			break;
	}

	$data["url"] = "https://www.kosher.com/" . $post_type . "/" . $post->post_name;

	if ($post->post_type === 'recipes') {
		$community_recipes = get_post_meta($post->ID, 'is_user_recipe');
		if (isset($community_recipes[0]) && $community_recipes[0] === '1') {
			$data['type'] = 'community_recipes';
		} else {
			$data['type'] = 'chef_recipes';
		}

		$data['community-recipe']  = false;
		$community_recipe = get_post_meta($post->ID, 'is_user_recipe');
		if (isset($community_recipe[0]) && ($community_recipe[0] == true || $community_recipe[0] == 1 || $community_recipe[0] == "1")) {
			$data['community-recipe'] = true;
		}
		
		$data['user_consent_public'] = false;
		$user_consent_public = get_post_meta($post->ID, 'user_consent_public');
		if (isset($user_consent_public[0]) && ($user_consent_public[0] == true || $user_consent_public[0] == 1 || $user_consent_public[0] == "1")) {
			$data['user_consent_public'] = true;
		}
		$data['hours']               = (int) @get_post_meta($post->ID, 'cook_time_hours')[0];
		$data['minutes']             = (int) @get_post_meta($post->ID, 'cook_time_minutes')[0];
		$data["source_image"]        = @wp_get_attachment_url(@get_term_meta(get_field("sources", $post->ID)->term_id, "source_image")[0]);
	}


	return array_merge($data, $values);
}

add_filter('recipes_to_record', 'typesense_post_to_record');
add_filter('articles_to_record', 'typesense_post_to_record');
add_filter('episodes_to_record', 'typesense_post_to_record');
add_filter('shows_to_record', 'typesense_post_to_record');
add_filter('menus_to_record', 'typesense_post_to_record');


function kosher_typesense_write_client()
{
	if (function_exists('ap_typesense_synonyms')) {
		$client = ap_typesense_synonyms();

		if (is_object($client)) {
			return $client;
		}
	}

	return null;
}

function kosher_typesense_normalize_record_for_index($record)
{
	if (!is_array($record)) {
		return array();
	}

	if (isset($record['author']) && is_array($record['author'])) {
		$record['author'] = isset($record['author']['name']) ? (string) $record['author']['name'] : '';
	}

	foreach ($record as $key => $value) {
		if (is_object($value)) {
			unset($record[$key]);
			continue;
		}

		if (is_array($value)) {
			$clean_items = array();

			foreach ($value as $item) {
				if (is_array($item) || is_object($item) || $item === null || $item === false || $item === '') {
					continue;
				}

				$clean_items[] = is_string($item) ? $item : (string) $item;
			}

			$record[$key] = array_values($clean_items);
		}
	}

	return $record;
}


function typesense_get_doc_id($post)
{
	if (isset($post->ID)) {
		return $post->ID;
	}
	return false;
	/*
	global $typesense;

	$searchParameters = [
		'q'         => '',
		'query_by'  => 'title',
		'filter_by' => 'postID:' . $post->ID,
	];
	
	$resp = $typesense->collections[env('TYPESENSE_COLLECTION_PREFIX').$post->post_type]->documents->search($searchParameters);

	$ids = [];
	if (isset($resp['hits'])) {
		foreach ($resp['hits'] as $hit) {
			$ids[] = $hit['document']['id'];
		}
	}
	if ($resp['found'] > 1) {
		return $ids;
	} else {
		return end($ids);	
	}
	*/
}

function typesense_update_record($post_id, $data)
{
	$typesense = kosher_typesense_write_client();

	if (!is_object($typesense)) {
		return;
	}

	// Retrieve the post to get its type
	$post = get_post($post_id);
	if (!$post) {
		return;
	}

	// Generate the document ID based on the post
	$doc_id = typesense_get_doc_id($post);

	// Check if the document ID is valid
	if ($doc_id !== false) {
		try {
			// Update the record in the corresponding Typesense collection
			$collection = function_exists('kosher_typesense_collection_name') ? kosher_typesense_collection_name($post->post_type) : env('TYPESENSE_COLLECTION_PREFIX') . $post->post_type;
			$typesense->collections[$collection]->documents->update([
				'id' => (string) $doc_id,
				'comments_total' => $data['comments_total'],
				// Add more fields here if needed
			]);
		} catch (Exception $e) {
			// Handle the exception (optional)
			// var_dump($e);
			// exit();
		}
	}
}

function update_typesense_rating($post_id, $data)
{
	$typesense = kosher_typesense_write_client();

	if (!is_object($typesense)) {
		return;
	}

	// Retrieve the post object to determine the post type
	$post = get_post($post_id);
	if (!$post) {
		return;
	}

	// Generate the document ID based on the post
	$doc_id = typesense_get_doc_id($post);

	// Check if the document ID exists
	if ($doc_id !== false) {
		try {
			// Update the record in Typesense
			$collection = function_exists('kosher_typesense_collection_name') ? kosher_typesense_collection_name($post->post_type) : env('TYPESENSE_COLLECTION_PREFIX') . $post->post_type;
			$typesense->collections[$collection]->documents->update([
				'id' => (string) $doc_id,
				'rating' => $data['rating'],
			]);
		} catch (Exception $e) {
			// Handle exception if the Typesense update fails
			// You can log the error or display it for debugging
			error_log('Typesense update failed: ' . $e->getMessage());
		}
	} else {
		// Handle the case where the document ID could not be found
		error_log('Typesense document ID not found for post ID: ' . $post_id);
	}
}



//automatic post update
function typesense_update_post($id, WP_Post $post, $update) {
    // Skip revisions and autosaves
    if (wp_is_post_revision($id) || wp_is_post_autosave($id)) {
        return $post;
    }

    $typesense = kosher_typesense_write_client();

    if (!is_object($typesense)) {
        return $post;
    }

    $collection = function_exists('kosher_typesense_collection_name') ? kosher_typesense_collection_name($post->post_type) : env('TYPESENSE_COLLECTION_PREFIX') . $post->post_type;
    $doc_id     = typesense_get_doc_id($post);

    // Only handle published posts
    if ($post->post_status === 'publish') {

	        $record = kosher_typesense_normalize_record_for_index((array) typesense_post_to_record($post));
			error_log('POST STATUS SENT: ' . $post->post_status );

        try {
            $typesense->collections[$collection]->documents->upsert($record);
            error_log("[Typesense] Upserted post ID {$id} ({$post->post_type})");
        } catch (Exception $e) {
            error_log("[Typesense] Upsert failed for post ID {$id}: " . $e->getMessage());
        }

    } else {
        // For any non-published post, ensure it is removed if it exists in Typesense
        if ($doc_id !== false) {
            try {
                $typesense->collections[$collection]->documents[$doc_id]->delete();
                error_log("[Typesense] Removed post ID {$id} ({$post->post_status}) from Typesense");
            } catch (Exception $e) {
                error_log("[Typesense] Delete failed for post ID {$id}: " . $e->getMessage());
            }
        }
    }
	

    return $post;
}

add_action('save_post', 'typesense_update_post', 10, 3);



add_action('acf/save_post', 'kosher_update_typesense_synonyms', 20);

function kosher_update_typesense_synonyms($post_id)
{
    if ($post_id !== 'options') return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    error_log('[Typesense Synonyms] Sync start');

    if (!function_exists('get_field')) {
        error_log('[Typesense Synonyms] ACF not available');
        return;
    }

    // ✅ ACF FIELD
    $acf_synonyms = get_field('synonyms', 'option');

    if (empty($acf_synonyms) || !is_array($acf_synonyms)) {
        error_log('[Typesense Synonyms] No synonyms found');
        return;
    }

    /**
     * --------------------------------------------------
     * ✅ ENV CONFIG
     * --------------------------------------------------
     */

    $raw_host = function_exists('kosher_typesense_host') ? kosher_typesense_host() : env('TYPESENSE_API_HOST');
    $api_key  = function_exists('kosher_typesense_admin_api_key') ? kosher_typesense_admin_api_key() : env('TYPESENSE_ADMIN_KEY');

    if (empty($raw_host) || empty($api_key)) {
        error_log('[Typesense Synonyms] Missing Typesense admin config');
        return;
    }

    // Clean host
    $host = trim($raw_host);
    $host = preg_replace('#^https?://#', '', $host);
    $host = rtrim($host, '/');

    $protocol = function_exists('kosher_typesense_protocol') ? kosher_typesense_protocol() : env('TYPESENSE_API_PROTOCOL');

    /**
     * --------------------------------------------------
     * 🔁 BUILD SYNONYM SET ITEMS
     * --------------------------------------------------
     */
    $items = [];

    foreach ($acf_synonyms as $index => $row) {

        $words = $row['Synonym'] ?? null;

        if (empty($words)) {
            continue;
        }

        // Convert to array
        $words = explode(',', $words);

        // Normalize
        $words = array_map(function ($w) {
            return strtolower(trim($w));
        }, $words);

        $words = array_filter($words);
        $words = array_unique($words);

        if (count($words) < 2) {
            continue;
        }

        $synonym_id = sanitize_title(implode('-', $words)) . '-' . $index;

        $items[] = [
            'id' => $synonym_id,
            'synonyms' => array_values($words) // ⚠️ NO root here
        ];
    }

    if (empty($items)) {
        error_log('[Typesense Synonyms] No valid synonym items to send');
        return;
    }

    /**
     * --------------------------------------------------
     * 🚀 UPSERT SYNONYM SET (v30)
     * --------------------------------------------------
     */
    $url = "{$protocol}://{$host}/synonym_sets/kosher-synonyms";

    $body = json_encode([
        'items' => $items
    ]);

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-TYPESENSE-API-KEY: ' . $api_key
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $error    = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($error) {
        error_log("[Typesense Synonyms] Curl error: " . $error);
        return;
    }

    error_log("[Typesense Synonyms] Synonym set update status {$status}");

    /**
     * --------------------------------------------------
     * 🔗 LINK TO COLLECTION (IMPORTANT)
     * --------------------------------------------------
     */
    $collections       = array('recipes', 'articles', 'episodes', 'shows', 'menus');

    foreach ($collections as $collection_slug) {
        $collection = function_exists('kosher_typesense_collection_name') ? kosher_typesense_collection_name($collection_slug) : env('TYPESENSE_COLLECTION_PREFIX') . $collection_slug;
        $patch_url  = "{$protocol}://{$host}/collections/{$collection}";

        $patch_body = json_encode([
            'synonym_sets' => ['kosher-synonyms']
        ]);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $patch_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_POSTFIELDS => $patch_body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-TYPESENSE-API-KEY: ' . $api_key
            ],
            CURLOPT_TIMEOUT => 15,
        ]);

        curl_exec($ch);
        $patch_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        error_log("[Typesense Synonyms] {$collection} linked with status {$patch_status}");
    }

    error_log('[Typesense Synonyms] Sync end');
}
/*
function typesense_load_assets()
{
	wp_enqueue_style('typesense-theme', get_template_directory_uri() . '/satellite-min.css');
}

add_action('wp_enqueue_scripts', 'typesense_load_assets');
*/

function ap_typesense_get_filters_query_by_array()
{
	$facets = ap_get('facets', ap_post('facets', false));

	if (!$facets) {
		return false;
	}

	$filters = '';
	$selected_facets = [];

	if (isset($facets['include'])) {
		foreach ($facets['include'] as $facet => $options) {
			if (is_array($options)) {
				foreach ($options as $option) {
					if ($filters != '') {
						$filters .= ' && ';
					}
					$filters .= "{$facet}:={$option}";
				}
			}
		}
		$selected_facets = array_merge($selected_facets, array_keys($facets['include']));
	}

	if (isset($facets['exclude'])) {
		foreach ($facets['exclude'] as $facet => $options) {
			if (is_array($options)) {
				foreach ($options as $option) {
					if ($filters != '') {
						$filters .= ' && ';
					}
					$filters .= "{$facet}:!={$option}";
				}
			}
		}
		$selected_facets = array_merge($selected_facets, array_keys($facets['exclude']));
	}

	return [
		'facets' => implode(',', $selected_facets),
		'filters' => $filters
	];
}


function ap_typesense_template_filter_badges($all_facets)
{
	if (!$all_facets) {
		return null;
	}

	$output = '';

	foreach ($all_facets as $group => $facets) {
		foreach ($facets as $facet => $options) {
			$classes = '';
			switch ($group) {
				case 'include':
					$classes = 'text-bg-success';
					break;
				case 'exclude':
					$classes = 'text-bg-danger';
					break;
				default:
					$classes = 'text-bg-secondary';
			}
			$output .= "<small>{$facet}</small>: ";
			foreach ($options as $index => $option) {
				$output .= "<div class='badge {$classes}'>" . str_replace("\\", '', $option) . "</div>&nbsp;&nbsp;";
			}
		}
	}

	if ($output != '') {
		$output .= '<small>[<a class="text-kosher" href="?q='
			. ap_get('q', ap_post('q', '')) . '&index='
			. ap_get('index', 0) . '&type='
			. ap_get('type', 'chef_recipes') . '">clear filters</a>]</small>';
	}

	return $output;
}

function ap_typesense_get_prepared_scopes()
{

	$env = function_exists('kosher_typesense_collection_prefix') ? kosher_typesense_collection_prefix() : env('TYPESENSE_COLLECTION_PREFIX');

	return [
		/*
        [
            'name' => 'all_recipes',
            'group' => 'recipes',
            'label' => 'all recipes',
            'collection' => $env . 'recipes',
        ],
        */

		[
			'name' => 'chef_recipes',
			'group' => 'recipes',
			'label' => 'chef recipes',
			'collection' => $env . 'recipes',
			'filter_by' => 'community-recipe:=false',
			'page' => ap_get('chef_recipes_pg', 1),
			'query_by' => 'title,tags,ingredients,chefs',
		],
		[
			'name' => 'community_recipes',
			'group' => 'recipes',
			'label' => 'community recipes',
			'collection' => $env . 'recipes',
			'filter_by' => 'community-recipe:=true && user_consent_public:=true',
			'page' => ap_get('community_recipes_pg', 1),
			'query_by' => 'title,tags,ingredients',
		],
		[
			'name' => 'articles',
			'group' => 'articles',
			'label' => 'articles',
			'collection' => $env . 'articles',
			'page' => ap_get('articles_pg', 1),
			'query_by' => 'title,article_sub_category,tags,author',
			'filter_by' => ''
		],
		/*
		[
			'name' => 'shows',
			'group' => 'shows',
			'label' => 'shows',
			'collection' => $env . 'shows',
			'page' => ap_get('shows_pg', 1),
			'query_by' => 'title,tags,short_description',
		],
		*/
		[
			'name' => 'episodes',
			'group' => 'episodes',
			'label' => 'shows',
			'collection' => $env . 'episodes',
			'page' => ap_get('episodes_pg', 1),
			'query_by' => 'title,tags,show',
			'filter_by' => ''
		],
		[
			'name' => 'menus',
			'group' => 'menus',
			'label' => 'menus',
			'collection' => $env . 'menus',
			'page' => ap_get('menus_pg', 1),
			'query_by' => 'title,description,categories,section_titles,recipe_titles,card_text,author_name',
			'filter_by' => 'privacy:=public'
		]
	];
}


function ap_typesense_get_template_filters(String $filters_type)
{
	$path = 'tpl/base/common/items';

	$filters = ap_typesense_api_get_filters($filters_type);

	$filter_blueprints = ap_search_filter_blueprint();



	$active_filters = [];

	foreach ($filters as $key => $filter) {
		$field_name = $filter['field_name'];
		if (array_key_exists($field_name, $filter_blueprints)) {
			if (isset($filter['counts'])) {
				$values = [];
				foreach ($filter['counts'] as $field) {
					$values[$field['value']] = $field['value'];
				}
				if (isset($filter_blueprints[$field_name])) {
					$active_filters[$filter_blueprints[$field_name]['key']] = $filter_blueprints[$field_name];
				}
				// TODO: create ranges for appropriate types
				$active_filters[$filter_blueprints[$field_name]['key']]['values'] = $values;
			}
		}
	}

	ksort($active_filters);

	//$data = json_encode($active_filters, JSON_PRETTY_PRINT);
	//file_put_contents(__DIR__ . '/DEBUG.txt', $data);
	//exit();

	$data = [
		'type' => $filters_type,
		'type_label' => ucwords($filters_type),
		'filters' => $active_filters
	];
	ap_get_template_part("{$path}/common/search-filters-algolia", 'item', $data);
}


function ap_typesense_template_item_list_paginate($index, $scope)
{

	//echo '<code><pre>';
	//echo print_r($scope);
	//echo '</pre></code>';
	//exit();

	$back_cnt = 3;
	$fwd_cnt = 3;
	$path = 'tpl/base/common/items';

	$posts_per_page = $scope['per_page'];
	$total_posts = $scope['found'];
	$name = $scope['name'];
	$type = $scope['group'];
	$pages = 1;
	if ($posts_per_page > 0) {
		$pages = floor($total_posts / $posts_per_page);
	}
	//$pages = $scope['pages'];
	$current_page = ap_get($name . '_pg', 1);
	$route = 'search';

	$prev = [];
	for ($i = $back_cnt; $i != 0; $i--) {
		$p = $current_page - $i;
		if ($p > 0 && $p != $current_page) {
			$prev[] = $p;
		} else {
			continue;
		}
	}
	$next = [];
	for ($i = 1; $i <= $fwd_cnt; $i++) {
		$p = $current_page + $i;
		if ($p <= $pages && $p != $current_page) {
			$next[] = $p;
		} else {
			continue;
		}
	}

	$data = [
		'classes' => ''
	];


	$filters_route = '&';
	if (ap_get('facets', false)) {
		$facets = ap_get('facets');
		if (!is_array($facets)) {
			parse_str(urldecode($facets), $facets);
		}
		if (is_array($facets)) {
			$filters_route .= http_build_query(['facets' => $facets]);
		}
	}

	$paginate = [
		//'post_per_page_options' => $post_per_page_options,
		'query' => ap_get('q', ap_post('q', '')),
		'index' => $index,
		'posts_per_page' => $posts_per_page,
		'total_posts' => $total_posts,
		'pages' => $pages,
		'current_page' => $current_page,
		'prev' => $prev,
		'next' => $next,
		'route' => "/{$route}?q=" . ap_post('q', ap_get('q', '')) . "&type={$name}&index={$index}&{$name}_pg=",
		'filters_route' => $filters_route,
		'params' => $scope['params']
	];

	if (($current_page - 1) > 0) {
		$paginate['nav_prev'] = $current_page - 1;
	} else {
		$paginate['nav_prev'] = 1;
	}

	if (($current_page + 1) < $pages) {
		$paginate['nav_next'] = $current_page + 1;
	} else {
		$paginate['nav_next'] = $pages;
	}

	if (env('WP_AP_ALGOLIA_DEBUG') == 1) {
		echo '<code><pre>';
		echo print_r($paginate);
		echo '</pre></code>';
	}

	if ($scope['found'] > $posts_per_page) {
		ap_get_template_part("{$path}/common/paginate-algolia", 'item', array_merge($data, $paginate));
	}
}



function ap_search_filter_blueprint()
{
	return [
		'occasions' => [
			'key' => 1,
			'name' => 'occasions',
			'label' => 'Holidays',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'typesense'
		],
		'ingredients' => [
			'key' => 2,
			'name' => 'ingredients',
			'label' => 'Ingredients',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'typesense'
		],
		'recipe_category' => [
			'key' => 3,
			'name' => 'recipe_category',
			'label' => 'Courses',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'typesense'
		],
		'preference' => [
			'key' => 4,
			'name' => 'preference',
			'label' => 'Parve/Meat/Dairy',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'typesense'
		],
		'tags' => [
			'key' => 5,
			'name' => 'tags',
			'label' => 'Tags',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'typesense'
		],
		'chef' => [
			'key' => 6,
			'name' => 'chef',
			'label' => 'Chef',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'typesense'
		],
		'chefs' => [
			'key' => 7,
			'name' => 'chefs',
			'label' => 'Chefs',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'typesense'
		],
		'diets' => [
			'key' => 8,
			'name' => 'diets',
			'label' => 'Diets',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'typesense'
		],
		'cuisine' => [
			'key' => 9,
			'name' => 'cuisine',
			'label' => 'Cuisine',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'typesense'
		],
		/*
        'serving' => [
            'name' => 'serving',
            'label' => 'Serving',
        ],
        */
		'sources' => [
			'key' => 10,
			'name' => 'sources',
			'label' => 'Sources',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'typesense'
		],
		'cook_time' => [
			'key' => 11,
			'name' => 'cook_time',
			'label' => 'Cook Time',
			'form_type' => 'preset_multiselect',
			'options' => [
				'Less than 30 minutes' => '<30',
				'Less than 60 minutes' => '<60',
				'Less than 2 hours' => '<120',
				'More than 2 hours' => '>120'
			],
			'source' => 'typesense'
		],
		'difficulty' => [
			'key' => 12,
			'name' => 'difficulty',
			'label' => 'Difficulty',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'typesense'
		],
		'contains_allergents' => [
			'key' => 13,
			'name' => 'contains_allergents',
			'label' => 'Allergens',
			'form_type' => 'multiselect',
			'include' => false,
			'exclude' => true,
			'source' => 'typesense'
		],
		'show' => [
			'key' => 14,
			'name' => 'show',
			'label' => 'Show',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'kosherdb'
		],
		'article_sub_category' => [
			'key' => 15,
			'name' => 'article_sub_category',
			'label' => 'Article Category',
			'form_type' => 'multiselect',
			'include' => true,
			'exclude' => true,
			'source' => 'kosherdb'
		]
	];
}

function ap_typesense_organize_query_results($scopes, $results)
{
	foreach ($scopes as $index => $scope) {
		if (isset($results[$index])) {
			$scopes[$index]['found'] = $results[$index]['found'];
			$scopes[$index]['hits'] = $results[$index]['hits'];
			$scopes[$index]['out_of'] = $results[$index]['out_of'];
			$scopes[$index]['page'] = ap_get($scope['name'] .  '_pg');
			$scopes[$index]['per_page'] = $results[$index]['request_params']['per_page'];
			$scopes[$index]['collection'] = $results[$index]['request_params']['collection_name'];
		}
	}

	return $scopes;
}

function ap_typesense_api_get_multiquery($scopes = [], $query = '')
{

	$typesense = ap_typesense();

	$params = ap_typesense_get_filters_query_by_array();

	if (ap_get('ppp', false)) {
		ap_session_set('ppp', ap_get('ppp'), 'search');
	}

	$searchRequests = [];
	foreach ($scopes as $key => $scope) {
		$searchRequests['searches'][$key] = [
			'collection' => $scope['collection'],
			'q' => $query,
			'page' => $scope['page'],
			'query_by' => $scope['query_by']
		];

		if ($params['facets']) {
			//$searchRequests['searches'][$key]['facet_by'] = implode(',', $params['facets']);
		}

		if (isset($scope['filter_by'])) {
			$searchRequests['searches'][$key]['filter_by'] = $scope['filter_by'];
		}



		if (isset($searchRequests['searches'][$key]['filter_by']) && $searchRequests['searches'][$key]['filter_by'] !== '') {

			if ($params['filters']) {
				$searchRequests['searches'][$key]['filter_by'] .= ' && ';
				$searchRequests['searches'][$key]['filter_by'] .= $params['filters'];
			}
		} elseif (isset($searchRequests['searches'][$key]['filter_by'])) {
			if ($params['filters']) {
				$searchRequests['searches'][$key]['filter_by'] = $params['filters'];
			}
		}
	}


	$commonSearchParams =  [
		'per_page' => ap_session_get('ppp', 15, 'search'),
		//'drop_tokens_threshold' => 50,
		//'typo_tokens_threshold' => 200
	];

	ap_session_set('TS_QUERY', ['searchRequests' => $searchRequests, 'commonSearchParams' => $commonSearchParams], 'typesense');

	return ap_typesense_query_cache_get_results($typesense, $searchRequests, $commonSearchParams);
}

function ap_typesense_query_cache_get_results($typesense, $searchRequests, $commonSearchParams)
{
	if (env('TYPESENSE_CACHE') != 1 || !function_exists('apcu_fetch')) {
		return $typesense->multiSearch->perform($searchRequests, $commonSearchParams);
	}

	$unique_query_key = env('TYPESENSE_CACHE_VER') . base64_encode(json_encode([$searchRequests, $commonSearchParams]));

	if ($results = apcu_fetch($unique_query_key)) {
		return $results;
	}

	$results = $typesense->multiSearch->perform($searchRequests, $commonSearchParams);

	apcu_add($unique_query_key, $results, env('TYPESENSE_CACHE_TTL'));

	return $results;
}


function typesense_likes_to_record(int $post_id): void
{
    global $wpdb;
    $typesense = kosher_typesense_write_client();

    if (!is_object($typesense)) {
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        return;
    }

    $total_likes = function_exists('kayco_get_post_favorite_count')
        ? (int) kayco_get_post_favorite_count($post_id)
        : 0;

    $doc_id = typesense_get_doc_id($post);
    if ($doc_id === false) {
        return;
    }

    try {
        $collection = function_exists('kosher_typesense_collection_name') ? kosher_typesense_collection_name($post->post_type) : env('TYPESENSE_COLLECTION_PREFIX') . $post->post_type;
        $typesense
            ->collections[$collection]
            ->documents
            ->update([
                'id'    => (string) $doc_id,
                'likes' => $total_likes,
            ]);
    } catch (Exception $e) {
        error_log('[Typesense Likes Update Failed] ' . $e->getMessage());
    }
}


function ap_typesense_api_get_filters(String $filters_type)
{
	$typesense = ap_typesense();
	if (!$typesense) {
		return false;
	}

	$scopes = ap_typesense_get_prepared_scopes();

	$searchParameters = [];
	foreach ($scopes as $key => $scope) {
		if ($scope['name'] === $filters_type) {
			$searchParameters  = [
				'collection' => $scope['collection'],
				'q' => '',
				'query_by' => 'title',
				'facet_by' => '*',
				'max_facet_values' => 999999999
			];
			if (isset($scope['filter_by'])) {
				$searchParameters['filter_by'] = $scope['filter_by'];
			}

			$filters = $typesense->collections[$scope['collection']]->documents->search($searchParameters);

			if (isset($filters['facet_counts'])) {
				return $filters['facet_counts'];
			}
		}
	}

	return false;
}

function ap_typesense()
{
	if (!class_exists('\Typesense\Client')) {
		return null;
	}

	$api_key = function_exists('kosher_typesense_search_api_key') ? kosher_typesense_search_api_key() : env('TYPESENSE_API_KEY');
	if (!$api_key) {
		return null;
	}

	return new \Typesense\Client(
		[
			'api_key'         => $api_key,
			'nodes'           => [
				[
					'host'     => function_exists('kosher_typesense_host') ? kosher_typesense_host() : env('TYPESENSE_API_HOST'),
					'port'     => function_exists('kosher_typesense_port') ? kosher_typesense_port() : env('TYPESENSE_API_PORT'),
					'protocol' => function_exists('kosher_typesense_protocol') ? kosher_typesense_protocol() : env('TYPESENSE_API_PROTOCOL'),
				],
			],
			'connection_timeout_seconds' => function_exists('kosher_typesense_config_value') ? kosher_typesense_config_value('TYPESENSE_API_TIMEOUT', 2) : env('TYPESENSE_API_TIMEOUT'),
		]
	);
}


function ap_typesense_synonyms()
{
	if (!class_exists('\Typesense\Client')) {
		return null;
	}

	$api_key = function_exists('kosher_typesense_admin_api_key') ? kosher_typesense_admin_api_key() : env('TYPESENSE_ADMIN_KEY');
	if (!$api_key) {
		return null;
	}

	return new \Typesense\Client(
		[
			'api_key'         => $api_key,
			'nodes'           => [
				[
					'host'     => function_exists('kosher_typesense_host') ? kosher_typesense_host() : env('TYPESENSE_API_HOST'),
					'port'     => function_exists('kosher_typesense_port') ? kosher_typesense_port() : env('TYPESENSE_API_PORT'),
					'protocol' => function_exists('kosher_typesense_protocol') ? kosher_typesense_protocol() : env('TYPESENSE_API_PROTOCOL'),
				],
			],
			'connection_timeout_seconds' => function_exists('kosher_typesense_config_value') ? kosher_typesense_config_value('TYPESENSE_API_TIMEOUT', 2) : env('TYPESENSE_API_TIMEOUT'),
		]
	);
}
