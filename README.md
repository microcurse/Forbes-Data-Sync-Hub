# Forbes-Data-Sync-Hub
The "Forbes Data Sync Hub" (FDSH) will be a centralized WordPress plugin responsible for managing, syncing, and exposing various types of data from the Forbes Industries ecosystem. This includes, but is not limited to, WooCommerce Products, Product Attributes (including custom term meta and swatches), Rep Groups, and Rep Associates.

## 1. Overview

The "Forbes Data Sync Hub" (FDSH) will be a centralized WordPress plugin responsible for managing, syncing, and exposing various types of data from the Forbes Industries ecosystem. This includes, but is not limited to, WooCommerce Products, Product Attributes (including custom term meta and swatches), Rep Groups, and Rep Associates.

**Key Functionality:**

- **API Provider Mode (Source Site - e.g., Forbes Portal):** Exposes master data through a clean, well-structured JSON REST API. Manages access for client applications/sites.
- **API Client/Sync Mode (Destination Site(s) - e.g., Forbes Main Site):** Consumes data from the API Provider and syncs it to local WordPress data structures (CPTs, taxonomies, ACF fields), replicating custom data accurately.

The primary goal is to provide a single source of truth (via the API Provider) and a consistent mechanism for data synchronization and exposure for both internal WordPress sites and external applications. This plugin will supersede and consolidate functionality currently found in "forbes-product-sync" and parts of "Display-Reps-Groups-and-Associates."

## 2. Core Philosophy & Design Principles

- **Modularity:** The plugin will be architected with distinct modules for each data type (e.g., `ProductModule`, `RepGroupModule`). Each module will contain logic for both API provision and client-side synchronization.
- **Dual Role Capability:** A single codebase will adapt its functionality based on its configuration or the site's role (Provider or Client).
- **Centralized Infrastructure:** Common functionalities like API connection settings (for Client Mode), API endpoint registration (for Provider Mode), logging, admin UI framework, and core sync/API handling logic will be centralized.
- **API-First (for data exposure from Provider):** Data exposure for external consumers will be primarily via the WordPress REST API, under a dedicated namespace (e.g., `/forbes-data/v1/`).
- **Configuration over Convention:** Provide clear settings for administrators to define the plugin's role and necessary connection details.
- **Robust Logging:** Comprehensive logging for all API interactions, sync operations, and significant events, tailored to the plugin's role on a given site.
- **Security:** Adherence to WordPress security best practices for API authentication, data sanitization, and capability checks in both modes.
- **Code Reusability:** Leverage and adapt existing proven logic (e.g., attribute handling from `forbes-product-sync`) where appropriate.
- **Extensibility:** Design with future data types and operational modes in mind.

## 3. Phases of Development

### Phase 0: Preparation & Foundation (Current "Display Reps Groups and Associates" & "forbes-product-sync" plugins)

- **P0.1: Finalize Rep Group REST API Endpoint (in `Display-Reps-Groups-and-Associates` on Portal):**
    - **Objective:** Create a fully functional read-only REST API endpoint on the Portal.
    - **Details:** Endpoint: `wp-json/rep-groups/v1/all-data` (or similar). Authentication: Application Passwords. Data Output: Denormalized JSON. Filtering: `rg_map_scope`.
    - **Rationale:** Immediate value and blueprint for FDSH Rep Group Module (Provider).
- **P0.2: Analyze and Prepare `forbes-product-sync` for Migration:**
    - **Objective:** Identify and document key functionalities, especially attribute/term handling, for integration into FDSH.
    - **Tasks:**
        - Thoroughly review `FPS_AJAX::sync_single_attribute()` and helper functions (`_sideload_image`, `_find_existing_attachment_by_source_url`).
        - Document the exact process for fetching attribute/term data (including separate `/wp/v2/` calls and potential dual authentication from the original source in `forbes-product-sync`). This understanding is key to designing the FDSH Provider API correctly.
        - Note custom term meta keys handled (`term_price`, `_term_suffix`, `thumbnail_id`).
        - Identify reusable code for client-side API settings, logging, and admin page structure.
    - **Rationale:** Prepares existing, detailed attribute logic for FDSH.

### Phase 1: FDSH Plugin Core & Product Sync Module

