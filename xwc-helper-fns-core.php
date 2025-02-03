<?php
/**
 * Core utility functions for WooCommerce.
 *
 * @package eXtended WooCommerce
 * @subpackage Helpers
 */

use Automattic\Jetpack\Constants;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Enable a feature for WooCommerce.
 *
 * @param string $plugin The plugin name or constant.
 */
function wc_hpos_enable( string $plugin ): void {
    add_action(
        'before_woocommerce_init',
        static function () use ( $plugin ) {
            FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                Constants::get_constant( $plugin ) ?? $plugin,
                true,
            );
        },
        10,
    );
}
