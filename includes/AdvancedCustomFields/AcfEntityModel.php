<?php

namespace ExampleVendor\ExternalContentSyncImporter\AdvancedCustomFields;

use ExampleVendor\ExternalContentSyncImporter\Services\LogLevel;
use ExampleVendor\ExternalContentSyncImporter\Services\LogService;
use DateTime;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

/**
 * Data class for storing a single agency's data. Uses getter methods
 * for retrieving specific fields required by the plugin logic for
 * things like updating attributes on posts and taxonomies. All
 * field mappings used for plugin logic should be used here so that
 * they can be defined and managed in one place, instead of having
 * a bunch of string array keys in the logic that could easily fall
 * out of sync or be hard to trace.
 * 
 * @package ExampleVendor\ExternalContentSyncImporter\AdvancedCustomFields
 */
class AcfEntityModel {
    /**
     * URL prefix to automatically add to agency logo URL values in the 
     * agency data being imported. Can be set to empty if the full URL is 
     * contained in the agency data logo URL field.
     */
    /**
     * When displaying an agency's awards earned, limits the number of years
     * displayed to the N most recent years defined by this value (to prevent
     * infinite growth in text summary situations).
     */
    private int $maxAwardYearsToShow = 8;

    /**
     * The expected maximum number of awards an agency would normally be
     * eligible to win in a given year. This is used to normalize how the
     * award count is weighted for sorting purposes.
     */
    private int $awardWeightTargetCount = 4;

    /**
     * This is a nasty temporary hack for rankings for the top 100 award
     * because we were only given 24 hours to implement this new award and
     * the award has a concept of rankings that none of the previous awards
     * had.  
     * 
     * The array keys are the CompanyId and the values are the ranking order.
     * 
     * TODO: TEMP_TOP100: Replace with with a proper field in the warehouse ASAP.
     * 
     * @var array
     */
    private array $tempTop100Rankings = [
        '12987' => '96',
        '5216' => '29',
        '4620' => '61',
        '7477' => '83',
        '4398' => '85',
        '7368' => '78',
        '5266' => '57',
        '735' => '15',
        '6903' => '44',
        '1798' => '60',
        '1734' => '53',
        '4158' => '38',
        '2603' => '35',
        '4247' => '76',
        '2663' => '59',
        '3072' => '82',
        '2195' => '41',
        '4170' => '37',
        '12581' => '42',
        '2979' => '14',
        '4430' => '2',
        '802' => '36',
        '3752' => '8',
        '12321' => '9',
        '4949' => '92',
        '12345' => '10',
        '3994' => '54',
        '7717' => '62',
        '2561' => '30',
        '2046' => '66',
        '870' => '87',
        '890' => '21',
        '4947' => '11',
        '12737' => '58',
        '322' => '71',
        '4530' => '7',
        '4572' => '65',
        '12232' => '67',
        '5817' => '100',
        '5815' => '27',
        '7187' => '93',
        '5125' => '4',
        '5127' => '95',
        '5123' => '34',
        '5134' => '22',
        '5114' => '28',
        '5144' => '46',
        '12977' => '79',
        '6012' => '5',
        '12213' => '43',
        '12211' => '86',
        '4854' => '70',
        '2339' => '94',
        '1711' => '33',
        '2369' => '72',
        '2368' => '45',
        '2432' => '88',
        '2317' => '73',
        '2732' => '52',
        '3028' => '84',
        '2490' => '50',
        '2441' => '40',
        '5559' => '1',
        '6994' => '19',
        '2938' => '63',
        '2348' => '97',
        '3354' => '6',
        '4749' => '48',
        '4499' => '69',
        '527' => '39',
        '709' => '51',
        '3676' => '31',
        '480' => '98',
        '460' => '12',
        '4890' => '25',
        '13177' => '80',
        '6333' => '68',
        '6330' => '75',
        '5476' => '55',
        '1407' => '24',
        '3788' => '47',
        '4507' => '74',
        '3380' => '17',
        '2025' => '49',
        '12511' => '20',
        '4088' => '90',
        '624' => '23',
        '3843' => '18',
        '332' => '26',
        '325' => '81',
        '3410' => '64',
        '777' => '13',
        '50' => '32',
        '396' => '99',
        '4914' => '56',
        '4026' => '89',
        '4452' => '91',
        '5013' => '77',
        '12417' => '16',
        '7262' => '3'
    ];

