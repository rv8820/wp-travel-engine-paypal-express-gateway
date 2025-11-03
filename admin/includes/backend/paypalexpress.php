<?php
global $post;
$wp_travel_engine_postmeta_settings = get_post_meta( $post->ID, 'wp_travel_engine_booking_setting', true );
?>
<div class="trip-info-meta">
	<div>
		<label for="wp_travel_engine_booking_setting[place_order][payment][paymentid]"><?php _e('PaymentID: ','wte-paypalexpress');?></label>	
		<input type="text" id="wp_travel_engine_booking_setting[place_order][payment][paymentid]" name="wp_travel_engine_booking_setting[place_order][payment][paymentid]" value="<?php echo isset($wp_travel_engine_postmeta_settings['place_order']['payment']['paymentid']) ? esc_attr($wp_travel_engine_postmeta_settings['place_order']['payment']['paymentid']):''?>">
	</div>
	<div>
		<label for="wp_travel_engine_booking_setting[place_order][payment][payerid]"><?php _e('Payer ID: ','wte-paypalexpress');?></label>	
		<input type="text" id="wp_travel_engine_booking_setting[place_order][payment][payerid]" name="wp_travel_engine_booking_setting[place_order][payment][payerid]" value="<?php echo isset($wp_travel_engine_postmeta_settings['place_order']['payment']['payerid']) ? esc_attr($wp_travel_engine_postmeta_settings['place_order']['payment']['payerid']):'';?>">
	</div>
	<div>
		<label for="wp_travel_engine_booking_setting[place_order][payment][token]"><?php _e('Token: ','wte-paypalexpress');?></label>	
		<input type="text" id="wp_travel_engine_booking_setting[place_order][payment][token]" name="wp_travel_engine_booking_setting[place_order][payment][token]" value="<?php echo isset($wp_travel_engine_postmeta_settings['place_order']['payment']['token']) ? esc_attr($wp_travel_engine_postmeta_settings['place_order']['payment']['token']):'';?>">
	</div>
</div>