<?php

use FluentCart\App\Helpers\Helper;
use FluentCart\App\Modules\PaymentMethods\Core\BaseGatewaySettings;

/**
 * Gateway settings class.
 * 
 * @since 1.0.0
 */
class Billplz_FluentCart_Gateway_Settings extends BaseGatewaySettings
{
    public $methodHandler = 'fluent_cart_payment_settings_billplz';

    public static function getDefaults(): array
    {
        return [
            'is_active' => 'no',
            'payment_mode' => 'live',
            'test_api_key' => '',
            'test_xsignature_key' => '',
            'test_collection_id' => '',
            'live_api_key' => '',
            'live_xsignature_key' => '',
            'live_collection_id' => '',
            'debug_mode' => 'no',
        ];
    }

    public function get( $key = '' )
    {
        return $this->settings[ $key ] ?? $this->settings;
    }

    public function getMode(): string
    {
        return $this->get( 'payment_mode' );
    }

    public function isActive(): bool
    {
        return $this->settings['is_active'] === 'yes';
    }

    public function getApiKey(): string
    {
        $mode = $this->getMode();
        return $this->get( $mode . '_api_key' );
    }

    public function getXsignatureKey(): string
    {
        $mode = $this->getMode();
        return Helper::decryptKey( $this->get( $mode . '_xsignature_key' ) );
    }

    public function getCollectionId(): string
    {
        $mode = $this->getMode();
        return $this->get( $mode . '_collection_id' );
    }

    public function isTestMode(): bool
    {
        return $this->getMode() === 'test';
    }
}
