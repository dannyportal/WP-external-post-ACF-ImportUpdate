<?php

namespace ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

use ActivatedInsights\HomeCareAgencyImporter\Services\LogLevel;
use ActivatedInsights\HomeCareAgencyImporter\Services\LogService;

/**
 * Sync service used for importing/setting agency-related taxonomy terms
 * in WordPress.
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields
 */
class AcfTaxonomySync {

    /**
     * Taxonomy name to use for award taxonomy data. Pass this for the 
     * $taxonomy argument to WordPress taxonomy term functions such as
     * term_exists(), wp_insert_term(), wp_set_post_terms(), etc.  Also
     * acts as the slug for the taxonomy.
     */
    const TAXONOMY_AWARD = 'award';

    /**
     * Taxonomy name to use for award city data. Pass this for the 
     * $taxonomy argument to WordPress taxonomy term functions such as
     * term_exists(), wp_insert_term(), wp_set_post_terms(), etc.  Also
     * acts as the slug for the taxonomy.
     */
    const TAXONOMY_CITY = 'city';

    /**
     * Taxonomy name to use for award state/province data. Pass this for the 
     * $taxonomy argument to WordPress taxonomy term functions such as
     * term_exists(), wp_insert_term(), wp_set_post_terms(), etc.  Also
     * acts as the slug for the taxonomy.
     */
    const TAXONOMY_STATE = 'state';

    /**
     * Initialize the agency taxonomy sync.
     * 
     * @param AcfAgencyModel $agencyModel Agency data containing the taxonomy terms to be imported.
     * @param int $postId WordPress post ID associated with the agency model. Taxonomy terms will be added in relation to this post.
     * @return void 
     */
    public function __construct(
        private AcfAgencyModel $agencyModel,
        private int $postId
    ) {}

    /**
     * Updates/sets all the associated taxonomy terms for the agency from
     * the agency data.  Includes awards, location, etc.
     * 
     * @return void 
     */
    public function syncAgencyTaxonomies(): void {
        $awardTitles = $this->agencyModel->getAwardTitles();
        $awardTermIds = $this->setAgencyAwardTaxonomies($awardTitles);

        // Make sure this runs after setAgencyAwardTaxonomies() since
        // it has append=false when setting terms and wipes out any
        // that were set previously.
        $this->setAgencyAwardYearTaxonomies($awardTermIds);
        
        $state = $this->agencyModel->getState();
        $this->setAgencyTaxonomy($state, self::TAXONOMY_STATE);

        $city = $this->agencyModel->getCity();
        $this->setAgencyTaxonomy($city, self::TAXONOMY_CITY);
    }

    /**
     * Sets award year taxonomy terms for all of the agency's awards.
     * This handles adding a child taxonomy under the award taxonomy for
     * each individual year the award was received.
     * 
     * @param array $awardTermIds Array of award term IDs.
     * @return void 
     */
    private function setAgencyAwardYearTaxonomies($awardTermIds) {
        $awardYearTermIds = [];
        $awardInfo = $this->agencyModel->toArray()['AwardInfo'];

        foreach($awardInfo as $award) {
            $awardTitle = $award['Title'];
            $parentTermId = $awardTermIds[$awardTitle] ?? null;
            $recentAwardYears = explode(', ', $award['RecentAwardYears']);

            if (!empty($parentTermId)) {
                foreach ($recentAwardYears as $awardYear) {
                    $awardYearTermIds[] = $this->getOrCreateTaxonomyTerm($awardYear, self::TAXONOMY_AWARD, $parentTermId);
                }
            }
        }

        // Assign all the award term ID's to the agency's post
        wp_set_post_terms($this->postId, $awardYearTermIds, self::TAXONOMY_AWARD, true); // Assign terms to the post
    }

