<?php

namespace ExampleVendor\ExternalContentSyncImporter\AdvancedCustomFields;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ExampleVendor\ExternalContentSyncImporter\AdvancedCustomFields\AcfExternalFunctions;
use ExampleVendor\ExternalContentSyncImporter\Services\LogLevel;
use ExampleVendor\ExternalContentSyncImporter\Services\LogService;
use DateTime;

/**
 * Sync service to handle the main process of mapping agency award
 * data to the WordPress custom fields defined by the Advanced Custom 
 * Fields Pro plugin. Uses a name/structure matching convention where 
 * custom fields with the match the name and position in the 
 * data structure as the incoming agency data will have those values
 * mapped.  Repeater fields are used to represent nested arrays, and group
 * fields are used to represent nested objects.
 * 
 * @package ExampleVendor\ExternalContentSyncImporter\Services
 */
class AcfFieldSync {
    /**
     * URL of the WordPress admin page where Field Groups are managed.
     */
    const FIELD_GROUPS_URL = '/wp-admin/edit.php?post_type=acf-field-group';

    /**
     * Plugin settings field ID that will store the root
     * Advanced Custom Fields field group used for mapping agency data.
     */
    const SETTINGS_BASE_FIELD_GROUP_ID = 'ecs_customfield_base_group';

    /**
     * Plugin settings field ID that will store the Advanced Custom Fields
     * field that will act as the unique identifier for agencies.
     */
    const SETTINGS_UNIQUEID_FIELD_ID = 'ecs_customfield_uniqueid_field';

    /**
     * Plugin settings field ID that will store the Advanced Custom Fields
     * field that will act as the base URL for agency logos.
     */
    const LOGO_BASE_URL_FIELD_ID = 'ecs_customfield_logo_base_url';

    /**
     * Initialize the agency custom field sync.
     * 
     * @param AcfEntityModel $entityModel Data model representing an individual agency from the agency data to import.
     * @param AcfFieldIndex $fieldIndex Field index of the custom fields where the agency data will be mapped.
     * @param int $postId WordPress post ID associated with the agency model. Custom field values will be added in relation to this post.
     * @return void 
     */
    public function __construct(
        private AcfEntityModel $entityModel,
        private AcfFieldIndex $fieldIndex,
        private int $postId,
    ) {}

    /**
     * Return an array of Advanced Custom Fields plugin Field Groups, with
     * the title as the key and the ID as the value.  For use with the plugin
     * settings as a select (dropdown) widget to pick the base group.
     * 
     * @return array Associative array of field groups for use as the options of a SettingsFieldSelect instance.
     */
    public static function getFieldGroupsSelectOptions(): array {
        $fieldGroups = AcfExternalFunctions::acf_get_field_groups();
        $selectArray = [];

        // Format the output array the way the select (dropdown) input needs it.
        foreach($fieldGroups as $fieldGroup) {
            $fieldGroupTitle = $fieldGroup['title'] ?? '';
            $fieldGroupId = $fieldGroup['ID'] ?? '';

            if (!empty($fieldGroupTitle) AND !empty($fieldGroupId)) {
                $selectArray[$fieldGroupTitle] = $fieldGroupId;
            }
        }

        return $selectArray;
    }

    /**
     * Return an array of Advanced Custom Fields plugin Fields, with
     * the label as the key and the ID as the value.  For use with the plugin
     * settings as a select (dropdown) widget to pick from available custom
     * fields.
     * 
     * @return array Associative array of fields for use as the options of a SettingsFieldSelect instance.
     */
    public static function getFieldSelectOptions(): array {
        $fieldGroupId = get_option(self::SETTINGS_BASE_FIELD_GROUP_ID) ?: 0;
        $fields = AcfExternalFunctions::acf_get_fields($fieldGroupId);
        $selectArray = [];

        // Format the output array the way the select (dropdown) input needs it.
        foreach($fields as $field) {
            $fieldLabel = $field['label'] ?? '';
            $fieldId = $field['ID'] ?? '';

            if (!empty($fieldLabel) AND !empty($fieldId)) {
                $selectArray[$fieldLabel] = $fieldId;
            }
        }

        return $selectArray;
    }

    /**
     * Main mapping function used to parse the incoming agency data 
     * hierarchy and map the new values to their corresponding custom field
     * if it exists. This is the main function that should be called to start
     * the actual agency data mapping/updating process. 
     * 
     * @param array $agencyDataArray Associative array of agency data to import.
     * @return void 
     */
    public function syncAgencyFields() {
        $agencyData = $this->entityModel->toArray();
        $fieldIndexArray = $this->fieldIndex->toArray();
        
        // Set all the custom fields for the agency's post.
        foreach($agencyData as $agencyFieldName => $agencyFieldValue) {
            $customField = $fieldIndexArray[$agencyFieldName] ?? [];
            $this->setCustomFieldValue($this->postId, $customField, $agencyFieldValue);
        }
    } 

    /**
     * Set a new value for a specified custom field on a specified WordPress post.
     * Handles setting the data in the appropriate fashion for the custom field's type
     * including nested data for sub_fields.
     * 
     * @param int $postId Post ID that contains the custom field value being updated.
     * @param array $customField Full Advanced Custom Fields plugin field definition. See acf_get_field() method for details.
     * @param mixed $newValue New value for the custom field
     * @return void 
     */
    private function setCustomFieldValue(int $postId, array $customField, mixed $newValue): void {
        $customFieldKey = $customField['key'] ?? '';
        $customFieldType = $customField['type'] ?? '';
        
        if (!empty($postId) && !empty($customFieldKey) && !empty($customFieldType)) {
            // Remove all previous values
            AcfExternalFunctions::delete_field($customFieldKey, $postId);

            switch ($customFieldType) {
                case 'text':
                case 'number':
                case 'true_false':
                case 'group':
                case 'date_picker':
                case 'date_time_picker':
                    AcfExternalFunctions::update_field($customFieldKey, $newValue, $postId);
                    break;
                case 'repeater':
                    // New value should be a JSON array of objects.
                    // Insert a repeater row for each element (which should include nested child fields/arrays).
                    // Extra (unmapped) fields in the new rows are ignored and should not cause errors.
                    if (is_array($newValue)) {
                        foreach ($newValue as $newRow) {
                            AcfExternalFunctions::add_row($customFieldKey, $newRow, $postId);
                        }
                    }
                    break;
                default:
                    // Unknown/incompatible custom field type for primitive value
                    LogService::log(
                        __METHOD__,
                        LogLevel::ERROR,
                        "Unsupported field type of '$customFieldType' for ACF field key '$customFieldKey' attempted to map the value: " . json_encode($newValue),
                        false
                    );
            }
        }
    }
}