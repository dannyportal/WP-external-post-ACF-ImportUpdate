<?php

namespace ActivatedInsights\HomeCareAgencyImporter\Settings;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\Settings\SettingsField;

/**
 * HTML checkbox field type for plugin settings forms.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\Settings
 */
class SettingsFieldCheckbox extends SettingsField {
    
    /**
     * Callback function to be passed to WordPress Settings API 
     * add_settings_field() method.  Gets called when the field's HTML 
     * is being added to the settings form. Outputs the appropriate HTML 
     * for the field type, including the input element and any other desired
     * HTML that should be with it.
     * 
     * @return void 
     */
    public function defaultCallback(): void {
        $state = get_option($this->id) == 'on' ? 'checked' : '';
        
        ?>
            <p>
                <input 
                    type="checkbox"
                    name="<?=htmlspecialchars($this->id)?>"
                    id="<?=htmlspecialchars($this->id)?>"
                    <?=htmlspecialchars($state)?>
                    class="<?=htmlspecialchars($this->input_class)?>"
                >
            </p>
            <strong><?=$this->escapeHtmlAllowAnchors($this->tip)?></strong>
        <?php
    }
}