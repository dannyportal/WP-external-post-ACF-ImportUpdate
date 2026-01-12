<?php

namespace ActivatedInsights\HomeCareAgencyImporter\Settings;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields\AcfFieldSync;
use ActivatedInsights\HomeCareAgencyImporter\Services\AgencyDataRESTService;
use ActivatedInsights\HomeCareAgencyImporter\Services\TaskService;

/**
 * Helper class for setting up custom WordPress plugin settings
 * that can be managed in the WordPress admin pages. This can include
 * host URLs, credentials, and other runtime data or environmental data that 
 * needs to be stored securely for the plugin to function.
 */
class Settings {
    private array $sections = [];
    
    public function __construct(
        /**
         * The text to be displayed in the title tags of the page when the menu
         * is selected.
         */
        private string $pageTitle = '',

        /**
         * Text to display for the menu link in the WordPress admin navigation
         * menu.
         */
        private string $menuTitle = '',

        /**
         * The capability required for this menu to be displayed to the user. 
         * For example: "read"
         */
        private string $capability = '',

        /**
         * The slug name to refer to this menu by. Should be unique for this 
         * menu page and only include lowercase alphanumeric, dashes, and 
         * underscores characters to be compatible with sanitize_key().
         */
        private string $menuSlug = '',

        /**
         * Optional. The URL to the icon to be used for this menu.
         * * Pass a base64-encoded SVG using a data URI, which will be colored to match
         *   the color scheme. This should begin with 'data:image/svg+xml;base64,'.
         * * Pass the name of a Dashicons helper class to use a font icon,
         *   e.g. 'dashicons-chart-pie'.
         * * Pass 'none' to leave div.wp-menu-image empty so an icon can be added via CSS.
         */
        private string $iconUrl = '',

        /**
         * Optional. The position in the menu order this item should appear.
         * Values above 100 will appear after the final separator at the bottom.
         * See https://developer.wordpress.org/reference/functions/add_menu_page/#menu-structure
         * for details on the possible values and what they mean.
         */
        private ?string $position = null,
    ) {
        add_action('admin_menu', [$this, 'createMenuPage']);
        add_action('admin_init', [$this, 'setSectionsAndFields']);
    }

    /**
     * Adds a WordPress admin menu link and page for the plugin settings.
     * Intended to be used as the callback argument for an
     * add_action('admin_menu', ...) function call.
     * 
     * @return void
     */
    public function createMenuPage(): void {
        add_menu_page(
            $this->pageTitle,
            $this->menuTitle,
            $this->capability,
            $this->menuSlug,
            [$this, 'showForm'],
            $this->iconUrl,
            intval($this->position)
        );
    }
    
    /**
     * Output (echo) the final plugin settings HTML form for display in the WordPress
     * admin page.
     * 
     * @return void
     */
    public function showForm(): void {
        // verify user permissions
        if (!current_user_can( 'manage_options' )) {
            return;
        }

        settings_errors();

        echo "<h1>" . htmlspecialchars($this->pageTitle) . "</h1><hr>";
        echo '<form method="post" action="options.php">';
	
        settings_fields($this->menuSlug);
        do_settings_sections($this->menuSlug);
		
        submit_button();
		echo '</form>';
	}

    /**
     * Creates/adds a new section to the settings form, and returns the new 
     * SettingsSection instance so that fields can be added via the returned 
     * object's addSettingsField() method. 
     * 
     * @param string $id Text identifier of the SettingsSection used for html and database field naming. Should be limited to alphanumerics, underscores, and hyphens.
     * @param string $title Displayed title of the section when show it in the HTML form.
     * @return SettingsSection New settings section instance, where additional methods can be called for adding fields, etc.
     */
    public function addSettingsSection(string $id, string $title): SettingsSection {
        $section = new SettingsSection(
            id: $id,
            title: $title,
            menuSlug: $this->menuSlug // Use this menuSlug instead of a parameter to ensure consistency.
        );

        array_push($this->sections, $section);
        return $section;
    }

