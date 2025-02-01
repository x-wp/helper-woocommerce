<?php
/**
 * WooCommerce API Key helper functions definition file.
 *
 * @package eXtended WooCommerce
 * @subpackage Helper\Functions
 */

use XWC\Functions\API_Key;

if ( ! function_exists( 'xwc_create_api_key' ) ) :
    /**
     * Create a new API key for a user.
     *
     * @param  int|WP_User                 $user        User ID or object.
     * @param  string                      $description Description of the API key.
     * @param  'read'|'write'|'read_write' $scope       Permissions for the API key.
     * @param  string|null                 $meta_key    Optional. User meta key to store the API key ID.
     * @return array{
     *  ck: string,
     *  cs: string,
     *  id: int,
     * }
     *
     * @throws InvalidArgumentException If the user does not have permission to create an API key.
     * @throws Exception If there was an error generating the API key.
     */
    function xwc_create_api_key( int|WP_User $user, string $description = '', string $scope = 'read', ?string $meta_key = null ): array {
        return API_Key::create( $user, $description, $scope, $meta_key );
    }
endif;