- **P1.1: Create FDSH Plugin Shell:**
    - **Objective:** Establish the basic plugin structure.
    - **Tasks:** Main plugin file, constants (e.g., `FDSH_VERSION`, `FDSH_PATH`), autoloader, core directory structure (`includes/`, `admin/`, `modules/`, `assets/`, `core/` for shared classes).
- **P1.2: Implement Core Infrastructure & Role Management:**
    - **Objective:** Port reusable components and establish role-based functionality.
    - **Tasks:**
        - **Plugin Role Setting:** In FDSH general settings, allow admin to define role: "API Provider (Source Site)" or "API Client (Destination Site)".
        - **Core Classes (e.g., in `core/`):**
            - `FDSH_Settings_Manager`: Handles registration and retrieval of settings.
            - `FDSH_Logger`: Centralized logging.
            - `FDSH_AJAX_Handler`: Base for AJAX actions.
            - `FDSH_Admin_UI`: Base for admin pages/menus.
        - **Settings API & UI:**
            - *Provider Mode:* UI for Application Password management, API access logs.
            - *Client Mode:* UI for Source API URL, Application Password. "Test Connection" button.
        - Admin Menu & Page Framework based on `FDSH_Admin_UI`.
- **P1.3: Develop Product Sync Module (`modules/product/`):**
    - **Objective:** Integrate product/attribute syncing with dual-role awareness, incorporating detailed attribute handling.
    - **Class:** `Product_Module` (and potentially `Product_Admin`, `Product_API_Provider`, `Product_Sync_Client` sub-classes/controllers).
    - **Provider Mode (Source Site - e.g., Portal):**
        - **API Logic:**
            - Fetch local WooCommerce product/attribute data.
            - **Implement last_modified Tracking:**
                - For Products (CPT product): Utilize post_modified_gmt.
                - For Attributes (Taxonomy pa_*): When attribute core settings (name, slug, type, order_by) are saved, update a custom meta field on the attribute term itself (e.g., _fdsh_attribute_modified_gmt) with the current GMT timestamp.
                - For Attribute Terms: When term data (name, slug, description, custom meta term_price, _term_suffix, thumbnail_id) is saved, update a custom meta field on the term (e.g., _fdsh_term_modified_gmt) with the current GMT timestamp. *(Alternatively, if native term modified dates become reliable or a filter exists, use that).*
            - Prepare API responses, including these last_modified timestamps (e.g., in a modified_gmt field for each item).
            - Include product meta _swatch_type and _swatch_type_options in the product API response if they exist for a product.
            - Ensure full swatch image URL for thumbnail_id associated with terms is provided.
        - **API Endpoints Registration:**
            - /forbes-data/v1/products/: List products. Support ?modified_since=<YYYY-MM-DDTHH:MM:SS> parameter.
            - /forbes-data/v1/products/<id_or_sku>: Get single product.
            - /forbes-data/v1/attributes/: List attributes. Support ?modified_since=<YYYY-MM-DDTHH:MM:SS> parameter (based on _fdsh_attribute_modified_gmt).
            - /forbes-data/v1/attributes/<id_or_slug>: Get single attribute core details.
            - /forbes-data/v1/attributes/<id_or_slug>/terms: Returns all terms. Support ?modified_since=<YYYY-MM-DDTHH:MM:SS> parameter (based on _fdsh_term_modified_gmt).
    - **Client Mode (Destination Site - e.g., Main Site):**
        - **Admin UI:** Pages for Product Sync and Attribute Sync (functionally similar to `forbes-product-sync` but using FDSH framework).
        - **Attribute & Term Sync Logic:**
            - Fetch attribute core data from Provider's /attributes/<id_or_slug> API (or from /attributes?modified_since=... for delta syncs).
            - Overwrite local attribute core settings (name, slug, type, order_by) if the source attribute's _fdsh_attribute_modified_gmt is newer than a locally stored sync timestamp for that attribute, or if the attribute is new.
            - Fetch rich term data from Provider's /attributes/<id_or_slug>/terms API (or from /attributes/<id_or_slug>/terms?modified_since=... for delta syncs).
            - For each term from API:
                - If new or source _fdsh_term_modified_gmt is newer than local sync timestamp for that term:
                    - Create or update local term.
                    - Set local term meta.
                    - Sideload swatch image if swatch_image_url provided and differs or is new.
        - **Product Sync Logic:**
            - Fetch product data from Provider's /products API (using ?modified_since=... for delta syncs) or /products/<id_or_sku>.
            - For each product from API:
                - Compare modified_gmt from API with locally stored sync timestamp for that product. If source is newer or product is new:
                    - Fetch local product data (if exists).
                    - Compare Provider data object with local data object.
                    - If differences exist:
                        - Create or update local product (core fields, categories, tags - if enabled, stock, etc.).
                        - Update product meta, including _swatch_type and _swatch_type_options if provided by API.
                        - Handle variations, images.
                    - Store Provider's modified_gmt as the local sync timestamp for this product.
        - **Admin UI:**
            - Category-based product selection for manual sync:
                1. Dropdown to select a product category.
                2. AJAX call to populate a list of products within that category (perhaps with checkboxes).
                3. Button to "Sync Selected Products."
            - (Future) Options for syncing specific fields (Description, Short Description, Price).