    /**
     * Define and initialize all of the plugin settings to be shown on
     * the plugin setting form. This is where all plugin settings fields
     * should be added.
     * 
     * @return void 
     */
    public function setSectionsAndFields(): void {
        $this->addSettingsSection('ai_hcai_task', 'Task Management')
            ->addSettingsField(new SettingsFieldTask(
                id: TaskService::SETTINGS_RUN_TASK_ID,
                label: 'Manually Run a Task',
                tip: 'Select a task name below to manually run a task (after clicking Save). These can be used to run the sync or test/verify things. The tasks are defined in the TaskService class of this plugin (all public methods).',
            ))
            ->addSettingsField(new SettingsFieldPassword(
                id: TaskService::TASK_AUTH_KEY_FIELD_ID,
                label: 'Task Auth Key',
                tip: 'Secret key that must be included in the URL to trigger the task from a web request (e.g. cron job using curl). This is to prevent unauthorized task running.',
                placeholder: 'my_secret_key'
            ))
            ->addSettingsField(new SettingsFieldText(
                id: 'ai_hcai_task_last_import_start_date',
                label: 'Last Import Start Date',
                tip: 'The timestamp of when the most recent import of agency data was started. This value is automatically updated by the plugin and should not be manually changed.',
                placeholder: 'No successful import yet',
                readonly: true
            ))
            ->addSettingsField(new SettingsFieldText(
                id: 'ai_hcai_task_last_successful_import_date',
                label: 'Last Import Complete Date',
                tip: 'The timestamp of when the last complete import of agency data was successfully synced. This value is automatically updated by the plugin and should not be manually changed.',
                placeholder: 'No successful import yet',
                readonly: true
            ))
            ->addSettingsField(new SettingsFieldNumber(
                id: AgencyDataRESTService::CURRENT_OFFSET_FIELD_ID,
                label: 'Current Offset',
                tip: 'Current offset value used when fetching agency data from the REST API. A value of 0 indicates the process is complete and a full new batch is ready to start. A value greater than 0 indicates a sync process was started and has not completed. This value is automatically updated by the plugin and should not be manually changed under typical conditions. Can be manually set to 0 to start over.'
            ))
        ;

        $this->addSettingsSection('ai_hcai_customfield', 'Advanced Custom Fields plugin mapping')
            ->addSettingsField(new SettingsFieldSelect(
                id: AcfFieldSync::SETTINGS_BASE_FIELD_GROUP_ID,
                label: 'Field Group to use',
                tip: '<a href="' . AcfFieldSync::FIELD_GROUPS_URL . '">Field group (in the Advanced Custom Fields plugin)</a> with the custom fields the agency data should be mapped to. Custom field names should exactly match the agency data field names. Nested arrays should be set as "Repeater" type fields, and nested objects should be set as "Group" type fields.',
                options: AcfFieldSync::getFieldGroupsSelectOptions()
            ))
            ->addSettingsField(new SettingsFieldSelect(
                id: AcfFieldSync::SETTINGS_UNIQUEID_FIELD_ID,
                label: 'Field to use as unique agency identifier.',
                tip: 'Field (in the Advanced Custom Fields plugin) that should be used to uniquely identify each agency. NOTE: It is NOT RECOMMENDED to use the agency\'s name due to names changing often and not always being unique.',
                options: AcfFieldSync::getFieldSelectOptions()
            ))
            ->addSettingsField(new SettingsFieldText(
                id: AcfFieldSync::LOGO_BASE_URL_FIELD_ID,
                label: 'Base URL for Logo Images',
                tip: 'Base URL to use when constructing the full URL for the logo image. The logo image URL will be constructed by appending the logo image field value to this base URL. If this value is left empty, the logo field in the agency data must contain the full URL.',
                placeholder: 'https://www.example.com'
            ))
        ;
        
        $this->addSettingsSection('ai_hcai_oauth', 'OpenID Connect Settings')
            ->addSettingsField(new SettingsFieldText(
                id: 'ai_hcai_openid_token_endpoint',
                label: 'OpenID Token Endpoint URL',
                tip: 'URL for the OpenID service token endpoint used for issuing access tokens. This service will acquire an access token to use when fetching agency data.',
                placeholder: 'https://sso.example.com/auth/realms/myrealm/protocol/openid-connect/token'
            ))
            ->addSettingsField(new SettingsFieldText(
                id: 'ai_hcai_openid_client_id',
                label: 'OpenID Client ID',
                tip: 'Client ID to use for authentication via an OpenID client_credentials grant for getting an access token that will be used for fetching the agency data from a remote service.',
                placeholder: 'username'
            ))
            ->addSettingsField(new SettingsFieldPassword(
                id: 'ai_hcai_openid_client_secret',
                label: 'OpenID Client Secret',
                tip: 'Client secret to use for authentication via an OpenID client_credentials grant for getting an access token that will be used for fetching the agency data from a remote service.',
                placeholder: 'password'
            ))
        ;

        $this->addSettingsSection('ai_hcai_restapi', 'Agency Data REST API Settings')
            ->addSettingsField(new SettingsFieldText(
                id: 'ai_hcai_restapi_endpoint',
                label: 'REST API Endpoint (no query string)',
                tip: 'URL for the REST API endpoint that will be used to retrieve agency data. Do not include the query string here.',
                placeholder: 'https://api.example.com/agency_endpoint'
            ))
            ->addSettingsField(new SettingsFieldSelect(
                id: 'ai_hcai_restapi_http_request_method',
                label: 'HTTP Request Method',
                tip: 'HTTP request method that will be used when calling the REST API endpoint URL to retrieve agency data (GET, POST, PUT, etc.).',
                options: [
                    'GET' => 'GET',
                    'POST' => 'POST',
                    'PUT' => 'PUT',
                    'HEAD' => 'HEAD'
                ],
                default_option: 'GET'                
            ))
            ->addSettingsField(new SettingsFieldNumber(
                id: 'ai_hcai_max_results_per_request',
                label: 'REST API Max Results Per Request',
                tip: 'Maximum number of results fetch for each request from the REST service per request. Needs to be set such that each request takes less than the maximum execution time allowed by the server, typically 180 seconds for PHP op_chache defaults.',
                placeholder: '20'
            ))
            ->addSettingsField(new SettingsFieldNumber(
                id: 'ai_hcai_restapi_timeout_seconds',
                label: 'REST API Timeout Seconds',
                tip: 'Timeout in seconds to use for the REST call, after which the request will be cancelled.',
                placeholder: '600'
            ))
            ->addSettingsField(new SettingsFieldTextArea(
                id: 'ai_hcai_restapi_query_string',
                label: 'Query String (URL-encoded)',
                tip: 'Optional URL-encoded query string to use when calling the REST API.<br/>Spaces/tabs/newlines are allowed for formatting large queries for easier editing and will be removed when the call is made.',
                placeholder: 'keya=valuea&keyb=valueb'
            ))
        ;
    }    
}