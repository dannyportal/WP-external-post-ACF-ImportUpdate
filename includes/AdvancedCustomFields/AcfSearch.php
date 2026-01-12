<?php

namespace ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields;

use WP_Query;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

/**
 * Class AcfSearch
 * Handles search query optimization for the "agency" post type.
 * 
 * Key Features:
 * 1. Filters posts by the "award" taxonomy terms.
 * 2. Dynamically filters posts by custom fields like `hcai_State`.
 * 3. Allows searching by post title/content.
 * 4. Sorts posts by a precomputed `hcai_priority_order` field, ensuring posts with reviews are prioritized.
 * 5. Implements pagination for better performance.
 */
class AcfSearch {
    /**
     * Initializes the plugin functionality.
     * Hooks into WordPress to modify the main search query.
     */
    public static function initialize(): void {
        add_action('pre_get_posts', [self::class, 'optimized_query']);
        add_action('init', [self::class, 'companyid_url_rewrite']);
        add_filter('query_vars', [self::class, 'companyid_redirect_query_var']);
        add_action('template_redirect', [self::class, 'companyid_handle_request']);
    }

    /**
     * Adds support for going directly to an agency's page using the CompanyId in a
     * custom URL path segment, e.g. /companyid/12345
     * 
     * This is just the rewrite rule that parses out the CompanyId from the URL,
     * and should be used in conjunction with the `companyid_handle_request` method and
     * the `companyid_redirect_query_var` method.
     * 
     * @return void 
     */
    public static function companyid_url_rewrite(): void {
        add_rewrite_rule(
            '^companyid/([0-9]+)/?$',
            'index.php?companyid_redirect=$matches[1]',
            'top'
        );
    }

    /**
     * Adds the custom URL query variable companyid_redirect for getting the CompanyId
     * for the purpose of redirecting to the agency page (post).
     * 
     * @param mixed $vars 
     * @return mixed 
     */
    public static function companyid_redirect_query_var($vars) {
        $vars[] = 'companyid_redirect';
        return $vars;
    }

    /**
     * Handles the request for navigating to an agency's page using the
     * companyid_redirect query parameter in the URL by looking up the
     * CompanyId custom field value and then navigating to that post's permalink.
     * 
     * @return void 
     */
    public static function companyid_handle_request() {
        $companyId = get_query_var('companyid_redirect');
        if ($companyId) {
            $post = get_posts([
                'post_type'  => 'agency', // or your custom post type
                'meta_key'   => 'CompanyId',
                'meta_value' => $companyId,
                'numberposts' => 1,
            ]);
    
            if (!empty($post)) {
                wp_redirect(get_permalink($post[0]->ID), 301);
                exit;
            } else {
                // Optional: redirect to 404
                global $wp_query;
                $wp_query->set_404();
                status_header(404);
                nocache_headers();
            }
        }
    }

    /**
     * Optimizes the search query by applying filtering and sorting.
     *
     * @param WP_Query $query The main WordPress query object.
     * @return void
     */
    public static function optimized_query(WP_Query $query): void {
        // Ensure the query is a valid frontend search query
        if (!self::is_valid_search($query)) {
            return;
        }

        // Restrict results to the "agency" post type
        $query->set('post_type', 'agency');

        // Get the search term
        $search_term = $query->get('s');

        // Build the meta query
        $meta_query = [];

        // Add a meta query for the 'State' field if provided via query string
        if (!empty($_GET['hcai_State'])) {
            $state = sanitize_text_field($_GET['hcai_State']); // Sanitize user input
            $meta_query[] = [
                'key'     => 'State',
                'value'   => $state,
                'compare' => 'LIKE'
            ];
        }


        // Allow title search if the search term isn't numeric
        if (!empty($search_term)) {
            $query->set('s', $search_term); // Search titles and content
        } else {
            $query->set('s', ''); // Prevent duplicate search queries
        }


        // Apply the meta query only if conditions are met
        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }

        /**
         * Taxonomy Filtering:
         * - Filters posts to include only those with specific "award" taxonomy terms.
         */
        $tax_query = [
            [
                'taxonomy' => 'award',
                'field'    => 'slug',
                'terms'    => [
                    'employer-of-choice',
                    'provider-of-choice',
                    'leader-in-experience-formerly-leader-in-excellence',
                ],
                'operator' => 'IN', // Include posts matching any of these terms
            ],
        ];
        $query->set('tax_query', $tax_query);

        /**
         * Sorting:
         * - Sorts posts first by `hcai_priority_order` (higher values first).
         * - Falls back to sorting by `date` for posts with the same priority.
         */
        $query->set('meta_key', 'hcai_priority_order');
        $query->set('orderby', [
            'meta_value_num' => 'DESC', // Priority first
            'date'           => 'DESC', // Fallback to recent posts
        ]);

        // Limit results per page for pagination
        $query->set('posts_per_page', 10);

        /**
         * Debugging:
         * Logs query arguments and results for troubleshooting.
         */
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Search Term: ' . $search_term);
            error_log('Meta Query: ' . print_r($query->get('meta_query'), true));
            error_log('Posts Retrieved: ' . print_r($query->posts, true));
        }
    }

    /**
     * Validates if the current query is a frontend search query.
     *
     * @param WP_Query $query The main WordPress query object.
     * @return bool True if the query is a valid search query, false otherwise.
     */
    private static function is_valid_search(WP_Query $query): bool {
        return $query->is_search() && !is_admin() && $query->is_main_query();
    }
}

// Initialize the search customization
AcfSearch::initialize();

     