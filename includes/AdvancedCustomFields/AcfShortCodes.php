<?php

namespace ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields;

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

/**
 * This class acts is where all custom Wordpress shortcodes for this plugin
 * should be added as static functions. Shortcodes allow adding custom
 * HTML to a post via PHP code that has access to the full Wordpress
 * API, allowing much greater flexibility beyond what the theme offers.
 * 
 * IMPORTANT: After adding a shortcode as a static function here, be sure
 * to register it in the Plugin::initialize() method via the add_shortcode()
 * method, or it will not be available for use.
 * 
 * See https://codex.wordpress.org/Shortcode_API
 * 
 * @package ActivatedInsights\HomeCareAgencyImporter\AdvancedCustomFields
 */
class AcfShortCodes {
    /**
     * Initializes all the functions in the class for use as filters/actions
     * in WordPress.  Add any add_filter or add_action calls here that register
     * any methods in this class. This function should be called once during
     * plugin initialization.
     * 
     * @return void 
     */
    public static function initialize(): void {
        add_shortcode('acf_phone_number_shortcode', [self::class, 'acf_phone_number_shortcode']);
        add_shortcode('acf_checklist', [self::class, 'acf_checklist']);
        add_shortcode('acf_google_maps_address', [self::class, 'acf_google_maps_address']);
        add_shortcode('acf_logo_image', [self::class, 'acf_logo_image']);
    }

    /**
     * Function to display an agency's logo image in the post content
     * using the ACF custom field data.
     * 
     * @return string 
     */
    public static function acf_logo_image(mixed $atts): string {
        global $post;
        $defaultLogo = '/sample_logo.png';
        $html = '';

        $attributes = shortcode_atts([
            'style' => '',
        ], (array) $atts);
        $imageCssStyle = $attributes['style'] ?? 'max-width: 100%; height: auto; object-fit:contain';
    
        // Check if we are in the correct post type
        if (!$post || $post->post_type !== 'agency') {
            // Use the default logo if not in a known agency post
            $logoImage = $defaultLogo;
        } else {
            // Retrieve the logo image URL path from ACF, or use default logo if empty
            $logoImage = AcfExternalFunctions::get_field('Logo', $post->ID) ?? $defaultLogo;
        }

        // Retrieve the logo base URL from the settings. If empty, 
        // the Logo field must contain the full URL.
        $logoBaseUrl = get_option(AcfFieldSync::LOGO_BASE_URL_FIELD_ID, '');

        // Create the HTML for the logo image
        $html .= '<img src="' . esc_url($logoBaseUrl . $logoImage) . '" alt="Agency Logo" style="' . esc_attr($imageCssStyle) . '">';
        return $html;
    }

    /**
     * WordPress shortcode for creating formatted telephone number HTML 
     * anchor tags for display.
     * 
     * @param mixed $atts User-defined attributes in the shortcode that get passed in as arguments. For this code, requires an 'acf_field_name' key specifying the custom field that contains the phone number.
     * @return string Returns the HTML to be rendered for the shortcode.
     */
    public static function acf_phone_number_shortcode(mixed $atts): string {
        // Extract shortcode attributes
        $atts = shortcode_atts(
            [
                'acf_field_name' => '', // Name of the ACF field
            ],
            $atts
        );

        // Get the current post ID
        $post_id = get_the_ID();

        // Fetch the phone number from the specified ACF field
        $phone_number = get_field($atts['acf_field_name'], $post_id);

        // Ensure the phone number exists
        if (!empty($phone_number)) {
            // Format the phone number
            $formatted_phone = preg_replace('/[^0-9]/', '', $phone_number); // Remove non-numeric characters
            if (strlen($formatted_phone) === 10) {
                $formatted_phone = substr($formatted_phone, 0, 3) . '.' .
                                substr($formatted_phone, 3, 3) . '.' .
                                substr($formatted_phone, 6);
            }

            // Return the formatted phone number as a clickable link
            return '<a class="phone-number-link" href="tel:' . esc_attr($phone_number) . '" style="font-family: Archivo, sans-serif; font-weight: 600; font-size: 20px;color:#78429A;">' . esc_html($formatted_phone) . '</a>';
        }

        // Fallback message if no phone number is found
        return '<p style="font-family: Archivo, sans-serif; font-weight: 600; font-size: 20px;color:#78429A;">Phone number unavailable</p>';
    }

    /**
     * WordPress shortcode for creating a checklist from a repeater
     * subfield. Doing this using native Avada had issues creating
     * such a checklist under a toggle, so this simplifies the process.
     * 
     * @param mixed $atts User-defined attributes in the shortcode that get passed in as arguments. For this code, requires an 'acf_repeater_field' key specifying the custom repeater field, and a 'acf_item_subfield' key specifying the subfield with checklist values to be displayed.
     * @return string Returns the HTML to be rendered for the shortcode.
     */
    public static function acf_checklist(mixed $atts): string {
        // Extract shortcode attributes with default values
        $atts = shortcode_atts(
            [
                'acf_repeater_field' => '', // Name of the repeater field
                'acf_item_subfield'  => '', // Name of the subfield within the repeater
            ],
            $atts
        );

        // Get the current post ID
        $post_id = get_the_ID();

        // Get the repeater field data
        $repeater_field = get_field($atts['acf_repeater_field'], $post_id);

        // Check if the repeater field exists and has data
        if ($repeater_field && is_array($repeater_field)) {
            $output = '<ul>';
            foreach ($repeater_field as $item) {
                if (!empty($item[$atts['acf_item_subfield']])) {
                    $output .= '<li>' . esc_html($item[$atts['acf_item_subfield']]) . '</li>';
                }
            }
            $output .= '</ul>';

            return $output;
        }

        // Provide custom fallback messages for specific fields
        switch ($atts['acf_repeater_field']) {
            case 'PayMethod':
                return '<p>Call for available payment methods.</p>';
            case 'Service':
            default:
                return '<p>Call for a list of available services.</p>';
        }
    }

    /**
     * Wordpress shortcode for showing an agency's full address (from the
     * custom field data) as a clickable HTML link that shows it on 
     * Google Maps when clicked.
     * 
     * @return string Returns the HTML to be rendered for the shortcode.
     */
    public static function acf_google_maps_address(): string {
        global $post;
        $outputHtml = '';
    
        // Check if we are in the correct post type
        if (!$post || $post->post_type !== 'agency') {
            return 'Not in agency post type or post is not set.';
        }
    
        // Combine the fields into a single address line for display
        $fullAddress = AcfExternalFunctions::get_field('FullAddress', $post->ID);
    
        if (!empty($fullAddress)) {
            // Create the Google Maps link
            $google_maps_url = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($fullAddress);
        
            // Format the address as a clickable link to Google Maps
            $outputHtml = '<a href="' . esc_url($google_maps_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($fullAddress) . '</a>';
        }

        return $outputHtml;
    }
}