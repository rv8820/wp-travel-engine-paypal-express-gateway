<?php

/**
 * Paypal Express Global settings.
 */

$wp_travel_engine_settings = get_option('wp_travel_engine_settings', true);
?>
<div class="wpte-field wpte-text wpte-floated">
    <label for="wp_travel_engine_settings[paypalexpress_client_id]" class="wpte-field-label"><?php _e('Client ID', 'wte-paypalexpress');?></label>
    <input type="text" id="wp_travel_engine_settings[paypalexpress_client_id]" name="wp_travel_engine_settings[paypalexpress_client_id]" value="<?php echo isset($wp_travel_engine_settings['paypalexpress_client_id']) ? esc_attr($wp_travel_engine_settings['paypalexpress_client_id']): '';?>">
    <span class="wpte-tooltip"><?php esc_html_e("Enter a valid Client ID from PayPal-Express account. All payments will go to this account.", 'wte-paypalexpress'); ?></span>
</div>

<div class="wpte-field wpte-text wpte-floated">
    <label for="wp_travel_engine_settings[paypalexpress_secret]" class="wpte-field-label"><?php _e('Client Secret', 'wte-paypalexpress');?></label>
    <input type="text" id="wp_travel_engine_settings[paypalexpress_secret]" name="wp_travel_engine_settings[paypalexpress_secret]" value="<?php echo isset($wp_travel_engine_settings['paypalexpress_secret']) ? esc_attr($wp_travel_engine_settings['paypalexpress_secret']): ''; ?>">
    <span class="wpte-tooltip"><?php esc_html_e("Enter a valid Secret Key from PayPal-Express account.", 'wte-paypalexpress'); ?></span>
</div>

<?php 
    $payment_method_array = [
        'card'    =>__('PayPal Credit', 'wte-paypalexpress'),
        'venmo'     =>__('Venmo', 'wte-paypalexpress'), 
        'sepa'      =>__('SEPA-Lastschrift', 'wte-paypalexpress'), 
        'bancontact'=>__('Bancontact', 'wte-paypalexpress'), 
        'eps'       =>__('EPS', 'wte-paypalexpress'), 
        'giropay'   =>__('GIROPAY', 'wte-paypalexpress'),
        'ideal'     =>__('IDEAL', 'wte-paypalexpress'),
        'mybank'    =>__('MyBank', 'wte-paypalexpress'),
        'p24'       =>__('P24', 'wte-paypalexpress'),
        'sofort'    =>__('Sofort', 'wte-paypalexpress')
    ];
    ?>
<div class="wpte-field wpte-select wpte-floated">
    <label for="wp_travel_engine_settings[paypalexpress_payment_method]" class="wpte-field-label"><?php _e('Disable Funding', 'wte-paypalexpress');?></label>
    <select multiple id="wp_travel_engine_settings[paypalexpress_payment_method]" name="wp_travel_engine_settings[paypalexpress_payment_method]" class="wpte-enhanced-select">
        <option value="card" <?php echo (!isset($wp_travel_engine_settings['paypalexpress_payment_method']))?'selected="selected"':''; ?>
        ><?php echo __('Credit or debit cards', 'wte-paypalexpress'); ?></option>
        <?php foreach ($payment_method_array as $payment_methods => $label) { 
            if (isset($wp_travel_engine_settings['paypalexpress_payment_method']) && !empty($wp_travel_engine_settings['paypalexpress_payment_method']) && in_array($payment_methods, $wp_travel_engine_settings['paypalexpress_payment_method'])) {
                $selected = 'selected="selected"';
            } else {
            $selected = '';
            }
            ?>
            <option value="<?php echo $payment_methods;?>" <?php echo $selected; ?>><?php echo $label; ?></option>
        <?php } ?>
    </select>
    <span class="wpte-tooltip"><?php esc_html_e("Default: Credit/debit cards are disabled. 	Funding sources to disallow from showing in the Smart Payment Buttons.", 'wte-paypalexpress'); ?></span>
</div>
<script>
jQuery("select.wpte-enhanced-select").select2();
</script>
<?php
