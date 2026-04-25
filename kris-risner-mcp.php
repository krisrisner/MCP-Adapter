<?php
/**
 * Plugin Name: Kris Risner MCP Abilities
 * Description: Exposes site content, SEO data, structure, and Bricks Builder content to AI via MCP.
 * Version: 2.6
 * Author: Kris Risner
 */

defined( 'ABSPATH' ) || exit;

// ─── Schema Output via wp_head ──────────────────────────────────────────────

add_action( 'wp_head', 'kr_render_page_schema', 1 );

function kr_render_page_schema() {
    if ( ! is_singular() ) {
        return;
    }

    $post_id = get_the_ID();
    if ( ! $post_id ) {
        return;
    }

    $schemas = get_post_meta( $post_id, '_kr_page_schemas', true );
    if ( empty( $schemas ) || ! is_array( $schemas ) ) {
        return;
    }

    foreach ( $schemas as $schema ) {
        if ( empty( $schema['data'] ) ) {
            continue;
        }

        $json = wp_json_encode( $schema['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        if ( $json ) {
            echo "\n" . '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>' . "\n";
        }
    }
}

// ─── Admin Meta Box ─────────────────────────────────────────────────────────

add_action( 'add_meta_boxes', 'kr_add_schema_meta_box' );

function kr_add_schema_meta_box() {
    $post_types = array( 'post', 'page' );
    foreach ( $post_types as $pt ) {
        add_meta_box(
            'kr_page_schemas',
            'Page Schemas (JSON-LD)',
            'kr_render_schema_meta_box',
            $pt,
            'normal',
            'low'
        );
    }
}

function kr_render_schema_meta_box( $post ) {
    wp_nonce_field( 'kr_schema_meta_box', 'kr_schema_nonce' );

    $schemas = get_post_meta( $post->ID, '_kr_page_schemas', true );
    if ( ! is_array( $schemas ) ) {
        $schemas = array();
    }

    ?>
    <style>
        .kr-schema-wrap { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .kr-schema-item { background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 12px; padding: 0; }
        .kr-schema-header { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #fff; border-bottom: 1px solid #ddd; border-radius: 4px 4px 0 0; cursor: pointer; }
        .kr-schema-header:hover { background: #f5f5f5; }
        .kr-schema-title { font-weight: 600; font-size: 13px; color: #1d2327; }
        .kr-schema-type { background: #2271b1; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 3px; margin-left: 8px; font-weight: 400; }
        .kr-schema-date { color: #888; font-size: 11px; margin-left: auto; margin-right: 12px; }
        .kr-schema-body { padding: 12px 14px; display: none; }
        .kr-schema-body.kr-open { display: block; }
        .kr-schema-textarea { width: 100%; min-height: 200px; font-family: Menlo, Consolas, "Courier New", monospace; font-size: 12px; line-height: 1.5; padding: 10px; border: 1px solid #c3c4c7; border-radius: 3px; background: #fff; resize: vertical; tab-size: 2; }
        .kr-schema-textarea:focus { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; outline: none; }
        .kr-schema-actions { display: flex; gap: 8px; margin-top: 10px; align-items: center; }
        .kr-schema-delete { color: #b32d2e; text-decoration: none; font-size: 12px; cursor: pointer; margin-left: auto; }
        .kr-schema-delete:hover { color: #a00; }
        .kr-schema-empty { color: #888; font-style: italic; padding: 16px 0; text-align: center; }
        .kr-add-wrap { margin-top: 14px; padding-top: 14px; border-top: 1px solid #ddd; }
        .kr-add-row { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
        .kr-add-row input { padding: 4px 8px; font-size: 13px; }
        .kr-add-textarea { width: 100%; min-height: 120px; font-family: Menlo, Consolas, "Courier New", monospace; font-size: 12px; line-height: 1.5; padding: 10px; border: 1px solid #c3c4c7; border-radius: 3px; resize: vertical; tab-size: 2; display: none; margin-top: 8px; }
        .kr-toggle-arrow { font-size: 11px; color: #888; transition: transform 0.2s; }
        .kr-toggle-arrow.kr-rotated { transform: rotate(90deg); }
        .kr-validation-error { color: #b32d2e; font-size: 12px; margin-top: 6px; display: none; }
        .kr-hint { color: #888; font-size: 11px; margin-top: 4px; }
    </style>

    <div class="kr-schema-wrap">
        <?php if ( empty( $schemas ) ) : ?>
            <div class="kr-schema-empty" id="kr-empty-msg">No schemas configured for this page. Add one below or use the MCP connector.</div>
        <?php endif; ?>

        <div id="kr-schemas-list">
        <?php foreach ( $schemas as $key => $schema ) :
            $type  = isset( $schema['type'] ) ? $schema['type'] : 'Unknown';
            $added = isset( $schema['added'] ) ? $schema['added'] : '';
            $json  = isset( $schema['data'] ) ? wp_json_encode( $schema['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) : '{}';
        ?>
            <div class="kr-schema-item" data-key="<?php echo esc_attr( $key ); ?>">
                <div class="kr-schema-header" onclick="krToggleSchema(this)">
                    <span class="kr-toggle-arrow">&#9654;</span>
                    <span class="kr-schema-title"><?php echo esc_html( $key ); ?></span>
                    <span class="kr-schema-type"><?php echo esc_html( $type ); ?></span>
                    <?php if ( $added ) : ?>
                        <span class="kr-schema-date">Added <?php echo esc_html( $added ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="kr-schema-body">
                    <textarea
                        class="kr-schema-textarea"
                        name="kr_schemas[<?php echo esc_attr( $key ); ?>]"
                        spellcheck="false"
                    ><?php echo esc_textarea( $json ); ?></textarea>
                    <div class="kr-validation-error" id="kr-err-<?php echo esc_attr( $key ); ?>"></div>
                    <div class="kr-schema-actions">
                        <button type="button" class="button button-small" onclick="krValidateJson(this)">Validate JSON</button>
                        <button type="button" class="button button-small" onclick="krFormatJson(this)">Format</button>
                        <a class="kr-schema-delete" onclick="krDeleteSchema(this)" data-key="<?php echo esc_attr( $key ); ?>">Delete</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <input type="hidden" name="kr_schemas_deleted" id="kr-schemas-deleted" value="" />

        <div class="kr-add-wrap">
            <strong style="font-size: 13px;">Add New Schema</strong>
            <div class="kr-add-row" style="margin-top: 8px;">
                <label style="font-size: 12px; min-width: 30px;">Key:</label>
                <input type="text" id="kr-new-key" placeholder="e.g. service, localbusiness, faq" style="width: 220px;" />
                <button type="button" class="button button-small" onclick="krShowAddForm()">Add</button>
            </div>
            <textarea
                class="kr-add-textarea"
                id="kr-new-json"
                placeholder='{"@context": "https://schema.org", "@type": "Service", "name": "..."}'
                spellcheck="false"
            ></textarea>
            <div class="kr-validation-error" id="kr-add-error"></div>
            <div id="kr-add-actions" style="display: none; margin-top: 8px;">
                <button type="button" class="button button-primary button-small" onclick="krAddSchema()">Add Schema</button>
                <button type="button" class="button button-small" onclick="krCancelAdd()">Cancel</button>
            </div>
            <div class="kr-hint">Schemas are output as JSON-LD in the &lt;head&gt; of the page. Each schema needs a unique key and valid JSON with an @type field.</div>
        </div>
    </div>

    <script>
    function krToggleSchema(header) {
        var body = header.nextElementSibling;
        var arrow = header.querySelector('.kr-toggle-arrow');
        body.classList.toggle('kr-open');
        arrow.classList.toggle('kr-rotated');
    }

    function krValidateJson(btn) {
        var item = btn.closest('.kr-schema-item');
        var textarea = item.querySelector('.kr-schema-textarea');
        var errEl = item.querySelector('.kr-validation-error');
        try {
            var data = JSON.parse(textarea.value);
            if (!data['@type']) {
                errEl.textContent = 'Valid JSON but missing @type field.';
                errEl.style.display = 'block';
                errEl.style.color = '#dba617';
                return;
            }
            errEl.textContent = 'Valid JSON-LD (' + data['@type'] + ')';
            errEl.style.display = 'block';
            errEl.style.color = '#00a32a';
        } catch(e) {
            errEl.textContent = 'Invalid JSON: ' + e.message;
            errEl.style.display = 'block';
            errEl.style.color = '#b32d2e';
        }
    }

    function krFormatJson(btn) {
        var item = btn.closest('.kr-schema-item');
        var textarea = item.querySelector('.kr-schema-textarea');
        try {
            var data = JSON.parse(textarea.value);
            textarea.value = JSON.stringify(data, null, 2);
        } catch(e) {
            // Can't format invalid JSON
        }
    }

    function krDeleteSchema(link) {
        var key = link.getAttribute('data-key');
        if (!confirm('Delete the "' + key + '" schema?')) return;
        var item = link.closest('.kr-schema-item');
        item.remove();

        var deleted = document.getElementById('kr-schemas-deleted');
        deleted.value = deleted.value ? deleted.value + ',' + key : key;

        var list = document.getElementById('kr-schemas-list');
        if (!list.children.length) {
            var empty = document.getElementById('kr-empty-msg');
            if (empty) empty.style.display = 'block';
        }
    }

    function krShowAddForm() {
        var key = document.getElementById('kr-new-key').value.trim();
        if (!key) { alert('Enter a schema key first.'); return; }
        if (!/^[a-z0-9_-]+$/.test(key)) { alert('Key must be lowercase letters, numbers, hyphens, or underscores.'); return; }

        var existing = document.querySelector('.kr-schema-item[data-key="' + key + '"]');
        if (existing && !confirm('A schema with key "' + key + '" already exists. Adding will replace it. Continue?')) return;

        document.querySelector('.kr-add-textarea').style.display = 'block';
        document.getElementById('kr-add-actions').style.display = 'flex';
        document.getElementById('kr-new-json').focus();
    }

    function krAddSchema() {
        var key = document.getElementById('kr-new-key').value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
        var jsonStr = document.getElementById('kr-new-json').value.trim();
        var errEl = document.getElementById('kr-add-error');

        if (!key) { errEl.textContent = 'Key is required.'; errEl.style.display = 'block'; return; }
        if (!jsonStr) { errEl.textContent = 'JSON data is required.'; errEl.style.display = 'block'; return; }

        try {
            var data = JSON.parse(jsonStr);
            if (!data['@type']) { errEl.textContent = 'JSON must include an @type field.'; errEl.style.display = 'block'; return; }
        } catch(e) {
            errEl.textContent = 'Invalid JSON: ' + e.message; errEl.style.display = 'block'; return;
        }

        errEl.style.display = 'none';

        var existing = document.querySelector('.kr-schema-item[data-key="' + key + '"]');
        if (existing) existing.remove();

        var deleted = document.getElementById('kr-schemas-deleted');
        if (deleted.value) {
            var parts = deleted.value.split(',').filter(function(k) { return k !== key; });
            deleted.value = parts.join(',');
        }

        var formatted = JSON.stringify(data, null, 2);
        var now = new Date().toISOString().replace('T', ' ').substring(0, 19);
        var type = data['@type'];

        var html = '<div class="kr-schema-item" data-key="' + key + '">' +
            '<div class="kr-schema-header" onclick="krToggleSchema(this)">' +
                '<span class="kr-toggle-arrow kr-rotated">&#9654;</span>' +
                '<span class="kr-schema-title">' + key + '</span>' +
                '<span class="kr-schema-type">' + type + '</span>' +
                '<span class="kr-schema-date">Added ' + now + '</span>' +
            '</div>' +
            '<div class="kr-schema-body kr-open">' +
                '<textarea class="kr-schema-textarea" name="kr_schemas[' + key + ']" spellcheck="false">' +
                    formatted.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') +
                '</textarea>' +
                '<div class="kr-validation-error" id="kr-err-' + key + '"></div>' +
                '<div class="kr-schema-actions">' +
                    '<button type="button" class="button button-small" onclick="krValidateJson(this)">Validate JSON</button>' +
                    '<button type="button" class="button button-small" onclick="krFormatJson(this)">Format</button>' +
                    '<a class="kr-schema-delete" onclick="krDeleteSchema(this)" data-key="' + key + '">Delete</a>' +
                '</div>' +
            '</div>' +
        '</div>';

        var list = document.getElementById('kr-schemas-list');
        list.insertAdjacentHTML('beforeend', html);

        var emptyMsg = document.getElementById('kr-empty-msg');
        if (emptyMsg) emptyMsg.style.display = 'none';

        krCancelAdd();
        document.getElementById('kr-new-key').value = '';
    }

    function krCancelAdd() {
        document.querySelector('.kr-add-textarea').style.display = 'none';
        document.getElementById('kr-add-actions').style.display = 'none';
        document.getElementById('kr-new-json').value = '';
        document.getElementById('kr-add-error').style.display = 'none';
    }
    </script>
    <?php
}

add_action( 'save_post', 'kr_save_schema_meta_box', 10, 2 );

function kr_save_schema_meta_box( $post_id, $post ) {
    if ( ! isset( $_POST['kr_schema_nonce'] ) || ! wp_verify_nonce( $_POST['kr_schema_nonce'], 'kr_schema_meta_box' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $existing = get_post_meta( $post_id, '_kr_page_schemas', true );
    if ( ! is_array( $existing ) ) {
        $existing = array();
    }

    if ( ! empty( $_POST['kr_schemas_deleted'] ) ) {
        $deleted_keys = array_filter( explode( ',', sanitize_text_field( $_POST['kr_schemas_deleted'] ) ) );
        foreach ( $deleted_keys as $dk ) {
            unset( $existing[ $dk ] );
        }
    }

    if ( ! empty( $_POST['kr_schemas'] ) && is_array( $_POST['kr_schemas'] ) ) {
        foreach ( $_POST['kr_schemas'] as $key => $json_str ) {
            $key      = sanitize_key( $key );
            $json_str = wp_unslash( $json_str );
            $data     = json_decode( $json_str, true );

            if ( ! is_array( $data ) || empty( $data['@type'] ) ) {
                continue;
            }

            if ( empty( $data['@context'] ) ) {
                $data['@context'] = 'https://schema.org';
            }

            $existing[ $key ] = array(
                'key'   => $key,
                'type'  => $data['@type'],
                'data'  => $data,
                'added' => isset( $existing[ $key ]['added'] ) ? $existing[ $key ]['added'] : gmdate( 'Y-m-d H:i:s' ),
            );
        }
    }

    if ( empty( $existing ) ) {
        delete_post_meta( $post_id, '_kr_page_schemas' );
    } else {
        update_post_meta( $post_id, '_kr_page_schemas', $existing );
    }
}

// ─── Category ───────────────────────────────────────────────────────────────

add_action( 'wp_abilities_api_categories_init', 'kr_register_ability_category' );

function kr_register_ability_category() {
    wp_register_ability_category(
        'krisrisner',
        array(
            'label'       => 'Kris Risner',
            'description' => 'Abilities for Kris Risner site analysis and content management.',
        )
    );
}

// ─── Register All Abilities ─────────────────────────────────────────────────

add_action( 'wp_abilities_api_init', 'kr_register_abilities' );

function kr_register_abilities() {

    // 1. Get content
    wp_register_ability(
        'krisrisner/get-content',
        array(
            'category'            => 'krisrisner',
            'label'               => 'Get Site Content',
            'description'         => 'Returns published pages and posts with full content, SEO meta (Rank Math), URL, date, and word count.',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'post_type' => array( 'type' => 'string', 'description' => 'post, page, or all. Default all.', 'default' => 'all' ),
                    'per_page'  => array( 'type' => 'integer', 'description' => 'Number of items. Default 50.', 'default' => 50 ),
                    'search'    => array( 'type' => 'string', 'description' => 'Optional keyword filter.', 'default' => '' ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array( 'items' => array( 'type' => 'array' ), 'total' => array( 'type' => 'integer' ) ),
            ),
            'permission_callback' => '__return_true',
            'execute_callback'    => 'kr_get_content_execute',
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        )
    );

    // 2. SEO audit
    wp_register_ability(
        'krisrisner/seo-audit',
        array(
            'category'            => 'krisrisner',
            'label'               => 'SEO Audit',
            'description'         => 'Returns SEO meta for all published pages/posts with issue flags.',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'post_type' => array( 'type' => 'string', 'description' => 'post, page, or all. Default all.', 'default' => 'all' ),
                    'per_page'  => array( 'type' => 'integer', 'description' => 'Number of items. Default 100.', 'default' => 100 ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array( 'items' => array( 'type' => 'array' ), 'total' => array( 'type' => 'integer' ) ),
            ),
            'permission_callback' => '__return_true',
            'execute_callback'    => 'kr_seo_audit_execute',
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        )
    );

    // 3. Site structure
    wp_register_ability(
        'krisrisner/site-structure',
        array(
            'category'            => 'krisrisner',
            'label'               => 'Site Structure',
            'description'         => 'Returns the page hierarchy with parent/child relationships.',
            'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array( 'pages' => array( 'type' => 'array' ), 'total' => array( 'type' => 'integer' ) ),
            ),
            'permission_callback' => '__return_true',
            'execute_callback'    => 'kr_site_structure_execute',
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        )
    );

    // 4. Internal links
    wp_register_ability(
        'krisrisner/internal-links',
        array(
            'category'            => 'krisrisner',
            'label'               => 'Internal Links',
            'description'         => 'Analyzes internal linking for a specific page or all pages.',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id'  => array( 'type' => 'integer', 'description' => 'Specific post/page ID, or 0 for all.', 'default' => 0 ),
                    'per_page' => array( 'type' => 'integer', 'description' => 'Number of pages. Default 50.', 'default' => 50 ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array( 'items' => array( 'type' => 'array' ) ),
            ),
            'permission_callback' => '__return_true',
            'execute_callback'    => 'kr_internal_links_execute',
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        )
    );

    // 5. Plugins status
    wp_register_ability(
        'krisrisner/plugins-status',
        array(
            'category'            => 'krisrisner',
            'label'               => 'Plugins Status',
            'description'         => 'Returns installed plugins with version, active status, and updates.',
            'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array( 'plugins' => array( 'type' => 'array' ), 'total' => array( 'type' => 'integer' ) ),
            ),
            'permission_callback' => '__return_true',
            'execute_callback'    => 'kr_plugins_status_execute',
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        )
    );

    // 6. Gravity Forms
    wp_register_ability(
        'krisrisner/gravity-forms',
        array(
            'category'            => 'krisrisner',
            'label'               => 'Gravity Forms Data',
            'description'         => 'Returns Gravity Forms list or recent entries for a specific form.',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'form_id'  => array( 'type' => 'integer', 'description' => 'Form ID for entries, or 0 for all.', 'default' => 0 ),
                    'per_page' => array( 'type' => 'integer', 'description' => 'Number of entries. Default 20.', 'default' => 20 ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array( 'forms' => array( 'type' => 'array' ), 'entries' => array( 'type' => 'array' ) ),
            ),
            'permission_callback' => '__return_true',
            'execute_callback'    => 'kr_gravity_forms_execute',
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        )
    );

    // 7. Get Bricks content
    wp_register_ability(
        'krisrisner/get-bricks-content',
        array(
            'category'            => 'krisrisner',
            'label'               => 'Get Bricks Content',
            'description'         => 'Returns Bricks Builder elements with IDs, types, parent relationships, and settings.',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id'       => array( 'type' => 'integer', 'description' => 'Page/post ID. Required.' ),
                    'element_types' => array( 'type' => 'array', 'description' => 'Filter by type. Empty for all.', 'default' => array() ),
                    'include_raw'   => array( 'type' => 'boolean', 'description' => 'Include full raw settings.', 'default' => false ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ), 'post_id' => array( 'type' => 'integer' ),
                    'builder' => array( 'type' => 'string' ), 'elements' => array( 'type' => 'array' ),
                    'total'   => array( 'type' => 'integer' ),
                ),
            ),
            'permission_callback' => '__return_true',
            'execute_callback'    => 'kr_get_bricks_content_execute',
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        )
    );

    // 8. Update SEO meta
    wp_register_ability(
        'krisrisner/update-seo-meta',
        array(
            'category'            => 'krisrisner',
            'label'               => 'Update SEO Meta',
            'description'         => 'Updates Rank Math SEO meta: title, description, focus keyword, robots, canonical, schema type.',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id'         => array( 'type' => 'integer', 'description' => 'Page/post ID. Required.' ),
                    'focus_keyword'   => array( 'type' => 'string', 'default' => '' ),
                    'seo_title'       => array( 'type' => 'string', 'default' => '' ),
                    'seo_description' => array( 'type' => 'string', 'default' => '' ),
                    'robots'          => array( 'type' => 'array', 'default' => array() ),
                    'canonical'       => array( 'type' => 'string', 'default' => '' ),
                    'schema_type'     => array( 'type' => 'string', 'default' => '' ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ), 'updated' => array( 'type' => 'array' ),
                    'post_id' => array( 'type' => 'integer' ),
                ),
            ),
            'permission_callback' => '__return_true',
            'execute_callback'    => 'kr_update_seo_meta_execute',
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        )
    );

    // 9. Update Bricks content
    wp_register_ability(
        'krisrisner/update-bricks-content',
        array(
            'category'            => 'krisrisner',
            'label'               => 'Update Bricks Content',
            'description'         => 'Updates existing Bricks elements by ID. Merges settings, backs up before writing.',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'Page/post ID. Required.' ),
                    'updates' => array(
                        'type' => 'array', 'description' => 'Array of {element_id, settings}.',
                        'items' => array(
                            'type' => 'object',
                            'properties' => array( 'element_id' => array( 'type' => 'string' ), 'settings' => array( 'type' => 'object' ) ),
                            'required' => array( 'element_id', 'settings' ),
                        ),
                    ),
                    'dry_run' => array( 'type' => 'boolean', 'default' => false ),
                ),
                'required' => array( 'post_id', 'updates' ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ), 'post_id' => array( 'type' => 'integer' ),
                    'dry_run' => array( 'type' => 'boolean' ), 'changes' => array( 'type' => 'array' ),
                    'elements_updated' => array( 'type' => 'integer' ),
                ),
            ),
            'permission_callback' => '__return_true',
            'execute_callback'    => 'kr_update_bricks_content_execute',
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        )
    );

    // 10. Manage page schema
    wp_register_ability(
        'krisrisner/manage-page-schema',
        array(
            'category'            => 'krisrisner',
            'label'               => 'Manage Page Schema',
            'description'         => 'Add, list, or remove JSON-LD schema blocks rendered in <head> via wp_head. Also editable in the WP admin page editor.',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'post_id' => array( 'type' => 'integer', 'description' => 'Page/post ID. Required.' ),
                    'action'  => array( 'type' => 'string', 'description' => '"add", "remove", "list", or "clear".', 'default' => 'list' ),
                    'key'     => array( 'type' => 'string', 'description' => 'Unique schema key. Required for add/remove.', 'default' => '' ),
                    'data'    => array( 'type' => 'object', 'description' => 'Schema.org JSON-LD object. Required for add.' ),
                ),
                'required' => array( 'post_id' ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'success' => array( 'type' => 'boolean' ), 'post_id' => array( 'type' => 'integer' ),
                    'action'  => array( 'type' => 'string' ), 'schemas' => array( 'type' => 'array' ),
                ),
            ),
            'permission_callback' => '__return_true',
            'execute_callback'    => 'kr_manage_page_schema_execute',
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        )
    );

    // 11. Get images missing alt text
    wp_register_ability(
        'krisrisner/get-images-missing-alt',
        array(
            'category'            => 'krisrisner',
            'label'               => 'Get Images Missing Alt Text',
            'description'         => 'Returns all media library images with empty or missing alt text, including their public URL so Claude can view each image before writing alt text.',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'per_page' => array( 'type' => 'integer', 'description' => 'Max images to return. Default 100.', 'default' => 100 ),
                ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array( 'images' => array( 'type' => 'array' ), 'total' => array( 'type' => 'integer' ) ),
            ),
            'permission_callback' => '__return_true',
            'execute_callback'    => 'kr_get_images_missing_alt_execute',
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        )
    );

    // 12. Update image alt text
    wp_register_ability(
        'krisrisner/update-image-alt',
        array(
            'category'            => 'krisrisner',
            'label'               => 'Update Image Alt Text',
            'description'         => 'Batch-updates alt text for one or more media library images by attachment ID.',
            'input_schema'        => array(
                'type'       => 'object',
                'properties' => array(
                    'updates' => array(
                        'type'        => 'array',
                        'description' => 'Array of {id, alt} pairs.',
                        'items'       => array(
                            'type'       => 'object',
                            'properties' => array(
                                'id'  => array( 'type' => 'integer', 'description' => 'Attachment ID.' ),
                                'alt' => array( 'type' => 'string', 'description' => 'Alt text to set.' ),
                            ),
                            'required' => array( 'id', 'alt' ),
                        ),
                    ),
                ),
                'required' => array( 'updates' ),
            ),
            'output_schema'       => array(
                'type'       => 'object',
                'properties' => array(
                    'updated' => array( 'type' => 'integer' ),
                    'items'   => array( 'type' => 'array' ),
                ),
            ),
            'permission_callback' => '__return_true',
            'execute_callback'    => 'kr_update_image_alt_execute',
            'meta'                => array( 'mcp' => array( 'public' => true ) ),
        )
    );
}

// ─── Helpers ────────────────────────────────────────────────────────────────

function kr_get_bricks_elements( $post_id ) {
    $meta = get_post_meta( $post_id, '_bricks_page_content_2', true );
    if ( empty( $meta ) ) return array();
    if ( is_string( $meta ) ) {
        $decoded = json_decode( $meta, true );
        return is_array( $decoded ) ? $decoded : array();
    }
    return is_array( $meta ) ? $meta : array();
}

function kr_save_bricks_elements( $post_id, $elements, $original_meta = null ) {
    global $wpdb;

    if ( null !== $original_meta ) {
        $backup_key = '_bricks_page_content_2_backup_' . gmdate( 'Ymd_His' );
        update_post_meta( $post_id, $backup_key, $original_meta );
    }

    // Attempt 1: standard update_post_meta. Fires all normal hooks/filters.
    update_post_meta( $post_id, '_bricks_page_content_2', $elements );
    delete_post_meta( $post_id, '_bricks_page_content_2_html' );
    delete_post_meta( $post_id, '_bricks_page_content_2_css' );

    // Explicit object cache invalidation for persistent caches (Kinsta Redis, etc.).
    wp_cache_delete( $post_id, 'post_meta' );
    clean_post_cache( $post_id );

    // Verify the standard write landed by reading the raw DB row.
    // Bypass all caching by querying wp_postmeta directly.
    $raw_db_value = $wpdb->get_var( $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
        $post_id,
        '_bricks_page_content_2'
    ) );
    $db_elements = $raw_db_value ? maybe_unserialize( $raw_db_value ) : null;

    $write_ok = false;
    if ( is_array( $db_elements ) && count( $db_elements ) === count( $elements ) ) {
        // Spot-check the first element's settings to confirm content actually changed.
        $expected_first_id = $elements[0]['id'] ?? null;
        $actual_first_id   = $db_elements[0]['id'] ?? null;
        if ( $expected_first_id === $actual_first_id ) {
            // Compare serialized settings of every updated element against what we wrote.
            $write_ok = ( serialize( $db_elements ) === serialize( $elements ) );
        }
    }

    // Attempt 2: if the standard write didn't land, bypass hooks via $wpdb direct write.
    // This catches cases where a filter (e.g. Bricks code signature verification,
    // Wordfence file integrity, or a custom meta_filter) is silently rejecting the update.
    if ( ! $write_ok ) {
        $serialized = maybe_serialize( $elements );
        $existing   = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
            $post_id,
            '_bricks_page_content_2'
        ) );

        if ( $existing ) {
            $wpdb->update(
                $wpdb->postmeta,
                array( 'meta_value' => $serialized ),
                array( 'meta_id' => $existing )
            );
        } else {
            $wpdb->insert(
                $wpdb->postmeta,
                array(
                    'post_id'    => $post_id,
                    'meta_key'   => '_bricks_page_content_2',
                    'meta_value' => $serialized,
                )
            );
        }

        // Clear caches again after the direct write.
        wp_cache_delete( $post_id, 'post_meta' );
        clean_post_cache( $post_id );
    }

    return isset( $backup_key ) ? $backup_key : '';
}

// ─── Callbacks ──────────────────────────────────────────────────────────────

function kr_get_content_execute( $input ) {
    $post_type = isset( $input['post_type'] ) ? $input['post_type'] : 'all';
    $per_page  = isset( $input['per_page'] ) ? intval( $input['per_page'] ) : 50;
    $search    = isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';

    $args = array(
        'post_status' => 'publish', 'posts_per_page' => $per_page, 'orderby' => 'date', 'order' => 'DESC',
        'post_type'   => 'all' === $post_type ? array( 'post', 'page' ) : $post_type,
    );
    if ( ! empty( $search ) ) $args['s'] = $search;

    $query = new WP_Query( $args );
    $items = array();
    foreach ( $query->posts as $post ) {
        $content = wp_strip_all_tags( $post->post_content );
        $items[] = array(
            'id' => $post->ID, 'title' => $post->post_title, 'url' => get_permalink( $post->ID ),
            'post_type' => $post->post_type, 'date' => get_the_date( 'Y-m-d', $post->ID ),
            'modified' => get_the_modified_date( 'Y-m-d', $post->ID ), 'word_count' => str_word_count( $content ),
            'content' => $content,
            'excerpt' => has_excerpt( $post->ID ) ? get_the_excerpt( $post ) : wp_trim_words( $post->post_content, 40 ),
            'seo_title' => get_post_meta( $post->ID, 'rank_math_title', true ),
            'seo_description' => get_post_meta( $post->ID, 'rank_math_description', true ),
            'focus_keyword' => get_post_meta( $post->ID, 'rank_math_focus_keyword', true ),
            'robots' => get_post_meta( $post->ID, 'rank_math_robots', true ),
            'canonical' => get_post_meta( $post->ID, 'rank_math_canonical_url', true ),
        );
    }
    return array( 'items' => $items, 'total' => $query->found_posts );
}

function kr_seo_audit_execute( $input ) {
    $post_type = isset( $input['post_type'] ) ? $input['post_type'] : 'all';
    $per_page  = isset( $input['per_page'] ) ? intval( $input['per_page'] ) : 100;

    $query = new WP_Query( array(
        'post_status' => 'publish', 'posts_per_page' => $per_page, 'orderby' => 'menu_order', 'order' => 'ASC',
        'post_type'   => 'all' === $post_type ? array( 'post', 'page' ) : $post_type,
    ) );

    $items = array();
    foreach ( $query->posts as $post ) {
        $content   = wp_strip_all_tags( $post->post_content );
        $seo_title = get_post_meta( $post->ID, 'rank_math_title', true );
        $seo_desc  = get_post_meta( $post->ID, 'rank_math_description', true );
        $focus_kw  = get_post_meta( $post->ID, 'rank_math_focus_keyword', true );

        $issues = array();
        if ( empty( $seo_title ) )                                $issues[] = 'missing_seo_title';
        if ( empty( $seo_desc ) )                                 $issues[] = 'missing_meta_description';
        if ( empty( $focus_kw ) )                                 $issues[] = 'missing_focus_keyword';
        if ( str_word_count( $content ) < 300 )                   $issues[] = 'thin_content';
        if ( ! empty( $seo_desc ) && strlen( $seo_desc ) > 160 )  $issues[] = 'meta_description_too_long';
        if ( ! empty( $seo_title ) && strlen( $seo_title ) > 60 ) $issues[] = 'seo_title_too_long';

        $items[] = array(
            'id' => $post->ID, 'title' => $post->post_title, 'url' => get_permalink( $post->ID ),
            'post_type' => $post->post_type, 'word_count' => str_word_count( $content ),
            'seo_title' => $seo_title, 'seo_title_len' => strlen( $seo_title ?: '' ),
            'seo_description' => $seo_desc, 'seo_desc_len' => strlen( $seo_desc ?: '' ),
            'focus_keyword' => $focus_kw,
            'robots' => get_post_meta( $post->ID, 'rank_math_robots', true ),
            'canonical' => get_post_meta( $post->ID, 'rank_math_canonical_url', true ),
            'schema_type' => get_post_meta( $post->ID, 'rank_math_rich_snippet', true ),
            'seo_score' => get_post_meta( $post->ID, 'rank_math_seo_score', true ),
            'issues' => $issues,
        );
    }
    return array( 'items' => $items, 'total' => $query->found_posts );
}

function kr_site_structure_execute( $input ) {
    $pages = get_pages( array( 'sort_column' => 'menu_order, post_title', 'sort_order' => 'ASC' ) );
    $items = array();
    foreach ( $pages as $page ) {
        $pt = $page->post_parent ? get_post( $page->post_parent ) : null;
        $items[] = array(
            'id' => $page->ID, 'title' => $page->post_title, 'url' => get_permalink( $page->ID ),
            'slug' => $page->post_name, 'parent_id' => $page->post_parent,
            'parent_title' => $pt ? $pt->post_title : '', 'menu_order' => $page->menu_order,
            'depth' => count( get_post_ancestors( $page->ID ) ),
            'template' => get_page_template_slug( $page->ID ) ?: 'default',
        );
    }
    return array( 'pages' => $items, 'total' => count( $items ) );
}

function kr_internal_links_execute( $input ) {
    $post_id  = isset( $input['post_id'] ) ? intval( $input['post_id'] ) : 0;
    $per_page = isset( $input['per_page'] ) ? intval( $input['per_page'] ) : 50;
    $site_url = home_url();

    $posts = $post_id > 0
        ? array( get_post( $post_id ) )
        : ( new WP_Query( array( 'post_status' => 'publish', 'posts_per_page' => $per_page, 'post_type' => array( 'post', 'page' ) ) ) )->posts;

    $items = array();
    foreach ( $posts as $post ) {
        if ( ! $post ) continue;
        $links = array();
        if ( preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', $post->post_content, $m ) ) {
            foreach ( $m[1] as $i => $href ) {
                if ( strpos( $href, $site_url ) === 0 || strpos( $href, '/' ) === 0 ) {
                    $links[] = array( 'url' => $href, 'anchor_text' => wp_strip_all_tags( $m[2][ $i ] ) );
                }
            }
        }
        $items[] = array( 'id' => $post->ID, 'title' => $post->post_title, 'url' => get_permalink( $post->ID ), 'internal_links' => $links, 'link_count' => count( $links ) );
    }
    return array( 'items' => $items );
}

function kr_plugins_status_execute( $input ) {
    if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
    if ( ! function_exists( 'get_plugin_updates' ) ) require_once ABSPATH . 'wp-admin/includes/update.php';

    $all = get_plugins(); $active = get_option( 'active_plugins', array() ); $updates = get_plugin_updates();
    $items = array();
    foreach ( $all as $file => $data ) {
        $hu = isset( $updates[ $file ] );
        $items[] = array( 'file' => $file, 'name' => $data['Name'], 'version' => $data['Version'],
            'active' => in_array( $file, $active, true ), 'update_available' => $hu,
            'update_version' => $hu ? $updates[ $file ]->update->new_version : '', 'author' => $data['AuthorName'] );
    }
    return array( 'plugins' => $items, 'total' => count( $items ) );
}

function kr_gravity_forms_execute( $input ) {
    if ( ! class_exists( 'GFAPI' ) ) return array( 'error' => 'Gravity Forms is not active.' );
    $form_id = isset( $input['form_id'] ) ? intval( $input['form_id'] ) : 0;
    $per_page = isset( $input['per_page'] ) ? intval( $input['per_page'] ) : 20;

    if ( 0 === $form_id ) {
        $items = array();
        foreach ( GFAPI::get_forms() as $f ) {
            $items[] = array( 'id' => $f['id'], 'title' => $f['title'], 'entry_count' => GFAPI::count_entries( $f['id'] ), 'is_active' => (bool) $f['is_active'] );
        }
        return array( 'forms' => $items );
    }

    $entries = GFAPI::get_entries( $form_id, array( 'status' => 'active' ), array( 'key' => 'date_created', 'direction' => 'DESC' ), array( 'offset' => 0, 'page_size' => $per_page ) );
    $form = GFAPI::get_form( $form_id ); $fields = array();
    if ( $form && isset( $form['fields'] ) ) foreach ( $form['fields'] as $f ) $fields[ $f->id ] = $f->label;

    $items = array();
    foreach ( $entries as $e ) {
        $ed = array( 'entry_id' => $e['id'], 'date_created' => $e['date_created'], 'source_url' => $e['source_url'], 'fields' => array() );
        foreach ( $fields as $fid => $label ) { $v = rgar( $e, $fid ); if ( ! empty( $v ) ) $ed['fields'][ $label ] = $v; }
        $items[] = $ed;
    }
    return array( 'form' => $form['title'], 'entries' => $items );
}

function kr_get_bricks_content_execute( $input ) {
    $post_id = isset( $input['post_id'] ) ? intval( $input['post_id'] ) : 0;
    $types   = isset( $input['element_types'] ) ? (array) $input['element_types'] : array();
    $raw     = isset( $input['include_raw'] ) ? (bool) $input['include_raw'] : false;

    if ( ! $post_id || ! get_post( $post_id ) ) return array( 'success' => false, 'error' => 'Invalid post ID.' );
    $elements = kr_get_bricks_elements( $post_id );
    if ( empty( $elements ) ) return array( 'success' => true, 'post_id' => $post_id, 'builder' => 'none', 'elements' => array(), 'total' => 0 );

    $items = array();
    foreach ( $elements as $el ) {
        $name = $el['name'] ?? '';
        if ( ! empty( $types ) && ! in_array( $name, $types, true ) ) continue;
        $s = $el['settings'] ?? array();
        $item = array( 'element_id' => $el['id'] ?? '', 'name' => $name, 'parent' => $el['parent'] ?? 0, 'label' => $el['label'] ?? '' );
        foreach ( array( 'text', 'tag', 'link', 'title', 'content', 'label', 'placeholder', 'icon', '_cssClasses', 'code', 'executeCode', 'noRender' ) as $f ) {
            if ( isset( $s[ $f ] ) ) $item[ $f ] = $s[ $f ];
        }
        if ( $raw ) $item['raw_settings'] = $s;
        $items[] = $item;
    }
    return array( 'success' => true, 'post_id' => $post_id, 'builder' => 'bricks', 'elements' => $items, 'total' => count( $items ) );
}

function kr_update_seo_meta_execute( $input ) {
    $post_id = isset( $input['post_id'] ) ? intval( $input['post_id'] ) : 0;
    if ( ! $post_id || ! get_post( $post_id ) ) return array( 'success' => false, 'error' => 'Invalid post ID.' );

    $map = array( 'focus_keyword' => 'rank_math_focus_keyword', 'seo_title' => 'rank_math_title',
        'seo_description' => 'rank_math_description', 'canonical' => 'rank_math_canonical_url', 'schema_type' => 'rank_math_rich_snippet' );
    $updated = array();
    foreach ( $map as $k => $mk ) {
        if ( ! empty( $input[ $k ] ) ) { update_post_meta( $post_id, $mk, sanitize_text_field( $input[ $k ] ) ); $updated[] = $k; }
    }
    if ( ! empty( $input['robots'] ) && is_array( $input['robots'] ) ) {
        update_post_meta( $post_id, 'rank_math_robots', array_map( 'sanitize_text_field', $input['robots'] ) ); $updated[] = 'robots';
    }
    return array( 'success' => true, 'post_id' => $post_id, 'updated' => $updated, 'message' => sprintf( 'Updated %d field(s) for post %d.', count( $updated ), $post_id ) );
}

function kr_update_bricks_content_execute( $input ) {
    $post_id = isset( $input['post_id'] ) ? intval( $input['post_id'] ) : 0;
    $updates = isset( $input['updates'] ) ? (array) $input['updates'] : array();
    $dry_run = isset( $input['dry_run'] ) ? (bool) $input['dry_run'] : false;

    if ( ! $post_id || ! get_post( $post_id ) ) return array( 'success' => false, 'error' => 'Invalid post ID.' );
    if ( empty( $updates ) ) return array( 'success' => false, 'error' => 'No updates provided.' );

    $orig = get_post_meta( $post_id, '_bricks_page_content_2', true );
    $elements = kr_get_bricks_elements( $post_id );
    if ( empty( $elements ) ) return array( 'success' => false, 'error' => 'No Bricks content found.' );

    $map = array(); foreach ( $elements as $i => $e ) { if ( isset( $e['id'] ) ) $map[ $e['id'] ] = $i; }
    $changes = array(); $nf = array(); $cnt = 0;

    foreach ( $updates as $u ) {
        $eid = $u['element_id'] ?? ''; $ns = (array) ( $u['settings'] ?? array() );
        if ( empty( $eid ) || empty( $ns ) ) continue;
        if ( ! isset( $map[ $eid ] ) ) { $nf[] = $eid; continue; }
        $idx = $map[ $eid ]; $cs = $elements[ $idx ]['settings'] ?? array();
        $ch = array( 'element_id' => $eid, 'name' => $elements[ $idx ]['name'] ?? '', 'fields' => array() );
        foreach ( $ns as $k => $v ) {
            $ch['fields'][] = array( 'field' => $k, 'old' => $cs[ $k ] ?? null, 'new' => $v );
            $elements[ $idx ]['settings'][ $k ] = ( is_array( $v ) && is_array( $cs[ $k ] ?? null ) ) ? array_merge( $cs[ $k ], $v ) : $v;
        }
        $changes[] = $ch; $cnt++;
    }

    if ( $dry_run ) return array( 'success' => true, 'post_id' => $post_id, 'dry_run' => true, 'changes' => $changes, 'not_found' => $nf, 'elements_updated' => $cnt );
    $bk = kr_save_bricks_elements( $post_id, $elements, $orig );

    // Final verification: read directly from the DB via $wpdb, bypassing all
    // WordPress cache layers (object cache, meta cache). This is the ground truth.
    global $wpdb;
    $raw = $wpdb->get_var( $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
        $post_id,
        '_bricks_page_content_2'
    ) );
    $verify = $raw ? maybe_unserialize( $raw ) : array();
    if ( ! is_array( $verify ) ) $verify = array();

    if ( count( $verify ) !== count( $elements ) ) {
        return array(
            'success'        => false,
            'error'          => 'Write verification failed: element count mismatch after save (read directly from DB).',
            'post_id'        => $post_id,
            'backup_key'     => $bk,
            'expected_count' => count( $elements ),
            'actual_count'   => count( $verify ),
        );
    }

    // Spot-check that at least one updated field actually persisted.
    if ( ! empty( $changes ) ) {
        $first_change = $changes[0];
        $first_eid    = $first_change['element_id'];
        $first_field  = $first_change['fields'][0]['field'] ?? '';
        $first_new    = $first_change['fields'][0]['new'] ?? null;
        if ( $first_field && null !== $first_new ) {
            $verify_map = array();
            foreach ( $verify as $i => $e ) { if ( isset( $e['id'] ) ) $verify_map[ $e['id'] ] = $i; }
            if ( isset( $verify_map[ $first_eid ] ) ) {
                $verify_val = $verify[ $verify_map[ $first_eid ] ]['settings'][ $first_field ] ?? null;
                if ( $verify_val !== $first_new ) {
                    return array(
                        'success'    => false,
                        'error'      => 'Write verification failed: value read back does not match value written (read directly from DB, so this is NOT a cache issue — something is rejecting or rewriting the meta value).',
                        'post_id'    => $post_id,
                        'backup_key' => $bk,
                        'element_id' => $first_eid,
                        'field'      => $first_field,
                        'wrote'      => $first_new,
                        'read_back'  => $verify_val,
                    );
                }
            }
        }
    }

    return array( 'success' => true, 'post_id' => $post_id, 'dry_run' => false, 'changes' => $changes, 'not_found' => $nf, 'elements_updated' => $cnt, 'backup_key' => $bk );
}

function kr_manage_page_schema_execute( $input ) {
    $post_id = isset( $input['post_id'] ) ? intval( $input['post_id'] ) : 0;
    $action  = isset( $input['action'] ) ? $input['action'] : 'list';
    $key     = isset( $input['key'] ) ? sanitize_key( $input['key'] ) : '';
    $data    = isset( $input['data'] ) ? (array) $input['data'] : array();

    if ( ! $post_id || ! get_post( $post_id ) ) return array( 'success' => false, 'error' => 'Invalid post ID.' );
    $schemas = get_post_meta( $post_id, '_kr_page_schemas', true );
    if ( ! is_array( $schemas ) ) $schemas = array();

    switch ( $action ) {
        case 'add':
            if ( empty( $key ) ) return array( 'success' => false, 'error' => 'Key is required.' );
            if ( empty( $data ) || empty( $data['@type'] ) ) return array( 'success' => false, 'error' => 'Schema data with @type is required.' );
            if ( empty( $data['@context'] ) ) $data['@context'] = 'https://schema.org';
            $schemas[ $key ] = array( 'key' => $key, 'type' => $data['@type'], 'data' => $data, 'added' => gmdate( 'Y-m-d H:i:s' ) );
            update_post_meta( $post_id, '_kr_page_schemas', $schemas );
            return array( 'success' => true, 'post_id' => $post_id, 'action' => 'add', 'key' => $key, 'schema_type' => $data['@type'], 'total' => count( $schemas ) );

        case 'remove':
            if ( empty( $key ) ) return array( 'success' => false, 'error' => 'Key is required.' );
            if ( ! isset( $schemas[ $key ] ) ) return array( 'success' => false, 'error' => sprintf( 'Schema "%s" not found.', $key ) );
            unset( $schemas[ $key ] );
            update_post_meta( $post_id, '_kr_page_schemas', $schemas );
            return array( 'success' => true, 'post_id' => $post_id, 'action' => 'remove', 'key' => $key, 'total' => count( $schemas ) );

        case 'clear':
            delete_post_meta( $post_id, '_kr_page_schemas' );
            return array( 'success' => true, 'post_id' => $post_id, 'action' => 'clear', 'total' => 0 );

        case 'list': default:
            $list = array();
            foreach ( $schemas as $sk => $sv ) $list[] = array( 'key' => $sk, 'type' => $sv['type'] ?? '', 'added' => $sv['added'] ?? '' );
            return array( 'success' => true, 'post_id' => $post_id, 'action' => 'list', 'schemas' => $list, 'total' => count( $list ) );
    }
}

// ─── New Callbacks: Image Alt Text ──────────────────────────────────────────

function kr_get_images_missing_alt_execute( $input ) {
    $per_page = isset( $input['per_page'] ) ? intval( $input['per_page'] ) : 100;

    $images = get_posts( array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'posts_per_page' => $per_page,
        'orderby'        => 'ID',
        'order'          => 'ASC',
        'meta_query'     => array(
            'relation' => 'OR',
            array(
                'key'     => '_wp_attachment_image_alt',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => '_wp_attachment_image_alt',
                'value'   => '',
                'compare' => '=',
            ),
        ),
    ) );

    $items = array();
    foreach ( $images as $img ) {
        $items[] = array(
            'id'       => $img->ID,
            'title'    => $img->post_title,
            'filename' => basename( get_attached_file( $img->ID ) ),
            'url'      => wp_get_attachment_url( $img->ID ),
            'alt'      => get_post_meta( $img->ID, '_wp_attachment_image_alt', true ),
        );
    }

    return array( 'images' => $items, 'total' => count( $items ) );
}

function kr_update_image_alt_execute( $input ) {
    $updates = isset( $input['updates'] ) ? (array) $input['updates'] : array();
    if ( empty( $updates ) ) return array( 'success' => false, 'error' => 'No updates provided.' );

    $results = array();
    foreach ( $updates as $item ) {
        $id  = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
        $alt = isset( $item['alt'] ) ? sanitize_text_field( $item['alt'] ) : '';

        if ( ! $id || ! get_post( $id ) ) {
            $results[] = array( 'id' => $id, 'status' => 'error', 'error' => 'Invalid attachment ID.' );
            continue;
        }

        update_post_meta( $id, '_wp_attachment_image_alt', $alt );
        $results[] = array( 'id' => $id, 'alt' => $alt, 'status' => 'updated' );
    }

    $success_count = count( array_filter( $results, fn( $r ) => $r['status'] === 'updated' ) );
    return array( 'success' => true, 'updated' => $success_count, 'items' => $results );
}
