<?php
/**
 * Force Clear WTE Caches and Re-register UPay
 *
 * This script forces WordPress to clear all WTE-related caches
 * and re-registers the UPay settings tab.
 *
 * Upload to: wp-content/plugins/wte-upay/
 * Access via: wp-admin/admin.php?page=upay-force-fix
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'upay_force_fix_page' );

function upay_force_fix_page() {
    add_submenu_page(
        null,
        'UPay Force Fix',
        'UPay Force Fix',
        'manage_options',
        'upay-force-fix',
        'upay_force_fix_render'
    );
}

function upay_force_fix_render() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    // If fix button clicked
    if ( isset( $_POST['upay_force_fix'] ) && check_admin_referer( 'upay_force_fix_action' ) ) {
        upay_do_force_fix();
    }

    ?>
    <div class="wrap">
        <h1>🔧 UPay Force Fix & Cache Clear</h1>

        <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">
            <h2>What This Does:</h2>
            <ol>
                <li>Clears all WordPress transients and caches</li>
                <li>Clears WTE-specific caches</li>
                <li>Forces re-registration of UPay settings tab</li>
                <li>Reloads WordPress options</li>
                <li>Triggers plugin reactivation hooks</li>
            </ol>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'upay_force_fix_action' ); ?>
            <p>
                <button type="submit" name="upay_force_fix" class="button button-primary button-hero">
                    🚀 Run Force Fix Now
                </button>
            </p>
        </form>

        <div style="background: #fff8e5; padding: 15px; margin: 20px 0; border-left: 4px solid #ffb900;">
            <h3>After Running Fix:</h3>
            <ol>
                <li>Go to: <a href="<?php echo admin_url( 'edit.php?post_type=booking&page=class-wp-travel-engine-admin.php' ); ?>">WP Travel Engine → Settings</a></li>
                <li>Click the "Payment" tab</li>
                <li>Look for "UPay" or "UPay Settings" in the sub-tabs</li>
                <li>If still not showing, come back here and check the diagnostic below</li>
            </ol>
        </div>

        <?php if ( isset( $_POST['upay_force_fix'] ) ): ?>
        <div class="notice notice-success">
            <h2>✅ Force Fix Complete!</h2>
            <p><strong>Next Steps:</strong></p>
            <ul>
                <li>Navigate to WP Travel Engine → Settings → Payment</li>
                <li>The UPay tab should now appear</li>
                <li>If not, check the diagnostic below</li>
            </ul>
        </div>
        <?php endif; ?>

        <hr>

        <h2>Current Status:</h2>
        <table class="widefat">
            <tr>
                <td><strong>Filter Registered:</strong></td>
                <td><?php echo has_filter( 'wpte_settings_get_global_tabs' ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>Wte_UPay_Admin Class:</strong></td>
                <td><?php echo class_exists( 'Wte_UPay_Admin' ) ? '✅ Exists' : '❌ Not Found'; ?></td>
            </tr>
            <tr>
                <td><strong>UPay Tab in Filter:</strong></td>
                <td>
                    <?php
                    $tabs = apply_filters( 'wpte_settings_get_global_tabs', array( 'wpte-payment' => array( 'sub_tabs' => array() ) ) );
                    echo isset( $tabs['wpte-payment']['sub_tabs']['wte-upay'] ) ? '✅ Yes' : '❌ No';
                    ?>
                </td>
            </tr>
        </table>

        <h3>Registered Payment Sub-Tabs:</h3>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Tab ID</th>
                    <th>Label</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $tabs = apply_filters( 'wpte_settings_get_global_tabs', array( 'wpte-payment' => array( 'sub_tabs' => array() ) ) );
                if ( isset( $tabs['wpte-payment']['sub_tabs'] ) && ! empty( $tabs['wpte-payment']['sub_tabs'] ) ) {
                    foreach ( $tabs['wpte-payment']['sub_tabs'] as $tab_id => $config ) {
                        echo '<tr>';
                        echo '<td><code>' . esc_html( $tab_id ) . '</code></td>';
                        echo '<td>' . esc_html( $config['label'] ?? 'N/A' ) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="2">No sub-tabs found</td></tr>';
                }
                ?>
            </tbody>
        </table>
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

function upay_do_force_fix() {
    global $wpdb;

    // 1. Clear all transients
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'" );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'" );

    // 2. Clear WTE-specific options
    delete_option( 'wp_travel_engine_sorted_gateways' );
    delete_option( 'wte_payment_gateways' );
    delete_option( 'upay_cache_cleared' );

    // 3. Clear WordPress object cache
    wp_cache_flush();

    // 4. Force reload of options
    wp_load_alloptions();

    // 5. Trigger plugins_loaded again (force re-registration)
    do_action( 'plugins_loaded' );

    // 6. Clear any admin page caches
    delete_transient( 'wptravelengine_admin_page_tabs' );
    delete_transient( 'wpte_settings_tabs' );

    return true;
}
