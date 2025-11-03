<?php
/**
 * Pro Update view template.
 *
 * @since 1.0.0
 */

global $wp_list_table;

?>
<form id="plugin-filter" method="post">
	<?php wptravelengine_pro_config_view( 'list-table/pro-upgrade' ); ?>
	<div class="wp-list-table widefat plugin-install">
		<div id="the-list" class="wpte-addon__list">
			<?php
			foreach ( $wp_list_table->items as $item ) {
				$action_links = wp_get_plugin_action_button( $item->name, $item, true, true );
				wptravelengine_pro_config_view( 'list-table/single-row', compact( 'item', 'action_links' ) );
			}
			?>
		</div>
	</div>
</form>
