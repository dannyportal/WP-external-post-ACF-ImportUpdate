<?php

namespace ActivatedInsights\HomeCareAgencyImporter\Settings;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\Settings\SettingsField;

/**
 * HTML dropdown selector field type for plugin settings forms.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\Settings
 */
class SettingsFieldSelect extends SettingsField {
    
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
        $selected_value = get_option($this->id);
        
        ?>
            <p>
                <select 
                    type='text'
                    name="<?=htmlspecialchars($this->id)?>"
                    id="<?=htmlspecialchars($this->id)?>"
                    class="<?=htmlspecialchars($this->input_class)?>"
                >
                    <?php foreach ($this->options as $key => $value) {
                        $selected = ($value == $selected_value) ? 'selected' : ''; ?>
                        <option value="<?=htmlspecialchars($value)?>" <?=$selected?>><?=htmlspecialchars($key)?></option>
                    <?php } ?>
                </select>
            </p>
            <strong><?=$this->escapeHtmlAllowAnchors($this->tip)?></strong>
        <?php
    }
}