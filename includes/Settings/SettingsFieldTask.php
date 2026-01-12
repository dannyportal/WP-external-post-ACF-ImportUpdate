<?php

namespace ActivatedInsights\HomeCareAgencyImporter\Settings;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\Settings\SettingsField;
use ActivatedInsights\HomeCareAgencyImporter\Services\TaskService;

/**
 * HTML dropdown selector that lists all the public methods
 * from the TaskService class. Used to select a task to be
 * manually run after the settings are saved. Used for 
 * testing, development, manual sync, etc.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\Settings
 */
class SettingsFieldTask extends SettingsFieldSelect {
    /**
     * Initialize the settings field instance.
     * 
     * @param string $id Slug-name to identify the field. Used in the 'id' attribute of tags.
     * @param string $label Label for the HTML input field.
     * @param string $tip Optional. Informational text to display under the input field.
     * @param string $input_class  Optional. Additional CSS class to add to the input element.
     * @param string $class Optional. Additional CSS class to add to the form element.
     * @param string $section_id Optional. The slug-name of the section of the settings page in which to show the box. Default is 'default'.
     * @return void 
     */
    public function __construct(
        string $id,
        string $label,
        string $tip = '',
        string $input_class = '',
        string $class = '',
        string $section_id = 'default'
    ) {
        parent::__construct(
            id: $id,
            label: $label,
            tip: $tip,
            min: '',
            max: '',
            input_class: $input_class,
            class: $class,
            options: $this->getTaskOptions(),
            default_option: '',
            autocomplete: 'on',
            placeholder: '',
            section_id: $section_id,
        );
    }

    /**
     * Gets an array of available task functions that can be selected
     * and manually run.
     * 
     * @return array Associative array of task functions with the method name as the value, and a user-frienldy formatted display version of the function name as the key.
     */
    private function getTaskOptions(): array {
        $taskOptions = ['Select a task...' => ''];
        $taskFunctions = get_class_methods(TaskService::class);

        foreach($taskFunctions as $taskFunction) {
            // Don't show the constructer as an option
            if ($taskFunction === '__construct') {
                continue;
            }

            $taskOptionKey = $this->addSpacesToPascalCase($taskFunction);
            $taskOptions[$taskOptionKey] = $taskFunction;
        }

        return $taskOptions;
    }

    /**
     * Utility function to take a PascalCase string value and separated
     * the capitalized word terms and underscores with spaces. Used
     * to make a nicer display version of function names.
     * 
     * @param string $input PascalCase input string to be formatted.
     * @return string Formatted output string with word terms and underscores separated with spaces.
     */
    private function addSpacesToPascalCase(string $input): string {
        // Add space between lowercase and uppercase letter
        $output = preg_replace('/([a-z])([A-Z])/', '$1 $2', $input);
        // Add space between two uppercase letters when followed by a lowercase letter
        $output = preg_replace('/([A-Z])([A-Z][a-z])/', '$1 $2', $output);
        // Replace underscores with space
        $output = str_replace('_', ' ', $output);
        // Upper-case first letter
        $output = ucfirst($output);

        return $output;
    }
}