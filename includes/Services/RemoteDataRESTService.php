<?php

namespace ExampleVendor\ExternalContentSyncImporter\Services;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ExampleVendor\ExternalContentSyncImporter\Services\LogService;
use ExampleVendor\ExternalContentSyncImporter\Services\LogLevel;
use WpOrg\Requests\Exception\InvalidArgument;

/**
 * Service for fetching raw agency data from a remote REST service.
 * 
 * @package ExampleVendor\ExternalContentSyncImporter\Services
 */
class RemoteDataRESTService {

    /** 
     * Settings field ID for the current offset value. This is used to keep 
     * track of the offset value between requests so that the next request
     * can automatically pick up where the previous one left off.
     */
    const CURRENT_OFFSET_FIELD_ID = 'ecs_restapi_current_offset';

    /**
     * Settings field ID for the maximum number of results to fetch for each 
     * request from the REST service. Needs to be set such that each request
     * takes less than the maximum execution time allowed by the server,
     * typically 180 seconds for PHP op_chache defaults.
     */
    const MAX_RESULTS_PER_REQUEST_FIELD_ID = 'ecs_max_results_per_request';

    /**
     * Offset value that indicates all results have been fetched and the
     * agency sync process has completed.
     */
    const COMPLETED_OFFSET_VALUE = 0;

    /**
     * Raw agency data fetched from the REST service, decoded into an
     * assciative array
     * @var array
     */
    private array $agencyData;
    
    /**
     * REST API endpoint URL for the REST service providing agency data.
     * @var string
     */
    private string $restApiEndpoint;

    /**
     * HTTP request method to use when fetching agency data from the REST service.
     * @var string
     */
    private string $httpRequestMethod;

    /**
     * Optional additional HTTP headers to include in the request to the REST service.
     * @var array
     */
    private array $httpHeaders;

    /**
     * Timeout in seconds for the HTTP request to the REST service.
     * @var int
     */
    private int $timeoutSeconds;

    /**
     * Query string to include in the REST API request to the agency data service.
     * @var string
     */
    private string $queryString;

    /**
     * Current offset value for the REST API request to the agency data service.
     * Used to track which "page" of data the next request should fetch.
     * @var int
     */
    private int $currentOffset;

    /**
     * Maximum number of results to fetch for each request from the REST service.
     * Needs to be set such that each request takes less than the maximum execution
     * time of the server, typically 180 seconds for PHP op_cache defaults.
     * @var int
     */
    private int $maxResultsPerRequest;
    
    /**
     * Construct the RemoteDataRESTService with the necessary settings to fetch
     * the next batch of agency data.
     * 
     * @param string $token Auth token to use when fetching agency data.
     * @return void 
     */
    public function __construct(
        string $token
    ) {
        $this->restApiEndpoint = get_option('ecs_restapi_endpoint', '');
        $this->httpRequestMethod = get_option('ecs_restapi_http_method', 'GET');
        $this->httpHeaders = [
            'Authorization' => 'Bearer ' . $token,
            'Accept-Profile' => 'AgpAward',
            'Content-Profile' => 'AgpAward'
        ];
        $this->timeoutSeconds = get_option('ecs_restapi_timeout_seconds', 45); 
        $this->queryString = $this->removeWhiteSpaceFromString(
            get_option('ecs_restapi_query_string', '')
        );
        $this->currentOffset = get_option(self::CURRENT_OFFSET_FIELD_ID, 0);
        $this->maxResultsPerRequest = get_option(self::MAX_RESULTS_PER_REQUEST_FIELD_ID, 20);
    }

    /**
     * Main call to fetch agency data from the REST service, decoded into
     * an associative array.
     * 
     * @return array Associative array of agency data.
     * @throws InvalidArgument 
     */
    public function getAgencyData(): array {
        $agencyDataJson = $this->getAgencyDataResponse();
        $this->agencyData = $this->decodeAgencyDataJson($agencyDataJson) ?? [];
        
        return $this->agencyData;
    }

    /**
     * Update the current offset value in the database to reflect the next
     * offset value to use for the next request.
     * 
     * @return int New offset value.
     */
    public function updateCurrentOffset(): int {
        $resultCount = count($this->agencyData);

        $newOffset = ($resultCount < $this->maxResultsPerRequest) ?
            self::COMPLETED_OFFSET_VALUE :
            $this->currentOffset + $resultCount;

        update_option(self::CURRENT_OFFSET_FIELD_ID, $newOffset);

        return $newOffset;
    }

    /**
     * Decode the raw response from the agency data REST service from JSON 
     * into an associative array. Logs an error and creates an admin 
     * notification if there are any problems.
     * 
     * @param string $agencyDataJson 
     * @return null|array 
     */
    private function decodeAgencyDataJson(string $agencyDataJson): ?array {
        $agencyData = json_decode($agencyDataJson, true);

        if (
            !empty($agencyDataJson) &&
            (
                empty($agencyData) || 
                !is_array($agencyData)
            )
        ) {
            $errorMessage = 'There was a problem parsing the agency data response JSON (showing first 2048 chars): ' . substr($agencyDataJson, 0, 2048) . '...';
            LogService::log(__METHOD__, LogLevel::ERROR, $errorMessage, true);
        }

        return $agencyData;
    }

    /**
     * Removes any whitespace (space, tab, newline, carriage return) from a string.  Used
     * to prepare the query string value in case it has whitespace formatting
     * present (for viewing/editing purposes) that should be remove prior to use.
     * 
     * @param string $stringWithWhitespace 
     * @return string String with whitespace removed.
     */
    private function removeWhiteSpaceFromString(string $stringWithWhitespace): string {
        return preg_replace('/\s+/', '', $stringWithWhitespace);
    }

    /**
     * Fetch the raw agency data JSON from the REST service. Logs an error and creates an 
     * admin notification if there are any problems.
     * 
     * @return string 
     * @throws InvalidArgument 
     */
    private function getAgencyDataResponse(): string {
        $queryString = $this->queryString .
            '&limit=' . $this->maxResultsPerRequest .
            '&offset=' . $this->currentOffset;

        $fullUrl = $this->restApiEndpoint . '?' . $queryString;

        $response = wp_remote_request($fullUrl, [
            'method' => $this->httpRequestMethod,
            'timeout' => $this->timeoutSeconds,
            'headers' => $this->httpHeaders,
        ]);

        if (is_wp_error($response)) {
            $errorMessage = $response->get_error_message();
            LogService::log(__METHOD__, LogLevel::ERROR, $errorMessage, true);
            return '';
        }

        $responseBody = wp_remote_retrieve_body($response) ?? '';

        // 400-500 HTTP error response codes do not affect is_wp_error(). 
        // Check explicitly here so an error is still shown/logged.
        $httpResponseCode = wp_remote_retrieve_response_code($response);
        if ($httpResponseCode >= 400) {
            $errorMessage = 'HTTP Response Code ' . $httpResponseCode . ': Response Body | ' . $responseBody;
            LogService::log(__METHOD__, LogLevel::ERROR, $errorMessage, true);
            return '';
        }

        return $responseBody;
    }
}