<?php
/**
 * Standalone UPay Settings Page
 *
 * Creates a dedicated settings page in WordPress admin
 * Bypasses WTE's tab system entirely
 *
 * @package WTE_UPay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WTE_UPay_Standalone_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ), 99 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_menu_page(
            __( 'UPay Settings', 'wte-upay' ),
            __( 'UPay Settings', 'wte-upay' ),
            'manage_options',
            'wte-upay-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-money-alt',
            59 // Position after WP Travel Engine
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wte_upay_settings_group',
            'wp_travel_engine_settings',
            array( $this, 'sanitize_settings' )
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        // Get existing settings
        $settings = get_option( 'wp_travel_engine_settings', array() );

        // Update only UPay-related fields
        if ( isset( $input['upay_enable'] ) ) {
            $settings['upay_enable'] = '1';
        } else {
            $settings['upay_enable'] = '';
        }

        // Update nested upay_settings
        if ( ! isset( $settings['upay_settings'] ) ) {
            $settings['upay_settings'] = array();
        }

        $settings['upay_settings']['client_id'] = isset( $input['upay_settings']['client_id'] )
            ? sanitize_text_field( $input['upay_settings']['client_id'] )
            : '';

        $settings['upay_settings']['client_secret'] = isset( $input['upay_settings']['client_secret'] )
            ? sanitize_text_field( $input['upay_settings']['client_secret'] )
            : '';

        $settings['upay_settings']['biller_uuid'] = isset( $input['upay_settings']['biller_uuid'] )
            ? sanitize_text_field( $input['upay_settings']['biller_uuid'] )
            : '';

        $settings['upay_settings']['biller_ref'] = isset( $input['upay_settings']['biller_ref'] )
            ? sanitize_text_field( $input['upay_settings']['biller_ref'] )
            : '';

        $settings['upay_settings']['partner_id'] = isset( $input['upay_settings']['partner_id'] )
            ? sanitize_text_field( $input['upay_settings']['partner_id'] )
            : '';

        $settings['upay_settings']['partner_username'] = isset( $input['upay_settings']['partner_username'] )
            ? sanitize_text_field( $input['upay_settings']['partner_username'] )
            : '';

        $settings['upay_settings']['partner_password'] = isset( $input['upay_settings']['partner_password'] )
            ? sanitize_text_field( $input['upay_settings']['partner_password'] )
            : '';

        // Set success message
        add_settings_error(
            'wte_upay_messages',
            'wte_upay_message',
            __( 'UPay settings saved successfully!', 'wte-upay' ),
            'success'
        );

        return $settings;
    }

    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        settings_errors( 'wte_upay_messages' );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'wte-upay' ) );
        }

        // Get current settings
        $settings = get_option( 'wp_travel_engine_settings', array() );
        $upay_enabled = isset( $settings['upay_enable'] ) && $settings['upay_enable'] == '1';
        $client_id = isset( $settings['upay_settings']['client_id'] ) ? $settings['upay_settings']['client_id'] : '';
        $client_secret = isset( $settings['upay_settings']['client_secret'] ) ? $settings['upay_settings']['client_secret'] : '';
        $biller_uuid = isset( $settings['upay_settings']['biller_uuid'] ) ? $settings['upay_settings']['biller_uuid'] : '';
        $biller_ref = isset( $settings['upay_settings']['biller_ref'] ) ? $settings['upay_settings']['biller_ref'] : '';
        $partner_id = isset( $settings['upay_settings']['partner_id'] ) ? $settings['upay_settings']['partner_id'] : '';
        $partner_username = isset( $settings['upay_settings']['partner_username'] ) ? $settings['upay_settings']['partner_username'] : '';
        $partner_password = isset( $settings['upay_settings']['partner_password'] ) ? $settings['upay_settings']['partner_password'] : '';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <div class="notice notice-info" style="margin-top: 20px;">
                <p>
                    <strong><?php esc_html_e( 'Note:', 'wte-upay' ); ?></strong>
                    <?php esc_html_e( 'This is a standalone settings page for UPay Gateway. Configure your Union Bank UPay credentials here.', 'wte-upay' ); ?>
                </p>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'wte_upay_settings_group' );
                ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <!-- Enable UPay -->
                        <tr>
                            <th scope="row">
                                <label for="upay_enable">
                                    <?php esc_html_e( 'Enable UPay Payment', 'wte-upay' ); ?>
                                </label>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox"
                                               name="wp_travel_engine_settings[upay_enable]"
                                               id="upay_enable"
                                               value="1"
                                               <?php checked( $upay_enabled, true ); ?> />
                                        <?php esc_html_e( 'Enable Union Bank UPay payment gateway for trip bookings', 'wte-upay' ); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <!-- Client ID -->
                        <tr>
                            <th scope="row">
                                <label for="client_id">
                                    <?php esc_html_e( 'Client ID', 'wte-upay' ); ?>
                                    <span style="color: red;">*</span>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       name="wp_travel_engine_settings[upay_settings][client_id]"
                                       id="client_id"
                                       value="<?php echo esc_attr( $client_id ); ?>"
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Enter your Union Bank UPay Client ID (X-IBM-Client-Id) from Developer Portal', 'wte-upay' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Client Secret -->
                        <tr>
                            <th scope="row">
                                <label for="client_secret">
                                    <?php esc_html_e( 'Client Secret', 'wte-upay' ); ?>
                                    <span style="color: red;">*</span>
                                </label>
                            </th>
                            <td>
                                <input type="password"
                                       name="wp_travel_engine_settings[upay_settings][client_secret]"
                                       id="client_secret"
                                       value="<?php echo esc_attr( $client_secret ); ?>"
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e( 'Enter your Union Bank UPay Client Secret (X-IBM-Client-Secret) from Developer Portal', 'wte-upay' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Biller UUID -->
                        <tr>
                            <th scope="row">
                                <label for="biller_uuid">
                                    <?php esc_html_e( 'Biller UUID', 'wte-upay' ); ?>
                                    <span style="color: red;">*</span>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       name="wp_travel_engine_settings[upay_settings][biller_uuid]"
                                       id="biller_uuid"
                                       value="<?php echo esc_attr( $biller_uuid ); ?>"
                                       class="regular-text"
                                       placeholder="02C16F6A-8329-D696-FEA1-7E51304B8A2E" />
                                <p class="description">
                                    <?php esc_html_e( 'Enter your Biller UUID provided by Union Bank (36-character unique identifier)', 'wte-upay' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Biller Reference -->
                        <tr>
                            <th scope="row">
                                <label for="biller_ref">
                                    <?php esc_html_e( 'Biller Reference', 'wte-upay' ); ?>
                                    <span style="color: red;">*</span>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       name="wp_travel_engine_settings[upay_settings][biller_ref]"
                                       id="biller_ref"
                                       value="<?php echo esc_attr( $biller_ref ); ?>"
                                       class="regular-text"
                                       placeholder="a18677c4-6848-4a4d-96f6-141746083ccb" />
                                <p class="description">
                                    <?php esc_html_e( 'Enter your Biller Reference provided by Union Bank (used for transaction status checks)', 'wte-upay' ); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <hr>

                <h2><?php esc_html_e( 'Partner Authentication (OAuth2)', 'wte-upay' ); ?></h2>
                <p class="description" style="margin-top: 0;">
                    <?php esc_html_e( 'Partner Authentication credentials for OAuth2 token generation. These are provided by Union Bank when you register as a partner.', 'wte-upay' ); ?>
                </p>

                <table class="form-table" role="presentation">
                    <tbody>
                        <!-- Partner ID -->
                        <tr>
                            <th scope="row">
                                <label for="partner_id">
                                    <?php esc_html_e( 'Partner ID (x-partner-id)', 'wte-upay' ); ?>
                                    <span style="color: red;">*</span>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       name="wp_travel_engine_settings[upay_settings][partner_id]"
                                       id="partner_id"
                                       value="<?php echo esc_attr( $partner_id ); ?>"
                                       class="regular-text"
                                       placeholder="5dff2cdf-ef15-48fb-a87b-375ebff415bb" />
                                <p class="description">
                                    <?php esc_html_e( 'Partner ID linked to your Union Bank corporate account. For Sandbox: 5dff2cdf-ef15-48fb-a87b-375ebff415bb', 'wte-upay' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Partner Username -->
                        <tr>
                            <th scope="row">
                                <label for="partner_username">
                                    <?php esc_html_e( 'Partner Username', 'wte-upay' ); ?>
                                    <span style="color: red;">*</span>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       name="wp_travel_engine_settings[upay_settings][partner_username]"
                                       id="partner_username"
                                       value="<?php echo esc_attr( $partner_username ); ?>"
                                       class="regular-text"
                                       placeholder="partner_sb" />
                                <p class="description">
                                    <?php esc_html_e( 'Partner username for OAuth2 authentication. For Sandbox: partner_sb', 'wte-upay' ); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Partner Password -->
                        <tr>
                            <th scope="row">
                                <label for="partner_password">
                                    <?php esc_html_e( 'Partner Password', 'wte-upay' ); ?>
                                    <span style="color: red;">*</span>
                                </label>
                            </th>
                            <td>
                                <input type="password"
                                       name="wp_travel_engine_settings[upay_settings][partner_password]"
                                       id="partner_password"
                                       value="<?php echo esc_attr( $partner_password ); ?>"
                                       class="regular-text"
                                       placeholder="p@ssw0rd" />
                                <p class="description">
                                    <?php esc_html_e( 'Partner password for OAuth2 authentication. For Sandbox: p@ssw0rd', 'wte-upay' ); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <hr>

                <h2><?php esc_html_e( 'Test Mode Configuration', 'wte-upay' ); ?></h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e( 'Current Mode', 'wte-upay' ); ?>
                            </th>
                            <td>
                                <?php if ( defined( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG' ) && WP_TRAVEL_ENGINE_PAYMENT_DEBUG ): ?>
                                    <span style="color: #856404; background: #fff3cd; padding: 5px 10px; border-radius: 3px;">
                                        🧪 <?php esc_html_e( 'TEST MODE (UAT)', 'wte-upay' ); ?>
                                    </span>
                                    <p class="description">
                                        <?php esc_html_e( 'Using Union Bank UAT environment for testing.', 'wte-upay' ); ?>
                                        <br>
                                        <?php esc_html_e( 'API URL:', 'wte-upay' ); ?>
                                        <code>https://apiuat.unionbankph.com/ubp/external/upay/payments/v1</code>
                                    </p>
                                <?php else: ?>
                                    <span style="color: #155724; background: #d4edda; padding: 5px 10px; border-radius: 3px;">
                                        ✅ <?php esc_html_e( 'PRODUCTION MODE', 'wte-upay' ); ?>
                                    </span>
                                    <p class="description">
                                        <?php esc_html_e( 'Using Union Bank production environment.', 'wte-upay' ); ?>
                                        <br>
                                        <?php esc_html_e( 'API URL:', 'wte-upay' ); ?>
                                        <code>https://api.unionbankph.com/ubp/external/upay/payments/v1</code>
                                    </p>
                                <?php endif; ?>
                                <p class="description" style="margin-top: 10px;">
                                    <strong><?php esc_html_e( 'To enable test mode:', 'wte-upay' ); ?></strong>
                                    <?php esc_html_e( 'Add this to your wp-config.php:', 'wte-upay' ); ?>
                                    <br>
                                    <code>define( 'WP_TRAVEL_ENGINE_PAYMENT_DEBUG', true );</code>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button( __( 'Save UPay Settings', 'wte-upay' ) ); ?>
            </form>

            <hr>

            <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin-top: 20px;">
                <h3><?php esc_html_e( '📋 Quick Links', 'wte-upay' ); ?></h3>
                <ul>
                    <li>
                        <strong><?php esc_html_e( 'Union Bank Developer Portal (UAT):', 'wte-upay' ); ?></strong>
                        <a href="https://developer-uat.unionbankph.com" target="_blank">https://developer-uat.unionbankph.com</a>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Union Bank Developer Portal (Production):', 'wte-upay' ); ?></strong>
                        <a href="https://developer.unionbankph.com" target="_blank">https://developer.unionbankph.com</a>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'WP Travel Engine Settings:', 'wte-upay' ); ?></strong>
                        <a href="<?php echo admin_url( 'edit.php?post_type=booking&page=class-wp-travel-engine-admin.php' ); ?>">
                            <?php esc_html_e( 'Go to WTE Settings', 'wte-upay' ); ?>
                        </a>
                    </li>
                </ul>
            </div>

            <div style="background: #fff8e5; border-left: 4px solid #ffb900; padding: 15px; margin-top: 20px;">
                <h3><?php esc_html_e( '🔐 Security Note', 'wte-upay' ); ?></h3>
                <p><?php esc_html_e( 'Never share your Client Secret or credentials publicly. Keep them secure.', 'wte-upay' ); ?></p>
            </div>
        </div>

        <style>
            .form-table th {
                padding: 20px 10px 20px 0;
                width: 200px;
            }
            .form-table td {
                padding: 15px 10px;
            }
            .form-table .description {
                font-style: italic;
                color: #666;
            }
        </style>
        <?php
    }
}

// Initialize the standalone settings page
new WTE_UPay_Standalone_Settings();
