<?php
/**
 *  * Plugin Name: External Content Sync Importer
 *  * Description: Imports and updates content items from an external REST endpoint and synchronizes ACF fields on a scheduled basis.
 * Version: 2.1
 *  * Author: Danny Portal
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

/**
 * PHP Autoloader function for the plugin to automatically search
 * for classes in the "includes" folder that are in the 
 * ExampleVendor\ExternalContentSyncImporter namespace. Removes the
 * need for complex require_once statements and instead the "use"
 * statement can be used to include classes by their qualified names.
 */
spl_autoload_register('ecs_plugin_autoloader');
function ecs_plugin_autoloader($className) {
    $rootNamespace = 'ExampleVendor\ExternalContentSyncImporter\\';
    
    // Only load classes that are in the root namespace
    if (false === strpos($className, $rootNamespace)) {
        return;
    }

    // Replace the root namespace part of the class name with the includes path
    $className = str_replace($rootNamespace, __DIR__ . '/includes/', $className);

    // Replace the remaining backslashes with directory separators and add file extension.
    $classFile = str_replace('\\', '/', $className) . '.php';

    // Load class file directly
    require_once $classFile;
}

use ExampleVendor\ExternalContentSyncImporter\Plugin;
use ExampleVendor\ExternalContentSyncImporter\Services\TaskService;

/**
 * Initialize the plugin. Instantiating the class takes care of
 * running the intialization processes needed for the plugin.
 */
$plugin = new Plugin();
$plugin->initialize();

// Run tasks if they are manually selected from the plugin settings page
// or specified in the URL query string.
$taskService = new TaskService();
?>
