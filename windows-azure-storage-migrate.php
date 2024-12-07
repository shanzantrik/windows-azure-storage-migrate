<?php

/**
 * Plugin Name: Microsoft Azure Storage Migration for WordPress
 * Version: 2.0.0
 * Plugin URI: https://github.com/shanzantrik/windows-azure-storage-migrate.git
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

// Load plugin class files.
require_once 'includes/class-windows-azure-storage-migrate.php';
require_once 'includes/class-windows-azure-storage-migrate-runner.php';

/**
 * Returns the main instance of Windows_Azure_Storage_Migrate to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object Windows_Azure_Storage_Migrate
 */
function windows_azure_storage_migrate()
{
	$instance = Windows_Azure_Storage_Migrate::instance(__FILE__, '1.0.0');

	if (is_null($instance->runner)) {
		$instance->runner = Windows_Azure_Storage_Migrate_Runner::instance($instance);
	}

	return $instance;
}

windows_azure_storage_migrate();
