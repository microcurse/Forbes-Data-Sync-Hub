## Forbes Data Sync Hub (FDSH) Plugin - Project Catch-up

**Project Goal:** Develop a WordPress plugin called "Forbes Data Sync Hub" (FDSH). This plugin will serve as a centralized system for managing, synchronizing, and exposing various data types (Products, Attributes, Rep Groups, etc.) within the Forbes Industries ecosystem. It's designed to operate in two modes: "API Provider" (source site, exposing data) and "API Client/Sync Mode" (destination site, consuming and syncing data).

**Initial Context:** The development started by reviewing a detailed `README.md` file (which you will provide to the new AI instance) outlining the plugin's architecture, phases, and data structures. We then proceeded with **Phase 1: FDSH Plugin Core & Product Sync Module (Provider Mode)**.

**Current Status & Key Developments (Phase 1 - Provider Mode):**

The primary focus has been on establishing the core plugin infrastructure and the Product Sync module, specifically its API Provider capabilities.

**1. Plugin Directory Structure & Main File:**
*   The plugin directory is `wp-content/plugins/Forbes-Data-Sync-Hub/`.
*   Key subdirectories created: `core/`, `admin/`, `includes/`, `assets/`, `modules/product/api/`.
*   The main plugin file is `forbes-data-sync-hub.php`. It includes:
    *   Plugin headers, constants (`FDSH_VERSION`, `FDSH_PLUGIN_DIR`, `FDSH_TEXT_DOMAIN`).
    *   An `FDSH_DEBUG` constant (set to `false` by default in the main file, but can be overridden) to control debug logging.
    *   A basic autoloader for `FDSH_` prefixed classes and PSR-4 style classes under `ForbesDataSyncHub\\`.
    *   Initialization of core components and modules.

**2. Core Infrastructure Classes (primarily in `core/`):**
*   **`FDSH_Logger` (`core/class-fdsh-logger.php`):**
    *   Handles file-based logging (INFO, WARNING, ERROR, DEBUG levels).
    *   Logs are stored in `wp-content/uploads/fdsh-logs/fdsh-{date}.log`.
    *   Includes log rotation and security measures for the log directory.
*   **`FDSH_Settings_Manager` (`core/class-fdsh-settings-manager.php`):**
    *   Manages plugin settings using the WordPress Settings API.
    *   Registers a settings group `fdsh_settings` and an option key `fdsh_general_settings`.
    *   Defines fields for "Plugin Role" (Provider/Client), Provider mode information, and Client mode settings (API URL, Application Password, Test Connection button).
    *   Includes basic sanitization for settings.
    *   A TODO was noted for the "Test Connection" button's AJAX functionality.
*   **`FDSH_Admin_UI` (`admin/class-fdsh-admin-ui.php`):**
    *   Registers the main admin menu ("Forbes Data Sync") and a "General Settings" submenu page.
    *   Renders the settings fields defined by `FDSH_Settings_Manager`.
*   **`FDSH_AJAX_Handler` (`core/class-fdsh-ajax-handler.php`):**
    *   A base class for AJAX operations, providing helper methods for registering actions, sending JSON responses, nonce verification, and permission checks.

**3. Product Sync Module (`modules/product/`) - Provider Mode:**
*   **`FDSH_Product_Module` (`modules/product/class-fdsh-product-module.php`):**
    *   Orchestrates product synchronization logic.
    *   Initializes hooks for Provider Mode to track changes:
        *   `save_post_product`: Logs product saves (relies on WordPress `post_modified_gmt`).
        *   `woocommerce_attribute_added`, `woocommerce_attribute_updated`: Calls `handle_wc_attribute_definition_save_provider`.
        *   `saved_term` (for `pa_*` taxonomies): Calls `handle_attribute_term_save_provider`.
    *   `handle_wc_attribute_definition_save_provider`: Stores a `_fdsh_attribute_modified_gmt` timestamp for WooCommerce attribute *definitions* in a WordPress option `fdsh_attribute_modified_gmt_tracking` (keyed by attribute ID).
    *   `handle_attribute_term_save_provider`: Stores a `_fdsh_term_modified_gmt` timestamp in term meta for individual attribute *terms*.
