<?php

if ( !defined( 'ABSPATH' ) ) exit;

use FluentCart\Api\Orders;
use FluentCart\App\App;
use FluentCart\App\Helpers\Helper;
use FluentCart\App\Helpers\Status;
use FluentCart\App\Models\OrderTransaction;
use FluentCart\App\Modules\PaymentMethods\Core\AbstractPaymentGateway;
use FluentCart\App\Services\Payments\PaymentInstance;
use FluentCart\App\Services\Renderer\FormFieldRenderer;
use FluentCart\Framework\Support\Arr;

/**
 * Main class.
 * 
 * @since 1.0.0
 */
class Billplz_FluentCart_Gateway extends AbstractPaymentGateway
{
    /**
     * Supported features.
     * 
     * @since 1.0.0
     */
    public array $supportedFeatures = [
        'payment',
        'webhook',
    ];

    /**
     * Payment handler.
     * 
     * @since 1.0.0
     */
    private Billplz_FluentCart_Handler $paymentHandler;

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
    public function __construct()
    {
        // Initialize settings
        parent::__construct( new Billplz_FluentCart_Gateway_Settings() );

        $this->paymentHandler = new Billplz_FluentCart_Handler( $this );
        $this->logger = Billplz_FluentCart_Logger::get_instance();
    }

    /**
     * Initialize gateway.
     * 
     * @since 1.0.0
     */
    public function init(): void
    {
        parent::init();

        add_action( 'fluent_cart/before_render_redirect_page', [ $this, 'handleRedirect' ], 10, 4 );
        add_action( 'fluent_cart/checkout_embed_payment_method_content', [ $this, 'renderBanksField' ] );
    }

    /**
     * Gateway meta.
     * 
     * @since 1.0.0
     */
    public function meta(): array
    {
        return [
            'title' => __( 'Billplz', 'billplz-for-fluent-cart' ),
            'route' => 'billplz',
            'slug' => 'billplz',
            'description' => __( 'Accept payments with Billplz.', 'billplz-for-fluent-cart' ),
            'logo' => BILLPLZ_FLUENTCART_URL . 'assets/images/billplz-logo.svg',
            'icon' => BILLPLZ_FLUENTCART_URL .  'assets/images/billplz-logo.svg',
            'brand_color' => '#000000',
            'status' => $this->settings->get( 'is_active' ) === 'yes',
        ];
    }

    /**
     * Process the payment.
     * 
     * @since 1.0.0
     */
    public function makePaymentFromPaymentInstance( PaymentInstance $paymentInstance )
    {
        $paymentArgs = [
            'bank_code' => App::request()->get( 'billplz_bank' ),
        ];

        $response = $this->paymentHandler->handlePayment( $paymentInstance, $paymentArgs );

        if ( is_wp_error( $response ) ) {
            return [
                'status' => 'failed',
                'message' => 'Unable to process the payment: ' . $response->get_error_message(),
            ];
        }

        $billId = $response['id'] ?? null;
        $billUrl = $response['url'] ?? null;

        if ( empty( $billId ) || empty( $billUrl ) ) {
            return [
                'status' => 'failed',
                'message' => 'Unable to process the payment',
            ];
        }

        $order = $paymentInstance->order;
        $transaction = $paymentInstance->transaction;

        // Store external payment ID for webhook processing
        $transaction->update( [
            'vendor_charge_id' => $billId,
            'payment_mode' => $order->mode,
        ] );

        // Append the bill URL to auto redirect to bank payment page
        if ( $paymentArgs['bank_code'] ?? null ) {
            $billUrl = add_query_arg( 'auto_submit', 'true', $billUrl );
        }

        return [
            'status' => 'success',
            'message' => __( 'Redirecting to payment page', 'billplz-for-fluent-cart' ),
            'redirect_to' => $billUrl,
        ];
    }

    /**
     * Handle IPN.
     * 
     * @since 1.0.0
     */
    public function handleIPN()
    {
        if ( ( $_SERVER['REQUEST_METHOD'] ?? null ) !== 'POST' ) {
            return;
        }

        try {
            $this->paymentHandler->handleIpn();

            wp_send_json_success();
        } catch ( Exception $e ) {
            $this->logger->error( 'IPN (Callback) Error', $e->getMessage() );
        }
    }

