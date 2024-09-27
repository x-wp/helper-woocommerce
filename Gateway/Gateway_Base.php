<?php //phpcs:disable WordPress.WP.I18n.TextDomainMismatch
/**
 * Extended_Payment_Gateway class file.
 *
 * @package Serbian Addons for WooCommerce
 */

namespace XWC\Gateway;

use Automattic\Jetpack\Constants;

/**
 * Extended Payment Gateway which enables easy setting up of payment gateways.
 */
abstract class Gateway_Base extends \WC_Payment_Gateway {
    /**
	 * Whether or not logging is enabled
	 *
	 * @var array<string, bool>
	 */
	private static array $can_log = array();

	/**
	 * Logger instance
	 *
	 * @var \WC_Logger|null
	 */
	private static $logger = null;

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->init_base_props();
        $this->init_form_fields();
        $this->init_settings();

        // phpcs:ignore SlevomatCodingStandard.Functions.RequireMultiLineCall
        \add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        \add_action( 'wc_payment_gateways_initialized', array( $this, 'init_gateway' ), 100, 0 );
    }

    /**
     * Magic getter for our object.
     *
     * @param string $name Property to get.
     * @return mixed
     */
    public function __get( string $name ): mixed {
        $value = $this->$name ?? $this->settings[ $name ] ?? null;

        return 'no' === $value || 'yes' === $value
            ? \wc_string_to_bool( $value )
            : $value;
    }

    /**
     * Get base props needed for gateway functioning.
     *
     * Base props are: id, 'method_title', 'method_description', 'has_fields', 'supports'
     *
     * @return array
     */
    abstract protected function get_base_props(): array;

    /**
     * Get raw form fields.
     *
     * @return array
     */
    abstract protected function get_raw_form_fields(): array;

    /**
     * Initializes base props needed for gateway functioning.
     */
    final protected function init_base_props() {
        $props = $this->get_base_props();
        $props = \wp_parse_args(
            $props,
            array(
                'has_fields'        => false,
                'icon'              => \apply_filters( "{$props['id']}_icon", '' ), // phpcs:ignore
                'order_button_text' => null,
                'supports'          => array( 'products' ),
            ),
        );

        foreach ( $props as $key => $value ) {
            $this->$key = $value;
        }
    }

    /**
     * Get gateway options.
     *
     * @return array
     */
    public function get_options(): array {
        return \array_combine(
            \array_keys( $this->settings ),
            \array_map( fn( $v ) => $this->$v, \array_keys( $this->settings ) ),
        );
    }

    /**
     * {@inheritDoc}
     */
    final public function init_form_fields() {
        $this->form_fields = $this->is_accessing_settings()
            ? $this->process_form_fields()
            : $this->get_raw_form_fields();
    }

    /**
     * Loads settings from the database.
     */
    public function init_settings() {
        parent::init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->settings    = \array_diff_key(
            $this->settings,
            \wp_list_filter( $this->form_fields, array( 'type' => 'title' ) ),
        );

        self::$can_log[ $this->id ] = $this->debug ?? false;
    }

    /**
     * Initializes the gateway.
     *
     * Hooked to `wc_payment_gateways_initialized`.
     */
    public function init_gateway(): void {
        // Noop.
    }

    /**
     * Processes callbacks in form fields.
     *
     * @return array
     */
    final protected function process_form_fields(): array {
        $fields = $this->get_raw_form_fields();

        foreach ( $fields as &$field ) {
            $field = \array_map(
                static fn( $f ) => $f instanceof \Closure ? $f() : $f,
                $field,
            );
        }

        return $fields;
    }

    /**
     * Checks if the gateway is available for use.
     *
     * @return \WP_Error|bool
     */
    public function is_valid_for_use(): \WP_Error|bool {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function admin_options() {
        $is_available = $this->is_valid_for_use();

        if ( ! \is_wp_error( $is_available ) ) {
            return parent::admin_options();
        }

        ?>
        <div class="inline error">
            <p>
                <strong>
                    <?php \esc_html_e( 'Gateway disabled', 'woocommerce' ); ?>
                </strong>:
                <?php echo \esc_html( $is_available->get_error_message() ); ?>
            </p>
        </div>
        <?php
    }

    /**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	final protected function is_accessing_settings() {
        global $wp;
        $rrq = $wp->query_vars['rest_route'] ?? '';
        $req = \wp_parse_args(
            \xwp_array_slice_assoc( \xwp_req_arr(), 'page', 'tab', 'section' ),
            array(
                'page'    => '',
                'section' => '',
                'tab'     => '',
			),
        );

        if ( ! \is_admin() && ! Constants::is_true( 'REST_REQUEST' ) ) {
            return false;
        }

        return ( Constants::is_true( 'REST_REQUEST' ) && \str_contains( $rrq, '/payment_gateways' ) ) ||
        ( \is_admin() && 'wc-settings' === $req['page'] && 'checkout' === $req['tab'] && $this->id === $req['section'] );
	}

    /**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level Optional. Default 'info'. Possible values:
	 *                      emergency|alert|critical|error|warning|notice|info|debug.
     *
     * @return static
	 */
	final public function log( $message, $level = 'info' ): static {
        if ( self::$can_log[ $this->id ] ) {
            $this
            ->logger()
            ->log( $level, $message, array( 'source' => $this->id ) );
        }

		return $this;
	}

    /**
     * Get logger instance.
     *
     * @return \WC_Logger
     */
    final public function logger(): \WC_Logger {
        return self::$logger ??= \wc_get_logger();
    }
}
