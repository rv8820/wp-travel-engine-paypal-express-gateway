<?php
/**
 * UPay Debug Script
 *
 * Upload this file to your wp-content/plugins/wte-upay/ folder
 * Then access it via: wp-admin/admin.php?page=upay-debug
 *
 * This will show diagnostic information about why the settings tab isn't appearing.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'upay_add_debug_page' );

function upay_add_debug_page() {
    add_submenu_page(
        null, // No parent menu (hidden)
        'UPay Debug',
        'UPay Debug',
        'manage_options',
        'upay-debug',
        'upay_render_debug_page'
    );
}

function upay_render_debug_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    ?>
    <div class="wrap">
        <h1>🔍 UPay Gateway Debug Information</h1>
        <p>This page shows diagnostic information to help troubleshoot why the UPay settings tab isn't showing.</p>

        <hr>

        <h2>1. Plugin Status</h2>
        <table class="widefat">
            <tr>
                <td><strong>UPay Plugin File Exists:</strong></td>
                <td><?php echo file_exists( WP_PLUGIN_DIR . '/wte-upay/wte-upay.php' ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>UPay Plugin Active:</strong></td>
                <td><?php echo is_plugin_active( 'wte-upay/wte-upay.php' ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>WTE Plugin Active:</strong></td>
                <td><?php echo class_exists( 'WP_Travel_Engine' ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>WTE Version:</strong></td>
                <td><?php echo defined( 'WP_TRAVEL_ENGINE_VERSION' ) ? WP_TRAVEL_ENGINE_VERSION : 'Not defined'; ?></td>
            </tr>
        </table>

        <hr>

        <h2>2. Class Status</h2>
        <table class="widefat">
            <tr>
                <td><strong>WTE_UPay_Checkout exists:</strong></td>
                <td><?php echo class_exists( 'WTE_UPay_Checkout' ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>Wte_UPay_Admin exists:</strong></td>
                <td><?php echo class_exists( 'Wte_UPay_Admin' ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>WTE_UPay_API exists:</strong></td>
                <td><?php echo class_exists( 'WTE_UPay_API' ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>WTE_Payment_Gateway_UPay exists:</strong></td>
                <td><?php echo class_exists( 'WTE_Payment_Gateway_UPay' ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>WTE_UPay exists (6.0+):</strong></td>
                <td><?php echo class_exists( 'WTE_UPay' ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
        </table>

        <hr>

        <h2>3. File Paths</h2>
        <table class="widefat">
            <tr>
                <td><strong>Plugin Directory:</strong></td>
                <td><?php echo defined( 'WP_TRAVEL_ENGINE_UPAY_BASE_PATH' ) ? WP_TRAVEL_ENGINE_UPAY_BASE_PATH : 'Not defined'; ?></td>
            </tr>
            <tr>
                <td><strong>Global Settings File:</strong></td>
                <td>
                    <?php
                    $settings_file = defined( 'WP_TRAVEL_ENGINE_UPAY_BASE_PATH' )
                        ? WP_TRAVEL_ENGINE_UPAY_BASE_PATH . '/includes/backend/global-settings.php'
                        : 'Path not defined';
                    echo esc_html( $settings_file );
                    ?>
                </td>
            </tr>
            <tr>
                <td><strong>Settings File Exists:</strong></td>
                <td>
                    <?php
                    if ( defined( 'WP_TRAVEL_ENGINE_UPAY_BASE_PATH' ) ) {
                        echo file_exists( WP_TRAVEL_ENGINE_UPAY_BASE_PATH . '/includes/backend/global-settings.php' ) ? '✅ Yes' : '❌ No';
                    } else {
                        echo '❓ Unknown';
                    }
                    ?>
                </td>
            </tr>
        </table>

        <hr>

        <h2>4. WTE Global Tabs Filter</h2>
        <?php
        // Test the filter
        $test_tabs = array(
            'wpte-payment' => array(
                'sub_tabs' => array()
            )
        );

        $filtered_tabs = apply_filters( 'wpte_settings_get_global_tabs', $test_tabs );
        ?>
        <table class="widefat">
            <tr>
                <td><strong>Filter 'wpte_settings_get_global_tabs' exists:</strong></td>
                <td><?php echo has_filter( 'wpte_settings_get_global_tabs' ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>Number of callbacks:</strong></td>
                <td><?php echo has_filter( 'wpte_settings_get_global_tabs' ) ?: '0'; ?></td>
            </tr>
            <tr>
                <td><strong>UPay tab registered:</strong></td>
                <td><?php echo isset( $filtered_tabs['wpte-payment']['sub_tabs']['wte-upay'] ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
        </table>

        <?php if ( isset( $filtered_tabs['wpte-payment']['sub_tabs']['wte-upay'] ) ): ?>
        <h3>UPay Tab Configuration:</h3>
        <pre style="background: #f5f5f5; padding: 15px; overflow: auto;"><?php print_r( $filtered_tabs['wpte-payment']['sub_tabs']['wte-upay'] ); ?></pre>
        <?php endif; ?>

        <hr>

        <h2>5. All Payment Sub-Tabs</h2>
        <?php if ( isset( $filtered_tabs['wpte-payment']['sub_tabs'] ) ): ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Tab ID</th>
                    <th>Label</th>
                    <th>Content Path</th>
                    <th>File Exists</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $filtered_tabs['wpte-payment']['sub_tabs'] as $tab_id => $tab_config ): ?>
                <tr>
                    <td><code><?php echo esc_html( $tab_id ); ?></code></td>
                    <td><?php echo esc_html( $tab_config['label'] ?? 'N/A' ); ?></td>
                    <td><small><?php echo esc_html( $tab_config['content_path'] ?? 'N/A' ); ?></small></td>
                    <td>
                        <?php
                        if ( isset( $tab_config['content_path'] ) ) {
                            echo file_exists( $tab_config['content_path'] ) ? '✅' : '❌';
                        } else {
                            echo '❓';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>❌ No payment sub-tabs found.</p>
        <?php endif; ?>

        <hr>

        <h2>6. Settings Data</h2>
        <?php
        $settings = get_option( 'wp_travel_engine_settings', array() );
        ?>
        <table class="widefat">
            <tr>
                <td><strong>UPay Enabled:</strong></td>
                <td><?php echo isset( $settings['upay_enable'] ) && $settings['upay_enable'] ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>Client ID Set:</strong></td>
                <td><?php echo ! empty( $settings['upay_settings']['client_id'] ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>Client Secret Set:</strong></td>
                <td><?php echo ! empty( $settings['upay_settings']['client_secret'] ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>Partner ID Set:</strong></td>
                <td><?php echo ! empty( $settings['upay_settings']['partner_id'] ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>Biller UUID Set:</strong></td>
                <td><?php echo ! empty( $settings['upay_settings']['biller_uuid'] ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
        </table>

        <hr>

        <h2>7. Actions & Hooks</h2>
        <table class="widefat">
            <tr>
                <td><strong>Action: wte_upay_settings</strong></td>
                <td><?php echo has_action( 'wte_upay_settings' ) ? '✅ Registered' : '❌ Not registered'; ?></td>
            </tr>
            <tr>
                <td><strong>Action: wte_upay_enable</strong></td>
                <td><?php echo has_action( 'wte_upay_enable' ) ? '✅ Registered' : '❌ Not registered'; ?></td>
            </tr>
            <tr>
                <td><strong>Filter: wptravelengine_payment_gateways</strong></td>
                <td><?php echo has_filter( 'wptravelengine_payment_gateways' ) ? '✅ Registered' : '❌ Not registered'; ?></td>
            </tr>
        </table>

        <hr>

        <h2>8. Quick Fixes</h2>
        <div style="background: #fff8e5; border-left: 4px solid #ffb900; padding: 15px; margin: 20px 0;">
            <h3>Try These Direct Links:</h3>
            <ul>
                <li>
                    <a href="<?php echo admin_url( 'edit.php?post_type=booking&page=class-wp-travel-engine-admin.php#payment-wte-upay' ); ?>" target="_blank">
                        Direct Link to UPay Settings
                    </a>
                </li>
                <li>
                    <a href="<?php echo admin_url( 'edit.php?post_type=booking&page=class-wp-travel-engine-admin.php' ); ?>" target="_blank">
                        WP Travel Engine Settings (Main)
                    </a>
                </li>
            </ul>
        </div>

        <div style="background: #e7f5fe; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
            <h3>Recommended Actions:</h3>
            <ol>
                <li>If "UPay Plugin Active" is ❌, activate the plugin</li>
                <li>If "Wte_UPay_Admin exists" is ❌, the class file isn't loading</li>
                <li>If "UPay tab registered" is ❌, the filter isn't working</li>
                <li>If "Settings File Exists" is ❌, the path is wrong</li>
            </ol>
        </div>

        <hr>

        <p><em>Last checked: <?php echo date( 'Y-m-d H:i:s' ); ?></em></p>
    </div>

    <style>
        .widefat td, .widefat th {
            padding: 10px;
        }
        .widefat tr:nth-child(even) {
            background: #f9f9f9;
        }
    </style>
    <?php
}