    /**
     * Handle redirect upon payment completion.
     * 
     * @since 1.0.0
     * 
     * @param array $data
     * @return void
     */
    public function handleRedirect( $data ): void
    {
        if ( ( $_SERVER['REQUEST_METHOD'] ?? null ) !== 'GET' ) {
            return;
        }

        $paymentMethod = $data['method'] ?? null;
        $orderHash = $data['order_hash'] ?? null;
        $transactionHash = $data['trx_hash'] ?? null;

        if ( $paymentMethod !== $this->getMeta( 'route' ) ) {
            return;
        }

        if ( empty( $orderHash ) && empty( $transactionHash ) ) {
            return;
        }

        // Find the order
        if ( !empty( $orderHash ) ) {
            $order = ( new Orders() )->getBy( 'uuid', $orderHash );
        } else {
            $transaction = OrderTransaction::query()
                ->where( 'uuid', $transactionHash )
                ->first();

            if ( !$transaction ) {
                return;
            }

            $order = ( new Orders() )->getById( $transaction->order_id );
        }

        if ( !$order ) {
            return;
        }

        // Bails if order is already paid
        if ( in_array( $order->payment_status, Status::getOrderPaymentSuccessStatuses() ) ) {
            return;
        }

        // Update order and payment status upon customer is redirected back to the site
        try {
            $this->paymentHandler->handleIpn();
        } catch ( Exception $e ) {
            $this->logger->error( 'IPN (Redirect) Error', $e->getMessage() );
        }
    }

    /**
     * Pass order information to frontend.
     * 
     * @since 1.0.0
     * 
     * @param array $data
     */
    public function getOrderInfo( array $data )
    {
        wp_send_json( [
            'status' => 'success',
            'payment_args' => [],
            'has_subscription' => false,
        ], 200 );
    }

    /**
     * Set receipt URL.
     * 
     * @since 1.0.0
     */
    public function getTransactionUrl( $url, $data )
    {
        $billId = $data['vendor_charge_id'] ?? null;
        $paymentMode = $data['payment_mode'] ?? $data->order['mode'] ?? $this->settings->getMode();
        $sandbox = $paymentMode === 'test';

        if ( !$billId ) {
            return $url;
        }

        return $sandbox
            ? 'https://www.billplz-sandbox.com/bills/' . $billId
            : 'https://www.billplz.com/bills/' . $billId;
    }

    /**
     * Gateway settings fields.
     * 
     * @since 1.0.0
     */
    public function fields(): array
    {
        // Test mode credentials
        $testSchema = [
            'test_api_key' => [
                'type' => 'text',
                'label' => __( 'Test API Key', 'billplz-for-fluent-cart' ),
                'placeholder' => __( 'Enter your test API key', 'billplz-for-fluent-cart' ),
            ],
            'test_xsignature_key' => [
                'type' => 'password',
                'label' => __( 'Test X-Signature Key', 'billplz-for-fluent-cart' ),
                'placeholder' => __( 'Enter your test x-signature key', 'billplz-for-fluent-cart' ),
            ],
            'test_collection_id' => [
                'type' => 'text',
                'label' => __( 'Test Collection ID', 'billplz-for-fluent-cart' ),
                'placeholder' => __( 'Enter your test collection ID', 'billplz-for-fluent-cart' ),
            ],
        ];

        // Live mode credentials
        $liveSchema = [
            'live_api_key' => [
                'type' => 'text',
                'label' => __( 'Live API Key', 'billplz-for-fluent-cart' ),
                'placeholder' => __( 'Enter your live API key', 'billplz-for-fluent-cart' ),
            ],
            'live_xsignature_key' => [
                'type' => 'password',
                'label' => __( 'Live X-Signature Key', 'billplz-for-fluent-cart' ),
                'placeholder' => __( 'Enter your live x-signature key', 'billplz-for-fluent-cart' ),
            ],
            'live_collection_id' => [
                'type' => 'text',
                'label' => __( 'Live Collection ID', 'billplz-for-fluent-cart' ),
                'placeholder' => __( 'Enter your live collection ID', 'billplz-for-fluent-cart' ),
            ],
        ];

        return [
            'setup_notice' => [
                'type' => 'notice',
                'value' => wp_kses(
                    sprintf(
                        "<div class='pt-4'>
                            <p>%s</p>
                        </div>",
                        __('Configure your gateway settings below.', 'billplz-for-fluent-cart')
                    ),
                    [
                        'p'   => [],
                        'div' => ['class' => true],
                        'i'   => [],
                    ]
                ),
            ],
            'payment_mode' => [
                'type' => 'tabs',
                'schema' => [
                    [
                        'type' => 'tab',
                        'label' => __( 'Live credentials', 'billplz-for-fluent-cart' ),
                        'value' => 'live',
                        'schema' => $liveSchema,
                    ],
                    [
                        'type' => 'tab',
                        'label' => __( 'Test credentials', 'billplz-for-fluent-cart' ),
                        'value' => 'test',
                        'schema' => $testSchema,
                    ],
                ],
            ],
            'debug_mode' => [
                'type' => 'checkbox',
                'label' => __( 'Debug Mode', 'billplz-for-fluent-cart' ),
                'value' => 'no',
                'tooltip' => __( 'Enable logging for debugging purposes', 'billplz-for-fluent-cart' ),
            ],
        ];
    }

