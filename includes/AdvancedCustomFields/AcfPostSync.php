<?php

namespace ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\Services\LogLevel;
use ActivatedInsights\HomeCareAgencyImporter\Services\LogService;
use SodiumException;
use WP_Query;

/**
 * Sync service used for creating/updating the WordPress post that will be
 * used for displaying an agency's award data and other information.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields
 */
class AcfPostSync {
    /**
     * Wordpress post meta key value to use for storing the agency logo URLs
     * as image attachments. Used to search posts for their agency logo images 
     * by their URL.
     */
    const META_KEY_AGENCY_LOGO_URL = 'ai_hcai_agency_logo_url';

    /**
     * Wordpress post type to use for the agency posts created by this
     * plugin's sync process. All posts of this type will by assumed to be
     * created by this plugin so they can be separately managed from other
     * types of posts.
     */
    const POST_TYPE_AGENCY = 'agency';

    /**
     * Initialize the agency post sync with the agency's data.
     * 
     * @param AcfAgencyModel $agencyModel Data model for the agency whose data will be used to create/update the WordPress post.
     * @return void 
     */
    public function __construct(
        private AcfAgencyModel $agencyModel
    ) {}

    /**
     * Get a WordPress agency post by searching for a match on the agency 
     * unique identifier custom field designated by the plugin settings.
     * 
     * @return int WordPress post ID of the post that matched the unique identifier, or 0 if no match was found (0 indicates creating a new post when used for updating).
     */
    private function queryAgencyPostId(): int {
        $wpQueryArgs = [
            'post_type' => self::POST_TYPE_AGENCY,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'meta_key' => $this->agencyModel->getUniqueIdFieldName(),
            'meta_value' => $this->agencyModel->getUniqueId()
        ];

        $query = new WP_Query($wpQueryArgs);
        $post = $query->have_posts() ? $query->posts[0] : null;
        $postId = $post ? $post->ID : 0;
        
        return $postId;
    }

    /**
     * Create/update the WordPress post for a single agency from
     * the agency data. This just handles the basic post attributes
     * and does not map all the custom field data.
     * 
     * @return int Returns the WordPress post ID for the agency, or 0 if there were errors.
     */
    public function syncAgencyPost(): int {
        // Get existing agency post ID if one exists
        $existingPostId = $this->queryAgencyPostId();
        $uniqueIdFieldName = $this->agencyModel->getUniqueIdFieldName() ?: 'Foo';
        $uniqueIdFieldValue = $this->agencyModel->getUniqueId() ?: '0';

        // Create/update the agency post and set its main properties
        $wpPostParameters = [
            'ID' => $existingPostId,
            'post_title' => $this->agencyModel->getAgencyName(),
            'post_content' => $this->agencyModel->getAgencyDescription() . $this->getHiddenSearchContent(),
            'post_type' => self::POST_TYPE_AGENCY,
            'post_status' => 'publish',
            'meta_input' => [
                $uniqueIdFieldName => $uniqueIdFieldValue
            ]
        ];
        $newPostId = wp_insert_post($wpPostParameters, true);

        // Handle errors from creating/updating the post
        if (is_wp_error($newPostId) || empty($newPostId)) {
            $errorMessage = is_wp_error($newPostId) ? $newPostId->get_error_message() : 'Unknown error';
            LogService::log(
                __METHOD__,
                LogLevel::ERROR,
                "Failed to insert/update post: $errorMessage | " . json_encode($wpPostParameters),
                false
            );
            $newPostId = 0;
        } else {
            // TODO: Disabled due to slow performance on full initial sync where all logos have to be downloaded.
            // Re-enable once a solution is found such as a side job/queue.
            //$this->setAgencyPostFeaturedImage($this->agencyModel->getLogoUrl(), $newPostId);
        }
        
        return $newPostId;
    }

