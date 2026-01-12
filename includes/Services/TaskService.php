<?php

namespace ActivatedInsights\HomeCareAgencyImporter\Services;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields\AcfAgencyModel;
use ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields\AcfExternalFunctions;
use ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields\AcfFieldIndex;
use ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields\AcfFieldSync;
use ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields\AcfPostSync;
use ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields\AcfTaxonomySync;
use ActivatedInsights\HomeCareAgencyImporter\Services\LogLevel;
use ActivatedInsights\HomeCareAgencyImporter\Services\LogService;
use ActivatedInsights\HomeCareAgencyImporter\Plugin;
use WpOrg\Requests\Exception\InvalidArgument;

/**
 * Class for performing tests/simulations/verifications of the plugin
 * and its expected functionality.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\Services
 */
class TaskService {
    /**
     * Plugin settings field ID that will store the name of the
     * task function to run when one is manually selected.
     */
    const SETTINGS_RUN_TASK_ID = 'ai_hcai_acf_run_task';

    /**
     * URL query string key used to set a task function to run from
     * an external web request. The query string value should be the
     * name of a public method from this class. (e.g. ai_haci_task_function=runAgencyImport)
     */
    const TASK_FUNCTION_QUERY_VAR = 'ai_hcai_task_function';

    /**
     * URL query string key used to set the auth key for running a task
     * from an external web request. The query string value should match
     * the auth key stored in the settings.
     */
    const TASK_AUTH_KEY_QUERY_VAR = 'ai_hcai_task_auth_key';

    /**
     * Plugin settings field ID that will store the auth key for running
     * a task from an external web request.
     */
    const TASK_AUTH_KEY_FIELD_ID = 'ai_hcai_task_auth_key_field';

    /**
     * Plugin settings field ID that will store the date/time of the last
     * successful import task run.
     */
    const TASK_LAST_SUCCESSFUL_IMPORT_FIELD_ID = 'ai_hcai_task_last_successful_import_date';

     /**
     * Runs a task if one was selected on the plugin settings page,
     * and then resets the selected task value back to the default
     * so the task doesn't keep running each page load.
     */
    public function __construct() {
        // Get the name of the task function to run from the settings. This
        // will be set when an admin user manually selects a task to run from
        // the plugin management page.
        $taskFunction = get_option(TaskService::SETTINGS_RUN_TASK_ID, '');
        
        // Get the task function being submitted from the URL query string.
        // This will be set when the task is being run from a URL request,
        // such as a cron job making a curl call.
        $taskFunctionFromUrlQuery = trim(sanitize_text_field($_GET[TaskService::TASK_FUNCTION_QUERY_VAR] ?? ''));
        
        // Task function is set in the URL query string, set the task to run
        // if the request meets all the requirements.
        if (!empty($taskFunctionFromUrlQuery)) {
            LogService::setIsDirectEcho(true);

            // Get the auth key being submitted from the URL query string
            $taskAuthKeyFromUrlQuery = trim(sanitize_text_field($_GET[TaskService::TASK_AUTH_KEY_QUERY_VAR] ?? ''));

            // Get the auth key value from the settings
            $taskAuthKey = EncryptionService::decrypt_reversible(
                get_option(TaskService::TASK_AUTH_KEY_FIELD_ID, '')
            );

            if (empty($taskAuthKey)) {
                LogService::log(
                    __METHOD__,
                    LogLevel::ERROR,
                    'Task Auth Key is not set in the settings. Cannot run task.',
                    true
                );
                http_response_code(500); die;

            }

            if ($taskAuthKeyFromUrlQuery !== $taskAuthKey) {
                LogService::log(
                    __METHOD__,
                    LogLevel::ERROR,
                    'Task Auth Key in the URL query string does not match the settings value. Cannot run task.',
                    true
                );
                http_response_code(401); die;
            }

            $taskFunction = $taskFunctionFromUrlQuery;
        }

        if (!empty($taskFunction)) {
            add_action('init', [$this, $taskFunction]);
            add_action('init', function() {
                http_response_code(200);
                LogService::dieIfDirectEcho();
            });
            update_option($this::SETTINGS_RUN_TASK_ID, '');
        }
    }

