<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing

namespace XWC\Functions;

use WP_User;

/**
 * WooCommerce REST API Key helper functions.
 */
class API_Key {
    private static function get_user_id( int|WP_User $user ): int {
        $user_id = \is_int( $user ) ? $user : $user->ID;

        if ( $user_id && ! \current_user_can( 'edit_user', $user_id ) && \get_current_user_id() !== $user_id ) {
            throw new \InvalidArgumentException(
                \esc_html__(
                    'You do not have permission to assign API Keys to the selected user.',
                    'woocommerce',
                ),
            );
        }

        return $user_id;
    }

    private static function get_permissions( string $permissions ): string {
        return \in_array( $permissions, array( 'read', 'write', 'read_write' ), true )
            ? \sanitize_text_field( $permissions )
            : 'read';
    }

    public static function create( int|WP_User $user, string $description, string $permissions, ?string $meta_key ): array {
        $user_id         = self::get_user_id( $user );
        $permissions     = self::get_permissions( $permissions );
		$description     = \wc_trim_string( $description, 200 );
		$consumer_key    = 'ck_' . \wc_rand_hash();
		$consumer_secret = 'cs_' . \wc_rand_hash();

        //phpcs:disable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder
		$data = array(
			'user_id'         => $user_id,
			'description'     => $description,
			'permissions'     => $permissions,
			'consumer_key'    => \wc_api_hash( $consumer_key ),
			'consumer_secret' => $consumer_secret,
			'truncated_key'   => \substr( $consumer_key, -7 ),
		);
        //phpcs:enable SlevomatCodingStandard.Arrays.AlphabeticallySortedByKeys.IncorrectKeyOrder

		global $wpdb;

		$wpdb->insert(
            $wpdb->prefix . 'woocommerce_api_keys',
            $data,
            array( '%d', '%s', '%s', '%s', '%s', '%s' ),
        );

		if ( 0 === $wpdb->insert_id ) {
			throw new \Exception( \esc_html__( 'There was an error generating your API Key.', 'woocommerce' ) );
		}

        if ( null !== $meta_key ) {
            \update_user_meta( $user_id, $meta_key, $wpdb->insert_id );
        }

		return array(
			'ck' => $consumer_key,
			'cs' => $consumer_secret,
			'id' => $wpdb->insert_id,
		);
    }
}
