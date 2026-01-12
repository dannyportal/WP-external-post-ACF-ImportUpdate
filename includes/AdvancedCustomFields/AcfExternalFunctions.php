<?php

namespace ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\Services\LogLevel;
use ActivatedInsights\HomeCareAgencyImporter\Services\LogService;

/**
 * Service to handle interop and integration between this plugin and
 * the Advanced Custom Fields plugin that this depends on. Any calls
 * to Advanced Custom Fields functions should be done in this class
 * to minimize breakage/refactoring if they change their plugin.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\Services
 */
class AcfExternalFunctions {
    /**
     * USE THIS METHOD IN ALL WRAPPER FUNCTIONS ADDED TO THIS CLASS TO VERIFY
     * THAT THE FUNCTION EXISTS BEFORE CALLING IT! Handles creating a log and 
     * admin notice if the function is not available with troubleshooting 
     * information, and prevents an exception from being thrown and crashing 
     * the entire WordPress site.
     * 
     * @param string $functionName Name of the function to check, as a string.
     * @return bool Returns true if the function exists and is safe to call, or false if it is missing and should not be called.
     */
    protected static function acfFunctionExists(string $functionName): bool {
        if (function_exists($functionName)) {
            return true;
        }

        LogService::log(
            __METHOD__,
            LogLevel::ERROR,
            "The \"$functionName\" function from the Advanced Custom Fields PRO plugin cannot be found. This indicates that either the plugin is not installed/active and needs to be, or a change has occurred in the plugin and the call needs to be refactored to the new approach used by the plugin.",
            true
        );

        return false;
    }

    /**
     * Safety wrapper for the Advanced Custom Fields (ACF) plugin function 
     * acf_get_field_groups() that first verifies the function exists,
     * creating an admin notice/log if it does not. Prevents the site
     * from crashing if it is missing. The ACF documentation does not
     * define the nature of the $filter array, unfortunately.
     * 
     * --- ACF FUNCTION DOCUMENTATION: --- 
     * Returns an array of field_groups for the given $filter.
     *
     * @date    30/09/13
     * @since   5.0.0
     *
     * @param   array $filter An array of args to filter results by.
     * @return  array Returns an array of ACF field_groups for the given $filter.
     */
    public static function acf_get_field_groups(array $filter = []): array {
        $fieldGroups = [];

        if (self::acfFunctionExists('acf_get_field_groups')) {
            $fieldGroups = acf_get_field_groups($filter);
        }

        return $fieldGroups;
    }

    /**
     * Safety wrapper for the Advanced Custom Fields (ACF) plugin function 
     * acf_get_fields() that first verifies the function exists,
     * creating an admin notice/log if it does not. Prevents the site
     * from crashing if it is missing. The ACF documentation does not
     * define the nature of array params/returns, unfortunately.
     * 
     * --- ACF FUNCTION DOCUMENTATION: --- 
     * Returns an array of fields for the given $parent.
     *
     * @date    30/09/13
     * @since   5.0.0
     *
     * @param   int|string|array $parent The field group or field settings. Also accepts the field group ID or key.
     * @return  array Associative array of fields
     */
    public static function acf_get_fields(int|string|array $parent): array {
        $fields = [];

        if (self::acfFunctionExists('acf_get_field_groups')) {
            $fields = acf_get_fields($parent);
        }

        return $fields;
    }

    /**
     * Safety wrapper for the Advanced Custom Fields (ACF) plugin function 
     * update_field() that first verifies the function exists,
     * creating an admin notice/log if it does not. Prevents the site
     * from crashing if it is missing. 
     * 
     * --- ACF FUNCTION DOCUMENTATION: ---
     * This function will update a value in the database
     *
     * @since   3.1.9
     *
     * @param string $selector The field name or key.
     * @param mixed  $value    The value to save in the database.
     * @param mixed  $post_id  The post_id of which the value is saved against.
     *
     * @return boolean
     */
    public static function update_field(string $selector, mixed $value, mixed $post_id = false): bool {
        $result = false;
        
        if (self::acfFunctionExists('update_field')) {
            $result = update_field($selector, $value, $post_id);
        }

        return $result;
    }

    /**
     * Safety wrapper for the Advanced Custom Fields (ACF) plugin function 
     * delete_field() that first verifies the function exists,
     * creating an admin notice/log if it does not. Prevents the site
     * from crashing if it is missing. 
     * 
     * --- ACF FUNCTION DOCUMENTATION: ---
     * This function will remove a value from the database
     *
     * @since   3.1.9
     *
     * @param   $selector (string) the field name or key
     * @param   $post_id (mixed) the post_id of which the value is saved against
     *
     * @return  boolean
     */
    public static function delete_field(string $selector, mixed $post_id = false): bool {
        $result = false;
        
        if (self::acfFunctionExists('delete_field')) {
            $result = delete_field($selector, $post_id);
        }

        return $result;
    }

    /**
     * Safety wrapper for the Advanced Custom Fields (ACF) plugin function 
     * add_row() that first verifies the function exists,
     * creating an admin notice/log if it does not. Prevents the site
     * from crashing if it is missing. 
     * 
     * --- ACF FUNCTION DOCUMENTATION: ---
     * This function will add a row of data to a field
     *
     * @since   5.2.3
     *
     * @param   $selector (string)
     * @param   $row (array)
     * @param   $post_id (mixed)
     * @return  (boolean)
     */
    public static function add_row(string $selector, mixed $row = false, mixed $post_id = false): bool {
        $result = [];
        
        if (self::acfFunctionExists('add_row')) {
            $result = add_row($selector, $row, $post_id);
        }

        return $result;
    }

    /**
     * Safety wrapper for the Advanced Custom Fields (ACF) plugin function 
     * acf_get_field() that first verifies the function exists,
     * creating an admin notice/log if it does not. Prevents the site
     * from crashing if it is missing. 
     * 
     * --- ACF FUNCTION DOCUMENTATION: ---
     * Retrieves a field for the given identifier.
     *
     * @date    17/1/19
     * @since   5.7.10
     *
     * @param   int|string $id The field ID, key or name.
     * @return  array|false The field array.
     */
    public static function acf_get_field(int|string $id): array|false {
        $result = false;

        if (self::acfFunctionExists('acf_get_field')) {
            $result = acf_get_field($id);  
        }

        return $result;
    }

    /**
     * Safety wrapper for the Advanced Custom Fields (ACF) plugin function 
     * get_field() that first verifies the function exists, creating an admin 
     * notice/log if it does not. Prevents the site from crashing if it is 
     * missing. 
     * 
     * --- ACF FUNCTION DOCUMENTATION: ---
     * This function will return a custom field value for a specific field name/key + post_id.
     * There is a 3rd parameter to turn on/off formating. This means that an image field will not use
     * its 'return option' to format the value but return only what was saved in the database
     *
     * @since   3.6
     *
     * @param string  $selector     The field name or key.
     * @param mixed   $post_id      The post_id of which the value is saved against.
     * @param boolean $format_value Whether or not to format the value as described above.
     * @param boolean $escape_html  If we're formatting the value, make sure it's also HTML safe.
     *
     * @return mixed
     */
    public static function get_field($selector, $post_id = false, $format_value = true, $escape_html = false): mixed {
        $fieldValue = null;

        // Ensure the ACF function exists
        if (self::acfFunctionExists('get_field')) {
            $fieldValue = get_field($selector, $post_id, $format_value, $escape_html);
        }

        return $fieldValue;
    }
}