    /**
     * Manually run the full agency data import process. Runs the next batch (page)
     * of data from the API. Should be called repeatedly until all agencies have been
     * synced, indicated the "BATCH COMPLETE" log message output.
     * 
     * @return void 
     * @throws InvalidArgument 
     */
    public function runAgencyImport(): void {
        update_option('ai_hcai_task_last_import_start_date', (new \DateTime())->format('Y-m-d H:i:s'));
        $startingOffset = get_option(AgencyDataRESTService::CURRENT_OFFSET_FIELD_ID, 0);

        $plugin = new Plugin();
        LogService::log(__METHOD__, LogLevel::INFO, 'Starting agency import action, initial offset: '. $startingOffset, true);

        $token = $plugin->getOpenIdToken();
        LogService::log(__METHOD__, LogLevel::INFO, 'STAGE 1/4: COMPLETE Fetch OpenID Token', true);

        $agencyDataRestService = new AgencyDataRESTService($token);
        $agencyDataResponse = $agencyDataRestService->getAgencyData($token);
        LogService::log(__METHOD__, LogLevel::INFO, 'STAGE 2/4: COMPLETE Get Agency Data', true);

        // Initialize the field index outside of the loop since it is the
        // same for all agencies
        $fieldIndex = new AcfFieldIndex();
        LogService::log(__METHOD__, LogLevel::INFO, 'STAGE 3/4: COMPLETE Get ACF Custom Field index', true);

        $agencyCount = count($agencyDataResponse);
        for($i = 0; $i < $agencyCount; $i++) {
            $agencyData = $agencyDataResponse[$i];
            $agency = new AcfAgencyModel($agencyData);

            $agencyPost = new AcfPostSync($agency);
            $postId = $agencyPost->syncAgencyPost();
            
            $fieldSync = new AcfFieldSync($agency, $fieldIndex, $postId);
            $fieldSync->syncAgencyFields();
            
            $taxonomy = new AcfTaxonomySync($agency, $postId);
            $taxonomy->syncAgencyTaxonomies();

            $syncedAgencyCount = $i + 1;
            LogService::log(__METHOD__, LogLevel::INFO, "STAGE 4/4: RUNNING Synced $syncedAgencyCount/$agencyCount agencies, ID " . $agency->getUniqueId(), false);
        }

        LogService::log(__METHOD__, LogLevel::INFO, 'STAGE 4/4: COMPLETE Agency import action complete.', true);

        $newOffset = $agencyDataRestService->updateCurrentOffset();
        if ($newOffset == AgencyDataRESTService::COMPLETED_OFFSET_VALUE) {
            update_option(TaskService::TASK_LAST_SUCCESSFUL_IMPORT_FIELD_ID, (new \DateTime())->format('Y-m-d H:i:s'));
            LogService::log(__METHOD__, LogLevel::INFO, 'BATCH COMPLETE: All agencies have been imported. Resetting offset.', true);
            http_response_code(206);
        } else {
            LogService::log(__METHOD__, LogLevel::INFO, 'BATCH CONTINUE: Next offset: ' . $newOffset, true);
            http_response_code(200);
        }

        LogService::dieIfDirectEcho();
    }

    /**
     * Tests the full process of fetching the OpenID access token using the current
     * plugin settings values, and then using the token as an 
     * Authorization: Bearer <token> to fetch agency data and output it to 
     * WordPress admin notification in the admin UI.
     * 
     * @param ?int $rowLimit Limit the number of agency listings to return.
     * @param ?int $logCharLimit Limit the number of characters to show in the log and admin notification.
     * @return void 
     * @throws InvalidArgument 
     */
    public function showFetchedAgencyData(): void {
        $logCharLimit = 40960;
        $plugin = new Plugin();
        $token = $plugin->getOpenIdToken();

        $agencyDataRestService = new AgencyDataRESTService($token);
        $agencyData = $agencyDataRestService->getAgencyData($token);
        
        $agencySample = substr(json_encode($agencyData), 0, $logCharLimit);
 
        // Log the response and output an admin notification
        LogService::log(
            __METHOD__,
            LogLevel::INFO,
            'Agency Sample: ' . $agencySample,
            true
        );
    }

    /**
     * Fetches the first agency's data and displays the AcfAgencyModel
     * data as parsed by the plugin, including all the generated/aggregate
     * fields that are not in the original raw data.
     * 
     * @return void 
     * @throws InvalidArgument 
     */
    public function showModelAgencyData(): void {
        $logCharLimit = 40960;
        $plugin = new Plugin();
        $token = $plugin->getOpenIdToken();

        $agencyDataRestService = new AgencyDataRESTService($token);
        $agencyData = $agencyDataRestService->getAgencyData($token)[0] ?? [];
        
        $agencyModel = (new AcfAgencyModel($agencyData))->toArray();
        $agencySample = substr(json_encode($agencyModel), 0, $logCharLimit);
 
        // Log the response and output an admin notification
        LogService::log(
            __METHOD__,
            LogLevel::INFO,
            'Agency Sample: ' . $agencySample,
            true
        );
    }

    /**
     * Call the global acf_get_field_groups() method from the
     * Advanced Custom Fields plugin and output the raw data to
     * see what the field group data looks like under the hood.
     * Outputs a log/admin notification with the result.
     * 
     * @return void 
     */
    public function showACFFieldGroups() {
        $fieldGroups = json_encode(AcfExternalFunctions::acf_get_field_groups());
        LogService::log(
            __METHOD__,
            LogLevel::INFO,
            'Agency Groups: ' . $fieldGroups,
            true
        );
    }

    /**
     * Test building the field heirarchy index that will be used for mapping
     * agency award data to custom fields. The main purpose is to put the field
     * name as the key, and the properties (including sub_fields) as the value
     * so that the agency data can be parsed and saved into a custom field with
     * a matching name / parent path if it exists.
     * 
     * @return void 
     */
    public function showACFFields() {
        $fieldIndex = (new AcfFieldIndex())->toArray();

        LogService::log(
            __METHOD__,
            LogLevel::INFO,
            'ACF Fields: ' . json_encode($fieldIndex),
            true
        );
    }
}