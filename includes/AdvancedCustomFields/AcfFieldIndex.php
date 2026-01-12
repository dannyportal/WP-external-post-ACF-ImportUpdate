<?php

namespace ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields\AcfExternalFunctions;

/**
 * Utility class used to build an associative array of all the Advanced
 * Custom Fields (and their properties) available for agency data import,
 * indexed by their field name. This allows incoming data to quickly find
 * the correct destination using a simple name/structure matching convention.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\Services
 */
class AcfFieldIndex {
    /**
     * Associative array containing the custom field definitions indexed by field name.
     * @var array
     */
    private array $fieldIndex;

    public function __construct() {
        // The field index should only include custom fields in the
        // designated field group in the plugin's settings.
        $baseFieldGroupId = get_option(AcfFieldSync::SETTINGS_BASE_FIELD_GROUP_ID) ?: 0;
        $this->fieldIndex = $this->buildFieldIndex($baseFieldGroupId);
    }

    /**
     * Get the associative array containing the custom field definitions 
     * indexed by field name.
     * 
     * See the buildFieldIndex() method for more details on the array structure.
     * 
     * @return array Associative array with the custom field definitions indexed by field name.
     */
    public function toArray(): array {
        return $this->fieldIndex;
    }

    /**
     * Builds an associative array of Advanced Custom Fields within a
     * specified field or field group. This is recursive and builds the
     * full heirarchy of sub_fields (child fields) for repeater fields
     * and other nested types. The output array has the field name as the
     * key, and all the field properties (including sub_fields) as the value.
     * For example:
     * 
     * "ListingDetail": {
     *  "ID": 4537,
     *  "label": "Listing Detail",
     *  "key": "field_66c8e55a96a21",
     *  "type": "repeater",
     *  "parent": 4038,
     *  ...
     *  "sub_fields": {
     *      "ListingDetailId": {
     *          "ID": 4538,
     *          "label": "ListingDetail ID",
     *          "key": "field_66c8e59696a22",
     *          "type": "number",
     *          "parent": 4537,
     *          "sub_fields": []
     *          ...
     *      },
     *      ...
     *  }
     * 
     * @param mixed $fieldOrFieldGroupId Either a field group's ID or key, or a field array from the acf_get_field() or acf_get_fields() method.
     * @return array Associative array of all child fields and their sub_fields, with their field name as the key.
     */
    private function buildFieldIndex(mixed $fieldOrFieldGroupId): array {
        $groupFields = AcfExternalFunctions::acf_get_fields($fieldOrFieldGroupId);
        $fieldIndex = [];

        foreach($groupFields as $field) {
            $field['sub_fields'] = $this->buildFieldIndex($field);
            $fieldIndex[$field['name']] = $field;
        }

        return $fieldIndex;
    }
}