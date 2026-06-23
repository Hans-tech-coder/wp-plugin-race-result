# WP Race Results

A custom WordPress plugin designed to manage, import, and display race results. It features an Elementor widget for easy frontend integration and an AJAX-powered searchable data table for seamless user experience.

## Features

* **Event Management:** Add and manage race events with details like event date, location, banner image, event logo, and social media links.
* **Distance Categories & Winner Declaration:** Define custom race distances (e.g., 5K, 10K, 21K) and configure the number of declared winners (male/female) for each distance.
* **Results Management & CSV Import:** Add race results manually or use the built-in CSV import tool to upload bulk data containing bib numbers, names, genders, distances, gun times, chip times, and rankings.
* **AJAX Data Table:** A fast, AJAX-powered frontend table for viewing results. Includes search (by name or bib number), distance filtering, and gender filtering without reloading the page.
* **Cache-Friendly:** Designed to work smoothly with full-page caching solutions (e.g., LiteSpeed Cache, WP Rocket) by using stateless AJAX filters.
* **Winner Modal:** An interactive modal that proudly displays the top declared winners of each category.
* **Elementor Integration:** Includes a custom Elementor widget allowing you to drag and drop the race results table anywhere on your Elementor pages.
* **SEO & Deep Linking:** Supports custom rewrite rules for pretty URLs and deep linking straight to specific event results and distances.

## Installation

1. Download or clone this repository into your WordPress `wp-content/plugins/` directory (name the folder `wp-race-results`).
2. Go to the **Plugins** menu in WordPress and activate **WP Race Results**.
3. Navigate to **Race Results > Settings** to configure the Master Page ID and permalink bases for deep linking.

## Usage

1. **Create an Event:** Go to **Race Results > Race Events > Add New Event**. Set your distances (e.g., "5K, 10K, 21K") and save. After saving, set your winner declarations.
2. **Import Results:** Go to **Race Results > Import Results** to upload your CSV file containing the race data.
3. **Display:** Edit your page with Elementor, search for the **Race Results** widget, drop it onto the page, and select the event you want to display from the widget settings.

## License

GPL-2.0+
