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
    static $enabled = array();

    $reg_fn = static function ( $plugin ) use ( &$enabled ) {
        $enabled[ $plugin ] ??= xwc_declare_compat( $plugin );
    };

    if ( doing_action( 'before_woocommerce_init' ) ) {
        $reg_fn( $plugin );
        return;
    }

    if ( did_action( 'before_woocommerce_init' ) || did_action( 'woocommerce_init' ) ) {
        wc_doing_it_wrong(
            __FUNCTION__,
            'This function must be called before WooCommerce is initialized.',
            '1.0.0',
        );
        return;
    }

    add_action(
        'before_woocommerce_init',
        static function () use ( $reg_fn, $plugin ) {
            $reg_fn( $plugin );
        },
        10,
    );
}

/**
 * Declare compatibility with a WooCommerce feature.
 *
 * @param  string $plugin     Full path to the plugin file.
 * @param  string $feature    Feature name, defaults to 'custom_order_tables'.
 * @param  bool   $compatible Whether the plugin is compatible with the feature, defaults to true.
 * @return bool True on success, false on failure.
 */
function xwc_declare_compat( string $plugin, string $feature = 'custom_order_tables', bool $compatible = true ): bool {
    return FeaturesUtil::declare_compatibility( $feature, $plugin, $compatible );
}
