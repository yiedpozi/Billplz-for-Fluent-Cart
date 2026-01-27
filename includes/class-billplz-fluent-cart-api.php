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
     * Get available payment methods for specified collection.
     * 
     * @since 1.0.0
     * 
     * @param string $collection_id
     * @return array<integer, mixed>
     * @throws \Exception
     */
    public function get_payment_methods( string $collection_id ) {
        return $this->get( "v3/collections/{$collection_id}/payment_methods" );
    }

    /**
     * Get a webhook rank.
     * 
     * @since 1.0.0
     * 
     * @return array<integer, mixed>
     * @throws \Exception
     */
    public function get_webhook_rank() {
        return $this->get( 'v4/webhook_rank' );
    }

    /**
     * Get payment gateways.
     * 
     * @since 1.0.0
     * 
     * @return array<integer, mixed>
     * @throws \Exception
     */
    public function get_payment_gateways() {
        return $this->get( 'v4/payment_gateways' );
    }
}
