<?php

namespace ActivatedInsights\HomeCareAgencyImporter\Settings;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

/**
 * Abstract class for creating the different types of input fields for the plugin 
 * settings form.  Extend this class and implement the defaultCallback to output 
 * the desired type of HTML input element template when rendering the form.
 * 
 * Organizationally, SettingsField instances are added to SettingsSection instances.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\Settings
 */
abstract class SettingsField {
    /**
     * Initialize the settings field instance.
     * 
     * @param string $id Slug-name to identify the field. Used in the 'id' attribute of tags.
     * @param string $label Label for the HTML input field.
     * @param string $tip Optional. Informational text to display under the input field.
     * @param string $min Optional. Minimum numeric value for numeric inputs, ignored for others.
     * @param string $max Optional. Maximum numeric value for numeric inputs, ignored for others.
     * @param string $input_class  Optional. Additional CSS class to add to the input element.
     * @param string $class Optional. Additional CSS class to add to the form element.
     * @param array $options Optional. Associative array where the keys are the display value and the values are the raw value that will be stored. Only used for some field types like select fields.
     * @param string $default_option Optional. Default value when the input is unset. Defaults is an empty string.
     * @param string $autocomplete Optional. Set to true to enable autocomplete for field types where this is relevent such as 'select'.
     * @param string $placeholder Optional. Placeholder text to display when the input field is empty.
     * @param string $section_id Optional. The slug-name of the section of the settings page in which to show the box. Default is 'default'.
     * @param bool $readonly Optional. Set to true to make the field read-only. Only used for some field types like text fields.
     * @return void 
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $tip = '',
        public string $min = '',
        public string $max = '',
        public string $input_class = '',
        public string $class = '',
        public array $options = ['Select a value' => ''],
        public string $default_option = '',
        public string $autocomplete = 'on',
        public string $placeholder = '',
        public string $section_id = 'default',
        public bool $readonly = false
    ) {
        $this->initialize();
    }

    /**
     * Optional initialization function that can be overriden in child classes
     * to run custom functionality during object initialization 
     * (e.g. during constructor);
     * 
     * @return void 
     */
    public function initialize(): void {}

    /**
     * Returns this instance as an associative array of all its 
     * properties. Used for passing to native WordPress functions
     * that require an array.
     * 
     * @return array 
     */
    public function toArray(): array {
        return (array) $this;
    }

    /**
     * Use to escape unsafe text that will be output onto the HTML page, 
     * but still allow anchor <a> tags (with only href and title attributes)
     * as an exception so links can still be displayed.
     * 
     * @param string $unescapedText Raw text to be HTML-escaped, similar to htmlspecialchars.
     * @return string Returns escaped text with anchor tags and their href/title attributes preserved.
     */
    protected function escapeHtmlAllowAnchors(string $unescapedText): string {
        $escapedText = wp_kses(
            content: $unescapedText, 
            allowed_html: [
                "a" => [
                    "href" => [],
                    "title" => []
                ]
            ]
        );

        return $escapedText;
    }

    /**
     * Callback function to be passed to WordPress Settings API 
     * add_settings_field() method.  Gets called when the field's HTML is being added
     * to the settings form. This should echo/output the appropriate
     * HTML for the field, including the input element and any other desired
     * HTML that should be with it.
     * 
     * @return void 
     */
    abstract public function defaultCallback(): void;
}