<?php
/**
 * Single row for the extensions list table.
 *
 * @since 1.0.0
 */

/**
 * @var stdClass $item
 * @var string $action_links
 * @var string|object|bool $license_status
 */

$_license_status    = $license_status->license ?? 'check-status';
$masked_license_key = ! empty( $item->license_key ) ? str_repeat( '*', strlen( $item->license_key ) - 5 ) . substr( $item->license_key, - 5 ) : '';
?>
<div class="plugin-card plugin-card-<?php echo esc_attr( $item->slug ); ?> wpte-addon__card"
     data-license-status="<?php echo esc_attr( empty( $item->download_link ) ? 'check-status' : $_license_status ); ?>"
     data-slug="<?php echo esc_attr( $item->slug ) ?>"
     data-id="<?php echo esc_attr( $item->id ) ?>"
     data-license="<?php echo esc_attr( $item->license_key ); ?>"
>
    <div class="plugin-card-top">
        <div class="wpte-addon__logo">
            <a href="<?php echo esc_url( $item->homepage ) ?>" target="_blank">
                <img src="<?php echo $item->thumbnail; ?>" alt="">
            </a>
        </div>
        <div class="wpte-addon__name">
            <a href="<?php echo esc_url( $item->homepage ) ?>" target="_blank" data-title>
				<?php echo esc_html( $item->name ); ?>
            </a>
        </div>
        <div class="desc column-description">
            <p data-description><?php echo esc_html( $item->short_description ); ?></p>
        </div>
        <div class="action-links">
			<?php echo $action_links; ?>
        </div>
    </div>
    <div class="plugin-card-bottom">
        <span
            class="wpte-addon__version"><?php echo sprintf( 'Version: %s', "<code class='badge warning'>{$item->version}</code>" ); ?></span>
    </div>
</div>
