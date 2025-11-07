<?php
/**
 * Quick WTE Version Check
 *
 * Access via: wp-admin/admin.php?page=upay-wte-version
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_menu', 'upay_wte_version_check_page' );

function upay_wte_version_check_page() {
    add_submenu_page(
        null,
        'WTE Version Check',
        'WTE Version Check',
        'manage_options',
        'upay-wte-version',
        'upay_wte_version_render'
    );
}

function upay_wte_version_render() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }
    ?>
    <div class="wrap">
        <h1>WP Travel Engine Version Check</h1>

        <h2>WTE Version Information</h2>
        <table class="widefat">
            <tr>
                <td><strong>WTE Version:</strong></td>
                <td><?php echo defined( 'WP_TRAVEL_ENGINE_VERSION' ) ? WP_TRAVEL_ENGINE_VERSION : 'Not defined'; ?></td>
            </tr>
            <tr>
                <td><strong>WTE File Path:</strong></td>
                <td><?php echo defined( 'WP_TRAVEL_ENGINE_FILE_PATH' ) ? WP_TRAVEL_ENGINE_FILE_PATH : 'Not defined'; ?></td>
            </tr>
        </table>

        <h2>Required Parent Classes</h2>
        <table class="widefat">
            <tr>
                <td><strong>\WPTravelEngine\Payments\Payment_Gateway</strong></td>
                <td><?php echo class_exists( '\WPTravelEngine\Payments\Payment_Gateway' ) ? '✅ EXISTS' : '❌ NOT FOUND'; ?></td>
            </tr>
            <tr>
                <td><strong>\WPTravelEngine\PaymentGateways\BaseGateway</strong></td>
                <td><?php echo class_exists( '\WPTravelEngine\PaymentGateways\BaseGateway' ) ? '✅ EXISTS' : '❌ NOT FOUND'; ?></td>
            </tr>
            <tr>
                <td><strong>WP_Travel_Engine</strong></td>
                <td><?php echo class_exists( 'WP_Travel_Engine' ) ? '✅ EXISTS' : '❌ NOT FOUND'; ?></td>
            </tr>
        </table>

        <h2>Payment Gateway Classes in WTE</h2>
        <?php
        $wte_classes = get_declared_classes();
        $payment_classes = array_filter( $wte_classes, function( $class ) {
            return stripos( $class, 'payment' ) !== false && stripos( $class, 'wptravelengine' ) !== false;
        });
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Class Name</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $payment_classes ) ): ?>
                    <?php foreach ( $payment_classes as $class ): ?>
                    <tr>
                        <td><code><?php echo esc_html( $class ); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td>No payment gateway classes found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2>All WPTravelEngine Classes</h2>
        <?php
        $wte_all_classes = array_filter( $wte_classes, function( $class ) {
            return stripos( $class, 'wptravelengine' ) !== false || stripos( $class, 'wp_travel_engine' ) !== false;
        });
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Class Name</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $wte_all_classes ) ): ?>
                    <?php $i = 1; foreach ( $wte_all_classes as $class ): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><code><?php echo esc_html( $class ); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2">No WPTravelEngine classes found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2>Recommendation</h2>
        <div style="background: #fff8e5; border-left: 4px solid #ffb900; padding: 15px; margin: 20px 0;">
            <?php if ( ! class_exists( '\WPTravelEngine\Payments\Payment_Gateway' ) ): ?>
                <h3>⚠️ Missing Parent Class</h3>
                <p>The class <code>\WPTravelEngine\Payments\Payment_Gateway</code> does not exist in your WTE installation.</p>
                <p><strong>This is why UPay settings aren't showing!</strong></p>

                <h4>Possible Solutions:</h4>
                <ol>
                    <li><strong>Update WP Travel Engine</strong> to the latest version (5.5+)</li>
                    <li>Check if you have WP Travel Engine <strong>Free</strong> or <strong>Pro</strong></li>
                    <li>Contact WTE support to confirm the correct parent class for your version</li>
                </ol>
            <?php else: ?>
                <h3>✅ Parent Class Found</h3>
                <p>The required parent class exists. The UPay gateway should work!</p>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .widefat td, .widefat th {
            padding: 10px;
        }
        .widefat tr:nth-child(even) {
            background: #f9f9f9;
        }
        .widefat code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
    <?php
}
