<?php

namespace ActivatedInsights\HomeCareAgencyImporter\Services;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\Services\LogService;
use ActivatedInsights\HomeCareAgencyImporter\Services\LogLevel;
use WpOrg\Requests\Exception\InvalidArgument;

/**
 * Service for fetching an access token from an OpenID compatible
 * host (via password authentication), which can then be used to 
 * retrieve agency data from a REST service as the Auth: Bearer token
 * in the request headers.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\Services
 */
class OpenIDService {
    /**
     * Number of seconds before the actual token expiration that the token
     * should be considered expired. Should be set to allow enough time to
     * make a full REST request so the token does not expire mid-flight.
     */
    const TOKEN_EXPIRATION_OFFSET_SECONDS = 30;

    /**
     * Unique key to use with the WordPress transient() method to indicate
     * there is a valid OpenID access token from this service.
     */
    const TOKEN_EXPIRATION_TRANSIENT_KEY = 'ai_hcai_openid_token_expiration';

    /**
     * The fetched OpenID access token from the token endpoint.
     * @var string 
     */
    private ?string $accessToken = null;

    /**
     * The full token grant response from the token endpoint, parsed into a
     * PHP associative array. See https://www.oauth.com/oauth2-servers/access-tokens/access-token-response/ 
     * @var array
     */
    private array $tokenGrant = [];

    public function __construct(
        /**
         * Full URL of the OpenID token endpoint for the OpenID service.
         */
        private string $tokenEndpoint,

        /**
         * Value of the client_id parameter to use in the OAuth client_credentials grant request
         */
        private string $clientId,

        /**
         * Value of the client_secret parameter to use in the OAuth client_credentials grant request
         */
        private string $clientSecret,

        /**
         * Optional scope parameter to use in the OAuth client_credentials grant request. Whether
         * this is needed depends on the OpenID service being used.
         */
        private string $scope
    ) { }

    /**
     * Gets the access token from the OpenID service. Automatically handles
     * fetching a new token if one has not yet been fetched or the existing
     * token has expired.
     * 
     * @return ?string Access token from the OpenID service.
     */
    public function getAccessToken(): ?string {
        // Get a new token if the current one is expired/missing
        if (empty($this->accessToken) OR $this->isAccessTokenExpired()) {    
            
            $tokenGrantJson = $this->getTokenGrantJson();
            if (empty($tokenGrantJson)) { return null; }

            $this->decodeTokenGrantJson($tokenGrantJson);
            if (empty($this->tokenGrant)) { return null; }
            
            $this->setAccessTokenFromTokenGrant();
        }

        return $this->accessToken;
    }

    /**
     * Verify if the access token is valid for use or not.
     * 
     * @return bool Returns TRUE if the current access token is expired (or unset), or FALSE if the access token is still valid for use.
     */
    public function isAccessTokenExpired(): bool {
        $isExpired = empty(get_transient(self::TOKEN_EXPIRATION_TRANSIENT_KEY));
        return $isExpired;
    }

    /**
     * Sets the expiration for the current token per the currently stored token grant.
     * Uses the WordPress set_transient() method in combination with the token "expires_in"
     * value to make an expiring cache value, with a sufficient early offset to prevent
     * using a token that expires in the course of making a typical request.
     * 
     * @return void 
     */
    private function setTokenExpiration(): void {
        $expiresIn = $this->tokenGrant['expires_in'] ?? '';

        if (ctype_digit((string) $expiresIn)) {
            set_transient(
                self::TOKEN_EXPIRATION_TRANSIENT_KEY,
                'not_expired',
                $expiresIn - self::TOKEN_EXPIRATION_OFFSET_SECONDS
            );
        }
    }

    /**
     * Sets the stored access token by extracting it from the currently
     * stored tokenGrant value. Requires decodeTokenGrantJson() to be run
     * first in order to successfully retrieve a value. Logs an error and 
     * creates an admin notification if there are any problems.
     * 
     * @return void 
     */
    private function setAccessTokenFromTokenGrant(): void {
        $this->accessToken = $this->tokenGrant['access_token'] ?? null;

        if (empty($this->accessToken)) {
            LogService::log(__METHOD__, LogLevel::ERROR, 'No "access_token" value found in the token grant JSON response from OpenID token endpoint: ' . $this->tokenEndpoint . ' | Response Body: ' . json_encode($this->tokenGrant), true);
        }
    }

    /**
     * Sets the stored tokenGrant value by decoding the JSON response 
     * from an OpenID token grant request. Logs an error and creates an
     * admin notification if there are any problems.
     * 
     * @param string $tokenGrantJson Raw JSON response from the token endpoint, typically acquired via the getTokenGrantJson() method.
     * @return void 
     */
    private function decodeTokenGrantJson(string $tokenGrantJson): void {   
        $this->tokenGrant = json_decode($tokenGrantJson, true);

        if (
            empty($this->tokenGrant) OR 
            !is_array($this->tokenGrant)
        ) {
            $errorMessage = 'There was a problem parsing the OpenID token grant response JSON: ' . $tokenGrantJson;
            LogService::log(__METHOD__, LogLevel::ERROR, $errorMessage, true);
            $this->tokenGrant = [];
        } else {
            $this->setTokenExpiration();
        }
    }

    /**
     * Requests a token grant from the token endpoint and fetches the raw 
     * JSON response. Logs an error and creates an admin notification if 
     * there are any problems.
     * 
     * @return string JSON respons from the token endpoint of the full token grant. See https://www.oauth.com/oauth2-servers/access-tokens/access-token-response/
     * @throws InvalidArgument 
     */
    private function getTokenGrantJson(): string {
        $response = wp_remote_post($this->tokenEndpoint, [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => $this->scope
            ],
            'timeout' => '30'
        ]);
        
        if (is_wp_error($response)) {
            $errorMessage = $response->get_error_message();
            LogService::log(__METHOD__, LogLevel::ERROR, $errorMessage, true);
            return '';
        }

        $responseBody = wp_remote_retrieve_body($response);

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