- **P1.4: Testing & Refinement:**
    - Test Provider Mode: API endpoints for attributes and terms, ensuring custom meta and swatch URLs are present and correct.
    - Test Client Mode: Full attribute/term sync, including custom meta and swatch image sideloading. Initial product sync tests.

### Phase 2: Rep Group Sync Module

- **P2.1: Develop Rep Group Module (`modules/rep-group/`):**
    - **Objective:** Integrate Rep Group data management with dual-role awareness.
    - **Class:** `Rep_Group_Module` (and potentially sub-classes).
    - **Data Definitions (CPT, Taxonomy, ACF):**
        - Register `rep-group` CPT, `area-served` taxonomy. Load ACF JSON (e.g., `group_6765c0e631dea.json`). Active on sites needing local Rep Group data storage.
    - **Provider Mode (Source Site - e.g., Portal):**
        - **Implement last_modified Tracking:**
            - For Rep Groups (CPT rep-group): Utilize post_modified_gmt.
            - For Areas Served (Taxonomy area-served): When term data is saved, update a custom meta field on the term (e.g., _fdsh_term_modified_gmt).
        - **API Endpoint Registration:** /forbes-data/v1/rep-groups/all. Support ?modified_since=<YYYY-MM-DDTHH:MM:SS> parameter.
        - **API Logic:** Fetch local Rep Group CPT/ACF data, denormalize, include modified_gmt timestamps.
    - **Client Mode (Destination Site - e.g., Main Site):**
        - **Sync Logic:** Fetch from Provider's Rep Group API (using ?modified_since=... for delta syncs).
            - For each rep group from API:
                - If new or source modified_gmt is newer than local sync timestamp:
                    - Create/update local rep-group CPTs.
                    - Handle "Rep Associates" and ACF fields.
                    - Link to area-served terms (create/update if needed, respecting their own _fdsh_term_modified_gmt).
                    - Store Provider's modified_gmt as local sync timestamp.
- **P2.2: Frontend Display Decoupling (External to FDSH):**
    - `Display-Reps-Groups-and-Associates` plugin (or a new display plugin) to consume data from FDSH Rep Group API. FDSH will not manage frontend map display.
- **P2.3: Testing & Refinement:** Test Provider Rep Group API. Test Client Rep Group sync.

### Phase 3: Extensibility & Future Modules

- **P3.1: Develop Core Extensibility Features:** Define interfaces/hooks for new modules (e.g., `FDSH_Sync_Module_Interface`).
- **P3.2: Example Future Modules:** Media Sync, Generic CPT Sync.

## 4. Data Structures & Definitions

- **Products & Attributes:** As per WooCommerce. Custom term meta for attributes: `term_price`, `_term_suffix`. Swatch image via `thumbnail_id` on terms.
- **Rep Groups (CPT `rep-group`):** (As previously detailed - `rg_logo` URL, `rg_map_scope`, `rep_associates` repeater with User, Title, Territory Served etc.)
- **Areas Served (Taxonomy `area-served`):** Name, slug, `_rep_svg_target_id`.
- **Tracking Meta (Client Mode):**
    - CPTs: _fdsh_source_id, _fdsh_last_synced_gmt (stores the modified_gmt of the source item from the last successful sync).
    - Taxonomy Terms: _fdsh_source_term_id, _fdsh_last_synced_gmt.
    - Attachments (sideloaded images): _source_image_url.