    /**
     * Transform gateway settings value before update.
     * 
     * @since 1.0.0
     */
    public static function beforeSettingsUpdate( $data, $oldSettings ): array
    {
        $mode = Arr::get( $data, 'payment_mode', 'live' );
        $xsignatureKeyField = $mode . '_xsignature_key';

        // Encrypt sensitive data before storage
        $data[ $xsignatureKeyField ] = Helper::encryptKey( $data[ $xsignatureKeyField ] );

        return $data;
    }

    /**
     * Validate gateway settings.
     * 
     * @since 1.0.0
     */
    public static function validateSettings( $data ): array
    {
        $mode = Arr::get( $data, 'payment_mode', 'live' );
        $apiKey = Arr::get( $data, $mode . '_api_key' );
        $xsignatureKey = Arr::get( $data, $mode . '_xsignature_key' );
        $collectionId = Arr::get( $data, $mode . '_collection_id' );

        if ( empty( $apiKey ) ) {
            return [
                'status' => 'failed',
                'message' => __( 'API key is required.', 'billplz-for-fluent-cart' ),
            ];
        }

        if ( empty( $xsignatureKey ) ) {
            return [
                'status' => 'failed',
                'message' => __( 'X-Signature key is required.', 'billplz-for-fluent-cart' ),
            ];
        }

        if ( empty( $collectionId ) ) {
            return [
                'status' => 'failed',
                'message' => __( 'Collection ID is required.', 'billplz-for-fluent-cart' ),
            ];
        }

        $logger = Billplz_FluentCart_Logger::get_instance();

        // Validate API credentials
        try {
            $sandbox = $mode === 'test';
            $billplz = new Billplz_FluentCart_API( $apiKey, $xsignatureKey, $sandbox );

            list( $code, $response ) = $billplz->get_webhook_rank();

            if ( $code !== 200 ) {
                $logger->error( 'Get Webhook Rank Error', 'Response: ' . json_encode( $response ) );

                return [
                    'status' => 'failed',
                    'message' => 'Invalid API credentials.',
                ];
            }
        } catch ( Exception $e ) {
            $logger->error( 'Get Webhook Rank Error', $e->getMessage() );

            return [
                'status' => 'failed',
                'message' => __( 'Unable to validate API credentials.', 'billplz-for-fluent-cart' ),
            ];
        }

        return $data;
    }

    /**
     * Render banks dropdown field.
     * 
     * @param array $data
     * @return string
     */
    public function renderBanksField( array $data )
    {
        $method = $data['method'] ?? null;
        $cart = $data['cart'] ?? null;
        $route = $data['route'] ?? null;

        if ( empty( $method ) || empty( $cart ) || empty( $route ) ) {
            return;
        }

        // Bails for other payment method
        if ( $route !== $this->getMeta( 'route' ) ) {
            return;
        }

        $formRender = new FormFieldRenderer();
        $title = __( 'Select any payment method', 'billplz-for-fluent-cart' );

        $collectionId = $this->settings->getCollectionId();
        $activePaymentGateways = $this->paymentHandler->getActivePaymentGateways( $collectionId );
        $paymentOptions = [];

        foreach ( $activePaymentGateways as $bankCode => $bankName ) {
            $paymentOptions[] = [
                'value' => $bankCode,
                'name' => $bankName,
            ];
        }
        ?>

        <div class="fct_billplz_bank_wrapper fct-has-default-font-size">
            <?php
                $formRender->renderField( [
                    'type' => 'select',
                    'id' => 'billplz_bank',
                    'name' => 'billplz_bank',
                    'label' => $title,
                    'options' => $paymentOptions,
                    'value' => Arr::get( $cart->checkout_data, 'form_data.billplz_bank', '' ),
                    'required' => true,
                ] );
            ?>
        </div>
        <?php
    }

    /**
     * Enqueue styles.
     * 
     * @since 1.0.0
     */
    public function getEnqueueStyleSrc(): array
    {
        return [
            [
                'src' => BILLPLZ_FLUENTCART_URL . 'assets/css/style.css',
                'handle' => 'styles',
            ],
        ];
    }

    /**
     * Set enqueue version.
     * 
     * @since 1.0.0
     */
    public function getEnqueueVersion()
    {
        return BILLPLZ_FLUENTCART_VERSION;
    }
}