    /**
     * Sets award taxonomy terms for all of the agency's awards.
     * 
     * @param array $awardTitles Array of strings with the title of each award.
     * @return array 
     */
    private function setAgencyAwardTaxonomies(array $awardTitles): array {
        // This will collect the IDs of all the award taxonomy terms for
        // the agency so they can be assigned to the agency's post.
        $termIds = [];

        // Get the taxonomy term for each award the agency has
        foreach ($awardTitles as $awardTitle) {
            $termId = $this->getOrCreateTaxonomyTerm($awardTitle, self::TAXONOMY_AWARD);

            if ($termId) {
                $termIds[$awardTitle] = $termId;
            }
        }

        // Assign all the award term ID's to the agency's post
        wp_set_post_terms($this->postId, array_values($termIds), self::TAXONOMY_AWARD, false); // Assign terms to the post

        return $termIds;
    }

    /**
     * Generic function for setting a taxonomy item for the WordPress post ID
     * associated with this instance.
     * 
     * @param string $term The taxonomy term to add/update for the post.
     * @param string $taxonomy The target taxonomy to assign the term to.
     * @return void 
     */
    private function setAgencyTaxonomy(string $term, string $taxonomy): void {
        $termId = $this->getOrCreateTaxonomyTerm($term, $taxonomy);
        
        if (!empty($termId)) {
            $termResult = wp_set_post_terms($this->postId, $termId, $taxonomy, false);

            if (is_wp_error($termResult)) {
                LogService::log(
                    __METHOD__,
                    LogLevel::ERROR,
                    "Failed to set taxonomy term '$term' in taxonomy '$taxonomy' for post ID '$this->postId' | " . json_encode($this->agencyModel->toArray(), 0, 1),
                    false
                );
            }
        }
    }

    /**
     * Used to either get the existing taxonomy term ID or create it
     * if it does not yet exist.
     * 
     * @param int|string $term The term to check. Accepts term ID, slug, or name.
     * @param string $taxonomy The taxonomy name to use in association with the term.
     * @return null|int Returns null if the term does not exist. Returns the term ID if no taxonomy is specified and the term ID exists. Returns an array of the term ID and the term taxonomy ID if the taxonomy is specified and the pairing exists. Returns 0 if term ID 0 is passed to the function.
     */
    private function getOrCreateTaxonomyTerm(int|string $term, string $taxonomy, ?int $parent = null): ?int {
        // Check if a taxonomy term exists for the current agency award
        $termResult = term_exists($term, $taxonomy);

        // Create the taxonomy term if it does not yet exist
        if ($termResult === 0 || $termResult === null) {
            $termArgs = [];
            if ($parent !== null) {
                $termArgs['parent'] = $parent;
            }
            $termResult = wp_insert_term($term, $taxonomy, $termArgs);
        }
        
        if (is_wp_error($termResult)) {
            $termResult = null;
            LogService::log(
                __METHOD__,
                LogLevel::ERROR,
                "Failed to create taxonomy term '$term' in taxonomy '$taxonomy' | " . json_encode($this->agencyModel->toArray(), 0, 1),
                false
            );
        } elseif (is_array($termResult)) {
            // Existing term association was found, extract just the term ID
            $termResult = $termResult['term_id'] ?? null;
        }

        return $termResult;
    }

    /**
     * Utility function to delete all taxonomy terms associated with agency
     * import. Primarily for testing and development. Displays a notification
     * with the number of terms deleted/found when complete.
     * 
     * @return void 
     */
    public static function deleteAllTaxonomyTerms(): void {
        $taxonomies = [
            self::TAXONOMY_AWARD,
            self::TAXONOMY_CITY,
            self::TAXONOMY_STATE
        ];
        
        $terms = get_terms();
        $termsCount = count($terms);
        $termsDeletedCount = 0;

        if (is_wp_error($terms)) {
            LogService::log(__METHOD__, LogLevel::ERROR, 'Error getting terms: ' . $terms->get_error_message(), true);
        } elseif (!empty($terms)) {
            foreach($terms as $term) {
                foreach ($taxonomies as $taxonomy) {
                    if ($term->taxonomy == $taxonomy) {
                        // Delete the term and its associations
                        $isDeleted = wp_delete_term($term->term_id, $taxonomy);

                        if ($isDeleted) {
                            $termsDeletedCount++;
                        }
                    }
                }
            }
        }

        LogService::log(__METHOD__, LogLevel::WARNING, "Deleted $termsDeletedCount/$termsCount terms.", true);
    }
}