    /**
     * Initialize the agency model.
     * 
     * @param array $agencyData Associative array of the raw response with a single agency's data.
     * @return void 
     */
    public function __construct(
        private array $agencyData,
    ) {
        $this->agencyData['AwardInfo'] = $this->getAwardInfo();
        $this->agencyData['ReviewStarRatingAverage'] = $this->getReviewStarRatingAverage();
        $this->agencyData['FullAddress'] = $this->getFullAddress();
        $this->agencyData['SearchResultSortOrder'] = $this->getSearchResultSortOrder();

        // TODO: TEMP_TOP100: Remove this once the warehouse has a proper field for this
        $this->tempSetTop100Rankings();
    }

    /**
     * Get the agency data as an associative array. If using a specific field
     * in plugin logic, you should instead use the various get...() methods
     * so that code is not dependent on specific array key string values.
     * 
     * @return array Associative array of all the agency data fields/values.
     */
    public function toArray(): array {
        return $this->agencyData;
    }
    
    /**
     * Get the name of the agency to be shown for display purposes. This
     * should NOT be used as a unique identifier, since many agencies can have
     * the same name.
     * 
     * @return string Display name of the agency.
     */
    public function getAgencyName(): string {
        return $this->agencyData['Name'] ?? '';
    }

    /**
     * Get the summary description of the agency for display purposes.
     * 
     * @return string Text of the agency summary description.
     */
    public function getAgencyDescription(): string {
        return $this->agencyData['ListingDetail'][0]['Description'] ?? '';
    }

    /**
     * Get the full address of the agency as a single string, with all the
     * individual address parts separated by commas and a space.
     * 
     * @return string Returns full concatenated address string.
     */
    public function getFullAddress(): string {
        // Remove any empty parts from the value to be concatenated so there
        // are not multiple consecutive delimiters with no values between them
        // once concatenated.
        $addressParts = array_filter([
            $this->agencyData['Address1'],
            $this->agencyData['Address2'],
            $this->agencyData['City'],
            $this->agencyData['State'],
            $this->agencyData['Zip']
        ]);

        $fullAddress = implode(', ', $addressParts);
        return $fullAddress;
    }

    /**
     * Get the unique identifier value of the agency. This is used to
     * uniquely identify the agency when associating it with
     * WordPress posts or other agency-specific entities.
     * 
     * @return mixed 
     */
    public function getUniqueId(): mixed {
        return $this->agencyData[$this->getUniqueIdFieldName()] ?? 0;
    }

    /**
     * Get the name of the field that stores the unique identifier
     * for the agency. It is assumed to be one of the fields at the
     * top level of the agency data associative array, and is used
     * to retrieve the ID value in the getUniqueId() method.
     * 
     * @return string Name of the field containing the agency unique identifer.
     */
    public function getUniqueIdFieldName(): string {
        $customFieldId = get_option(AcfFieldSync::SETTINGS_UNIQUEID_FIELD_ID, '');
        $customField = AcfExternalFunctions::acf_get_field($customFieldId) ?: [];
        $customFieldName = $customField['name'] ?? '';
        return $customFieldName;
    }

    /**
     * Get the aggregate data for each award. This is generated data that
     * is created by going through the normalized award data and extracting
     * necessary aggregate data by award, instead of by award-per-year.
     * 
     * @return array 
     */
    private function getAwardInfo(): array {
        $unsortedAwards = [];
        $outputAwardInfo = [];
        $awards = $this->getAwards();
        
        // Do a preliminatr 1st pass to organize data by award alias 
        // (as the key) regardless of year earned
        foreach($awards as $award) {
            // Organize awards by using the alias as the key
            $awardAlias = $award['Award']['Alias'];
            // Extract the year from the date earned
            $awardYear = substr($award['DateEarned'], 0, 4);
            // Add the year to a temporary array of years won
            $unsortedAwards[$awardAlias]['AwardYear'][] = $awardYear;
            // Add the award title
            $unsortedAwards[$awardAlias]['Title'] = $award['Award']['Title'] ?? '';
        }

        // Do a second pass to aggregate the award data that has now
        // been organized by award alias, producing the final fields to be 
        // returned
        foreach($unsortedAwards as $awardAlias => $award) {
            // Sort award years in descending order (most recent first)
            rsort($award['AwardYear']);
            // Only show the most recent award years, defined by maxAwardYearsToShow
            $recentAwardYears = array_slice($award['AwardYear'], 0, $this->maxAwardYearsToShow);
            
            // Set RecentAwardYears as comma-separated list of years
            $outputAwardInfo[$awardAlias]['RecentAwardYears'] = implode(', ', $recentAwardYears);
            // Set boolean to flag the ward has been won (present or past)
            $outputAwardInfo[$awardAlias]['IsAwardWinner'] = !empty($recentAwardYears);
            // Set the award title for display purposes
            $outputAwardInfo[$awardAlias]['Title'] = $award['Title'];
        }

        return $outputAwardInfo;
    }