- **Tracking Meta (Provider Mode - for entities without native modified dates):**
    - Attribute Definitions (terms of pa_ taxonomies): _fdsh_attribute_modified_gmt.
    - Attribute Terms: _fdsh_term_modified_gmt (if needed, or rely on native).
    - Custom Taxonomy Terms (e.g., area-served): _fdsh_term_modified_gmt.

## 5. API Design (FDSH Namespace: `/forbes-data/v1/` - Exposed by Provider Mode)

- **Authentication:** Application Passwords.
- **Endpoints (Read-Only Focus for External Consumption):**
    - `/products`, `/products/<id_or_sku>`
    - `/attributes`, `/attributes/<id_or_slug>`
    - `/attributes/<id_or_slug>/terms` (Returns standard fields + custom `term_meta` object + `swatch_image_url`)
    - `/rep-groups`, `/rep-groups/<id_or_portal_id>`
    - `/areas-served`
- **Data Format:** Clean JSON. Resolve IDs where appropriate (user emails, term names).

## 6. Admin Interface (FDSH)

- **Main Menu:** "Forbes Data Sync"
- **General Settings:** Plugin Role, Provider settings (App Password Mgmt), Client settings (Source API Config, Test Connection).
- **Module-Specific Sections (UI varies by role):**
    - *Product Module:* Provider (API stats), Client (Sync Controls for Products & Attributes).
    - *Rep Group Module:* Provider (API stats), Client (Sync Controls for Rep Groups).
- **Sync Logs:** Centralized, filterable logs.

## 7. Dependencies

- WordPress 5.8+
- PHP 7.4+
- WooCommerce (for Product Module features).
- Advanced Custom Fields Pro (for Rep Group Module & others using ACF).

## 8. Edge Cases & Considerations (To be expanded)

(As previously detailed in v1.1, plus:)

- **Attribute Core Data Updates (Client):** Define strategy for updating existing attribute core properties (name, slug, type, order_by) if they change on the source.
- **Term Slug Changes (Client):** How to handle if a term slug changes on the source but the term ID/name might still indicate it's the "same" term conceptually. Matching primarily by slug is common.
- **Error Handling for Image Sideloading:** Robust retry mechanisms or clear logging for failures. Ensure timeouts are adequate.

## 9. Detailed Data Mapping (Example Structure)

We'll need to create tables like this for each module (Products, Attributes, Rep Groups).

### 9.1 Product Module - Product Data

| **Provider Data Point** | **Source (WP Hook / Meta Key)** | **Provider API Endpoint / JSON Key** | **Client Destination (WP Field / Meta Key)** | **Sync Logic Notes** |
| --- | --- | --- | --- | --- |
| **Core Product** |  | /products/<id>, /products |  | Create if _fdsh_source_id not found. Update if source.modified_gmt > client._fdsh_last_synced_gmt. |
| Product Title | post_title | title.rendered | post_title | Overwrite. |
| Product Content (Description) | post_content | content.rendered | post_content | Overwrite. |
| Product Excerpt (Short Desc.) | post_excerpt | excerpt.rendered | post_excerpt | Overwrite. |
| Product Status | post_status | status | post_status | Overwrite (e.g., 'publish', 'draft'). |
| SKU | _sku (meta) | sku | _sku (meta) | Overwrite. Crucial for matching if ID not used. |
| Regular Price | _regular_price (meta) | regular_price | _regular_price (meta) | Overwrite. |
| Sale Price | _sale_price (meta) | sale_price | _sale_price (meta) | Overwrite. |
| Stock Status | _stock_status (meta) | stock_status | _stock_status (meta) | Overwrite ('instock', 'outofstock'). |
| Manage Stock | _manage_stock (meta) | manage_stock | _manage_stock (meta) | Overwrite ('yes', 'no'). |
| Stock Quantity | _stock (meta) | stock_quantity | _stock (meta) | Overwrite (if manage_stock is 'yes'). |
| Weight | _weight (meta) | weight | _weight (meta) | Overwrite. |
| Dimensions (L, W, H) | _length, _width, _height (meta) | dimensions.length, .width, .height | _length, _width, _height (meta) | Overwrite. |
| Featured Image | _thumbnail_id (meta) | images[0].src (full URL), images[0].id (source attachment ID) | _thumbnail_id (meta) | Sideload image from images[0].src. Store source URL in _source_image_url on new attachment. Match by _source_image_url to prevent dupes. |
| Product Gallery Images | (Handled by WC product_image_gallery) | images[1+].src, images[1+].id | (Set via update_post_meta) | Sideload all images. Reconcile gallery based on source image IDs/URLs. |
| Product Categories | wp_set_object_terms (taxonomy product_cat) | categories (array of term objects: id, name, slug) | wp_set_object_terms | Match terms by _fdsh_source_term_id or slug. Create if not exist. Assign to product. (Sync of categories themselves is TBD). |
| Product Tags | wp_set_object_terms (taxonomy product_tag) | tags (array of term objects: id, name, slug) | wp_set_object_terms | Match/create/assign similar to categories. (Sync of tags themselves is TBD). |
| Product Attributes (link to taxonomy) | (WC internal relation) | attributes (array: id, name, slug, options, variation, visible) | (Set via wp_set_object_terms for pa_* and update_post_meta for _product_attributes) | For each attribute on source product, ensure local product is assigned the correct terms (synced via Attribute module). Configure visibility/variation use. |
| Product Variations | (CPT product_variation) | variations (array of variation IDs, or full variation objects) | (CPT product_variation) | Complex: For each source variation, create/update local variation. Sync variation-specific SKU, price, stock, attributes, image. |
| _swatch_type (Product Meta) | _swatch_type (meta) | meta_data (array, item with key _swatch_type) | _swatch_type (meta) | Overwrite if present in API. |
| _swatch_type_options (Prod. Meta) | _swatch_type_options (meta) | meta_data (array, item with key _swatch_type_options) | _swatch_type_options (meta) | Overwrite if present in API. Value can be complex (serialized array/JSON). |
| Modified GMT | post_modified_gmt | modified_gmt | _fdsh_last_synced_gmt (meta) | Store source modified_gmt after successful sync. |
| Source ID | ID | id | _fdsh_source_id (meta) | Store source ID. |

