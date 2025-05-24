<?php
/**
 * Helper  for InterLinker Plugins.
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Interlinker_Helper {



	/**
	 * Return all posts of site by post type
	 *
	 * @param int $limit //Limit of posts to return. Set 0 for Unlimited
	 * @param bool $ids //true = Return array IDs, false = Return objects
	 * @param array|string $post_type
	 * @param array|string $categoryes //Categories ID
	 *
	 * @return array|WP_Post[]
	 */
	public function get_site_posts($limit = 0, $ids = false, $post_type = [], $categoryes = '') {
		if(empty($post_type)){
			//$post_type = ['post', 'page'];
			$post_type = ['post'];
		}else{
			if(!is_array($post_type) && str_contains($post_type, ',')){
				$arr_keys = explode(',', $post_type);
				$post_type = array_map('trim', $arr_keys);
			}
		}

		$arg = array(
			'post_type' => $post_type,
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'orderby'        => 'date',
            'order'          => 'DESC',
		);


		if(!empty($limit)){
			$arg['posts_per_page'] = $limit;
			$arg['numberposts'] = $limit;
			//$arg['offset'] = $this->number_posts_limit;
		}

		if(!empty($ids)){
			$arg['fields'] = 'ids';
		}

		if(!empty($categoryes)){
			$arg['category'] = $categoryes;
		}

		$posts = get_posts($arg);

		if(empty($posts)) return array();

		return $posts;
	}

	/**
	 * Converts textarea input into an array.
	 * Supports commas, newlines and trims values.
	 *
	 * @param string|array $keys Array or string from textarea keys.
	 * @return array Cleaned array of strings.
	 */
	public function extract_keys($keys) {
		if(empty($keys)){
			error_log(date("Y-m-d H:i:s" , time()) . "[InterLinker] Field keys is empty");
			return array();
		}
		if(is_array($keys)){
			return array_filter(array_map('trim', $keys));
		}

	    $input = str_replace([ "\r\n", "\r", "\n" ], ',', $keys);
	    $items = explode(',', $input);
	    $cleaned = array_filter(array_map('trim', $items));
		return array_values($cleaned);

	}


	/**
	 * Parse anchors of page or post and return array of links.
	 * @param WP_Post $post
	 * @param bool $acf
	 * @param array $anchors
	 *
	 * @return array|void
	 */
	public function get_anchors($post, $acf = false, $anchors = []) {
		if(empty($post) || empty($post->post_content)) return array();

		$result = [];
		$result = $this->merge_links_recursive( $result, $this->extract_links_from_html( $post->post_content, $anchors ) );
		// ACF support
	    if (  $acf && function_exists( 'get_fields' ) ) {
	        $acf_fields = get_fields( $post->ID );
	        if(!empty($acf_fields) && is_array( $acf_fields )){
				if(!empty($acf_fields['accordion'])){
					foreach ($acf_fields['accordion'] as $acordion){
						if(!empty($acordion['content'])){
							$result = $this->merge_links_recursive( $result, $this->extract_links_from_html( $acordion['content'], $anchors ) );
						}
					}
				}
	        }
	    }

		return $result;

	}


	/**
	 * Extract all anchor tags from HTML string.
	 *
	 * @param string $html The HTML string to parse.
	 * @param array $anchors Anchors to search
	 * @return array [ URL => Anchor Text ]
	 */
	public function extract_links_from_html( $html, $anchors  ) {
		if(empty($html) || empty($anchors)) return array();

	    $links = [];

	    libxml_use_internal_errors(true);
	    $dom = new DOMDocument();
	    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
	    libxml_clear_errors();

	    $anchors_dom = $dom->getElementsByTagName('a');
	    $site_url = rtrim( get_site_url(), '/' );


	    foreach ( $anchors_dom as $a ) {

			// Skip <a> if it is inside <h1> ... <h6>
	        $parent = $a->parentNode;
	        while ( $parent ) {
	            if ( preg_match('/^h[1-6]$/i', $parent->nodeName) ) {
	                continue 2;
	            }
	            $parent = $parent->parentNode;
	        }

	        $href = trim( $a->getAttribute('href') );
	        $text = trim( $a->textContent );

	        if ( empty($href) ) continue;

	        $is_internal = false;

	        // Relative links
	        if ( strpos($href, '/') === 0 || strpos($href, '?') === 0 ) {
	            $is_internal = true;
	        }

	        // Absolute links to the current site
	        if ( strpos($href, $site_url) === 0 || strpos($href, str_replace('http:', 'https:', $site_url)) === 0) {
	            $is_internal = true;
	        }


	        if ( $is_internal ) {
	            // Give absolute links to relative
	            if ( strpos($href, $site_url) === 0 ) {
	                $href = substr($href, strlen($site_url));
	                if ($href === '') $href = '/';
	            }

				$href = $this->normalise_url($href);

				if ( !empty($text) ) {
					if(!empty($anchors)){
						foreach ( (array) $anchors as $anchor ) {
							if(preg_match('/'. $anchor . '/i', $text)){
								if ( ! isset($links[$href]) ) {
					                $links[$href] = [];
					            }

								$links[$href][] = ['anchor' => $text, 'contain'=>$anchor];

								//Save global count of anchor
								$this->save_global_count_anchor($anchor, 1, $href);
							}
						}
					}
	            }

	        }
	    }

	    return $links;
	}


	/**
	 * Combines the arrays of references [url => [anchors]] without duplicates.
	 *
	 * @param array $base
	 * @param array $new
	 * @return array
	 */
	public function merge_links_recursive( $base, $new ) {
	    foreach ( $new as $url => $anchors ) {
	        if ( ! isset( $base[$url] ) ) {
	            $base[$url] = [];
	        }

	        foreach ( $anchors as $anchor ) {
	            $base[$url][] = $anchor;
	        }
	    }

	    return $base;
	}

	/**
	 * Normalise urls to absolute
	 *
	 * @param string $url
	 * @return string
	 */
	public function normalise_url( $url ) {
		if(empty($url)) return '';
		$site_url = rtrim( get_site_url(), '/' );
		if(!str_contains($url, 'http')){
			$url = $site_url .'/'. trim( preg_replace('#^(/||//)#', '', $url) );
		}

	    return $url;
	}


	/**
	 * Generate and save extended data in csv file
	 *
	 * @param array $data
	 * @return void
	 */
	public function generate_extend_csv( $data, $json = '' ) {
		if(empty($data) || !is_array($data)) return;

		$result_csv = '';
		$arr_pages = [];
		$arr_to_url = [];
		$arr_from_url = [];
		$arr_from_pages = [];

		$result_arr['first_row'] = ';;;;';
		$result_arr['second_row'] = ';;;;';
		$result_arr['third_row'] = 'URL; Anchors; Count All; Count To;';

		//Array urls pages
		foreach ( (array) $data as $url_page => $arr_links ) {
			if(!in_array($url_page, $arr_pages)){
				$arr_pages[] = trailingslashit( trim($url_page));
			}
		}

		//Array data links from pages
		foreach ( (array) $data as $url_page => $arr_links ) {
			if(empty($url_page)) continue;
			$arr_from_url[$url_page] = [];
			foreach ( (array) $arr_links['anchors'] as $url_link => $links ) {
				foreach ( (array) $links as $keys ) {
					$arr_from_url[$url_page][] = ['anchor' => $keys['anchor'], 'contain' => $keys['contain'], 'to_link'=> trailingslashit( trim($url_link)), 'from_title'=> $arr_links['title']];
				}
			}
		}
		$arr_from_links_count = [];
		if( !empty($arr_from_url)) $arr_from_links_count = $this->process_anchor_array($arr_from_url);

		//Array data links to pages
		foreach ( (array) $data as $data_url_from_page => $arr_data_anchors ) {
			foreach ( (array) $arr_data_anchors['anchors'] as $url_to_page => $links ) {
				foreach ( (array) $links as $data_for_link ) {
					$arr_to_url[ trailingslashit( trim($url_to_page))][] = ['anchor' => $data_for_link['anchor'], 'contain' => $data_for_link['contain'], 'from_link'=> trailingslashit( trim($data_url_from_page)), 'from_title'=> $arr_data_anchors['title'] ];
				}
			}
		}
		$arr_to_links_count = [];
		if( !empty($arr_to_url)) $arr_to_links_count = $this->process_anchor_array($arr_to_url);

		//Make first 3 rows
		foreach ( (array) $data as $url_page => $arr_links ) {
			$result_arr['first_row'] .= $url_page . ';';
			$arr_from_pages[] = $url_page;
			$result_arr['second_row'] .= $arr_links['title'] . ';';
			if(!empty($arr_from_url[$url_page])){
				$result_arr['third_row'] .= count((array)$arr_from_url[$url_page]) . ';';
			}else{
				$result_arr['third_row'] .= ';';
			}
		}

		//Make next rows
		$counter_pages = 0;
		foreach ( (array) $arr_to_links_count as $url_to_page =>  $url_to_page_data ) {
			$result_arr['url_'.$counter_pages.'_row'] = $url_to_page . ';;' . count( (array)$arr_to_url[trailingslashit( trim($url_to_page) )])  . ";";
			foreach ( (array) $arr_to_links_count[ trailingslashit( trim($url_to_page) ) ] as $to_page_data ) {
				$left_str = ';' . $to_page_data['anchor'] . ';;' . $to_page_data['count'] . ";";
				$right_str = '';
				foreach ( (array) $arr_from_pages as $from_page_url ) {
					if( !empty( $arr_from_links_count[trailingslashit(trim($from_page_url))][$to_page_data['anchor']]['count']) ){
						$right_str .= $arr_from_links_count[trailingslashit(trim($from_page_url))][$to_page_data['anchor']]['count'] .';';
					}else{
						$right_str .= ';';
					}
				}
				$result_arr['url_'.$counter_pages.'_row_anchors'][] = $left_str . $right_str;
			}

			$counter_pages++;
		}

		// Make CSV file data
		foreach ( (array) $result_arr as $scv_key_row => $csv_row ) {
			if(!is_array($csv_row)){
				$result_csv .= $csv_row . "\n";
			}else{
				$result_csv .= implode("\n", $csv_row) . "\n";
			}
		}

		file_put_contents( INTERLINKER_UPLOADS_DIR_PATH.'/interlinker_report.csv', $result_csv);

	}

	/**
	 * Processes a multidimensional anchor array:
	 * - Removes duplicate entries based on the 'anchor' field per target URL.
	 * - Adds a 'count' field to each unique anchor, indicating how many times that anchor appeared originally.
	 *
	 * @param array $arr_to_url Input array structured as:
	 * @return array
	 **/
	public function process_anchor_array($arr_to_url) {
	    $result = [];

	    foreach ($arr_to_url as $to_url => $entries) {
	        $anchor_map = [];
	        $anchor_count = [];

	        // Count each Anchor meets
	        foreach ($entries as $entry) {
	            $anchor = $entry['anchor'];
	            if (!isset($anchor_count[$anchor])) {
	                $anchor_count[$anchor] = 1;
	            } else {
	                $anchor_count[$anchor]++;
	            }
	        }

	        // Form an array without duplicate Anchor
	        foreach ($entries as $entry) {
	            $anchor = $entry['anchor'];

	            if (isset($anchor_map[$anchor])) continue;

	            $entry['count'] = $anchor_count[$anchor];
	            $result[$to_url][$anchor] = $entry;

	            $anchor_map[$anchor] = true;
	        }
	    }

	    return $result;
	}



	/**
	 * Count number of <a> tags in content with anchor text contains or not.
	 *
	 * @param string $content
	 * @param string $keyword
	 * @return int
	 */
	public function count_internal_links($content, $keyword = '') {
	    $count = 0;
		$site_url = rtrim(get_site_url(), '/');

		libxml_use_internal_errors(true);
		$dom = new DOMDocument();
		$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
		libxml_clear_errors();

		$anchors = $dom->getElementsByTagName('a');

		foreach ($anchors as $a) {
			$href = trim($a->getAttribute('href'));
			$anchor_text = trim($a->textContent);

			// Check: internal link?
			$is_internal = false;

			// Relative link (begins with / or?)
			if (strpos($href, '/') === 0 || strpos($href, '?') === 0) {
				$is_internal = true;
			}

			// Absolute links to the current site
	        if ( strpos($href, $site_url) === 0 || strpos($href, str_replace('http:', 'https:', $site_url)) === 0) {
	            $is_internal = true;
	        }

			if($is_internal){
				if (!empty($keyword)) {
					if (preg_match('/' . preg_quote($keyword, '/') . '/i', $anchor_text)) {
						$count++;
					}
				}else{
					$count++;
				}
			}

		}

		return $count;
	}

	/**
	 * Start mapping
	 *
	 * @param int $post_id Post ID.
	 * @param array $mapping_data Array data for mapping
	 * @param int $lcount Number of internal links already in this content.
	 *
	 * @return bool|int Number of links inserted.
	 */
	public function start_mapping_content($post_id, $mapping_data, $lcount = 0) {
		if (empty($post_id) || empty($mapping_data) || empty($mapping_data['templates'])) return false;

		$limit_links_on_page = $mapping_data['limit_links_page'] ?? 0;
		$current_limit_links = $lcount;
		$post_content = get_post_field('post_content', $post_id);
		if(empty($post_content)) return false;
		$old_post_content = $post_content;
		$post_url = trailingslashit(get_permalink($post_id));
		$keys_log = [];
		$urls_log = [];

		foreach ( (array) $mapping_data['expanders'] as $expander_key ) {
			if(!empty($limit_links_on_page) && $current_limit_links >= $limit_links_on_page) break;

			//Select anchor
			$arr_anchor = $this->sel_anchor($expander_key, $mapping_data['authors'], $mapping_data['expanders'], $urls_log, $post_id);
			if(empty($arr_anchor)) continue;

			//Generate link
			$generated_link = '<a href="' . esc_url($arr_anchor['url']) . '">'.$arr_anchor['key'].'</a>';


			//Get Template
			$template = $this->get_template_for_insert($mapping_data['templates']); //String
			if(empty($template)) continue;
			$curent_template = str_replace('{{KEYWORD}}', $generated_link, $template);



			$post_content_arr = $this->search_keys_in_htags($post_content, 'h2', 'h2', trim($expander_key), $curent_template);
			if($post_content_arr['added'] == true){
				$post_content = $post_content_arr['content'];
				$urls_log[] = $arr_anchor['url'];
				$keys_log[] = $arr_anchor['key'];
			}
			$current_limit_links++;
		}

		//Update post content
		if($post_content != $old_post_content){
			$post_updated_id = wp_update_post(wp_slash([
				'ID' => (int) $post_id,
				'post_content' => str_replace('{{used}}', '', $post_content),
			]), true);

			if ( is_wp_error( $post_updated_id ) ) {
				error_log(date("Y-m-d H:i:s" , time()) . "[InterLinker] Post ID:".$post_id . " update error: ". $post_updated_id->get_error_message());
				return false;
			}else{
				if(!empty($keys_log)){
					foreach ((array)$keys_log as $key) {
						//Save keyword used in database
						$this->interlinker_insert_key(trim($key), $post_id);
					}
				//Log mapping
				file_put_contents( INTERLINKER_UPLOADS_DIR_PATH.'/mappinglog.txt', date("Y-m-d H:i:s" , time()) . ' Keys:'.count($keys_log).' ('. implode(', ', $keys_log).'), '.'On Post: '. $post_url . "\n", FILE_APPEND );
				return count($keys_log);
				}
			}
		}

		return false;
	}


	/**
	 * Searches for a keyword inside a specific HTML tag in the content,
	 *
	 * @param string $post_content The HTML content to search in.
	 * @param string $tag_search The tag name (e.g., 'h2', 'div') to look inside.
	 * @param string $tag_before The tag before which the marker should be inserted.
	 * @param string $key The keyword to search for (case-insensitive).
	 * @param string $marker The marker or content to insert
    *
	 * @return array The modified content
	 */
	function search_keys_in_htags($post_content, $tag_search, $tag_before, $key, $marker = '{{insert_template}}') {

	    if (empty($post_content) || empty($tag_search) || empty($key)) {
	        return ['content' => $post_content, 'added' => false];
	    }

	    // Match tag content first (to isolate <h2>...</h2>)
	    $pattern = '#<'. $tag_search .'\b[^>]*>(.*?)</'. $tag_search .'>#is';
		$added = false;

	    if (preg_match_all($pattern, $post_content, $matches, PREG_OFFSET_CAPTURE)) {

	        foreach ($matches[0] as $i => $full_tag) {
	            $full_html = $full_tag[0];
	            $start_pos = $full_tag[1];
	            $inner_text = $matches[1][$i][0];

	            // Skip if already processed (marked)
	            if (strpos($full_html, '{{used}}') !== false) {
	                continue;
	            }

	            // Strict match: check keyword as full word (case-insensitive)
	            if (preg_match('/\b' . preg_quote($key, '/') . '\b/i', $inner_text)) {
	                $end_pos = $start_pos + strlen($full_html);

	                // Find the next tag_before or <hr> after this tag
	                $after = substr($post_content, $end_pos);
	                $next_pattern = '#(<(' . preg_quote($tag_before, '#') . '|hr)\b[^>]*>)#is';

	                if (preg_match($next_pattern, $after, $next_match, PREG_OFFSET_CAPTURE)) {
	                    $insert_pos = $end_pos + $next_match[0][1];
	                } else {
	                    $insert_pos = strlen($post_content); // End of content
	                }

	                // Insert marker
	                $post_content = substr_replace($post_content, $marker, $insert_pos, 0);

	                // Mark this tag as processed
	                $marked_html = str_replace('</' . $tag_search . '>', ' {{used}}</' . $tag_search . '>', $full_html);
	                $post_content = substr_replace($post_content, $marked_html, $start_pos, strlen($full_html));
					$added = true;

	                break;
	            }
	        }
	    }

	    return ['content' => $post_content, 'added' => $added ?? false];
	}



	/**
	 * Retrieves the global counter for keywords or a specific keyword.
	 *
	 * @param string $key Optional. The specific keyword for which the count is to be retrieved.
	 * @return array
	 */
	public function get_global_counter_keywords($key = '') {
		$arr_stat = get_option(INTERLINKER_GLOBAL_COUNTER_OPTION, []);

		if(empty($arr_stat)){
			$arr_stat = [];
			return $arr_stat;

		}elseif(!empty($key) && empty($arr_stat)){
			$arr_stat = [];
			$arr_stat[$key] = 0;
			update_option(INTERLINKER_GLOBAL_COUNTER_OPTION, $arr_stat);
			return $arr_stat;

		}elseif(!empty($key) && !empty($arr_stat)){
			return $arr_stat[$key];
		}

		return $arr_stat;
	}


	/**
	 * Reset keyword counter
	 * @return void
	 */
	public function reset_global_counter_keywords() {
		update_option(INTERLINKER_GLOBAL_COUNTER_OPTION, []);
	}

	/**
	 * Saves or updates the global count for a given anchor in the WordPress options table.
	 *
	 * @param string $anchor The anchor text to be saved or updated.
     * @param int $count The count to be saved or added to the existing count for the anchor.
	 * @param string $url The URL associated with the anchor. (Currently not used in the function logic)
	 *
	 * @return bool
	 */
	public function save_global_count_anchor($anchor, $count, $url = '') {
		if ( empty($anchor) || empty($count)) return false;
		$arr_stat = $this->get_global_counter_keywords();
		if(empty($arr_stat)){
			$arr_stat = [$anchor => (int)$count];

		}else{
			if(empty($arr_stat[$anchor])){
				$arr_stat[$anchor] = (int)$count;

			}else{
				$arr_stat[$anchor] += (int)$count;
			}

		}
		update_option(INTERLINKER_GLOBAL_COUNTER_OPTION, $arr_stat);

		return true;
	}



	/**
	 * Checks if a key exists in the interlinker_keys table.
	 *
	 * @param string $key The phrase to search.
	 * @return array|false Array of result (id, key, id_post) or false if not found.
	 */
	public function interlinker_find_key($key) {
		if(empty($key)) return false;
		global $wpdb;
		$table_name = $wpdb->prefix . 'interlinker_keys';

		$result = $wpdb->get_row(
			$wpdb->prepare("SELECT id, `key`, id_post FROM $table_name WHERE `key` = %s LIMIT 1", $key),
			ARRAY_A
		);

		return $result ?: false;
	}

	/**
	 * Inserts a new key and post ID into the interlinker_keys table.
	 *
	 * @param string $key The key/phrase to insert.
	 * @param int $post_id The related post ID.
	 * @return int|false Inserted row ID on success, false on failure.
	 */
	public function interlinker_insert_key($key, $post_id) {
		if(empty($key) || empty($post_id)) return false;
		global $wpdb;
		$table_name = $wpdb->prefix . 'interlinker_keys';

		$wpdb->insert(
			$table_name,
			[
				'key'     => $key,
				'id_post' => intval($post_id),
			],
			['%s', '%d']
		);

		return $wpdb->insert_id ?: false;
	}

	/**
	 * Truncates all records from interlinker_keys table.
	 */
	public function interlinker_clear_keys_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'interlinker_keys';
		$wpdb->query("TRUNCATE TABLE $table_name");
	}

	/**
	 * Combines author names with expansion keywords and normalizes URLs.
	 *
	 * @param array $arr_authors Associative array of authors => urls.
	 * @param array $arr_expanders List of keywords to combine.
	 *
	 * @return array Combined and normalized array.
	 */
	public function build_combined_author_expansions(array $arr_authors, array $arr_expanders): array {
		if (empty($arr_authors) || empty($arr_expanders)) return [];
	    $result_arr = [];
		$site_url = rtrim( get_site_url(), '/' );

	    foreach ($arr_authors as $author => $url) {
	        // Normalize URL: if it's not full URL, make it .php
	        if (!preg_match('#^https?://#i', $url)) {
	            $url = $site_url .'/'. ltrim( $url, '/' );
	        }
			$url = trailingslashit( $url );

	        foreach ($arr_expanders as $expander) {
	            $key = $author . ' ' . $expander;
	            $result_arr[$expander][] = ['author' => $author, 'key' => $key, 'url' => $url];
	        }
	    }

	    return $result_arr;
	}


	/**
	 * Selects author data based on authors array, and expanders array.
	 *
	 * @param string $expander The phrase expander
	 * @param array $arr_authors An array of authors and their associated keywords.
	 * @param array $arr_expanders An array of expanders and their associated keywords.
	 * @param array $used_urls An array of used URLs.
     * @param int $post_id Post ID
     *
	 * @return array|false An array of selected author data or false
	 */
	public function sel_anchor($expander, $arr_authors, $arr_expanders, $used_urls, $post_id) {
		if (empty($expander) || empty($arr_authors) || empty($arr_expanders) || empty($post_id)) return false;

		$arr_result = [];
		$post_url = trailingslashit(get_permalink($post_id));
		$arr_authors = $this->build_combined_author_expansions($arr_authors, $arr_expanders);
		if(!empty($arr_authors[$expander])){
			shuffle($arr_authors[$expander]);
			foreach ( (array) $arr_authors[$expander] as $arr_anchors ) {
				//Not found in interlinker_keys table, and not the same as the post URL, and url not used on this page before
				if(!$this->interlinker_find_key($arr_anchors['key']) && $post_url !== $arr_anchors['url'] && !in_array($arr_anchors['url'], $used_urls)){
					$arr_result = ['author' => $arr_anchors['author'], 'key' => $arr_anchors['key'], 'url' => $arr_anchors['url']];
					break;
				}
			}
		}

		return $arr_result;
	}


	/**
	 * Retrieves the next template slot based on the provided array of templates.
	 *
	 * @param array $arr_templates An array of templates, each containing 'template_name' and 'template'.
	 * @return string|bool The template string if found, otherwise false.
	 */
	public function get_template_for_insert($arr_templates) {
		if (empty($arr_templates) || !is_array($arr_templates)) return false;
		$used_last_template = get_option(INTERLINKER_TEMPLATES_COUNTER_OPTION, '');
		if(empty($used_last_template)){
			update_option(INTERLINKER_TEMPLATES_COUNTER_OPTION, $arr_templates[0]['template_name']);
			return $arr_templates[0]['template'];
		}else{
			return $this->get_next_template_by_name($arr_templates, $used_last_template);
		}
	}




	/**
	 * Returns the next template from the list by template_name, looping back to the first if at the end.
	 *
	 * @param array $arr_templates Array of templates with keys: template_name and template.
	 * @param string $last_template_name The name of the last used template.
	 *
	 * @return array|bool The next template array, or null if not found.
	 */
	function get_next_template_by_name($arr_templates, $last_template_name) {
		if(empty($arr_templates) ) return false;

		if(empty($last_template_name)){
			update_option(INTERLINKER_TEMPLATES_COUNTER_OPTION, $arr_templates[0]['template_name']);
			return $arr_templates[0]['template'];
		}

	    foreach ($arr_templates as $index => $template) {
	        if (isset($template['template_name']) && $template['template_name'] === $last_template_name) {
	            // Calculate next index (circular)
	            $next_index = ($index + 1) % count($arr_templates);
				update_option(INTERLINKER_TEMPLATES_COUNTER_OPTION, $arr_templates[$next_index]['template_name']);
	            return $arr_templates[$next_index]['template'];
	        }
	    }
	    return false;
	}

	/**
	 * Calculates how much time has passed since the start.
	 *
	 * @param int $start_time The start mark (Timestamp).
	 * @return string Time in format "HH:MM:SS".
	 */
	function interlinker_get_elapsed_time($start_time) {
	    $elapsed = time() - $start_time;

	    $hours = floor($elapsed / 3600);
	    $minutes = floor(($elapsed % 3600) / 60);
	    $seconds = $elapsed % 60;

	    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
	}

}