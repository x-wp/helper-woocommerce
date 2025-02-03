<?php
/**
 * Core utility functions for WooCommerce.
 *
 * @package eXtended WooCommerce
 * @subpackage Helpers
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

/**
 * Enable a feature for WooCommerce.
 *
 * @param string $plugin The plugin name or constant.
 */
function wc_hpos_enable( string $plugin ): void {
    static $enabled;
    $enabled ??= array();

    add_action(
        'before_woocommerce_init',
        static function () use ( $plugin, &$enabled ) {
            $enabled[ $plugin ] ??= FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                $plugin,
                true,
            );
        },
        10,
    );
}
