<?php
/**
 * UPay Global Settings
 *
 * @package WTE_UPay
 */

$wp_travel_engine_settings = get_option( 'wp_travel_engine_settings', array() );
$upay_client_id     = isset( $wp_travel_engine_settings['upay_settings']['client_id'] ) ? $wp_travel_engine_settings['upay_settings']['client_id'] : '';
$upay_client_secret = isset( $wp_travel_engine_settings['upay_settings']['client_secret'] ) ? $wp_travel_engine_settings['upay_settings']['client_secret'] : '';
?>

<div class="wpte-field wpte-checkbox wpte-floated">
    <label class="wpte-field-label" for="wp_travel_engine_settings[upay_enable]">
        <?php esc_html_e( 'Enable UPay Payment', 'wte-upay' ); ?>
    </label>
    <div class="wpte-checkbox-wrap">
        <input type="checkbox"
               id="wp_travel_engine_settings[upay_enable]"
               name="wp_travel_engine_settings[upay_enable]"
               value="1"
               <?php checked( ! empty( $wp_travel_engine_settings['upay_enable'] ), true ); ?> />
        <label for="wp_travel_engine_settings[upay_enable]"></label>
    </div>
    <span class="wpte-tooltip"><?php esc_html_e( 'Enable Union Bank UPay payment gateway for trip bookings.', 'wte-upay' ); ?></span>
</div>

<div class="wpte-field wpte-text wpte-floated">
    <label for="wp_travel_engine_settings[upay_settings][client_id]" class="wpte-field-label">
        <?php esc_html_e( 'Client ID (X-IBM-Client-Id)', 'wte-upay' ); ?>
    </label>
    <input type="text"
           id="wp_travel_engine_settings[upay_settings][client_id]"
           name="wp_travel_engine_settings[upay_settings][client_id]"
           value="<?php echo esc_attr( $upay_client_id ); ?>" />
    <span class="wpte-tooltip"><?php esc_html_e( 'Enter your Union Bank UPay Client ID from Developer Portal', 'wte-upay' ); ?></span>
</div>

<div class="wpte-field wpte-text wpte-floated">
    <label for="wp_travel_engine_settings[upay_settings][client_secret]" class="wpte-field-label">
        <?php esc_html_e( 'Client Secret (X-IBM-Client-Secret)', 'wte-upay' ); ?>
    </label>
    <input type="password"
           id="wp_travel_engine_settings[upay_settings][client_secret]"
           name="wp_travel_engine_settings[upay_settings][client_secret]"
           value="<?php echo esc_attr( $upay_client_secret ); ?>" />
    <span class="wpte-tooltip"><?php esc_html_e( 'Enter your Union Bank UPay Client Secret from Developer Portal', 'wte-upay' ); ?></span>
</div>
