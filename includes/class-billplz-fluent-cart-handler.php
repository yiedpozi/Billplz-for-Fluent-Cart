<?php

use FluentCart\App\Helpers\Status;
use FluentCart\App\Helpers\StatusHelper;
use FluentCart\App\Models\Order;
use FluentCart\App\Models\OrderTransaction;
use FluentCart\App\Services\Payments\PaymentInstance;

if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Payment handler class.
 * 
 * @since 1.0.0
 */
class Billplz_FluentCart_Handler
{
    public Billplz_FluentCart_Gateway $gateway;
    public Billplz_FluentCart_Gateway_Settings $settings;

    private $apiKey;
    private $xsignatureKey;
    private $collectionId;
    private bool $sandbox;

    /**
     * API class.
     * 
     * @since 1.0.0
     */
    private Billplz_FluentCart_API $billplz;

    /**
     * Debug logger.
     * 
     * @since 1.0.0
     */
    private Billplz_FluentCart_Logger $logger;

    /* 
     * Constructor.
     * 
     * @since 1.0.0
     */
    public function __construct( Billplz_FluentCart_Gateway $gateway ) {
        $this->gateway = $gateway;
        $this->settings = $gateway->settings;

        $this->logger = Billplz_FluentCart_Logger::get_instance();
    }

    /**
     * Supported currencies.
     * 
     * @since 1.0.0
     */
    public array $supportedCurrencies = [ 'MYR' ];

    /**
     * Initialize API.
     * 
     * @since 1.0.0
     * 
     * @return Billplz_FluentCart_API|WP_Error
     */
    private function initApi(): Billplz_FluentCart_API|WP_Error
    {
        $this->apiKey = $this->settings->getApiKey();
        $this->xsignatureKey = $this->settings->getXsignatureKey();
        $this->collectionId = $this->settings->getCollectionId();

        $paymentMode = $order->mode ?? $this->settings->getMode();
        $this->sandbox = $paymentMode === 'test';

        if ( empty( $this->apiKey ) ) {
			return new WP_Error( 'missing_api_key', __( 'Missing API key.', 'billplz-for-fluent-cart' ) );
        }

        if ( empty( $this->xsignatureKey ) ) {
			return new WP_Error( 'missing_xsignature_key', __( 'Missing X-Signature key.', 'billplz-for-fluent-cart' ) );
        }

        if ( empty( $this->collectionId ) ) {
			return new WP_Error( 'missing_collection_id', __( 'Missing collection ID.', 'billplz-for-fluent-cart' ) );
        }

        $this->billplz = new Billplz_FluentCart_API( $this->apiKey, $this->xsignatureKey, $this->sandbox );

        return $this->billplz;
    }

