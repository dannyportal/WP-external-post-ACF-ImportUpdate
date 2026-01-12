<?php
/**
 * Plugin Name: HomeCare Agency Importer
 * Description: Imports and updates agency data from a REST service, setting logos as featured images only if they haven't been uploaded before, handling ACF fields for addresses, award images, trusted provider badges, and assigning multiple award taxonomy terms. Deletes entries not present in the latest JSON and updates existing posts. Includes immediate import upon activation and daily updates.
 * Version: 2.1
 * Author: Activated Insights
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

/**
 * PHP Autoloader function for the plugin to automatically search
 * for classes in the "includes" folder that are in the 
 * ActivatedInsights\HomeCareAgencyImport namespace. Removes the
 * need for complex require_once statements and instead the "use"
 * statement can be used to include classes by their qualified names.
 */
spl_autoload_register('ai_hcai_plugin_autoloader');
function ai_hcai_plugin_autoloader($className) {
    $rootNamespace = 'ActivatedInsights\\HomeCareAgencyImporter\\';
    
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

use ActivatedInsights\HomeCareAgencyImporter\Plugin;
use ActivatedInsights\HomeCareAgencyImporter\Services\TaskService;

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
