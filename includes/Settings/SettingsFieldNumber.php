<?php

namespace ActivatedInsights\HomeCareAgencyImporter\Settings;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\Settings\SettingsField;

/**
 * HTML numeric field type for plugin settings forms.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\Settings
 */
class SettingsFieldNumber extends SettingsField {
    
    /**
     * Callback function to be passed to WordPress Settings API 
     * add_settings_field() method.  Gets called when the field's HTML 
     * is being added to the settings form. Outputs the appropriate HTML 
     * for the field type, including the input element and any other desired
     * HTML that should be with it.
     * 
     * @return void 
     */
    public function defaultCallback(): void{
        $value = get_option($this->id);
        
        ?>
            <p>
                <input
                    type='number'
                    name="<?=htmlspecialchars($this->id)?>"
                    value="<?=htmlspecialchars($value)?>"
                    min="<?=htmlspecialchars($this->min)?>"
                    max="<?=htmlspecialchars($this->max)?>"
                    class="<?=htmlspecialchars($this->input_class)?>"
                    placeholder="<?=htmlspecialchars($this->placeholder)?>"
                    <?php if ($this->readonly) echo 'readonly'; ?>
                >
            </p>
            <strong><?=$this->escapeHtmlAllowAnchors($this->tip)?></strong>
        <?php
    }
}