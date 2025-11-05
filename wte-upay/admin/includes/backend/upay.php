<?php
/**
 * UPay Payment Details Meta Box
 *
 * @package WTE_UPay
 */

global $post;

$transaction_id = get_post_meta( $post->ID, 'upay_transaction_id', true );
$uuid           = get_post_meta( $post->ID, 'upay_uuid', true );
$sender_ref_id  = get_post_meta( $post->ID, 'upay_sender_ref_id', true );
$payment_status = get_post_meta( $post->ID, 'payment_status', true );
$response       = get_post_meta( $post->ID, 'upay_response', true );

?>
<div class="upay-payment-details">
    <?php if ( $payment_status ) : ?>
        <p>
            <strong><?php esc_html_e( 'Status:', 'wte-upay' ); ?></strong>
            <span class="payment-status status-<?php echo esc_attr( $payment_status ); ?>">
                <?php echo esc_html( ucfirst( $payment_status ) ); ?>
            </span>
        </p>
    <?php endif; ?>

    <?php if ( $transaction_id ) : ?>
        <p>
            <strong><?php esc_html_e( 'Transaction ID:', 'wte-upay' ); ?></strong>
            <?php echo esc_html( $transaction_id ); ?>
        </p>
    <?php endif; ?>

    <?php if ( $sender_ref_id ) : ?>
        <p>
            <strong><?php esc_html_e( 'Sender Reference ID:', 'wte-upay' ); ?></strong>
            <?php echo esc_html( $sender_ref_id ); ?>
        </p>
    <?php endif; ?>

    <?php if ( $uuid ) : ?>
        <p>
            <strong><?php esc_html_e( 'UUID:', 'wte-upay' ); ?></strong>
            <?php echo esc_html( $uuid ); ?>
        </p>
    <?php endif; ?>

    <?php if ( $response && is_array( $response ) ) : ?>
        <details style="margin-top: 10px;">
            <summary style="cursor: pointer; font-weight: bold;">
                <?php esc_html_e( 'Full API Response', 'wte-upay' ); ?>
            </summary>
            <pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px; font-size: 11px;">
<?php echo esc_html( print_r( $response, true ) ); ?>
            </pre>
        </details>
    <?php endif; ?>
</div>

<style>
.upay-payment-details p {
    margin: 10px 0;
}
.upay-payment-details strong {
    display: inline-block;
    min-width: 140px;
}
.payment-status {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
}
.status-completed,
.status-success,
.status-paid {
    background: #d4edda;
    color: #155724;
}
.status-pending {
    background: #fff3cd;
    color: #856404;
}
.status-failed,
.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}
</style>