### 9.2 Product Module - Attribute & Term Data

| **Provider Data Point** | **Source (WP Hook / Meta Key)** | **Provider API Endpoint / JSON Key** | **Client Destination (WP Field / Meta Key)** | **Sync Logic Notes** |
| --- | --- | --- | --- | --- |
| **Attribute Definition** |  | /attributes/<id>, /attributes |  | Create if _fdsh_source_term_id (for the attribute itself) not found. Update if source.modified_gmt > client._fdsh_last_synced_gmt. |
| Attribute Name | attribute_label (from wc_get_attribute_taxonomies()) | name | attribute_label (via wc_create_attribute(), wc_update_attribute()) | Overwrite. |
| Attribute Slug (pa_) | attribute_name (slug) | slug | attribute_name | Critical. Create if not exists. Match by this. |
| Attribute Type | attribute_type (select, text) | type | attribute_type | Overwrite. |
| Attribute Order By | attribute_orderby (menu_order, name, etc.) | order_by | attribute_orderby | Overwrite. |
| Attribute Public | attribute_public (0 or 1) | has_archives | attribute_public | Overwrite. |
| Attr. Modified GMT | _fdsh_attribute_modified_gmt (term meta on attribute) | modified_gmt | _fdsh_last_synced_gmt (term meta on client attribute) | Store source modified_gmt after successful sync. |
| Attr. Source ID | Term ID of the attribute | id | _fdsh_source_term_id (term meta on client attribute) | Store source Term ID. |
| **Attribute Term** |  | /attributes/<attr_id>/terms |  | Create if _fdsh_source_term_id not found. Update if source.modified_gmt > client._fdsh_last_synced_gmt. |
| Term Name | name (term field) | name | name (term field) | Overwrite. |
| Term Slug | slug (term field) | slug | slug (term field) | Overwrite. Handle potential conflicts carefully. |
| Term Description | description (term field) | description | description (term field) | Overwrite. |
| Term Meta: term_price | term_price (term meta) | meta.term_price | term_price (term meta) | Overwrite. |
| Term Meta: _term_suffix | _term_suffix (term meta) | meta._term_suffix | _term_suffix (term meta) | Overwrite. |
| Term Meta: thumbnail_id | thumbnail_id (term meta) | swatch_image_url (full URL from API), meta.thumbnail_id (source ID) | thumbnail_id (term meta) | Sideload image from swatch_image_url. Store source URL in _source_image_url on new attachment. If URL empty, remove local thumbnail_id. |
| Term Modified GMT | _fdsh_term_modified_gmt (term meta) | modified_gmt | _fdsh_last_synced_gmt (term meta on client term) | Store source modified_gmt after successful sync. |
| Term Source ID | term_id | id | _fdsh_source_term_id (term meta on client term) |  |

