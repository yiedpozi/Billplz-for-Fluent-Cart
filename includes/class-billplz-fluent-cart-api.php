<?php
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * API class.
 * 
 * @since 1.0.0
 */
class Billplz_FluentCart_API extends Billplz_FluentCart_Client {
    /**
     * Create a bill.
     * 
     * @since 1.0.0
     * 
     * @param array $params
     * @return array<integer, mixed>
     * @throws \Exception
     */
    public function create_bill( array $params ) {
        return $this->post( 'v3/bills', $params );
    }

    /**
     * Webhook rank.
     * 
     * @since 1.0.0
     * 
     * @param array $params
     * @return array<integer, mixed>
     * @throws \Exception
     */
    public function get_webhook_rank() {
        return $this->get( 'v4/webhook_rank' );
    }
}
