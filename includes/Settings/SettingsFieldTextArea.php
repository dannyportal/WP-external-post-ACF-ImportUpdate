<?php

namespace ExampleVendor\ExternalContentSyncImporter\Settings;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ExampleVendor\ExternalContentSyncImporter\Settings\SettingsField;

/**
 * HTML text area field type for plugin settings forms.
 * 
 * @package ExampleVendor\ExternalContentSyncImporter\Settings
 */
class SettingsFieldTextArea extends SettingsField {
    
    /**
     * Callback function to be passed to WordPress Settings API 
     * add_settings_field() method.  Gets called when the field's HTML 
     * is being added to the settings form. Outputs the appropriate HTML 
     * for the field type, including the input element and any other desired
     * HTML that should be with it.
     * 
     * @return void 
     */
    public function defaultCallback(): void
    {
        $value = get_option($this->id);

        ?>
            <p>
                <textarea
                    id="<?=htmlspecialchars($this->id)?>"
                    name="<?=htmlspecialchars($this->id)?>"
                    cols='80'
                    rows='8'
                    autocomplete="<?=htmlspecialchars($this->autocomplete)?>"
                    class="<?=htmlspecialchars($this->input_class)?>"
                    placeholder="<?=htmlspecialchars($this->placeholder)?>"
                    <?php if ($this->readonly) echo 'readonly'; ?>
                ><?=htmlspecialchars($value)?></textarea>
            </p>
            <strong><?=$this->escapeHtmlAllowAnchors($this->tip)?></strong>
        <?php
    }
}