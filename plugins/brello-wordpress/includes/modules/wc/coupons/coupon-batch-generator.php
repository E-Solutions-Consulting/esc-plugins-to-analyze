<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'BH_Coupon_Batch_Generator' ) ) {

class BH_Coupon_Batch_Generator {

    const DEFAULT_PREFIX  = 'BRELLO';
    const TOKEN_CHUNK     = 10;   // regenerate token every N codes

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_submenu' ], 20 );
        add_action( 'admin_post_bh_generate_coupon_batch',   [ $this, 'handle_generate' ] );
        add_action( 'admin_post_bh_export_coupon_batch_csv', [ $this, 'handle_export_csv' ] );
    }

    public function register_submenu() {
        add_submenu_page(
            PARENT_MENU_SLUG,
            'Coupon Batch Generator',
            'Coupon Batch',
            'manage_woocommerce',
            PARENT_MENU_SLUG . '--coupon-batch',
            [ $this, 'render_page' ]
        );
    }

    // =========================================================
    //  RENDER PAGE
    // =========================================================
    public function render_page() {
        wp_enqueue_style(  'bh-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', [], null );
        wp_enqueue_script( 'bh-select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', [ 'jquery' ], null, true );

        $has_wcs    = class_exists( 'WC_Subscriptions_Coupon' );
        $categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ] );
        $products   = wc_get_products( [ 'status' => 'publish', 'limit' => -1, 'orderby' => 'name', 'order' => 'ASC' ] );

        $batch_id      = isset( $_GET['batch_id'] )   ? sanitize_text_field( $_GET['batch_id'] )              : '';
        $success_count = isset( $_GET['bh_success'] ) ? (int) $_GET['bh_success']                             : 0;
        $error_msg     = isset( $_GET['bh_error'] )   ? sanitize_text_field( urldecode( $_GET['bh_error'] ) ) : '';
        $batch_coupons = $batch_id ? $this->get_batch_coupons( $batch_id ) : [];

        $cat_options = ! is_wp_error( $categories ) ? $categories : [];
        ?>
        <div class="wrap">
            <h1>Coupon Batch Generator</h1>

            <?php if ( $error_msg ) : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error_msg ); ?></p></div>
            <?php endif; ?>
            <?php if ( $success_count && $batch_id ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo (int) $success_count; ?> coupon(s) generated — Batch ID: <strong><?php echo esc_html( $batch_id ); ?></strong></p>
                </div>
            <?php endif; ?>

            <div class="bh-cbg-layout <?php echo $batch_coupons ? 'bh-cbg-layout--has-results' : ''; ?>">

                <!-- ═══════════════════════════════════════════
                     FORM
                ═══════════════════════════════════════════════ -->
                <div class="postbox bh-cbg-form-box">
                    <div class="postbox-header">
                        <h2 class="hndle">Coupon Configuration</h2>
                    </div>
                    <div class="inside" style="padding:0 0 1.5rem;">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="bh-cbg-form" novalidate>
                            <?php wp_nonce_field( 'bh_generate_coupon_batch', '_bh_nonce' ); ?>
                            <input type="hidden" name="action" value="bh_generate_coupon_batch">

                            <!-- ── VALIDATION SUMMARY ──────────────── -->
                            <div id="bh-cbg-errors" class="bh-cbg-error-box" style="display:none;"></div>

                            <!-- ── TAB NAV ──────────────────────────── -->
                            <ul class="bh-cbg-tabs" role="tablist">
                                <li class="active" data-tab="batch" role="tab">
                                    <a href="#tab-batch">
                                        <span class="dashicons dashicons-clipboard"></span>
                                        Batch
                                    </a>
                                </li>
                                <li data-tab="general" role="tab">
                                    <a href="#tab-general">
                                        <span class="dashicons dashicons-tag"></span>
                                        General
                                    </a>
                                </li>
                                <li data-tab="restriction" role="tab">
                                    <a href="#tab-restriction">
                                        <span class="dashicons dashicons-lock"></span>
                                        Usage Restriction
                                    </a>
                                </li>
                                <li data-tab="limits" role="tab">
                                    <a href="#tab-limits">
                                        <span class="dashicons dashicons-chart-bar"></span>
                                        Usage Limits
                                    </a>
                                </li>
                            </ul>

                            <!-- ══ TAB: BATCH ═══════════════════════════ -->
                            <div id="tab-batch" class="bh-cbg-panel active">

                                <div class="bh-cbg-field">
                                    <label for="bh_campaign">Campaign / Partner <span class="required">*</span></label>
                                    <input type="text" id="bh_campaign" name="bh_campaign"
                                        class="regular-text"
                                        placeholder="BRELLO PROMO - INFLUENCERS">
                                    <p class="description">Saved as the coupon description in WooCommerce.</p>
                                </div>

                                <div class="bh-cbg-field">
                                    <label for="bh_prefix">Code Prefix</label>
                                    <input type="text" id="bh_prefix" name="bh_prefix"
                                        class="regular-text"
                                        placeholder="<?php echo esc_attr( self::DEFAULT_PREFIX ); ?>"
                                        maxlength="20" autocomplete="off" style="text-transform:lowercase;">
                                    <p class="description">
                                        Leave empty to use <code><?php echo esc_html( strtolower( self::DEFAULT_PREFIX ) ); ?></code> as prefix.
                                        Example: <code>brellox7k4a2m9qp</code>
                                    </p>
                                </div>

                                <div class="bh-cbg-field">
                                    <label for="bh_count">Number of Codes <span class="required">*</span></label>
                                    <input type="number" id="bh_count" name="bh_count"
                                        value="30" min="1" max="500" class="small-text">
                                </div>

                            </div><!-- #tab-batch -->

                            <!-- ══ TAB: GENERAL ═════════════════════════ -->
                            <div id="tab-general" class="bh-cbg-panel">

                                <div class="bh-cbg-field">
                                    <label for="bh_discount_type">Discount Type</label>
                                    <select id="bh_discount_type" name="bh_discount_type" style="max-width:320px;">
                                        <optgroup label="Standard">
                                            <option value="percent">Percentage discount</option>
                                            <option value="fixed_cart">Fixed cart discount</option>
                                            <option value="fixed_product">Fixed product discount</option>
                                        </optgroup>
                                        <?php if ( $has_wcs ) : ?>
                                        <optgroup label="Subscriptions">
                                            <option value="sign_up_fee">Sign Up Fee Discount</option>
                                            <option value="sign_up_fee_percent">Sign Up Fee % Discount</option>
                                            <option value="recurring_fee">Recurring Product Discount</option>
                                            <option value="recurring_percent">Recurring Product % Discount</option>
                                        </optgroup>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="bh-cbg-field">
                                    <label for="bh_amount">Coupon Amount <span class="required">*</span></label>
                                    <div class="bh-cbg-input-row">
                                        <input type="number" id="bh_amount" name="bh_amount"
                                            value="" min="0" step="0.01" class="short"
                                            placeholder="0">
                                        <span class="bh-cbg-suffix" id="bh_amount_suffix">%</span>
                                    </div>
                                </div>

                                <?php if ( $has_wcs ) : ?>
                                <div class="bh-cbg-field" id="bh_number_payments_row" style="display:none;">
                                    <label for="bh_number_payments">Active for X Payments</label>
                                    <input type="number" id="bh_number_payments" name="bh_number_payments"
                                        value="" min="0" step="1" class="short"
                                        placeholder="Unlimited">
                                    <p class="description">
                                        Limits the coupon to this many payments (including the initial one).
                                        Leave empty for unlimited. Only applies to recurring types.
                                    </p>
                                </div>
                                <?php endif; ?>

                                <div class="bh-cbg-field bh-cbg-checkbox" id="bh_free_shipping_row">
                                    <label>
                                        <input type="checkbox" name="bh_free_shipping" value="yes">
                                        Allow free shipping
                                    </label>
                                    <p class="description">Check this box if the coupon grants free shipping. A free shipping method must be enabled.</p>
                                </div>

                                <div class="bh-cbg-field">
                                    <label for="bh_expiry">Coupon Expiry Date</label>
                                    <input type="date" id="bh_expiry" name="bh_expiry" style="max-width:200px;">
                                    <p class="description">Optional. All codes in the batch will share the same expiry date.</p>
                                </div>

                            </div><!-- #tab-general -->

                            <!-- ══ TAB: USAGE RESTRICTION ════════════════ -->
                            <div id="tab-restriction" class="bh-cbg-panel">

                                <div class="bh-cbg-row-2col">
                                    <div class="bh-cbg-field">
                                        <label for="bh_min_amount">Minimum Spend ($)</label>
                                        <input type="number" id="bh_min_amount" name="bh_min_amount"
                                            value="" min="0" step="0.01" class="short"
                                            placeholder="No minimum">
                                    </div>
                                    <div class="bh-cbg-field">
                                        <label for="bh_max_amount">Maximum Spend ($)</label>
                                        <input type="number" id="bh_max_amount" name="bh_max_amount"
                                            value="" min="0" step="0.01" class="short"
                                            placeholder="No maximum">
                                    </div>
                                </div>

                                <div class="bh-cbg-field bh-cbg-checkbox">
                                    <label>
                                        <input type="checkbox" name="bh_individual_use" value="yes" checked>
                                        Individual use only
                                    </label>
                                    <p class="description">Cannot be used in conjunction with other coupons.</p>
                                </div>

                                <div class="bh-cbg-field bh-cbg-checkbox">
                                    <label>
                                        <input type="checkbox" name="bh_exclude_sale_items" value="yes">
                                        Exclude sale items
                                    </label>
                                    <p class="description">Coupon does not apply to items on sale.</p>
                                </div>

                                <div class="bh-cbg-field">
                                    <label for="bh_products">Products</label>
                                    <select id="bh_products" name="bh_products[]"
                                        multiple="multiple" class="bh-cbg-select" style="width:100%;">
                                        <?php foreach ( $products as $p ) : ?>
                                            <option value="<?php echo esc_attr( $p->get_id() ); ?>">
                                                <?php echo esc_html( $p->get_name() ); ?> (#<?php echo (int) $p->get_id(); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Products the coupon can be used on. Leave empty for all products.</p>
                                </div>

                                <div class="bh-cbg-field">
                                    <label for="bh_exclude_products">Exclude Products</label>
                                    <select id="bh_exclude_products" name="bh_exclude_products[]"
                                        multiple="multiple" class="bh-cbg-select" style="width:100%;">
                                        <?php foreach ( $products as $p ) : ?>
                                            <option value="<?php echo esc_attr( $p->get_id() ); ?>">
                                                <?php echo esc_html( $p->get_name() ); ?> (#<?php echo (int) $p->get_id(); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Products the coupon cannot be used on.</p>
                                </div>

                                <div class="bh-cbg-field">
                                    <label for="bh_categories">Product Categories</label>
                                    <select id="bh_categories" name="bh_categories[]"
                                        multiple="multiple" class="bh-cbg-select" style="width:100%;">
                                        <?php foreach ( $cat_options as $cat ) : ?>
                                            <option value="<?php echo esc_attr( $cat->term_id ); ?>">
                                                <?php echo esc_html( $cat->name ); ?> (<?php echo (int) $cat->count; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        Categories the coupon applies to. Leave empty for all categories.
                                        <strong>Note:</strong> Products &amp; Categories use <strong>OR</strong> logic — valid if the item matches any selected product <em>or</em> any selected category.
                                    </p>
                                </div>

                                <div class="bh-cbg-field">
                                    <label for="bh_exclude_categories">Exclude Categories</label>
                                    <select id="bh_exclude_categories" name="bh_exclude_categories[]"
                                        multiple="multiple" class="bh-cbg-select" style="width:100%;">
                                        <?php foreach ( $cat_options as $cat ) : ?>
                                            <option value="<?php echo esc_attr( $cat->term_id ); ?>">
                                                <?php echo esc_html( $cat->name ); ?> (<?php echo (int) $cat->count; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Categories the coupon cannot be used on.</p>
                                </div>

                                <div class="bh-cbg-field">
                                    <label for="bh_allowed_emails">Allowed Emails</label>
                                    <input type="text" id="bh_allowed_emails" name="bh_allowed_emails"
                                        class="large-text"
                                        placeholder="e.g. user@example.com, *@company.com">
                                    <p class="description">Comma-separated. Wildcards (*) accepted. Leave empty to allow any email.</p>
                                </div>

                            </div><!-- #tab-restriction -->

                            <!-- ══ TAB: USAGE LIMITS ═════════════════════ -->
                            <div id="tab-limits" class="bh-cbg-panel">

                                <div class="bh-cbg-field">
                                    <label for="bh_usage_limit">Usage Limit per Coupon</label>
                                    <input type="number" id="bh_usage_limit" name="bh_usage_limit"
                                        value="1" min="0" step="1" class="short"
                                        placeholder="Unlimited">
                                    <p class="description">How many times this coupon code can be used. 0 = unlimited.</p>
                                </div>

                                <div class="bh-cbg-field" id="bh_limit_items_row">
                                    <label for="bh_limit_usage_to_x_items">Limit Usage to X Items</label>
                                    <input type="number" id="bh_limit_usage_to_x_items" name="bh_limit_usage_to_x_items"
                                        value="" min="0" step="1" class="short"
                                        placeholder="Apply to all qualifying items">
                                    <p class="description">The maximum number of individual items this coupon can apply to. Only for "Fixed product discount".</p>
                                </div>

                                <div class="bh-cbg-field">
                                    <label for="bh_usage_limit_per_user">Usage Limit per User</label>
                                    <input type="number" id="bh_usage_limit_per_user" name="bh_usage_limit_per_user"
                                        value="1" min="0" step="1" class="short"
                                        placeholder="Unlimited">
                                    <p class="description">How many times a single user can use each code. 0 = unlimited.</p>
                                </div>

                            </div><!-- #tab-limits -->

                            <div style="padding:0 1.5rem;">
                                <?php submit_button( 'Generate Coupons', 'primary large', 'submit', false ); ?>
                            </div>

                        </form>
                    </div>
                </div><!-- .bh-cbg-form-box -->

                <!-- ═══════════════════════════════════════════
                     RESULTS
                ═══════════════════════════════════════════════ -->
                <?php if ( $batch_id && ! empty( $batch_coupons ) ) : ?>
                <div class="postbox bh-cbg-results-box">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            Generated Codes
                            <span class="bh-cbg-badge"><?php echo count( $batch_coupons ); ?></span>
                        </h2>
                    </div>
                    <div class="inside">
                        <div class="bh-cbg-results-toolbar">
                            <div class="bh-cbg-results-meta">
                                <span class="dashicons dashicons-tickets-alt" style="color:#2271b1;"></span>
                                Batch: <code><?php echo esc_html( $batch_id ); ?></code>
                            </div>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'bh_export_coupon_batch_csv', '_bh_export_nonce' ); ?>
                                <input type="hidden" name="action"   value="bh_export_coupon_batch_csv">
                                <input type="hidden" name="batch_id" value="<?php echo esc_attr( $batch_id ); ?>">
                                <button type="submit" class="button button-secondary">
                                    <span class="dashicons dashicons-download" style="vertical-align:text-top;margin-right:4px;"></span>
                                    Export CSV
                                </button>
                            </form>
                        </div>

                        <div class="bh-cbg-table-wrap">
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th width="36">#</th>
                                        <th>Code</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Expires</th>
                                        <th>Free Ship</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $batch_coupons as $i => $c ) : ?>
                                    <tr>
                                        <td><?php echo $i + 1; ?></td>
                                        <td>
                                            <code><?php echo esc_html( $c['code'] ); ?></code>
                                        </td>
                                        <td style="white-space:nowrap;font-size:.8rem;">
                                            <?php echo esc_html( $c['type_label'] ); ?>
                                        </td>
                                        <td><?php echo esc_html( $c['discount'] ); ?></td>
                                        <td style="font-size:.8rem;">
                                            <?php echo $c['expiry'] ? esc_html( $c['expiry'] ) : '<span style="color:#999">—</span>'; ?>
                                        </td>
                                        <td>
                                            <?php if ( $c['free_shipping'] ) : ?>
                                                <span class="dashicons dashicons-yes" style="color:#46b450;"></span>
                                            <?php else : ?>
                                                <span style="color:#ccc;">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- .bh-cbg-results-box -->
                <?php endif; ?>

            </div><!-- .bh-cbg-layout -->
        </div><!-- .wrap -->

        <?php $this->render_styles(); ?>
        <?php $this->render_scripts(); ?>
        <?php
    }

    // =========================================================
    //  STYLES
    // =========================================================
    private function render_styles() { ?>
        <style>
        /* ── Page layout ───────────────────────────────────── */
        .bh-cbg-layout {
            display: flex;
            gap: 2rem;
            align-items: flex-start;
            max-width: 760px;
            margin-top: 1.25rem;
        }
        .bh-cbg-layout.bh-cbg-layout--has-results {
            max-width: 1400px;
        }
        .bh-cbg-form-box {
            width: 600px;
            flex-shrink: 0;
        }
        .bh-cbg-results-box {
            flex: 1;
            min-width: 360px;
        }
        .bh-cbg-layout .postbox { margin: 0; }
        .bh-cbg-layout .postbox .inside { padding: 1.5rem; }
        @media (max-width: 1100px) {
            .bh-cbg-layout { flex-direction: column; }
            .bh-cbg-form-box { width: 100%; }
        }

        /* ── Tabs ──────────────────────────────────────────── */
        .bh-cbg-tabs {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0 1.5rem;
            border-bottom: 1px solid #dcdcde;
            background: #f6f7f7;
            gap: 0;
        }
        .bh-cbg-tabs li {
            margin: 0;
            padding: 0;
        }
        .bh-cbg-tabs li a {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 10px 14px;
            color: #50575e;
            text-decoration: none;
            font-size: .82rem;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            margin-bottom: -1px;
            transition: color .15s, border-color .15s;
            white-space: nowrap;
        }
        .bh-cbg-tabs li a:hover { color: #135e96; }
        .bh-cbg-tabs li a .dashicons { font-size: 14px; width: 14px; height: 14px; }
        .bh-cbg-tabs li.active a {
            color: #135e96;
            font-weight: 600;
            border-bottom-color: #135e96;
            background: #fff;
        }

        /* ── Panels ────────────────────────────────────────── */
        .bh-cbg-panel { display: none; padding: 1.5rem; }
        .bh-cbg-panel.active { display: block; }

        /* ── Fields ────────────────────────────────────────── */
        .bh-cbg-field {
            margin-bottom: 1.1rem;
        }
        .bh-cbg-field > label {
            display: block;
            font-weight: 600;
            font-size: .83rem;
            color: #1d2327;
            margin-bottom: 5px;
        }
        .bh-cbg-field .description {
            color: #646970;
            font-size: .78rem;
            margin: 4px 0 0;
            line-height: 1.5;
        }
        .bh-cbg-field .required { color: #d63638; }
        .bh-cbg-field.bh-cbg-checkbox > label {
            display: flex;
            align-items: flex-start;
            gap: 7px;
            font-weight: 400;
            cursor: pointer;
        }
        .bh-cbg-field.bh-cbg-checkbox input[type=checkbox] {
            margin-top: 2px;
            flex-shrink: 0;
        }
        .bh-cbg-row-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* ── Amount with suffix ────────────────────────────── */
        .bh-cbg-input-row {
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .bh-cbg-suffix {
            font-weight: 700;
            color: #50575e;
            font-size: .9rem;
            min-width: 14px;
        }

        /* ── Select2 overrides ─────────────────────────────── */
        .bh-cbg-select + .select2-container { width: 100% !important; }

        /* ── Results toolbar ───────────────────────────────── */
        .bh-cbg-results-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .bh-cbg-results-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .83rem;
            color: #50575e;
        }
        .bh-cbg-results-meta code {
            font-size: .78rem;
            background: #f0f0f1;
            padding: 1px 6px;
            border-radius: 3px;
        }
        .bh-cbg-badge {
            display: inline-block;
            background: #2271b1;
            color: #fff;
            font-size: .72rem;
            font-weight: 700;
            border-radius: 10px;
            padding: 1px 8px;
            margin-left: 6px;
            vertical-align: middle;
        }

        /* ── Results table ─────────────────────────────────── */
        .bh-cbg-table-wrap {
            max-height: 580px;
            overflow-y: auto;
            border: 1px solid #dcdcde;
            border-radius: 3px;
        }
        .bh-cbg-table-wrap table { border-collapse: collapse; margin: 0; }
        .bh-cbg-table-wrap thead th {
            position: sticky;
            top: 0;
            background: #f6f7f7;
            z-index: 1;
            font-size: .8rem;
            padding: 7px 10px;
        }
        .bh-cbg-table-wrap tbody td { padding: 6px 10px; font-size: .82rem; }
        /* ── Validation ───────────────────────────────────── */
        .bh-cbg-error-box {
            margin: 0 1.5rem 0;
            padding: .75rem 1rem;
            background: #fef7f7;
            border-left: 4px solid #d63638;
            font-size: .83rem;
            line-height: 1.6;
        }
        .bh-cbg-error-box ul { margin: .4rem 0 0 1.2rem; padding: 0; }
        .bh-cbg-error-box li { margin: 0; }
        .bh-cbg-field-error > label { color: #d63638; }
        .bh-cbg-field-error input,
        .bh-cbg-field-error select { border-color: #d63638 !important; }
        .bh-cbg-tabs li.bh-cbg-tab-error a { color: #d63638; }
        .bh-cbg-tabs li.bh-cbg-tab-error a::after {
            content: '●';
            font-size: 7px;
            color: #d63638;
            margin-left: 4px;
            vertical-align: middle;
        }

        .bh-cbg-table-wrap code {
            font-size: .82rem;
            letter-spacing: .03em;
            background: #f0f0f1;
            padding: 2px 6px;
            border-radius: 3px;
        }
        </style>
    <?php }

    // =========================================================
    //  SCRIPTS
    // =========================================================
    private function render_scripts() { ?>
        <script>
        jQuery(document).ready(function ($) {

            // ── Select2 ────────────────────────────────────────
            $('.bh-cbg-select').select2({
                placeholder: 'Search and select…',
                allowClear: true,
                width: '100%'
            });

            // ── Tab switching ───────────────────────────────────
            function switchTab(tab) {
                $('.bh-cbg-tabs li').removeClass('active');
                $('.bh-cbg-tabs li[data-tab="' + tab + '"]').addClass('active');
                $('.bh-cbg-panel').removeClass('active');
                $('#tab-' + tab).addClass('active');
            }

            $('.bh-cbg-tabs li').on('click', function (e) {
                e.preventDefault();
                switchTab($(this).data('tab'));
            });

            // ── Prefix lowercase ────────────────────────────────
            $('#bh_prefix').on('input', function () {
                this.value = this.value.toLowerCase();
            });

            // ── Discount type reactive UI ───────────────────────
            function onDiscountTypeChange() {
                var type    = $('#bh_discount_type').val();
                var isFixed = (type === 'fixed_product');
                var isRecur = (type === 'recurring_fee' || type === 'recurring_percent');
                var isSubs  = (type === 'sign_up_fee' || type === 'sign_up_fee_percent' || isRecur);
                var suffix  = (type === 'percent' || type === 'sign_up_fee_percent' || type === 'recurring_percent') ? '%' : '$';

                $('#bh_amount_suffix').text(suffix);
                $('#bh_limit_items_row').toggle(isFixed);
                $('#bh_free_shipping_row').toggle(!isSubs);
                $('#bh_number_payments_row').toggle(isRecur);
            }

            $('#bh_discount_type').on('change', onDiscountTypeChange);
            onDiscountTypeChange();

            // ── Validation ──────────────────────────────────────
            var rules = [
                { field: '#bh_campaign', tab: 'batch',   message: 'Campaign / Partner is required.' },
                { field: '#bh_count',    tab: 'batch',   message: 'Number of codes must be between 1 and 500.',
                  test: function () {
                      var v = parseInt($('#bh_count').val(), 10);
                      return !isNaN(v) && v >= 1 && v <= 500;
                  }
                },
                { field: '#bh_amount',   tab: 'general', message: 'Coupon amount is required (or enable Free Shipping).',
                  test: function () {
                      var amount = parseFloat($('#bh_amount').val());
                      var freeShip = $('input[name="bh_free_shipping"]').is(':checked');
                      return freeShip || (!isNaN(amount) && amount > 0);
                  }
                },
            ];

            function clearErrors() {
                $('#bh-cbg-errors').hide().empty();
                $('.bh-cbg-field-error').removeClass('bh-cbg-field-error');
                $('.bh-cbg-tabs li').removeClass('bh-cbg-tab-error');
            }

            function showErrors(errors) {
                var $box = $('#bh-cbg-errors');
                var html = '<strong>Please fix the following before generating:</strong><ul>';
                $.each(errors, function (i, e) {
                    html += '<li>' + e.message + '</li>';
                });
                html += '</ul>';
                $box.html(html).show();

                // Switch to the tab of the first error
                switchTab(errors[0].tab);

                // Mark fields
                $.each(errors, function (i, e) {
                    $(e.field).closest('.bh-cbg-field').addClass('bh-cbg-field-error');
                    $('.bh-cbg-tabs li[data-tab="' + e.tab + '"]').addClass('bh-cbg-tab-error');
                });

                // Scroll to error box
                $('html, body').animate({ scrollTop: $('#bh-cbg-errors').offset().top - 40 }, 200);
            }

            $('#bh-cbg-form').on('submit', function (e) {
                clearErrors();
                var errors = [];

                $.each(rules, function (i, rule) {
                    var passes = rule.test
                        ? rule.test()
                        : $.trim($(rule.field).val()) !== '';

                    if (!passes) {
                        errors.push(rule);
                    }
                });

                if (errors.length) {
                    e.preventDefault();
                    showErrors(errors);
                }
            });

            // Clear field error on input
            $('#bh-cbg-form').on('input change', 'input, select', function () {
                $(this).closest('.bh-cbg-field').removeClass('bh-cbg-field-error');
            });

        });
        </script>
    <?php }

    // =========================================================
    //  HANDLE GENERATE
    // =========================================================
    public function handle_generate() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'bh_generate_coupon_batch', '_bh_nonce' );

        $base = admin_url( 'admin.php?page=' . PARENT_MENU_SLUG . '--coupon-batch' );

        // Batch
        $campaign = sanitize_text_field( $_POST['bh_campaign'] ?? '' );
        $prefix   = strtolower( sanitize_text_field( $_POST['bh_prefix'] ?? '' ) );
        $prefix   = $prefix !== '' ? $prefix : strtolower( self::DEFAULT_PREFIX );
        $count    = max( 1, min( 500, (int) ( $_POST['bh_count'] ?? 30 ) ) );

        // General
        $valid_types   = [ 'percent', 'fixed_cart', 'fixed_product', 'sign_up_fee', 'sign_up_fee_percent', 'recurring_fee', 'recurring_percent' ];
        $discount_type = in_array( $_POST['bh_discount_type'] ?? '', $valid_types, true ) ? $_POST['bh_discount_type'] : 'percent';
        $amount        = (float) ( $_POST['bh_amount'] ?? 0 );
        $free_shipping = ( ( $_POST['bh_free_shipping'] ?? '' ) === 'yes' );
        $expiry        = sanitize_text_field( $_POST['bh_expiry'] ?? '' );
        $num_payments  = (int) ( $_POST['bh_number_payments'] ?? 0 );

        // Usage restriction
        $min_amount         = sanitize_text_field( $_POST['bh_min_amount'] ?? '' );
        $max_amount         = sanitize_text_field( $_POST['bh_max_amount'] ?? '' );
        $individual_use     = ( ( $_POST['bh_individual_use']     ?? '' ) === 'yes' );
        $excl_sale          = ( ( $_POST['bh_exclude_sale_items'] ?? '' ) === 'yes' );
        $product_ids        = array_filter( array_map( 'intval', (array) ( $_POST['bh_products']           ?? [] ) ) );
        $excl_product_ids   = array_filter( array_map( 'intval', (array) ( $_POST['bh_exclude_products']   ?? [] ) ) );
        $category_ids       = array_filter( array_map( 'intval', (array) ( $_POST['bh_categories']         ?? [] ) ) );
        $excl_category_ids  = array_filter( array_map( 'intval', (array) ( $_POST['bh_exclude_categories'] ?? [] ) ) );
        $allowed_emails_str = sanitize_text_field( $_POST['bh_allowed_emails'] ?? '' );
        $allowed_emails     = array_filter( array_map( 'trim', explode( ',', $allowed_emails_str ) ) );

        // Usage limits
        $usage_limit          = (int) ( $_POST['bh_usage_limit']            ?? 1 );
        $limit_to_x_items     = (int) ( $_POST['bh_limit_usage_to_x_items'] ?? 0 );
        $usage_limit_per_user = (int) ( $_POST['bh_usage_limit_per_user']   ?? 1 );

        // Validation
        if ( ! $campaign ) {
            wp_redirect( add_query_arg( 'bh_error', rawurlencode( 'Campaign name is required.' ), $base ) ); exit;
        }
        if ( $amount <= 0 && ! $free_shipping ) {
            wp_redirect( add_query_arg( 'bh_error', rawurlencode( 'Enter a discount amount or enable free shipping.' ), $base ) ); exit;
        }

        $batch_id = 'BH-' . strtoupper( wp_generate_password( 8, false, false ) ) . '-' . date( 'Ymd' );
        $created  = 0;
        $token    = $this->new_token();

        for ( $i = 0; $i < $count; $i++ ) {
            // Rotate token every TOKEN_CHUNK codes so the pattern is not guessable
            if ( $i > 0 && $i % self::TOKEN_CHUNK === 0 ) {
                $token = $this->new_token();
            }

            $code   = $this->generate_unique_code( $prefix, $token );
            $coupon = new WC_Coupon();

            $coupon->set_code( $code );
            $coupon->set_description( $campaign );

            // General
            $coupon->set_discount_type( $discount_type );
            $coupon->set_amount( $amount );
            $coupon->set_free_shipping( $free_shipping );
            if ( $expiry ) $coupon->set_date_expires( strtotime( $expiry ) );

            // Usage restriction
            if ( $min_amount !== '' ) $coupon->set_minimum_amount( $min_amount );
            if ( $max_amount !== '' ) $coupon->set_maximum_amount( $max_amount );
            $coupon->set_individual_use( $individual_use );
            $coupon->set_exclude_sale_items( $excl_sale );
            if ( ! empty( $product_ids ) )      $coupon->set_product_ids( array_values( $product_ids ) );
            if ( ! empty( $excl_product_ids ) ) $coupon->set_excluded_product_ids( array_values( $excl_product_ids ) );
            if ( ! empty( $category_ids ) )     $coupon->set_product_categories( array_values( $category_ids ) );
            if ( ! empty( $excl_category_ids ) ) $coupon->set_excluded_product_categories( array_values( $excl_category_ids ) );
            if ( ! empty( $allowed_emails ) )   $coupon->set_email_restrictions( $allowed_emails );

            // Usage limits
            $coupon->set_usage_limit( $usage_limit > 0 ? $usage_limit : '' );
            $coupon->set_usage_limit_per_user( $usage_limit_per_user > 0 ? $usage_limit_per_user : '' );
            if ( $limit_to_x_items > 0 ) $coupon->set_limit_usage_to_x_items( $limit_to_x_items );

            // Batch meta
            $coupon->add_meta_data( '_bh_batch_id', $batch_id, true );
            $coupon->add_meta_data( '_bh_campaign', $campaign,  true );

            // WCS: number of payments (only meaningful on recurring types)
            if ( $num_payments > 0 && in_array( $discount_type, [ 'recurring_fee', 'recurring_percent' ], true ) ) {
                $coupon->add_meta_data( '_wcs_number_payments', $num_payments, true );
            }

            $coupon->save();
            $created++;
        }

        wp_redirect( add_query_arg( [ 'batch_id' => rawurlencode( $batch_id ), 'bh_success' => $created ], $base ) );
        exit;
    }

    // =========================================================
    //  HANDLE CSV EXPORT
    // =========================================================
    public function handle_export_csv() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );
        check_admin_referer( 'bh_export_coupon_batch_csv', '_bh_export_nonce' );

        $batch_id = sanitize_text_field( $_POST['batch_id'] ?? '' );
        if ( ! $batch_id ) wp_die( 'Missing batch ID.' );

        $coupons = $this->get_batch_coupons( $batch_id );
        if ( empty( $coupons ) ) wp_die( 'No coupons found for batch: ' . esc_html( $batch_id ) );

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="coupons-' . sanitize_file_name( $batch_id ) . '.csv"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ '#', 'Code', 'Campaign', 'Type', 'Amount', 'Free Shipping', 'Expires', 'Batch ID' ] );
        foreach ( $coupons as $i => $c ) {
            fputcsv( $out, [ $i + 1, $c['code'], $c['campaign'], $c['type_label'], $c['discount'], $c['free_shipping'] ? 'Yes' : 'No', $c['expiry'], $batch_id ] );
        }
        fclose( $out );
        exit;
    }

    // =========================================================
    //  HELPERS
    // =========================================================
    private function get_batch_coupons( string $batch_id ): array {
        $posts = get_posts( [
            'post_type'      => 'shop_coupon',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => [ [ 'key' => '_bh_batch_id', 'value' => $batch_id, 'compare' => '=' ] ],
        ] );

        $type_labels = [
            'percent'             => 'Percentage',
            'fixed_cart'          => 'Fixed cart',
            'fixed_product'       => 'Fixed product',
            'sign_up_fee'         => 'Sign-up fee',
            'sign_up_fee_percent' => 'Sign-up fee %',
            'recurring_fee'       => 'Recurring',
            'recurring_percent'   => 'Recurring %',
        ];

        $result = [];
        foreach ( $posts as $post ) {
            $coupon  = new WC_Coupon( $post->ID );
            $type    = $coupon->get_discount_type();
            $amount  = (float) $coupon->get_amount();
            $expires = $coupon->get_date_expires();

            $result[] = [
                'code'         => $coupon->get_code(),
                'campaign'     => (string) get_post_meta( $post->ID, '_bh_campaign', true ),
                'type_label'   => $type_labels[ $type ] ?? $type,
                'discount'     => in_array( $type, [ 'percent', 'sign_up_fee_percent', 'recurring_percent' ], true )
                                    ? $amount . '%'
                                    : '$' . number_format( $amount, 2 ),
                'free_shipping'=> (bool) $coupon->get_free_shipping(),
                'expiry'       => $expires ? $expires->date( 'Y-m-d' ) : '',
            ];
        }

        return $result;
    }

    /**
     * Generate a random 6-char alphanumeric token.
     * Used as the middle segment of the code — rotated every TOKEN_CHUNK codes
     * so the pattern is not guessable across a large batch.
     */
    private function new_token(): string {
        return strtolower( wp_generate_password( 6, false, false ) );
    }

    /**
     * Generate a unique coupon code.
     *
     * Format: {prefix}{6-char-token}{4-char-random}
     * Example: brellox7k4a2m9qp
     *
     * The token is shared across TOKEN_CHUNK codes then rotated,
     * breaking any visible pattern within a large batch.
     * The 4-char random suffix handles intra-chunk uniqueness.
     * The do-while loop is the final safety net for any collision.
     */
    private function generate_unique_code( string $prefix, string $token ): string {
        do {
            $rand = strtolower( wp_generate_password( 4, false, false ) );
            $code = strtolower( $prefix ) . $token . $rand;
        } while ( wc_get_coupon_id_by_code( $code ) );
        return $code;
    }
}

add_action( 'woocommerce_loaded', function () {
    new BH_Coupon_Batch_Generator();
} );

}