*   **`FDSH_Product_API_Provider` (`modules/product/api/class-fdsh-product-api-provider.php`):**
    *   Registers REST API endpoints under the `/forbes-data/v1` namespace.
    *   Permission callback `permissions_check` currently uses `current_user_can('manage_woocommerce')`.
    *   **Implemented Endpoints (Provider Mode):**
        *   `GET /products`:
            *   Lists WooCommerce products.
            *   Supports `modified_since` (ISO8601 format) query parameter, filtering by product `post_modified_gmt`.
            *   Returns product data as detailed in `README.md` Table 9.1 (ID, SKU, title, content, prices, stock, dimensions, images, categories, tags, attributes, variation IDs, relevant meta_data like `_swatch_type`, `_swatch_type_options`).
        *   `GET /products/(?P<id>[^/]+)`:
            *   Gets a single product by its numeric ID or SKU.
            *   The regex `[^/]+` is used for the `id` parameter to accommodate various SKU formats (though the user confirmed SKUs are hyphen-delimited without spaces).
            *   Returns data similar to the list endpoint for a single product.
        *   `GET /attributes`:
            *   Lists WooCommerce attribute definitions (from `wc_get_attribute_taxonomies()`).
            *   Supports `modified_since` (ISO8601 format) filtering based on the `_fdsh_attribute_modified_gmt` timestamp stored in the `fdsh_attribute_modified_gmt_tracking` option.
            *   Returns data as per `README.md` Table 9.2 (ID, name, slug, type, order_by, has_archives, modified_gmt).
        *   `GET /attributes/(?P<slug>[\w-]+)`:
            *   Gets a single attribute definition by its slug (e.g., `pa_color`).
        *   `GET /attributes/(?P<attribute_slug>[\w-]+)/terms`:
            *   Lists all terms for a given attribute slug.
            *   Supports `modified_since` (ISO8601 format) filtering based on the `_fdsh_term_modified_gmt` term meta.
            *   Returns data as per `README.md` Table 9.2 (ID, name, slug, description, relevant term meta like `term_price`, `_term_suffix`, `thumbnail_id`, and `swatch_image_url`, modified_gmt).

**4. Current Debugging & Next Steps:**
*   **Issue Resolved:** We encountered an issue where fetching products by SKUs that were purely numeric (e.g., "3770", "3720") via `GET /products/{sku}` would result in a 404. This was because `wc_get_product()` was being called with the numeric SKU, which it treats as a Post ID. If no post exists with that ID, it fails. The product actually needed to be looked up using `wc_get_product_id_by_sku()`.
*   **Solution Implemented:** The `get_product` method in `FDSH_Product_API_Provider` was updated with more robust logic:
    1.  It checks if the `id_or_sku` parameter is numeric.
    2.  If numeric, it first tries `wc_get_product()`.
    3.  If that fails, it then tries `wc_get_product_id_by_sku()` with the numeric value.
    4.  If the `id_or_sku` is not numeric, it directly uses `wc_get_product_id_by_sku()`.
    5.  Detailed `DEBUG` level logging was added throughout this process to trace the execution flow and the results of these WooCommerce function calls. These logs are written by `FDSH_Logger` to `wp-content/uploads/fdsh-logs/`.
*   **Verification:**
    *   The user confirmed that `GET /wp-json/forbes-data/v1/products/3720` is now working correctly.
*   **Immediate Next Step (before this handoff request):** The plan was to:
    1.  Have the user test `GET /wp-json/forbes-data/v1/products/3770` (another previously problematic numeric SKU).
    2.  Review the FDSH logs for both the "3720" and "3770" calls to see the detailed debug output and confirm the logic path and results from `wc_get_product_id_by_sku()`.

**Summary of File Paths for Key Classes:**
*   Main Plugin File: `plugins/Forbes-Data-Sync-Hub/forbes-data-sync-hub.php`
*   Logger: `plugins/Forbes-Data-Sync-Hub/core/class-fdsh-logger.php`
*   Settings Manager: `plugins/Forbes-Data-Sync-Hub/core/class-fdsh-settings-manager.php`
*   Admin UI: `plugins/Forbes-Data-Sync-Hub/admin/class-fdsh-admin-ui.php`
*   AJAX Handler: `plugins/Forbes-Data-Sync-Hub/core/class-fdsh-ajax-handler.php`
*   Product Module: `plugins/Forbes-Data-Sync-Hub/modules/product/class-fdsh-product-module.php`
*   Product API Provider: `plugins/Forbes-Data-Sync-Hub/modules/product/api/class-fdsh-product-api-provider.php` (This file contains the recently modified `get_product` method).

This summary, along with the `README.md` file, should give the new AI instance a good understanding of the project's progress.