<?php // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query, WordPress.DB.SlowDBQuery.slow_db_query_tax_query

namespace XWC\Query;

/**
 * Enables custom query vars for product query.
 */
abstract class Product_Query_Customizer {
    /**
     * Class constructor
     *
     * @param array<string,'meta'|'custom'|string> $vars Array of query vars to add.
     */
    public function __construct( protected array $vars = array() ) {
        $pfx = 'woocommerce_product';

        \add_filter( "{$pfx}_object_query_args", array( $this, 'add_query_vars' ), 100, 2 );
        \add_filter( "{$pfx}_data_store_cpt_get_products_query", array( $this, 'change_query_vars' ), 100, 2 );
    }

    /**
     * Registers custom query vars for product query.
     *
     * @param  array<string,mixed> $query_vars Query vars.
     * @return array<string,mixed>
     */
    public function add_query_vars( array $query_vars ): array {
        $request_arr = \xwp_req_arr();

        foreach ( \array_keys( $this->vars ) as $var ) {
            $query_vars[ $var ] ??= $request_arr[ $var ] ?? '';
        }

        return $query_vars;
    }

    /**
     * Modifies the query vars to include the custom ones.
     *
     * @param  array<string,mixed> $query Query vars.
     * @param  array<string,mixed> $vars  Requested query vars.
     * @return array<string,mixed>
     */
    public function change_query_vars( array $query, array $vars ): array {
        foreach ( $this->vars as $var => $type ) {
            if ( '' === $vars[ $var ] ) {
                continue;
            }

            match ( true ) {
                'custom' === $type    => $this->set_custom_value( $query, $vars[ $var ], $var, $type ),
                'meta' === $type      => $this->set_meta_value( $query, $vars[ $var ], $var ),
                $this->tax( $type )   => $this->set_taxonomy_value( $query, $vars[ $var ], $var, $type ),
                $this->check( $type ) => $this->{"set_{$type}_value"}( $query, $vars[ $var ], $var, $type ),
                default               => $query[ $var ] = $vars[ $var ],
            };
        }

        return $query;
    }

    /**
     * Sets the custom value.
     *
     * @param  array<string,mixed> $query Query vars.
     * @param  mixed               $value Value to set.
     * @param  string              $key   Query var key.
     * @param  string              $type  Type of the value.
     */
    protected function set_custom_value( array &$query, mixed $value, string $key, string $type ): void {
        /**
         * Filters the custom query var value before it is added to the query.
         *
         * @param  mixed  $value The value to set.
         * @param  string $type  The type of the value.
         * @param  string $key   The key of the value.
         * @param  array  $query The query vars.
         * @return mixed         The modified value.
         *
         * @since 1.3.0
         */
        $value = \apply_filters( 'xwc_product_query_custom_var', $value, $type, $key, $query );

        $query[ $key ] = $value;
    }

    /**
     * Sets the meta value.
     *
     * @param  array<string,mixed> $query Query vars.
     * @param  mixed               $value Value to set.
     * @param  string              $key   Query var key.
     */
    protected function set_meta_value( array &$query, mixed $value, string $key ): void {
        $value = \is_array( $value ) && ( isset( $value['value'] ) || isset( $value['compare'] ) )
            ? \array_merge( $value, array( 'key' => "_{$key}" ) )
            : \array_merge( \compact( 'value' ), array( 'key' => "_{$key}" ) );

        $query['meta_query'] ??= array();
        $query['meta_query'][] = $value;
    }

    /**
     * Sets the taxonomy value.
     *
     * @param array<string,mixed> $query Query vars.
     * @param mixed               $value Value to set.
     * @param string              $key   Query var key.
     * @param string              $type  Taxonomy type.
     */
    protected function set_taxonomy_value( array &$query, mixed $value, string $key, string $type ): void {
        $field = \str_starts_with( $key, 'product_' ) && \str_ends_with( $key, '_id' ) ? 'term_id' : 'slug';

        $query['tax_query'] ??= array();
        $query['tax_query'][] = array(
            'field'    => $field,
            'taxonomy' => $type,
            'terms'    => $value,
        );
    }

    /**
     * Check if a typed set method exists for the given type.
     *
     * @param  string $type The type to check for.
     * @return bool
     */
    protected function check( string $type ): bool {
        return \method_exists( $this, "set_{$type}_value" );
    }

    /**
     * Check if the given type is a taxonomy.
     *
     * @param  string $type The type to check for.
     * @return bool
     */
    protected function tax( string $type ): bool {
        return \taxonomy_exists( $type );
    }
}
