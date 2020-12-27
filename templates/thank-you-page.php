<section class="woocommerce-accept-bitcoin-payment-instructions">
	
	<h2 class="woocommerce-accept-bitcoin-payment-instructions__title"><?php _e('Payment instructions', 'accept-bitcoin') ?></h2>

	<img src="<?php echo $this->get_qr_code_url($btc_address, $btc_amount); ?>" alt="Bitcoin QR code">

    <p><?php printf( __('Pay %s BTC to %s.', 'accept-bitcoin'), $btc_amount, $btc_address ); ?></p>

</section>