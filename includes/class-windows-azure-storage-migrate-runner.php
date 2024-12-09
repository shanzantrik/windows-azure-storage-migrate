<?php

/**
 * Plugin Name: Microsoft Azure Storage Migration for WordPress
 * Version: 2.0.0
 * Plugin URI: https://wordpress.org/plugins/windows_azure_storage_migrate/
 * Description: This will add the ability to migration existing media files to azure for Microsoft Azure Storage for WordPress. This requires the Microsoft Azure Storage for WordPress plugin.
 * Author: GTech.
 * Author URI: http://www.gtechme.com/
 *
 * Text Domain: windows_azure_storage_migrate
 * Domain Path: /lang/
 *
 * @category  WordPress_Plugin
 * @package   Microsoft Azure Storage Migration for WordPress/Runner
 * @author    GTech.
 * @copyright GTech.
 * @link      http://www.gtechme.com
 * @since 2.0.0
 */

if (! defined('ABSPATH')) {
	exit;
}

require_once(ABSPATH . 'wp-includes/pluggable.php');

/**
 * Settings class.
 */
class Windows_Azure_Storage_Migrate_Runner
{

	/**
	 * The single instance of Windows_Azure_Storage_Migrate_Runner.
	 *
	 * @var     object
	 * @access  private
	 * @since   1.0.0
	 */
	private static $_instance = null; //phpcs:ignore

	/**
	 * The main plugin object.
	 *
	 * @var     object
	 * @access  public
	 * @since   1.0.0
	 */
	public $parent = null;

	/**
	 * Prefix for plugin runner.
	 *
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $base = '';

	/**
	 * Add this near the top of the Windows_Azure_Storage_Migrate_Runner class
	 *
	 * @var     string
	 * @access  private
	 * @since   1.0.0
	 */
	private $option_name = 'wasm_last_migrated_position';
	private $processed_files_option = 'wasm_processed_files';
	private $uploads_dir;
	private $uploads_url;

	/**
	 * Constructor function.
	 *
	 * @since 1.0.0
	 *
	 * @param object $parent Parent object.
	 */
	public function __construct($parent)
	{
		$this->parent = $parent;

		$this->base = 'wam_';

		// Get WordPress uploads directory info
		$upload_dir = wp_upload_dir();
		$this->uploads_dir = $upload_dir['basedir'];
		$this->uploads_url = $upload_dir['baseurl'];

		// Add runner page to menu.
		add_action('admin_menu', array($this, 'add_menu_item'));

		add_action('wp_ajax_windows_azure_storage_migrate_media', array($this, 'windows_azure_storage_migrate_media'));
	}