    /**
     * Temporary function to set the top 100 rankings for the top 100 award using
     * hard-coded data, until the warehouse can have a proper field added for this.
     * This is due to only being provided 24 hours to implement this new award.
     * 
     * TODO: TEMP_TOP100: Remove this once the warehouse has a proper field for this
     */
    private function tempSetTop100Rankings() {
        $top100ranking = $this->tempTop100Rankings[$this->getUniqueId()] ?? null;

        if (!empty($top100ranking)) {
            $this->agencyData['AwardInfo']['top100lie']['RankingOrder'] = $top100ranking;
        }
    }

    /**
     * Get the calculated average star rating value from the reviews.
     * 
     * @return null|float Returns averaged star rating value, or null if there are no ratings.
     */
    public function getReviewStarRatingAverage(): ?float {
        $reviews = $this->agencyData['ListingReview'] ?? [];
        $ratingAverage = null;
        $ratingSum = 0;
        $ratingCount = count($reviews);

        foreach($reviews as $review) {
            $ratingSum += $review['StarRating'] ?? 0;
        }

        $ratingAverage = ($ratingCount > 0) ?
            $ratingSum / $ratingCount :
            null;

        return $ratingAverage;
    }

    /**
     * Get the sort order value to be used when sorting search results.
     * Higher values should be displayed first in the search results.
     * 
     * @return int Integer value to be used for sorting search results.
     */
    public function getSearchResultSortOrder(): int {
        // Review weight is the average rating out of 5, so we need to convert it to a percentage
        // so that the scale is 0-100 for sorting purposes.
        $reviewAvg = $this->agencyData['ReviewStarRatingAverage'] ?? 0;
        $reviewWeight = round($reviewAvg / 5 * 100, 0);

        // Award weight is the percentage of the total awards won by the agency
        // normalized to a 0-100 scale for sorting purposes.
        $awardWeight = round(
            ($this->getCurrentAwardCount() / $this->awardWeightTargetCount) * 100, 
            0
        );

        $searchResultSortOrder = $reviewWeight + $awardWeight;
        return $searchResultSortOrder;
    }

    /**
     * Get the number of awards the agency for the current year. This is used 
     * to calculate the sort order for search results.
     * 
     * @return int Number of awards won by the agency in the most recent years.
     */
    private function getCurrentAwardCount(): int {
        $awardInfo = $this->agencyData['AwardInfo'] ?? [];
        $currentAwardCount = 0;

        foreach($awardInfo as $award) {
            if ($award['IsAwardWinner'] ?? false) {
                $currentAwardCount++;
            }
        }

        return $currentAwardCount;
    }

    /**
     * Get the full URL to be used for retreiving agency logo images.
     * 
     * @return string Gets the agency's specific logo image path, prefixed the the configured base URL in this class.
     */
    public function getLogoUrl(): string {
        $logoUrlValue = $this->agencyData['Logo'] ?? '';
        
        // Only prefix the logo URL if the original value isn't empty
        $logoUrl = (empty($logoUrlValue)) ? 
            '':
            (get_option(AcfFieldSync::LOGO_BASE_URL_FIELD_ID) ?: 'https://example.com') . $logoUrlValue;

        return $logoUrl;
    }

    /**
     * Get the list of awards the agency has earned. This is assumed to
     * be an array nested withing the agency data array.
     * 
     * @return array Associative array of the agency's earned awards.
     */
    public function getAwards(): array {
        return $this->agencyData['ListingAward'] ?? [];
    }

    /**
     * Get the city to be displayed in the agency's main address,
     * and to be used for agency taxonomy associations.
     * 
     * @return string Name of the city for the agency.
     */
    public function getCity(): string {
        return $this->agencyData['City'] ?? null;
    }

    /**
     * Get the state/province to be displayed in the agency's main
     * address, and to be used for agency taxonomy associations.
     * 
     * @return string Name of the state/province for the agency.
     */
    public function getState(): string {
        return $this->agencyData['State'] ?? null;
    }

    /**
     * Get an array of award titles for each of the awards earned
     * by the agency.  This uses the getAwards() method as a
     * starting point, and extracts the title value from each
     * awards for use with taxonomy assocations.
     * 
     * @return array Array of string values with each of the agency's earned award titles.
     */
    public function getAwardTitles(): array {
        return array_map(function ($award) {
            return $award['Award']['Title'] ?? null;
        }, $this->getAwards());
    }
}