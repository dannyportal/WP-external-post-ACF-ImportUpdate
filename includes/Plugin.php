<?php

namespace ExampleVendor\ExternalContentSyncImporter;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ExampleVendor\ExternalContentSyncImporter\Settings\Settings;
use ExampleVendor\ExternalContentSyncImporter\AdvancedCustomFields\AcfShortCodes;
use ExampleVendor\ExternalContentSyncImporter\AdvancedCustomFields\AcfSearch;
use ExampleVendor\ExternalContentSyncImporter\Services\RemoteDataRESTService;
use ExampleVendor\ExternalContentSyncImporter\Services\EncryptionService;
use ExampleVendor\ExternalContentSyncImporter\Services\LogLevel;
use ExampleVendor\ExternalContentSyncImporter\Services\LogService;
use ExampleVendor\ExternalContentSyncImporter\Services\OpenIDService;
use WpOrg\Requests\Exception\InvalidArgument;

/**
 * Main plugin class used to bootstrap the WordPress plugin.  Registers hooks, 
 * actions, events, constants, etc. that the plugin will use, and initializes 
 * other classes/utilities. 
 * 
 * Keeps the plugin-specific logic contained away from the global PHP space.
 * 
 * @package ExampleVendor\ExternalContentSyncImporter
 */
class Plugin {

    /**
     * Name to use for the WordPress action hook that will call the actual import 
     * process. Does not have to be any particular value, but it is good to include 
     * the plugin name and the method name for easier reading/auditing. Used as a 
     * class constant here instead of just a "magic string" that gets used 
     * everywhere for better code traceability.
     */
    const RUN_IMPORT_ACTION_HOOK = 'external-content-sync-importer-run-import';

    /**
     * Menu slug to use for plugin admin page URLs.
     */
    const MENU_SLUG = 'ai-ecsi';

    /**
     * Plugin settings class instance that is used to the field
     * definitions, UI form, and data for plugin configuration settings.
     */
    private Settings $settings;

    /**
     * Main plugin initialization. All the necessary WordPress actions and hooks for the
     * plugin are added/registered here.
     * 
     * @return void 
     */
    public function initialize(): void {
        // Initialize the plugin settings/fields
        $this->settings = new Settings(
            pageTitle: 'External Content Sync Importer - Plugin Settings',
            menuTitle: "HCA Importer",
            capability: 'read',
            menuSlug: Plugin::MENU_SLUG,
            iconUrl: 'dashicons-awards',
            position: 101 // Value >100 indicates after the final separator
        );

        // Initialize the plugin settings page and menu item
        add_action('admin_menu', [$this->settings, 'createMenuPage']);

        // Initialize search customizations that extend default search behavior
        AcfSearch::initialize();

        // Register short codes for custom HTML that can be used in pages
        AcfShortCodes::initialize();
    }

    /**
     * Event handler for when the plugin is activated in WordPress. Handles
     * setting up any scheduled/automatic plugin actions.
     * 
     * @return void 
     */
    public function onActivate(): void {
        LogService::log(__METHOD__, LogLevel::INFO, 'Plugin activation complete.', true);
    }

    /**
     * Event handler for when the plugin is deactivated in WordPress.  Handles
     * removing any scheduled plugin actions and other cleanup.
     * 
     * @return void 
     */
    public function onDeactivate(): void {
        LogService::log(__METHOD__, LogLevel::INFO, 'Plugin deactivation complete.', true);
    }

    /**
     * Gets the token from the OpenIDService using the current
     * plugin settings. This access token is to be used when making
     * REST calls to get the external data.
     * 
     * @return string Returns the access token that should be used when making external data REST calls.
     * @throws InvalidArgument 
     */
    public function getOpenIdToken(): string {
        // Get all the necessary options from the plugin settings
        $tokenEndpoint = get_option('ecs_openid_token_endpoint');
        $clientId = get_option('ecs_openid_client_id');
        $clientSecret = EncryptionService::decrypt_reversible(get_option('ecs_openid_client_secret'));
    
        // Get the OpenID access token
        $openIdService = new OpenIDService(
            $tokenEndpoint,
            $clientId,
            $clientSecret,
            'profile email roles'
        );
        
        return $openIdService->getAccessToken() ?? '';
    }
}