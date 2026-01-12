<?php

namespace ExampleVendor\ExternalContentSyncImporter\Settings;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ExampleVendor\ExternalContentSyncImporter\Settings\SettingsField;
use ExampleVendor\ExternalContentSyncImporter\Services\EncryptionService;

/**
 * HTML password input field type for settings plugin forms. Intended for 
 * secure/sensitive data that should be encrypted when stored.
 * 
 * @package ExampleVendor\ExternalContentSyncImporter\Settings
 */
class SettingsFieldPassword extends SettingsField {

    /**
     * Register some custom filters (callbacks) during object initialization 
     * to handle password encryption.
     * 
     * @return void 
     */
    public function initialize(): void {
        // Automatically encrypt value before saving in database
        add_filter('pre_update_option_' . $this->id, [$this, 'preUpdateCallback'], 10, 2);

        // Automatically decrypt value when retrieving from database
        add_filter('option_' . $this->id, [$this, 'preReadCallback']);
    }
    
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
        $value = get_option($this->id);
        
        ?>
            <p>
                <input
                    type='password'
                    name="<?=htmlspecialchars($this->id)?>"
                    value="<?=htmlspecialchars($value)?>"
                    class="<?=htmlspecialchars($this->input_class)?>"
                    placeholder="<?=htmlspecialchars($this->placeholder)?>"
                >
            </p>
            <strong><?=$this->escapeHtmlAllowAnchors($this->tip)?></strong>
        <?php
    }

    /**
     * Callback function to be used with the WordPress add_filter() 
     * `pre_update_option_{$option}` method to encrypt the password value 
     * before saving it in the database.
     * 
     * @param mixed $newValue New password value submitted by the plugin settings form.
     * @param mixed $oldValue Original password value when the form was loaded.
     * @return string  Returns encrypted password value to be stored in the database.
     */
    public function preUpdateCallback(string $newValue, string $oldValue): string {
        return EncryptionService::encrypt_reversible($newValue);
    }

    /**
     * Callback function to be used with the WordPress add_filter() 
     * `option_{$option}` method to decrypt the password value when 
     * retreiving it from the database.
     * 
     * @param mixed $encryptedValue Encrypted password value from the database.
     * @return string Returns decrypted password value from the database.
     */
    public function preReadCallback(string $encryptedValue): string {
        return EncryptionService::decrypt_reversible($encryptedValue);
    }
}