    /**
     * Create a bill for order.
     * 
     * @param \FluentCart\App\Services\Payments\PaymentInstance $paymentInstance
     * @return array|WP_Error
     */
    public function handlePayment( PaymentInstance $paymentInstance ): array|WP_Error
    {
        $order = $paymentInstance->order;
        $transaction = $paymentInstance->transaction;
        $customer = $order->customer;

        if ( !in_array( $transaction->currency, $this->supportedCurrencies ) ) {
			return new WP_Error( 'unsupported_currency', __( 'Unsupported currency.', 'billplz-for-fluent-cart' ) );
        }

        $api = $this->initApi();

        if ( is_wp_error( $api ) ) {
            $this->logger->error( 'Initialize API Error', 'Failed to initialize Billplz API: ' . $api->get_error_message() );

            return $api;
        }

        $description = $order->order_items->implode( 'full_name', ', ' );
        $selectedBank = null;

        $redirectUrl = $this->gateway->getSuccessUrl( $transaction );
        $callbackUrl = home_url( '?fluent-cart=fct_payment_listener_ipn&method=' . $this->gateway->getMeta( 'slug' ) );

        $params = [
            'collection_id' => $this->collectionId,
            'email' => $customer->email,
            'mobile' => $order->billing_address->phone ?? $order->shipping_address->phone,
            'name' => $customer->full_name,
            'amount' => (int) $order->total_amount * 100,
            'redirect_url' => $redirectUrl,
            'callback_url' => $callbackUrl,
            'description' => $description,
            'reference_1_label' => $selectedBank ? 'Bank Code' : null,
            'reference_1' => $selectedBank ?? null,
            'reference_2_label' => 'Order ID',
            'reference_2' => $order->id,
        ];

        // Limit string length
        $params['description'] = mb_substr( $params['description'] ?: '', 0, 200 );
        $params['reference_1_label'] = mb_substr( $params['reference_1_label'] ?: '', 0, 20 );
        $params['reference_2_label'] = mb_substr( $params['reference_2_label'] ?: '', 0, 20 );
        $params['reference_1'] = mb_substr( $params['reference_1'] ?: '', 0, 120 );
        $params['reference_2'] = mb_substr( $params['reference_2'] ?: '', 0, 120 );

        list( $code, $response ) = $this->billplz->create_bill( $params );

        $errorType = $response['error']['type'] ?? null;

        if ( $code !== 200 && $errorType ) {
            $this->logger->error( 'Bill Creation Error', 'Failed to create a bill. ' . json_encode( [
                'order_id' => $order->id,
                'response' => $response,
            ] ) );

			return new WP_Error( 'billplz_api_error', $errorType );
        }

        if ( $code !== 200 ) {
            $this->logger->error( 'Bill Creation Error', 'Failed to create a bill. ' . json_encode( [
                'order_id' => $order->id,
                'response' => $response,
            ] ) );

			return new WP_Error( 'billplz_api_error', 'HTTP ' . $code );
        }

        $this->logger->info( 'Bill Created', 'Successfully create a bill. ' . json_encode( [
            'order_id' => $order->id,
            'bill_id' => $response['id'] ?? null,
        ] ) );

        return $response;
    }

    /**
     * Handle IPN.
     * 
     * @since 1.0.0
     * 
     * @return void
     * @throws \Exception
     */
    public function handleIpn(): void
    {
        $api = $this->initApi();

        if ( is_wp_error( $api ) ) {
            throw new Exception( $api->get_error_message() );
        }

        $response = $this->billplz->get_ipn_response();

        if ( $this->billplz->validate_ipn_response( $response ) ) {
            $this->logger->info( 'IPN Received', 'Successfully validate IPN response. Response: ' . json_encode( $response ) );

            $billId = $response['id'] ?? $response['billplzid'];

            $orderTransaction = OrderTransaction::query()
                ->where( 'vendor_charge_id', $billId )
                ->first();

            if ( !$orderTransaction ) {
                throw new Exception( 'Transaction not found' );
            }

            $this->handlePaymentUpdated( $orderTransaction, $response );
        }
    }

    /**
     * Handle payment status update based on IPN response.
     * 
     * @since 1.0.0
     * 
     * @param \FluentCart\App\Models\OrderTransaction $orderTransaction
     * @param array $response
     * @return \FluentCart\App\Models\Order
     */
    private function handlePaymentUpdated( OrderTransaction $orderTransaction, array $response ): Order
    {
        $orderTransaction->status = Status::TRANSACTION_PENDING;
        $orderTransaction->payment_method_type = __( 'Billplz', 'billplz-for-fluent-cart' );

        $transactionStatus = $response['transaction_status'] ?? $response['billplztransaction_status'];
        $paid = $response['paid'] ?? $response['billplzpaid'];

        // If Extra Payment Completion Information option is enabled in Billplz, get the transaction status
        if ( $transactionStatus ) {
            switch ( $transactionStatus ) {
                case 'completed':
                    $orderTransaction->status = Status::TRANSACTION_SUCCEEDED;
                    break;

                case 'failed':
                    $orderTransaction->status = Status::TRANSACTION_FAILED;
                    break;
            }
        } elseif ( $paid == 'true' ) {
            $orderTransaction->status = Status::TRANSACTION_SUCCEEDED;
        }

        $orderTransaction->save();

        return ( new StatusHelper( $orderTransaction->order ) )->syncOrderStatuses( $orderTransaction );
    }
}