	/**
	 * Add runner page to admin menu
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_menu_item()
	{
		if (current_user_can('manage_options')) {
			$page = $this->parent->_token;
			add_options_page(
				__('Microsoft Azure Migrate', 'windows-azure-storage-migrate'),
				__('Microsoft Azure Migrate', 'windows-azure-storage-migrate'),
				'manage_options',
				$page . '_page',
				array($this, 'runner_page')
			);
		}
	}

	/**
	 * Load runner page content.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function runner_page()
	{
		wp_register_script($this->parent->_token . '-runner-js', $this->parent->assets_url . 'js/runner' . $this->parent->script_suffix . '.js', array('jquery'), '1.0.0', true);
		wp_localize_script($this->parent->_token . '-runner-js', 'myAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
		wp_enqueue_script($this->parent->_token . '-runner-js');

		$disabled = empty(Windows_Azure_Helper::get_default_container()) || empty(Windows_Azure_Helper::get_account_name()) || empty(Windows_Azure_Helper::get_account_key());

		$this->windows_azure_storage_runner_preamble($disabled);

		echo '<div class="wrap" id="' . $this->parent->_token . '_runner">';
		echo '<div id="icon-options-general" class="icon32"><br/></div>';

		if (!Windows_Azure_Helper::get_use_for_default_upload()) {
			echo '<p>Unable to Migrate! </p>';
		} else {
			$nonce = wp_create_nonce("windows_azure_storage_runner_nonce");
			$total = array_sum((array) wp_count_attachments());
			$last_position = $this->get_last_migration_position();

			echo '<div id="responce"></div>';
			echo '<p class="submit">';
			echo '<input type="button" class="button submit button-primary azure-migrate-button" ' .
				'data-nonce="' . $nonce . '" ' .
				'data-total="' . $total . '" ' .
				'data-position="' . $last_position . '" ' .
				'value="' . __('Migrate Existing Media', 'windows-azure-storage') . '"' .
				disabled($disabled) . '/>';

			if ($last_position > 0) {
				echo ' <input type="button" class="button submit button-secondary azure-migrate-reset" ' .
					'value="' . __('Reset Migration Progress', 'windows-azure-storage') . '"' .
					'data-nonce="' . $nonce . '" />';
			}

			echo '</p>';

			if ($last_position > 0) {
				echo '<p class="description">' .
					sprintf(
						__('Migration will resume from position %d of %d', 'windows-azure-storage'),
						$last_position,
						$total
					) .
					'</p>';
			}
		}

		echo '</div>';
	}

	/**
	 * Preamble text on Microsoft Azure Storage plugin migrate media page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function windows_azure_storage_runner_preamble($disabled)
	{
		echo '<div class="wrap">';
		echo '<h2>';
		echo '<img src="' . esc_url(MSFT_AZURE_PLUGIN_URL . 'images/azure-icon.png') . '" alt="' . __('Microsoft Azure', 'windows-azure-storage') . '" style="width:32px">';
		esc_html_e('GTech Microsoft Azure Storage Migration for WordPress', 'windows-azure-storage');
		echo '</h2>';
		echo '<p>';
		if ($disabled) {
			esc_html_e('Please update you Microsoft Azure Storage for WordPress Setting before trying to migrate existing media.');
		} else {
			esc_html_e('Migrate your existing media files to your Microsoft Azure Storage.');
		}
		echo '</p>';
		echo '</div>';
	}

	/**
	 * A
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	function windows_azure_storage_migrate_media()
	{
		if (!wp_verify_nonce($_REQUEST['nonce'], "windows_azure_storage_runner_nonce")) {
			wp_send_json(array('type' => 'error', 'data' => 'Invalid request'));
			return;
		}

		$page = intval($_REQUEST["page"]);
		$processed_files = get_option($this->processed_files_option, array());

		// Get all files if not already scanned
		$all_files = get_option('wasm_all_files');
		if (empty($all_files)) {
			$all_files = $this->scan_uploads_directory();
			update_option('wasm_all_files', $all_files);
		}

		// Get current file
		if (isset($all_files[$page])) {
			$current_file = $all_files[$page];

			// Skip if already processed
			if (in_array($current_file, $processed_files)) {
				wp_send_json(array(
					'type' => 'warning',
					'data' => basename($current_file) . ' already migrated'
				));
				return;
			}

			// Upload to Azure
			if ($this->upload_to_azure($current_file)) {
				// Add to processed files
				$processed_files[] = $current_file;
				update_option($this->processed_files_option, $processed_files);

				// Update URLs in database
				$this->update_urls_in_database($current_file);

				wp_send_json(array(
					'type' => 'success',
					'data' => basename($current_file) . ' migrated'
				));
			} else {
				wp_send_json(array(
					'type' => 'error',
					'data' => 'Failed to migrate ' . basename($current_file)
				));
			}
		} else {
			// No more files to process
			delete_option('wasm_all_files');
			wp_send_json(array('type' => 'none'));
		}
	}

	private function scan_uploads_directory($dir = '')
	{
		$files = array();
		$scan_dir = $dir ? $dir : $this->uploads_dir;

		$items = scandir($scan_dir);
		foreach ($items as $item) {
			if ($item == '.' || $item == '..') continue;

			$path = $scan_dir . DIRECTORY_SEPARATOR . $item;
			if (is_dir($path)) {
				$files = array_merge($files, $this->scan_uploads_directory($path));
			} else {
				$files[] = str_replace($this->uploads_dir . DIRECTORY_SEPARATOR, '', $path);
			}
		}
		return $files;
	}

	private function upload_to_azure($file_path)
	{
		if (!file_exists($this->uploads_dir . DIRECTORY_SEPARATOR . $file_path)) {
			return false;
		}

		$azure_storage = Windows_Azure_Helper::get_storage_client();
		$container = Windows_Azure_Helper::get_default_container();

		try {
			// Upload file to Azure
			$azure_path = str_replace('\\', '/', $file_path);
			$content_type = mime_content_type($this->uploads_dir . DIRECTORY_SEPARATOR . $file_path);

			$azure_storage->putBlob(
				$container,
				$azure_path,
				$this->uploads_dir . DIRECTORY_SEPARATOR . $file_path,
				array('contentType' => $content_type)
			);

			return true;
		} catch (Exception $e) {
			error_log('Azure Upload Error: ' . $e->getMessage());
			return false;
		}
	}

	private function update_urls_in_database($file_path)
	{
		global $wpdb;

		$old_url = $this->uploads_url . '/' . str_replace('\\', '/', $file_path);
		$new_url = Windows_Azure_Helper::get_storage_url_prefix() . '/' . str_replace('\\', '/', $file_path);

		// Update posts content
		$wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->posts}
			SET post_content = REPLACE(post_content, %s, %s)",
			$old_url,
			$new_url
		));

		// Update post meta
		$wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->postmeta}
			SET meta_value = REPLACE(meta_value, %s, %s)
			WHERE meta_value LIKE %s",
			$old_url,
			$new_url,
			'%' . $wpdb->esc_like($old_url) . '%'
		));

		// Update options
		$wpdb->query($wpdb->prepare(
			"UPDATE {$wpdb->options}
			SET option_value = REPLACE(option_value, %s, %s)
			WHERE option_value LIKE %s",
			$old_url,
			$new_url,
			'%' . $wpdb->esc_like($old_url) . '%'
		));
	}

	/**
	 * Main WordPress_Plugin_Template_Settings Instance
	 *
	 * Ensures only one instance of WordPress_Plugin_Template_Settings is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see WordPress_Plugin_Template()
	 * @param object $parent Object instance.
	 * @return object WordPress_Plugin_Template_Settings instance
	 */
	public static function instance($parent)
	{
		if (is_null(self::$_instance)) {
			self::$_instance = new self($parent);
		}
		return self::$_instance;
	} // End instance()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone()
	{
		_doing_it_wrong(__FUNCTION__, esc_html(__('Cloning of WordPress_Plugin_Template_API is forbidden.')), esc_attr($this->parent->_version));
	} // End __clone()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup()
	{
		_doing_it_wrong(__FUNCTION__, esc_html(__('Unserializing instances of WordPress_Plugin_Template_API is forbidden.')), esc_attr($this->parent->_version));
	} // End __wakeup()

	/**
	 * Add this method to the class
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function update_migration_position($position)
	{
		update_option($this->option_name, $position);
	}

	/**
	 * Add this method to the class
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function get_last_migration_position()
	{
		return get_option($this->option_name, 0);
	}

	/**
	 * Add this method to the class
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function reset_migration_position()
	{
		delete_option($this->option_name);
	}
}
