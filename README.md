# Dynamic Post Importer with ACF Integration

This WordPress plugin dynamically imports and updates posts from an external source, ensuring that custom fields are always up-to-date. It is designed to be flexible and can be adapted for various types of content beyond a specific industry.

## Features

- **Daily Content Sync:** Automatically fetches posts from a specified external endpoint and updates them daily.
- **ACF Integration:** Updates Advanced Custom Fields (ACF) associated with each post to reflect any changes in titles, logos, or other fields.
- **Flexible Configuration:** Easily adaptable to different industries or use cases by changing the endpoint and field mappings.

## Usage

1. **Configuration:** Set your external data source URL and map the ACF fields in the plugin settings.
2. **Automated Updates:** The plugin runs a daily cron job to check for updates and sync any changes.
3. **Customizable Fields:** Adjust field mappings as needed to match your custom post types and ACF setup.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the plugin settings to configure your external data source and field mappings.

## Configuration

- **Endpoint URL:** Specify the URL of the external API or data source.
- **Field Mapping:** Define which ACF fields correspond to which fields from the external data.

## License

This project is licensed under the MIT License - see the LICENSE.md file for details.

---