*(Similar tables would be created for Rep Groups CPT, Area Served Taxonomy, and any other future modules.)*

## 10. Error Handling & Logging Specifics

For each module and operation, define potential errors and how they're handled.

**General Logging:**

- **FDSH_Logger:**
    - Levels: INFO, WARNING, ERROR, DEBUG (Debug only if WP_DEBUG is true or specific FDSH debug constant is set).
    - Storage: wp-content/uploads/fdsh-logs/fdsh-{date}.log (for file-based logs, especially debug). DB table for admin-viewable logs (Info, Warning, Error).
    - Rotation: Files older than 30 days deleted. DB logs older than 30 days (or configurable) pruned. Manual prune option.

**Product Sync Errors:**

| **Error Scenario** | **Trigger / Detection** | **Log Level** | **Admin Notification** | **Action Taken by Plugin** |
| --- | --- | --- | --- | --- |
| Provider API Unreachable | cURL error, HTTP status 4xx/5xx (not 401/403) | ERROR | "Error connecting to Provider API: [details]" | Halt current sync batch for this module. Retry mechanism (e.g., 3 attempts with delay) for cron jobs. |
| Provider API Auth Failure (Application Password) | HTTP status 401/403 | ERROR | "Authentication failed with Provider API. Check credentials." | Halt sync. Admin must reconfigure. |
| Required API Endpoint Missing | HTTP 404 on expected endpoint (e.g., /products) | ERROR | "Provider API endpoint [endpoint] not found. Is FDSH active & configured on Provider?" | Halt sync for this module. |
| Malformed JSON Response from Provider | json_decode() returns null/error | ERROR | "Received malformed data from Provider API for [endpoint]." | Skip processing this item/batch. Log problematic data if possible (truncated). |
| **Product Specific** |  |  |  |  |
| Source Product ID not found (for single product sync) | 404 from /products/<id> | WARNING | "Product with Source ID [id] not found on Provider." | Skip this product. |
| Failed to Create/Update Product on Client | wp_insert_post/wp_update_post returns WP_Error or 0 | ERROR | "Failed to save product [name/ID] locally: [WP_Error msg]" | Log error, continue with next product in batch. |
| Image Sideloading Failure (Featured/Gallery/Swatch) | media_sideload_image returns WP_Error | WARNING | "Failed to sideload image [URL] for product/term [name/ID]: [WP_Error msg]" | Log error. Product/term still created/updated without this image. Store _fdsh_failed_image_sideload_attempts. |
| Variation Sync Issue | Mismatch in attributes, failure to create/update variation | WARNING | "Issue syncing variation for product [name/ID]: [details]" | Log details. Parent product might be created, but variations may be incomplete. |
| Deleting local product (due to source deletion) fails | wp_trash_post/wp_delete_post returns false/WP_Error | ERROR | "Failed to delete local product [ID] matching deleted source: [WP_Error msg]" | Log error. Product remains. |

**Attribute/Term Sync Errors:**

| **Error Scenario** | **Trigger / Detection** | **Log Level** | **Admin Notification** | **Action Taken by Plugin** |
| --- | --- | --- | --- | --- |
| Failed to Create/Update Attribute | wc_create_attribute/wc_update_attribute returns error | ERROR | "Failed to save attribute [name] locally: [error]" | Log error, skip this attribute and its terms. |
| Failed to Create/Update Term | wp_insert_term/wp_update_term returns WP_Error | ERROR | "Failed to save term [name] for attribute [attr_name] locally: [WP_Error msg]" | Log error, continue with next term. |
| Term Slug Conflict (new term from source) | wp_insert_term fails due to existing slug for *unrelated* local term. | ERROR | "Term slug [slug] from source conflicts with an existing unrelated local term for attribute [attr_name]. Manual resolution needed." | Skip this term. Admin intervention required. |
| Deleting local attribute (due to source deletion) fails | wc_delete_attribute (or direct term/taxonomy deletion) fails | ERROR | "Failed to delete local attribute [name] matching deleted source: [error]" | Log error. Attribute remains. |

*(Similar tables for Rep Group sync errors, focusing on CPT creation, ACF field updates, user matching for associates, taxonomy term handling.)*