    /**
     * Get the hidden content to include in the agency post for search indexing.
     * This can include any custom field data that should be searchable in the
     * default WordPress search and not as a separate dedicated search field.
     * 
     * @return string Returns the hidden content to include in the post for search indexing.
     */
    protected function getHiddenSearchContent(): string {
        $hiddenContent = '<p class="hcai_hidden_search_content" style="line-height:0; overflow:hidden; padding:0; margin:0;">';
        $agencyModelArray = $this->agencyModel->toArray();

        // Include the full address for searching the agency's direct address
        $fullAddress = $agencyModelArray['FullAddress'] ?? '';
        $hiddenContent .= ' ' . $fullAddress;

        // Include all related Postal Codes for seaching service areas.
        $listingPostalCodes = $agencyModelArray['Listing_PostalCode'] ?? [];
        foreach($listingPostalCodes AS $listingPostalCode) {
            $postalCodeId = $listingPostalCode['PostalCodeId'] ?? '';
            $hiddenContent .= ' ' . $postalCodeId;
        }

        $hiddenContent .= '</p>';
        return $hiddenContent;
    }

    /**
     * Handles creating/updating a WordPress post attachment for an agency logo
     * image at an external URL, sideloading it into WordPress if it is missing,
     * and setting it as the specified agency post's featured image.
     * 
     * @param string $agencyLogoUrl External URL of the original agency logo image.
     * @param int $postId WordPress post ID for the agency that should have the logo set as the featured image.
     * @return void 
     * @throws SodiumException 
     */
    private function setAgencyPostFeaturedImage(string $agencyLogoUrl, int $postId): void {
        if (empty($agencyLogoUrl)) {
            return;
        }

        // Include dependencies for image handling
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $existingImages = $this->searchAgencyLogoImageByUrl($agencyLogoUrl);

        if ($existingImages->have_posts()) {
            // Found existing image, set as featured
            set_post_thumbnail($postId, $existingImages->posts[0]->ID);
        } else {
            // No existing image found, download from original URL and
            // upload to WordPress
            $attachmentId = media_sideload_image($agencyLogoUrl, $postId, null, 'id');

            if (!is_wp_error($attachmentId)) {
                set_post_thumbnail($postId, $attachmentId);
                // Save the original URL as post meta for future checks
                update_post_meta($attachmentId, self::META_KEY_AGENCY_LOGO_URL, esc_url_raw($agencyLogoUrl));
            } else {
                // Log error if upload failed
                LogService::log(
                    __METHOD__,
                    LogLevel::ERROR,
                    "Failed to upload image '$agencyLogoUrl' for post ID '$postId' | " . $attachmentId->get_error_message(),
                    false
                );
            }
        }
    }

    /**
     * Searches for WordPress attachment posts that have a meta key matching
     * the an agency logo URL (as found in the original agency data).
     * 
     * @param string $agencyLogoUrl URL of the agency logo from the original agency data (meaning the original external logo URL and NOT the WordPress attachment URL).
     * @return WP_Query Returns a WP_Query instance with the search results.
     */
    private function searchAgencyLogoImageByUrl(string $agencyLogoUrl): WP_Query {
        // Check if an image with the same self::META_KEY_AGENCY_LOGO_URL meta exists
        $query_args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => self::META_KEY_AGENCY_LOGO_URL,
                    'value' => esc_url_raw($agencyLogoUrl),
                    'compare' => '='
                ]
            ]
        ];

        return new \WP_Query($query_args);
    }

    /**
     * Utility function to delete all agency posts. Primarily for testing
     * and development purposes. Displays a notification with the number
     * of posts deleted when complete.
     * 
     * @return void 
     */
    public static function deleteAllAgencyPosts(): void {
        // Query all agency posts
        $args = [
            'post_type'      => self::POST_TYPE_AGENCY,
            'posts_per_page' => -1, // Get all posts
            'post_status'    => 'any', // Include all post statuses
            'fields'         => 'ids' // Only get post IDs to optimize the query
        ];
        $agencyPostIds = get_posts($args);
        $postsDeletedCount = 0;
    
        // Loop through each post ID and delete the post
        foreach ($agencyPostIds as $postId) {
            $isDeleted = wp_delete_post($postId, true);

            if ($isDeleted) {
                $postsDeletedCount++;
            }
        }

        LogService::log(__METHOD__, LogLevel::WARNING, "Deleted $postsDeletedCount agency posts.", true);
    }
}