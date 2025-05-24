<?php
/**
 * Core  for InterLinker Plugins.
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class InterLinker_Core {


	protected $fields_global = [] ; //ACF fields for Global settings
	protected $fields_byname = [] ; //ACF fields by Name
	protected $fields_analized = [] ; //ACF fields for Analisс
	protected $fields_mapping = [] ; //ACF fields for Mapping

	protected $field_group_id = []; //ACF fields group ID from json

	protected $keys_count = 0; //Counter inserted keys for report

	public function __construct() {
		//ACF fields group ID from json? local or dev or prod group
		$this->field_group_id = ['group_6824b83e65f7a'];

		//Add ACF scripts and styles
		add_action('admin_enqueue_scripts', function($hook) {
			if ( $hook !== 'tools_page_interlinker' ) return;
			wp_enqueue_script('acf-input');
			wp_enqueue_style('acf-input');
			wp_enqueue_script('acf-pro-input');
			wp_enqueue_style('acf-pro-input');
		}, 10, 1);

		//Parse ACF fields
		add_action( 'init', [$this, 'interlinker_parse_form_fields'] );

		//Add settings page
		add_action( 'admin_menu', [$this, 'interlinker_add_settings_page'] );

		//Handle form Analisis submission
		add_action('admin_post_interlinker_analyze', [$this, 'interlinker_analisis_form']);

		//Handle form Mapping submission
		add_action('admin_post_interlinker_mapping', [$this, 'interlinker_mapping_form']);

		//Handle form Global settings submission
		add_action('admin_post_interlinker_global', [$this, 'interlinker_global_form']);

	}


	/**
	 * Parse ACF fields for InterLinker
	 *
	 * @return void
	 */
	public function interlinker_parse_form_fields() {
		$fields_by_group = [];
		foreach ($this->field_group_id as $group_id) {
			$fields_by_group = acf_get_fields( $group_id );
			if(!empty($fields_by_group)) {
				break;
			}
		}

		if(empty($fields_by_group)) return;

		foreach ($fields_by_group as $field) {
			if(preg_match('/^analise_/i', $field['name'])){
				$this->fields_analized[] = $field['key'];
			}elseif(preg_match('/^mapping_/i', $field['name'])){
				$this->fields_mapping[] = $field['key'];
			}else{
				$this->fields_global[] = $field['key'];
			}
			$this->fields_byname[$field['name']] = $field['key'];
		}

	}

	/**
	 * Registers the InterLinker settings page in the WordPress admin menu
	 * under the "Tools" section.
	 *
	 * @return void
	 */
	public function interlinker_add_settings_page() {
	    add_management_page(
	        'InterLinker',
	        'InterLinker',
	        'manage_options',
	        'interlinker',
	        [$this,'interlinker_render_settings_page']
	    );

		add_options_page('Interlinker', 'Interlinker', 'manage_options', 'interlinker', [$this, 'interlinker_option_page']);
	}



	/**
	 * Renders the InterLinker settings page in the admin interface.
	 *
	 * @return void
	 */
	public function interlinker_render_settings_page() {
	    ?>
		<div class="col-wrap">
			<h1>InterLinker — Linking Analysis</h1>

			<?php if ( !empty($_GET['status']) && $_GET['status'] === 'success-analisis' ) : ?>
				<div class="notice notice-success is-dismissible"><p>Analysis successfully.<?php echo !empty($_GET['time']) ?  ' Time: ' . esc_html( $_GET['time'] ) : ''; ?></p></div>
			<?php elseif ( !empty($_GET['status']) && $_GET['status'] === 'error-analisis'): ?>
				<div class="notice notice-error is-dismissible"><p>Error - keywords not found.</p></div>

			<?php elseif ( !empty($_GET['status']) && $_GET['status'] === 'success-mapping' ) : ?>
				<div class="notice notice-success is-dismissible"><p>Mapping successfully.<?php echo !empty($_GET['time']) ?  ' Time: ' . esc_html( $_GET['time'] ) : ''; ?></p></div>
			<?php elseif ( !empty($_GET['status']) && $_GET['status'] === 'error-mapping'): ?>
				<div class="notice notice-error is-dismissible"><p>Mapping error.</p></div>
			<?php elseif ( !empty($_GET['status']) && $_GET['status'] === 'success-mapping_save'): ?>
				<div class="notice notice-success is-dismissible"><p>Mapping save.</p></div>

			<?php elseif ( !empty($_GET['status']) && $_GET['status'] === 'success-global' ) : ?>
				<div class="notice notice-success is-dismissible"><p>Global settins saved successfully.</p></div>
			<?php endif; ?>

			<p> </p>

			<div class="form-wrap" style="margin-top: 50px;">
				<h1>Linking Analysis</h1>
				<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
					<?php
					do_action('acf/input/admin_head');
					do_action('acf/input/admin_footer');

					acf_form([
						'post_id'       => 'options',
						'field_groups'  => false,
						'fields'        => $this->fields_analized,
						'form'          => false
					]);
					?>

					<input type="hidden" name="action" value="interlinker_analyze">
					<?php wp_nonce_field('interlinker_analyze_nonce'); ?>
					<p>
						<button type="submit" class="button button-primary">🔍 Analyze</button>
					</p>
				</form>

				<?php
					if(file_exists(INTERLINKER_UPLOADS_DIR_PATH.'/interlinker_report.csv')){
						echo '<p><a href="'.INTERLINKER_UPLOADS_DIR_URL.'/interlinker_report.csv" class="button button-secondary">Download Interlinker CSV Report</a></p>';
					}
				?>
			</div>


			<hr style="margin-top: 50px;border:none;border-top:1px solid #4e4e4e;">


			<div class="form-wrap" style="margin-top: 50px;">
				<h1>Mapping</h1>
				<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
					<?php
					do_action('acf/input/admin_head');
					do_action('acf/input/admin_footer');

					acf_form([
						'post_id'       => 'options',
						'field_groups'  => false,
						'fields'        => $this->fields_mapping,
						'form'          => false
					]);
					?>

					<input type="hidden" name="action" value="interlinker_mapping">
					<?php wp_nonce_field('interlinker_mapping_nonce'); ?>
					<p>
						<button name="save_map" type="submit" class="button button-primary" style="margin-right: 40px;">Save</button>
						<button name="start_map" type="submit" class="button button-primary">🔍 Start Mapping</button>
					</p>
					<?php
						if(file_exists(INTERLINKER_UPLOADS_DIR_PATH.'/mappinglog.txt')){
							echo '<p><a target="_blank" href="'.INTERLINKER_UPLOADS_DIR_URL.'/mappinglog.txt" class="button button-secondary" style="margin: 40px 0 0 10px">View Mapping Report</a></p>';
						}
					?>
				</form>

			</div>


			<hr style="margin-top: 50px;border:none;border-top:1px solid #4e4e4e;">


			<div class="form-wrap" style="margin-top: 50px;">
				<h1>Global settings</h1>
				<form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
					<?php
					do_action('acf/input/admin_head');
					do_action('acf/input/admin_footer');

					acf_form([
						'post_id'       => 'options',
						'field_groups'  => false,
						'fields'        => $this->fields_global,
						'form'          => false
					]);
					?>

					<input type="hidden" name="action" value="interlinker_global">
					<?php wp_nonce_field('interlinker_global_nonce'); ?>
					<p>
						<button type="submit" class="button button-primary">🔍 Save</button>
					</p>
				</form>

			</div>

		</div>
	    <?php
	}


	/**
	 * Handles the form submission for Analisis
	 *
	 * @return void
	 */
	public function interlinker_analisis_form() {
	    if ( ! current_user_can('manage_options') || ! check_admin_referer('interlinker_analyze_nonce') ) {
	        wp_die('Access denied');
	    }

		$start_time = time();

	    if ( isset($_POST['acf']) && is_array($_POST['acf']) ) {
	        foreach ($_POST['acf'] as $field_key => $value) {
	            update_field($field_key, sanitize_textarea_field($value), 'options');
	        }
	    }

		$helpers = new Interlinker_Helper();

	    $field_keys = get_field($this->fields_byname['analise_kewords'], 'option');
		$arr_keys_result = $this->analise_site_by_keys($field_keys);

		if(!empty($arr_keys_result)){
			$helpers->generate_extend_csv($arr_keys_result);
			wp_redirect( admin_url('tools.php?page=interlinker&status=success-analisis&time=' . $helpers->interlinker_get_elapsed_time($start_time)) );
		}else{
			wp_redirect( admin_url('tools.php?page=interlinker&status=error-analisis') );
		}

	    exit;
	}


	/**
	 * Handles the form submission for Mapping
	 *
	 * @return void
	 */
	public function interlinker_mapping_form() {
	    if ( ! current_user_can('manage_options') || ! check_admin_referer('interlinker_mapping_nonce') ) {
	        wp_die('Access denied');
	    }
		$start_time = time();

	    if ( isset($_POST['acf']) && is_array($_POST['acf']) ) {
	        foreach ($_POST['acf'] as $field_key => $value) {
				update_field($field_key, $value, 'options');
	        }
	    }

		if(isset($_POST['save_map']) && !isset($_POST['start_map'])) {
			wp_redirect( admin_url('tools.php?page=interlinker&status=success-mapping_save') );
			exit;
		}

		$helpers = new Interlinker_Helper();
		$mapping_arr = [];

		//Get all posts
		$limit_posts=0;

		if(!empty($this->fields_byname['maximum_posts'])){
			$limit_posts = (int) get_field($this->fields_byname['maximum_posts'], 'option');
		}
		$arr_posts = $helpers->get_site_posts($limit_posts, true);
		if(empty($arr_posts)) return;

		//Get authors data
		$json_authors_data = get_field( $this->fields_byname['mapping_authors_json'], 'option' );
		if(empty($json_authors_data)){
			error_log(date("Y-m-d H:i:s" , time()) . "[InterLinker] Author keys is empty");
			wp_redirect( admin_url('tools.php?page=interlinker&status=error-mapping') );
			exit;
		}
		$mapping_arr['authors'] = json_decode($json_authors_data, true);

		//Get Phrases Expanders
		$expanders_keys = get_field( $this->fields_byname['mapping_phrases_expanders'], 'option' );
		if(empty($expanders_keys)){
			error_log(date("Y-m-d H:i:s" , time()) . "[InterLinker] Expanders keys is empty");
			wp_redirect( admin_url('tools.php?page=interlinker&status=error-mapping') );
			exit;
		}
		$mapping_arr['expanders'] = $helpers->extract_keys($expanders_keys);;

		//Transform keywords
		$mapping_arr['keys_result'] = $helpers->build_combined_author_expansions( $mapping_arr['authors'], $mapping_arr['expanders'] );
		if(empty($mapping_arr['keys_result'])){
			error_log(date("Y-m-d H:i:s" , time()) . "[InterLinker] Result keys is empty");
			wp_redirect( admin_url('tools.php?page=interlinker&status=error-mapping') );
			exit;
		}


		//Get limit links per page
		$mapping_arr['limit_links_page'] = (int) get_field($this->fields_byname['mapping_max_anchors_per_page'], 'option');

		//Get limit anchors per site
		//$mapping_arr['limit_occurrences_site'] = (int) get_field($this->fields_byname['mapping_max_anchor_occurrences_per_site'], 'option');

		//Get templates
		$mapping_arr['templates'] = get_field($this->fields_byname['mapping_templates'], 'option');

		//Reset keys table
		$helpers->interlinker_clear_keys_table();

		//Reset mapping log
		if(file_exists(INTERLINKER_UPLOADS_DIR_PATH.'/mappinglog.txt')) unlink(INTERLINKER_UPLOADS_DIR_PATH.'/mappinglog.txt');

		//Reset templates counter
		delete_option(INTERLINKER_TEMPLATES_COUNTER_OPTION);


		//Start mapping
		foreach ($arr_posts as $post_id) {
			if(empty($post_id)) continue;

			//Count links on page now
			$count_cur_links = $helpers->count_internal_links(get_post_field( 'post_content', $post_id ));

			//Check limit links per page
			if( !empty($mapping_arr['limit_links_page']) && (int)$count_cur_links >= $mapping_arr['limit_links_page']){
				error_log(date("Y-m-d H:i:s" , time()) . "[InterLinker] Limit links per page reached for post: ".$post_id);
				continue;
			}

			//Statr mapping in content
			$res = $helpers->start_mapping_content($post_id, $mapping_arr, (int)$count_cur_links);
			if(!empty($res)) $this->keys_count += (int)$res;

//			if(empty($res)){
//				error_log(date("Y-m-d H:i:s" , time()) . "[InterLinker] Error mappin post: ID:".$post_id.' Url: '.get_permalink($post_id));
//			}

		}

		if(!empty($this->keys_count)){
			//Log mapping
			file_put_contents( INTERLINKER_UPLOADS_DIR_PATH.'/mappinglog.txt', date("Y-m-d H:i:s" , time()) . ' Count Keys:'.$this->keys_count . "\n", FILE_APPEND );
		}

		wp_redirect( admin_url('tools.php?page=interlinker&status=success-mapping&time=' . $helpers->interlinker_get_elapsed_time($start_time)) );
	    exit;
	}

	/**
	 * Parses the fields and extracts the keys for internal linking analysis.
	 * @param string|array $keys
	 *
	 * @return array
	 */
	public function analise_site_by_keys($keys) {
		if(empty($keys)) {
			error_log(date("Y-m-d H:i:s" , time()) . "[InterLinker] Field keys is empty");
			return array();
		}
		$helpers = new Interlinker_Helper();
		$fkeys_arr = $helpers->extract_keys($keys);
		$limit_posts = (int) get_field($this->fields_byname['maximum_posts'], 'option');
		$posts_arr = $helpers->get_site_posts($limit_posts);


		if(empty($posts_arr)) {
			error_log(date("Y-m-d H:i:s" , time()) . "[InterLinker] Posts for parsing not found");
			return array();
		}

		$arr_result = [];
		foreach ($posts_arr as $post) {
			$post_id = $post->ID;
			$anchors = $helpers->get_anchors($post, true, $fkeys_arr);
			if(empty($anchors)) continue;
			$arr_result[get_permalink($post_id)] = ['anchors' => $anchors, 'title' => $post->post_title];
		}

		return $arr_result;

	}

	/**
	 * Handles the form submission for Global settings
	 *
	 * @return void
	 */
	public function interlinker_global_form() {
	    if ( ! current_user_can('manage_options') || ! check_admin_referer('interlinker_global_nonce') ) {
	        wp_die('Access denied');
	    }

	    if ( isset($_POST['acf']) && is_array($_POST['acf']) ) {
	        foreach ($_POST['acf'] as $field_key => $value) {
	            update_field($field_key, sanitize_textarea_field($value), 'options');
	        }
	    }
		wp_redirect( admin_url('tools.php?page=interlinker&status=success-global') );

	    exit;
	}



}