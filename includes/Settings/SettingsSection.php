<?php

namespace ActivatedInsights\HomeCareAgencyImporter\Settings;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\Settings\SettingsField;

/**
 * Class for creating a section in a WordPress plugin settings form. Sections
 * contain the individual fields (SettingsField) where plugin settings will
 * be input/stored. Sections should organize fields into related groups
 * such as username/password/host that all go to the same service.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\Settings
 */
class SettingsSection {
    public function __construct(
        public string $id,
        public string $title,
        public string $menuSlug
    ) {
        // Add/register the settings section per the WordPress settings API
        add_settings_section(
            $this->id,
            $this->title,
            [$this, 'defaultCallback'],
            $this->menuSlug
        );
    }

    /**
     * Adds a settings field to this section and registers 
     * it using the WordPress Settings API.
     * 
     * @param SettingsField $field Field definition to add to the this section.
     * @return self Returns self to allow chaining multiple calls.
     */
    public function addSettingsField(SettingsField $field): self {
        add_settings_field(
            $field->id,
            $field->label,
            function(array $callbackArgs) use ($field) {
                $field->defaultCallback();
            },
            $this->menuSlug,
            $this->id,
            []
        );

        // Use the section/field id's as the option group/name for consistency.
        register_setting($this->menuSlug, $field->id);

        return $this;
    }

    /**
     * Default callback when rendering plugin setting sections.
     */
    public function defaultCallback() {}
}