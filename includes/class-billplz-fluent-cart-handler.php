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
     * Bank codes and title.
     * 
     * @since 1.0.0
     */
    protected array $banks = [
        'ABMB0212' => 'allianceonline',
        'ABB0233' => 'affinOnline',
        'ABB0234' => 'Affin Bank',
        'AMBB0209' => 'AmOnline',
        'AGRO01' => 'AGRONet',
        'BCBB0235' => 'CIMB Clicks',
        'BIMB0340' => 'Bank Islam Internet Banking',
        'BKRM0602' => 'i-Rakyat',
        'BMMB0341' => 'i-Muamalat',
        'BOCM01' => 'Bank of China',
        'BSN0601' => 'myBSN',
        'CIT0219' => 'Citibank Online',
        'HLB0224' => 'HLB Connect',
        'HSBC0223' => 'HSBC Online Banking',
        'KFH0346' => 'KFH Online',
        'MB2U0227' => 'Maybank2u',
        'MBB0228' => 'Maybank2E',
        'OCBC0229' => 'OCBC Online Banking',
        'PBB0233' => 'PBe',
        'RHB0218' => 'RHB Now',
        'SCB0216' => 'SC Online Banking',
        'UOB0226' => 'UOB Internet Banking',
        'UOB0229' => 'UOB Bank',
        'TEST0001' => 'Test 0001',
        'TEST0002' => 'Test 0002',
        'TEST0003' => 'Test 0003',
        'TEST0004' => 'Test 0004',
        'TEST0021' => 'Test 0021',
        'TEST0022' => 'Test 0022',
        'TEST0023' => 'Test 0023',
        'BP-FKR01' => 'Billplz Simulator',
        'BP-BILLPLZ1' => 'Visa / Mastercard (Billplz)',
        'BP-PPL01' => 'PayPal',
        'BP-OCBC1' => 'Visa / Mastercard',
        'BP-2C2P1' => 'e-pay',
        'BP-2C2PC' => 'Visa / Mastercard',
        'BP-2C2PU' => 'UnionPay',
        'BP-2C2PGRB' => 'Grab',
        'BP-2C2PGRBPL' => 'GrabPayLater',
        'BP-2C2PATOME' => 'Atome',
        'BP-2C2PBST' => 'Boost',
        'BP-2C2PTNG' => 'TnG',
        'BP-2C2PSHPE' => 'Shopee Pay',
        'BP-2C2PSHPQR' => 'Shopee Pay QR',
        'BP-2C2PIPP' => 'IPP',
        'BP-BST01' => 'Boost',
        'BP-TNG01' => 'TouchNGo E-Wallet',
        'BP-SGP01' => 'Senangpay',
        'BP-BILM1' => 'Visa / Mastercard',
        'BP-RZRGRB' => 'Grab',
        'BP-RZRBST' => 'Boost',
        'BP-RZRTNG' => 'TnG',
        'BP-RZRPAY' => 'RazerPay',
        'BP-RZRMB2QR' => 'Maybank QR',
        'BP-RZRWCTP' => 'WeChat Pay',
        'BP-RZRSHPE' => 'Shopee Pay',
        'BP-MPGS1' => 'MPGS',
        'BP-CYBS1' => 'Secure Acceptance',
        'BP-EBPG1' => 'Visa / Mastercard',
        'BP-EBPG2' => 'AMEX',
        'BP-PAYDE' => 'Paydee',
        'BP-MGATE1' => 'Visa / Mastercard / AMEX',
        'B2B1-ABB0235' => 'AFFINMAX',
        'B2B1-ABMB0213' => 'Alliance BizSmart',
        'B2B1-AGRO02' => 'AGRONetBIZ',
        'B2B1-AMBB0208' => 'AmAccess Biz',
        'B2B1-BCBB0235' => 'BizChannel@CIMB',
        'B2B1-BIMB0340' => 'Bank Islam eBanker',
        'B2B1-BKRM0602' => 'i-bizRAKYAT',
        'B2B1-BMMB0342' => 'iBiz Muamalat',
        'B2B1-BNP003' => 'BNP Paribas',
        'B2B1-CIT0218' => 'CitiDirect BE',
        'B2B1-DBB0199' => 'Deutsche Bank Autobahn',
        'B2B1-HLB0224' => 'HLB ConnectFirst',
        'B2B1-HSBC0223' => 'HSBCnet',
        'B2B1-KFH0346' => 'KFH Online',
        'B2B1-MBB0228' => 'Maybank2E',
        'B2B1-OCBC0229' => 'Velocity@ocbc',
        'B2B1-PBB0233' => 'PBe',
        'B2B1-PBB0234' => 'PB enterprise',
        'B2B1-RHB0218' => 'RHB Reflex',
        'B2B1-SCB0215' => 'SC Straight2Bank',
        'B2B1-TEST0021' => 'SBI Bank A',
        'B2B1-TEST0022' => 'SBI Bank B',
        'B2B1-TEST0023' => 'SBI Bank C',
        'B2B1-UOB0228' => 'UOB BIBPlus',
    ];

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
     * @param \FluentCart\App\Models\Order|null $order
     * @return Billplz_FluentCart_API|WP_Error
     */
    private function initApi( ?Order $order = null ): Billplz_FluentCart_API|WP_Error
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
     * @param array $paymentArgs
     * @return array|WP_Error
     */
    public function handlePayment( PaymentInstance $paymentInstance, array $paymentArgs = [] ): array|WP_Error
    {
        $order = $paymentInstance->order;
        $transaction = $paymentInstance->transaction;
        $customer = $order->customer;

        if ( !in_array( $transaction->currency, $this->supportedCurrencies ) ) {
			return new WP_Error( 'unsupported_currency', __( 'Unsupported currency.', 'billplz-for-fluent-cart' ) );
        }

        $api = $this->initApi( $order );

        if ( is_wp_error( $api ) ) {
            $this->logger->error( 'Initialize API Error', 'Failed to initialize Billplz API: ' . $api->get_error_message() );

            return $api;
        }

        $description = $order->order_items->implode( 'full_name', ', ' );
        $bankCode = $paymentArgs['bank_code'] ?? null;

        $redirectUrl = $this->gateway->getSuccessUrl( $transaction );
        $callbackUrl = home_url( '?fluent-cart=fct_payment_listener_ipn&method=' . $this->gateway->getMeta( 'slug' ) );

        $params = [
            'collection_id' => $this->collectionId,
            'email' => $customer->email,
            'mobile' => $order->billing_address->phone ?? $order->shipping_address->phone,
            'name' => $customer->full_name,
            'amount' => (int) $order->total_amount,
            'redirect_url' => $redirectUrl,
            'callback_url' => $callbackUrl,
            'description' => $description,
            'reference_1_label' => $bankCode ? 'Bank Code' : null,
            'reference_1' => $bankCode ?? null,
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
        $billId = $_POST['id'] ?? $_GET['billplz']['id'] ?? null;

        // Find order transaction by bill ID
        $orderTransaction = OrderTransaction::query()
            ->where( 'vendor_charge_id', $billId )
            ->first();

        if ( !$orderTransaction ) {
            throw new Exception( 'Transaction not found' );
        }

        $api = $this->initApi( $orderTransaction->order );

        if ( is_wp_error( $api ) ) {
            throw new Exception( $api->get_error_message() );
        }

        $response = $this->billplz->get_ipn_response();

        if ( $this->billplz->validate_ipn_response( $response ) ) {
            $this->logger->info( 'IPN Received', 'Successfully validate IPN response. Response: ' . json_encode( $response ) );

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

    /**
     * Get payment gateways.
     * 
     * @since 1.0.0
     * 
     * @return array
     */
    public function getPaymentGateways(): array
    {
        $paymentGateways = get_transient( 'billplz_fluent_cart_payment_gateways' );

        if ( !empty( $paymentGateways ) && is_array( $paymentGateways ) ) {
            return $paymentGateways;
        }

        try {
            $api = $this->initApi();
            list( $code, $response ) = $this->billplz->get_payment_gateways();

            if ( $code !== 200 ) {
                $this->logger->error( 'Get Payment Gateways Error', 'Response: ' . json_encode( $response ) );
                return [];
            }

            $paymentGateways = $response['payment_gateways'] ?? [];

            if ( !empty( $paymentGateways ) && is_array( $paymentGateways ) ) {
                set_transient( 'billplz_fluent_cart_payment_gateways', $paymentGateways, HOUR_IN_SECONDS );
            }
        } catch ( Exception $e ) {
            $this->logger->error( 'Get Payment Gateways Error', $e->getMessage() );
        }

        return $paymentGateways;
    }

    /**
     * Get enabled payment gateways for specified collection.
     * 
     * @since 1.0.0
     * 
     * @param string $collectionId
     * @return array
     */
    public function getPaymentGatewaysForCollection( string $collectionId ): array
    {
        $activePaymentGateways = get_transient( 'billplz_fluent_cart_collection_payment_gateways' );

        if ( !empty( $activePaymentGateways ) && is_array( $activePaymentGateways ) ) {
            return $activePaymentGateways;
        }

        try {
            $api = $this->initApi();
            list( $code, $response ) = $this->billplz->get_payment_methods( $collectionId );

            if ( $code !== 200 ) {
                $this->logger->error( 'Get Collection Payment Gateways Error', 'Response: ' . json_encode( $response ) );
                return [];
            }

            $paymentGateways = $response['payment_methods'] ?? [];
            $activePaymentGateways = [];

            if ( !empty( $paymentGateways ) && is_array( $paymentGateways ) ) {
                foreach ( $paymentGateways as $paymentGateway ) {
                    if ( $paymentGateway['active'] === true ) {
                        $activePaymentGateways[] = $paymentGateway['code'];
                    }
                }

                set_transient( 'billplz_fluent_cart_collection_payment_gateways', $activePaymentGateways, HOUR_IN_SECONDS );
            }
        } catch ( Exception $e ) {
            $this->logger->error( 'Get Collection Payment Gateways Error', $e->getMessage() );
        }

        return $activePaymentGateways;
    }

    /**
     * Get active (online) payment gateways.
     * 
     * @since 1.0.0
     * 
     * @param string $collectionId
     * @return array
     */
    public function getActivePaymentGateways( string $collectionId ): array
    {
        $paymentGateways = $this->getPaymentGateways();
        $collectionPaymentGateways = $this->getPaymentGatewaysForCollection( $collectionId );

        if ( empty( $paymentGateways ) || empty( $collectionPaymentGateways ) ) {
            return [];
        }

        $activePaymentGateways = [];

        foreach ( $paymentGateways as $paymentGateway ) {
            if ( $paymentGateway['active'] === true ) {
                $bankCode = $paymentGateway['code'] ?? null;
                $bankName = $this->banks[ $paymentGateway['code'] ] ?? null;

                if ( $bankCode && $bankName ) {
                    $activePaymentGateways[ $bankCode ] = $bankName;
                }
            }
        }

        natcasesort( $activePaymentGateways );

        return $activePaymentGateways;
    }
}
