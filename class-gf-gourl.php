<?php

add_action( 'wp', array( 'GFGoUrl', 'maybe_process_gourl_page' ), 5 );

GFForms::include_payment_addon_framework();

class GFGoUrl extends GFPaymentAddOn {

	protected $_version = GF_GOURL_VERSION;
	protected $_min_gravityforms_version = '1.9.3';
	protected $_slug = 'gravityforms_gourl';
	protected $_path = 'gravityforms-gourl/gourl.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'Gravity Forms GoUrl Add-On';
	protected $_short_title = 'GoUrl';
	protected $_supports_callbacks = false;
	private $dev_key = 'DEV966GEA58DA77C78DF79G1625442002';
	private $plugin_name = 'wpgw_gravityforms';

	// Members plugin integration
	protected $_capabilities = array( 'gravityforms_gourl', 'gravityforms_gourl_uninstall' );

	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_gourl';
	protected $_capabilities_form_settings = 'gravityforms_gourl';
	protected $_capabilities_uninstall = 'gravityforms_gourl_uninstall';

	// Automatic upgrade enabled
	protected $_enable_rg_autoupgrade = false;

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFGoUrl();
		}

		return self::$_instance;
	}

	private function __clone() {
	} /* do nothing */

	public function init_frontend() {
		parent::init_frontend();

		add_filter( 'gform_disable_post_creation', array( $this, 'delay_post' ), 10, 3 );
		add_filter( 'gform_disable_notification', array( $this, 'delay_notification' ), 10, 4 );
	}


	public function feed_list_no_item_message() {
		global $gourl;
		if( !class_exists( 'gourlclass' ) || !defined( 'GOURL' ) || !is_object( $gourl ) ) {
			return sprintf( esc_html__( 'Please install and activate WordPress plugin %sGoUrl Bitcoin Gateway%s to accept Bitcoin/Altcoin Payments on your website', 'gravityformsgourl' ), '<a target="_blank" href="https://gourl.io/bitcoin-wordpress-plugin.html">', '</a>' );
		} else {
			return parent::feed_list_no_item_message();
		}
	}

	public function feed_settings_fields() {
		global $gourl;
		
		$default_settings = parent::feed_settings_fields();
		$default_settings = parent::remove_field( 'billingInformation', $default_settings );
		
		$currency_choices = array();
		foreach( $gourl->payments() as $key => $value ) {
			$currency_choices[] = array( 'id' => $value, 'label' => $value, 'value' => $key, );
		}

		$fields = array(
			array(
				'name'          => 'gourlGatewayLng',
				'label'         => esc_html__( 'Gateway Language', 'gf-gourl' ),
				'tooltip'       => '<h6>' . esc_html__( 'Gateway Language', 'gf-gourl' ) . '</h6>' . esc_html__( 'Language in which to display the payment gateway.', 'gf-gourl' ),
				'type'          => 'select',
				'choices'       => array(
					array( 'id' => 'english', 	 'label' => 'English',    'value' => 'en' ),
					array( 'id' => 'spanish',	 'label' => 'Spanish', 	  'value' => 'es' ),
					array( 'id' => 'french',     'label' => 'French', 	  'value' => 'fr' ),
					array( 'id' => 'german',     'label' => 'German', 	  'value' => 'de' ),
					array( 'id' => 'dutch',      'label' => 'Dutch', 	  'value' => 'nl' ),
					array( 'id' => 'italian',    'label' => 'Italian', 	  'value' => 'it' ),
					array( 'id' => 'russian',    'label' => 'Russian', 	  'value' => 'ru' ),
					array( 'id' => 'polish',     'label' => 'Polish', 	  'value' => 'pl' ),
					array( 'id' => 'portuguese', 'label' => 'Portuguese', 'value' => 'pt' ),
					array( 'id' => 'persian',    'label' => 'Persian', 	  'value' => 'fa' ),
					array( 'id' => 'korean', 	 'label' => 'Korean',     'value' => 'ko' ),
					array( 'id' => 'japanese',   'label' => 'Japanese',   'value' => 'ja' ),
					array( 'id' => 'indonesian', 'label' => 'Indonesian', 'value' => 'id' ),
					array( 'id' => 'turkish',    'label' => 'Turkish',    'value' => 'tr' ),
					array( 'id' => 'arabic', 	 'label' => 'Arabic', 	  'value' => 'ar' ),
					array( 'id' => 'hindi', 	 'label' => 'Hindi', 	  'value' => 'hi' ),
					array( 'id' => 'simplified chinese', 'label' => 'Simplified Chinese', 'value' => 'cn' ),
					array( 'id' => 'traditional chinese', 'label' => 'Traditional Chinese', 'value' => 'zh' ),
				),
			),
			array(
				'name'          => 'gourlCurrency',
				'label'         => esc_html__( 'Default Currency', 'gf-gourl' ),
				'tooltip'       => '<h6>' . esc_html__( 'Default Currency', 'gf-gourl' ) . '</h6>' . esc_html__( 'Select currency to be charged.', 'gf-gourl' ),
				'type'          => 'select',
				'choices'       => $currency_choices,
			),
		);
		
		$default_settings = parent::add_field_after( 'feedName', $fields, $default_settings );

		$transaction_type = parent::get_field( 'transactionType', $default_settings );

		$choices = $transaction_type['choices'];

		$subscription = array_search( 'subscription', array_column( $choices, 'value' ) );

		if( $subscription !== false ) {
			unset( $choices[$subscription] );
		}

		$transaction_type['choices'] = $choices;
		$default_settings            = $this->replace_field( 'transactionType', $transaction_type, $default_settings );

		$fields = array();

		//Add post fields if form has a post
		$form = $this->get_current_form();
		if ( GFCommon::has_post_field( $form['fields'] ) ) {
			$post_settings = array(
				'name'    => 'post_checkboxes',
				'label'   => esc_html__( 'Posts', 'gf-gourl' ),
				'type'    => 'checkbox',
				'tooltip' => '<h6>' . esc_html__( 'Posts', 'gf-gourl' ) . '</h6>' . esc_html__( 'Enable this option if you would like to only create the post after payment has been received.', 'gf-gourl' ),
				'choices' => array(
					array( 'label' => esc_html__( 'Create post only when payment is received.', 'gf-gourl' ), 'name' => 'delayPost' ),
				),
			);

			$fields[] = $post_settings;
		}

		$default_settings = $this->add_field_after( 'billingInformation', $fields, $default_settings );
		//-----------------------------------------------------------------------------------------

		/**
		 * Filter through the feed settings fields for the PayU feed
		 *
		 * @param array $default_settings The Default feed settings
		 * @param array $form The Form object to filter through
		 */
		return apply_filters( 'gform_gourl_feed_settings_fields', $default_settings, $form );
	}

	public function field_map_title() {
		return esc_html__( 'GoUrl Field', 'gravityformsgourl' );
	}

	public function settings_options( $field, $echo = true ) {
		$html = $this->settings_checkbox( $field, false );

		//--------------------------------------------------------
		//For backwards compatibility.
		ob_start();
		do_action( 'gform_gourl_action_fields', $this->get_current_feed(), $this->get_current_form() );
		$html .= ob_get_clean();
		//--------------------------------------------------------

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function settings_custom( $field, $echo = true ) {

		ob_start();
		?>
		<div id='gf_gourl_custom_settings'>
			<?php
			do_action( 'gform_gourl_add_option_group', $this->get_current_feed(), $this->get_current_form() );
			?>
		</div>

		<script type='text/javascript'>
			jQuery(document).ready(function () {
				jQuery('#gf_gourl_custom_settings label.left_header').css('margin-left', '-200px');
			});
		</script>

		<?php

		$html = ob_get_clean();

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function settings_notifications( $field, $echo = true ) {
		$checkboxes = array(
			'name'    => 'delay_notification',
			'type'    => 'checkboxes',
			'onclick' => 'ToggleNotifications();',
			'choices' => array(
				array(
					'label' => esc_html__( "Send notifications for the 'Form is submitted' event only when payment is received.", 'gravityformsgourl' ),
					'name'  => 'delayNotification',
				),
			)
		);

		$html = $this->settings_checkbox( $checkboxes, false );

		$html .= $this->settings_hidden( array( 'name' => 'selectedNotifications', 'id' => 'selectedNotifications' ), false );

		$form                      = $this->get_current_form();
		$has_delayed_notifications = $this->get_setting( 'delayNotification' );
		ob_start();
		?>
		<ul id="gf_gourl_notification_container" style="padding-left:20px; margin-top:10px; <?php echo $has_delayed_notifications ? '' : 'display:none;' ?>">
			<?php
			if ( ! empty( $form ) && is_array( $form['notifications'] ) ) {
				$selected_notifications = $this->get_setting( 'selectedNotifications' );
				if ( ! is_array( $selected_notifications ) ) {
					$selected_notifications = array();
				}

				//$selected_notifications = empty($selected_notifications) ? array() : json_decode($selected_notifications);

				$notifications = GFCommon::get_notifications( 'form_submission', $form );

				foreach ( $notifications as $notification ) {
					?>
					<li class="gf_gourl_notification">
						<input type="checkbox" class="notification_checkbox" value="<?php echo $notification['id'] ?>" onclick="SaveNotifications();" <?php checked( true, in_array( $notification['id'], $selected_notifications ) ) ?> />
						<label class="inline" for="gf_gourl_selected_notifications"><?php echo $notification['name']; ?></label>
					</li>
				<?php
				}
			}
			?>
		</ul>
		<script type='text/javascript'>
			function SaveNotifications() {
				var notifications = [];
				jQuery('.notification_checkbox').each(function () {
					if (jQuery(this).is(':checked')) {
						notifications.push(jQuery(this).val());
					}
				});
				jQuery('#selectedNotifications').val(jQuery.toJSON(notifications));
			}

			function ToggleNotifications() {

				var container = jQuery('#gf_gourl_notification_container');
				var isChecked = jQuery('#delaynotification').is(':checked');

				if (isChecked) {
					container.slideDown();
					jQuery('.gf_gourl_notification input').prop('checked', true);
				}
				else {
					container.slideUp();
					jQuery('.gf_gourl_notification input').prop('checked', false);
				}

				SaveNotifications();
			}
		</script>
		<?php

		$html .= ob_get_clean();

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function checkbox_input_change_post_status( $choice, $attributes, $value, $tooltip ) {
		$markup = $this->checkbox_input( $choice, $attributes, $value, $tooltip );

		$dropdown_field = array(
			'name'     => 'update_post_action',
			'choices'  => array(
				array( 'label' => '' ),
				array( 'label' => esc_html__( 'Mark Post as Draft', 'gravityformsgourl' ), 'value' => 'draft' ),
				array( 'label' => esc_html__( 'Delete Post', 'gravityformsgourl' ), 'value' => 'delete' ),

			),
			'onChange' => "var checked = jQuery(this).val() ? 'checked' : false; jQuery('#change_post_status').attr('checked', checked);",
		);
		$markup .= '&nbsp;&nbsp;' . $this->settings_select( $dropdown_field, false );

		return $markup;
	}

	/**
	 * Prevent the GFPaymentAddOn version of the options field being added to the feed settings.
	 * 
	 * @return bool
	 */
	public function option_choices() {
		
		return false;
	}


	//------ SENDING TO GOURL PAYMENTBOX -----------//

	public function redirect_url( $feed, $submission_data, $form, $entry ) {

		//Don't process redirect url if request is a GoUrl return
		if ( ! rgempty( 'gf_gourl_return', $_GET ) ) {
			return false;
		}
		
		//Current Currency
		$currency = rgar( $entry, 'currency' );
		$payment_amount = rgar( $submission_data, 'payment_amount' );

		//updating lead's payment_status to Processing
		GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Processing' );
		
		gform_update_meta( $entry['id'], 'payment_amount', $payment_amount );

		$return_url = $this->return_url( $form['id'], $entry['id'] );

		
		if ( $payment_amount <= 0 ) {
			$this->log_debug( __METHOD__ . '(): NOT sending to GoUrl: The price is either zero or the gform_gourl_query filter was used to remove the querystring that is sent to GoUrl.' );

			return '';
		}

		$url = gf_apply_filters( 'gform_gourl_request', $form['id'], $return_url, $form, $entry, $feed, $submission_data );


		$this->log_debug( __METHOD__ . "(): Sending to GoUrl paymentbox: {$url}" );

		return $url;
	}

	public function return_url( $form_id, $lead_id ) {
		$pageURL = GFCommon::is_ssl() ? 'https://' : 'http://';

		$server_port = apply_filters( 'gform_gourl_return_url_port', $_SERVER['SERVER_PORT'] );

		if ( $server_port != '80' ) {
			$pageURL .= $_SERVER['SERVER_NAME'] . ':' . $server_port . $_SERVER['REQUEST_URI'];
		} else {
			$pageURL .= $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
		}

		$ids_query = "ids={$form_id}|{$lead_id}";
		$ids_query .= '&hash=' . wp_hash( $ids_query );

		$url = add_query_arg( 'gf_gourl_return', base64_encode( $ids_query ), $pageURL );

		$query = 'gf_gourl_return=' . base64_encode( $ids_query );
		/**
		 * Filters GoUrl's return URL, which is the URL that users will be sent to after completing the payment on GoUrl's site.
		 * Useful when URL isn't created correctly (could happen on some server configurations using PROXY servers).
		 *
		 * @since 2.4.5
		 *
		 * @param string  $url 	The URL to be filtered.
		 * @param int $form_id	The ID of the form being submitted.
		 * @param int $entry_id	The ID of the entry that was just created.
		 * @param string $query	The query string portion of the URL.
		 */
		return apply_filters( 'gform_gourl_return_url', $url, $form_id, $lead_id, $query  );

	}

	public static function maybe_process_gourl_page() {
		$instance = self::get_instance();

		if ( ! $instance->is_gravityforms_supported() ) {
			return;
		}
		
		if ( $str = rgget( 'gf_gourl_return' ) ) {

			$str = base64_decode( $str );
			$instance->log_debug( __METHOD__ . '(): Paymentbox request received. Starting to process.' );

			parse_str( $str, $query );
			$result = $error = false;

			if ( wp_hash( 'ids=' . $query['ids'] ) != $query['hash'] ) {
				$instance->log_error( __METHOD__ . '(): Paymentbox request invalid. Aborting.' );
				return false;
			} else {
				list( $form_id, $lead_id ) = explode( '|', $query['ids'] );
				$form = GFAPI::get_form( $form_id );
				$entry = GFAPI::get_entry( $lead_id );
				
				if ( ! class_exists( 'GFFormDisplay' ) ) {
					require_once( GFCommon::get_base_path() . '/form_display.php' );
				}

				$confirmation = $instance->gourl_paymentbox( $form, $entry );
                
                if ( is_wp_error( $confirmation ) ) {
					$error = $instance->authorization_error( $confirmation->get_error_message() );
				}
				
			}
            
            if( $error ) {
				$amount = rgpost( 'amount' );
				$entry = GFAPI::get_entry( $lead_id );
				$feed  = $instance->get_payment_feed( $entry );

				$cancel_url = $instance->return_url( $form_id, $lead_id, 'cancel' );

				ob_start();
				?>
				<div class="gform_wrapper">
					<p><?php printf( __( 'Amount: %s', 'gf-payu' ), GFCommon::to_money( $amount, $entry['currency'] ) ); ?></p>
					<div class="validation_error"><?php echo $error['error_message']; ?></div>
					<div class="gform_footer top_label">
          				<form action="<?php echo gform_get_meta( $lead_id, 'redirect_url' ); ?>" method="post">
           					<input class="gform_button button" value="<?php _e( 'Retry Payment', 'gf-payu' ); ?>" type="submit" /> &nbsp;
           					<input class="gform_button button" value="<?php _e( 'Cancel', 'gf-payu' ); ?>" type="button" onclick="location.href='<?php echo $cancel_url; ?>';" />
           					<?php //$instance->print_hidden_fields( gform_get_meta( $lead_id, 'post_data' ) ); ?>
           				</form>
        			</div>
				</div>
				<?php $confirmation = ob_get_clean();
				GFFormDisplay::$submission[ $form_id ] = array( 'is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $entry );
				return;
			}
            
            if ( is_array( $confirmation ) && isset( $confirmation['redirect'] ) ) {
                header( "Location: {$confirmation['redirect']}" );
                exit;
            }

            GFFormDisplay::$submission[ $form_id ] = array( 'is_confirmation' => true, 'confirmation_message' => $confirmation, 'form' => $form, 'lead' => $entry );

		}

	}
		
	public function gourl_paymentbox( $form, $entry ) {
		global $gourl;
		$amount = gform_get_meta( $entry['id'], 'payment_amount' );
		$currency = $entry['currency'];
		$cryptos = $gourl->coin_names();
		//$amount_converted = convert_currency_live( $entry['currency'], 'BTC', $amount_native );
		if( array_key_exists( $currency, $cryptos ) ) {
			$coin = $cryptos[$currency];
		} elseif( $currency != 'USD' ) {
			$amount = gourl_convert_currency( $currency, 'USD', $amount );
			$currency = 'USD';
			$coin = 'bitcoin';
		} else {
			$coin = 'bitcoin';
		}
		
		$user_id = get_current_user_id();
		$feed = $this->get_payment_feed( $entry );
		$lang = $feed['meta']['gourlGatewayLng'];
		$result = $gourl->cryptopayments( $this->plugin_name, $amount, $currency, $entry['id'], '1 DAY', $lang, $coin, $this->dev_key, $user_id, '60' );
        //wp_mail( 'mohsinoffline@gmail.com', 'cryp', "$currency $amount : $coin " . print_r($result,1));
		if( isset( $result['is_paid'] ) && $result['is_paid'] ) {
            unset( $result["html_payment_box"] );
			$this->log_debug( __METHOD__ . '(): Payment already received => ' . print_r( $result, true ) );
			$confirmation = GFFormDisplay::handle_confirmation( $form, $entry, false );
		} elseif( isset( $result['error'] ) && $result['error'] ) {
			$this->log_error( __METHOD__ . '(): Error from GoUrl paymentbox => ' . print_r( $result, true ) );
            return new WP_Error( 'gourl_error', $result['error'] ); 
        } else {
			$confirmation = $result["html_payment_box"];
			unset( $result["html_payment_box"] );
			$this->log_debug( __METHOD__ . '(): Paymentbox result => ' . print_r( $result, true ) );
		}
		return $confirmation;
	}

	public function delay_post( $is_disabled, $form, $entry ) {

		$feed            = $this->get_payment_feed( $entry );
		$submission_data = $this->get_submission_data( $feed, $form, $entry );

		if ( ! $feed || empty( $submission_data['payment_amount'] ) ) {
			return $is_disabled;
		}

		return ! rgempty( 'delayPost', $feed['meta'] );
	}

	public function delay_notification( $is_disabled, $notification, $form, $entry ) {
		if ( rgar( $notification, 'event' ) != 'form_submission' ) {
			return $is_disabled;
		}

		$feed            = $this->get_payment_feed( $entry );
		$submission_data = $this->get_submission_data( $feed, $form, $entry );

		if ( ! $feed || empty( $submission_data['payment_amount'] ) ) {
			return $is_disabled;
		}

		$selected_notifications = is_array( rgar( $feed['meta'], 'selectedNotifications' ) ) ? rgar( $feed['meta'], 'selectedNotifications' ) : array();

		return isset( $feed['meta']['delayNotification'] ) && in_array( $notification['id'], $selected_notifications ) ? true : $is_disabled;
	}
    
    public function init_callback( $user_id, $entry_id, $payment_details, $box_status ) {

		// Ignoring requests that are not this addon's callbacks.
		if ( !is_array( $payment_details ) || !rgar( $payment_details, 'orderID' ) ) {
			return false;
		}

		// Returns either false or an array of data about the callback request which payment add-on will then use
		// to generically process the callback data
		$this->log_debug( __METHOD__ . '(): Initializing callback processing for: ' . $this->_slug );

		$callback_action = $this->callback( $user_id, $entry_id, $payment_details, $box_status );

		$this->log_debug( __METHOD__ . '(): Result from gateway callback => ' . print_r( $callback_action, true ) );

		$result = false;
		if ( is_wp_error( $callback_action ) ) {
			$this->display_callback_error( $callback_action );
		} elseif ( $callback_action && is_array( $callback_action ) && rgar( $callback_action, 'type' ) && ! rgar( $callback_action, 'abort_callback' ) ) {

			$result = $this->gourl_process_callback_action( $callback_action );

			$this->log_debug( __METHOD__ . '(): Result of callback action => ' . print_r( $result, true ) );

			if ( is_wp_error( $result ) ) {
				$this->display_callback_error( $result );
			} elseif ( ! $result ) {
				status_header( 200 );
				//echo 'Callback could not be processed.';
			} else {
				status_header( 200 );
				//echo 'Callback processed successfully.';
			}
		} else {
			status_header( 200 );
			//echo 'Callback bypassed';
		}

		$this->post_callback( $callback_action, $result );

		//die();
	}



	//------- PROCESSING GOURL IPN (Callback) -----------//

	public function callback( $user_id, $entry_id, $payment_details, $box_status ) {

		if ( ! $this->is_gravityforms_supported() ) {
			return false;
		}

		$this->log_debug( __METHOD__ . '(): IPN request received. Box Status: ' . $box_status . '. Starting to process => ' . print_r( $payment_details, true ) );


		//------ Getting entry related to this IPN ----------------------------------------------//
		$entry = GFAPI::get_entry( $entry_id );

		//Ignore orphan IPN messages (ones without an entry)
		if ( ! $entry ) {
			$this->log_error( __METHOD__ . '(): Entry could not be found. Aborting.' );

			return false;
		}
		$this->log_debug( __METHOD__ . '(): Entry has been found => ' . print_r( $entry, true ) );

		if ( $entry['status'] == 'spam' ) {
			$this->log_error( __METHOD__ . '(): Entry is marked as spam. Aborting.' );

			return false;
		}


		//------ Getting feed related to this IPN ------------------------------------------//
		$feed = $this->get_payment_feed( $entry );

		//Ignore IPN messages from forms that are no longer configured with the GoUrl add-on
		if ( ! $feed || ! rgar( $feed, 'is_active' ) ) {
			$this->log_error( __METHOD__ . "(): Form no longer is configured with GoUrl Addon. Form ID: {$entry['form_id']}. Aborting. Feed: " . print_r( $feed, 1 ) );

			return false;
		}
		$this->log_debug( __METHOD__ . "(): Form {$entry['form_id']} is properly configured." );


		//----- Processing IPN ------------------------------------------------------------//
		$this->log_debug( __METHOD__ . '(): Processing IPN...' );
		$action = $this->process_ipn( $feed, $entry, $box_status, $payment_details );
		$this->log_debug( __METHOD__ . '(): IPN processing complete.' );

		if ( rgempty( 'entry_id', $action ) ) {
			return false;
		}

		return $action;

	}

	public function get_payment_feed( $entry, $form = false ) {

		$feed = parent::get_payment_feed( $entry, $form );

		if ( empty( $feed ) && ! empty( $entry['id'] ) ) {
			//looking for feed created by legacy versions
			$feed = $this->get_gourl_feed_by_entry( $entry['id'] );
		}

		$feed = apply_filters( 'gform_gourl_get_payment_feed', $feed, $entry, $form ? $form : GFAPI::get_form( $entry['form_id'] ) );

		return $feed;
	}

	private function get_gourl_feed_by_entry( $entry_id ) {

		$feed_id = gform_get_meta( $entry_id, 'gourl_feed_id' );
		$feed    = $this->get_feed( $feed_id );

		return ! empty( $feed ) ? $feed : false;
	}
    
    private function gourl_process_callback_action( $action ) {
		$this->log_debug( __METHOD__ . '(): Processing callback action.' );
		$action = wp_parse_args(
			$action, array(
				'type'             => false,
				'amount'           => false,
				'transaction_type' => false,
				'transaction_id'   => false,
				'subscription_id'  => false,
				'entry_id'         => false,
				'payment_status'   => false,
				'note'             => false,
			)
		);

		$result = false;

		if ( rgar( $action, 'id' ) && $this->is_duplicate_callback( $action['id'] ) ) {
			return new WP_Error( 'duplicate', sprintf( esc_html__( 'This webhook has already been processed (Event Id: %s)', 'gravityforms' ), $action['id'] ) );
		}

		$entry = GFAPI::get_entry( $action['entry_id'] );
		if ( ! $entry || is_wp_error( $entry ) ) {
			return $result;
		}

		/**
		 * Performs actions before the the payment action callback is processed.
		 *
		 * @since Unknown
		 *
		 * @param array $action The action array.
		 * @param array $entry  The Entry Object.
		 */
		do_action( 'gform_action_pre_payment_callback', $action, $entry );
		if ( has_filter( 'gform_action_pre_payment_callback' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_action_pre_payment_callback.' );
		}

		switch ( $action['type'] ) {
			case 'complete_payment':
				$result = $this->complete_payment( $entry, $action );
				break;
			case 'fail_payment':
				$result = $this->fail_payment( $entry, $action );
				break;
			case 'add_pending_payment':
				$result = $this->add_pending_payment( $entry, $action );
				break;
			default:
				// Handle custom events.
				if ( is_callable( array( $this, rgar( $action, 'callback' ) ) ) ) {
					$result = call_user_func_array( array( $this, $action['callback'] ), array( $entry, $action ) );
				}
				break;
		}

		if ( rgar( $action, 'id' ) && $result ) {
			$this->register_callback( $action['id'], $action['entry_id'] );
		}

		/**
		 * Fires right after the payment callback.
		 *
		 * @since Unknown
		 *
		 * @param array $entry The Entry Object
		 * @param array $action {
		 *     The action performed.
		 *
		 *     @type string $type             The callback action type. Required.
		 *     @type string $transaction_id   The transaction ID to perform the action on. Required if the action is a payment.
		 *     @type string $subscription_id  The subscription ID. Required if this is related to a subscription.
		 *     @type string $amount           The transaction amount. Typically required.
		 *     @type int    $entry_id         The ID of the entry associated with the action. Typically required.
		 *     @type string $transaction_type The transaction type to process this action as. Optional.
		 *     @type string $payment_status   The payment status to set the payment to. Optional.
		 *     @type string $note             The note to associate with this payment action. Optional.
		 * }
		 * @param mixed $result The Result Object.
		 */
		do_action( 'gform_post_payment_callback', $entry, $action, $result );
		if ( has_filter( 'gform_post_payment_callback' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_post_payment_callback.' );
		}

		return $result;
	}

	public function post_callback( $callback_action, $callback_result ) {
		if ( is_wp_error( $callback_action ) || ! $callback_action ) {
			return false;
		}

		//run the necessary hooks
		$entry          = GFAPI::get_entry( $callback_action['entry_id'] );
		$feed           = $this->get_payment_feed( $entry );
		$transaction_id = rgar( $callback_action, 'transaction_id' );
		$amount         = rgar( $callback_action, 'amount' );

		//run gform_gourl_fulfillment only in certain conditions
		if ( rgar( $callback_action, 'ready_to_fulfill' ) && ! rgar( $callback_action, 'abort_callback' ) ) {
			$this->fulfill_order( $entry, $transaction_id, $amount, $feed );
		} else {
			if ( rgar( $callback_action, 'abort_callback' ) ) {
				$this->log_debug( __METHOD__ . '(): Callback processing was aborted. Not fulfilling entry.' );
			} else {
				$this->log_debug( __METHOD__ . '(): Entry is already fulfilled or not ready to be fulfilled, not running gform_gourl_fulfillment hook.' );
			}
		}

		do_action( 'gform_post_payment_status', $feed, $entry, $status, $transaction_id, $amount );
		if ( has_filter( 'gform_post_payment_status' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_post_payment_status.' );
		}

	}

	private function process_ipn( $config, $entry, $box_status, $payment_details ) {
		$this->log_debug( __METHOD__ . "(): Box status: {$box_status} - Payment status: {$payment_details['status']} - Transaction ID: {$payment_details['tx']} - Coin name: {$payment_details['coinname']} - Amount: {$payment_details['amount']} - Payment address: {$payment_details['addr']} - Confirmation: {$payment_details['is_confirmed']}" );

		$action = array();
		
		if( rgar( $payment_details, 'is_paid' ) ) {
			switch ( strtolower( $box_status ) ) {
				case 'cryptobox_updated' :
					if( rgar( $payment_details, 'is_confirmed' ) ) {
						//creates transaction
						$action['id']               = $payment_details['tx'] . '_' . $box_status;
						$action['type']             = 'complete_payment';
						$action['transaction_id']   = $payment_details['tx'];
						$action['amount']           = gform_get_meta( $entry['id'], 'payment_amount' );
						$action['entry_id']         = $entry['id'];
						$action['payment_date']     = gmdate( 'y-m-d H:i:s' );
						$action['payment_method']	= 'GoUrl';
						$action['ready_to_fulfill'] = ! $entry['is_fulfilled'] ? true : false;

						if ( rgar( $payment_details, 'status' ) == 'payment_received_unrecognised' ){
							//create note and transaction
							$this->log_debug( __METHOD__ . '(): Crypto payment amount is unrecognised and does not match original request. Entry will not be marked as Approved. Error: ' . print_r( $payment_details['error'], 1 ) );
							GFPaymentAddOn::add_note( $entry['id'], sprintf( __( 'Payment amount (%s) does not match the request. Entry will not be marked as Approved. Transaction ID: %s', 'gravityformsgourl' ), GFCommon::to_money( $payment_details['amount'], $payment_details['coinlabel'] ), $payment_details['tx'] ) );
							GFPaymentAddOn::insert_transaction( $entry['id'], 'payment', $payment_details['tx'], $action['amount'] );

							$action['abort_callback'] = true;
						}

						return $action;
					}
					
					break;

				case 'cryptobox_newrecord' :
					$action['id']             = $payment_details['tx'] . '_' . $box_status;
					$action['type']           = 'add_pending_payment';
					$action['transaction_id'] = $payment_details['tx'];
					$action['amount']         = gform_get_meta( $entry['id'], 'payment_amount' );
					$action['entry_id']       = $entry['id'];
					$amount_formatted         = GFCommon::to_money( $action['amount'], $entry['currency'] );
					$amount_crypto        	  = GFCommon::to_money( $payment_details['amount'], $payment_details['coinlabel'] );
					$action['note']           = sprintf( __( 'Payment is pending. Amount: %s. Converted to: %s. Transaction ID: %s. Payment Link: %s.', 'gravityformsgourl' ), $amount_formatted, $amount_crypto, $action['transaction_id'], $payment_details['paymentLink'] );

					return $action;
					break;

			}
		}
		
		

	}

	public function modify_post( $post_id, $action ) {

		$result = false;

		if ( ! $post_id ) {
			return $result;
		}

		switch ( $action ) {
			case 'draft':
				$post = get_post( $post_id );
				$post->post_status = 'draft';
				$result = wp_update_post( $post );
				$this->log_debug( __METHOD__ . "(): Set post (#{$post_id}) status to \"draft\"." );
				break;
			case 'delete':
				$result = wp_delete_post( $post_id );
				$this->log_debug( __METHOD__ . "(): Deleted post (#{$post_id})." );
				break;
		}

		return $result;
	}

	public function is_callback_valid() {
		if ( rgget( 'page' ) != 'gf_gourl_ipn' ) {
			return false;
		}

		return true;
	}


	//------- AJAX FUNCTIONS ------------------//

	public function init_ajax() {

		parent::init_ajax();

		add_action( 'wp_ajax_gf_dismiss_gourl_menu', array( $this, 'ajax_dismiss_menu' ) );

	}

	//------- ADMIN FUNCTIONS/HOOKS -----------//

	public function init_admin() {

		parent::init_admin();

		//add actions to allow the payment status to be modified
		add_action( 'gform_payment_status', array( $this, 'admin_edit_payment_status' ), 3, 3 );
		add_action( 'gform_payment_date', array( $this, 'admin_edit_payment_date' ), 3, 3 );
		add_action( 'gform_payment_transaction_id', array( $this, 'admin_edit_payment_transaction_id' ), 3, 3 );
		add_action( 'gform_payment_amount', array( $this, 'admin_edit_payment_amount' ), 3, 3 );
		add_action( 'gform_after_update_entry', array( $this, 'admin_update_payment' ), 4, 2 );


	}

	/**
	 * Add supported notification events.
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return array
	 */
	public function supported_notification_events( $form ) {
		if ( ! $this->has_feed( $form['id'] ) ) {
			return false;
		}

		return array(
				'complete_payment'          => esc_html__( 'Payment Completed', 'gravityformsgourl' ),
				'refund_payment'            => esc_html__( 'Payment Refunded', 'gravityformsgourl' ),
				'fail_payment'              => esc_html__( 'Payment Failed', 'gravityformsgourl' ),
				'add_pending_payment'       => esc_html__( 'Payment Pending', 'gravityformsgourl' ),
		);
	}

	public function admin_edit_payment_status( $payment_status, $form, $entry ) {
		if ( $this->payment_details_editing_disabled( $entry ) ) {
			return $payment_status;
		}

		//create drop down for payment status
		$payment_string = gform_tooltip( 'gourl_edit_payment_status', '', true );
		$payment_string .= '<select id="payment_status" name="payment_status">';
		$payment_string .= '<option value="' . $payment_status . '" selected>' . $payment_status . '</option>';
		$payment_string .= '<option value="Paid">Paid</option>';
		$payment_string .= '</select>';

		return $payment_string;
	}

	public function admin_edit_payment_date( $payment_date, $form, $entry ) {
		if ( $this->payment_details_editing_disabled( $entry ) ) {
			return $payment_date;
		}

		$payment_date = $entry['payment_date'];
		if ( empty( $payment_date ) ) {
			$payment_date = gmdate( 'y-m-d H:i:s' );
		}

		$input = '<input type="text" id="payment_date" name="payment_date" value="' . $payment_date . '">';

		return $input;
	}

	public function admin_edit_payment_transaction_id( $transaction_id, $form, $entry ) {
		if ( $this->payment_details_editing_disabled( $entry ) ) {
			return $transaction_id;
		}

		$input = '<input type="text" id="gourl_transaction_id" name="gourl_transaction_id" value="' . $transaction_id . '">';

		return $input;
	}

	public function admin_edit_payment_amount( $payment_amount, $form, $entry ) {
		if ( $this->payment_details_editing_disabled( $entry ) ) {
			return $payment_amount;
		}

		if ( empty( $payment_amount ) ) {
			$payment_amount = GFCommon::get_order_total( $form, $entry );
		}

		$input = '<input type="text" id="payment_amount" name="payment_amount" class="gform_currency" value="' . $payment_amount . '">';

		return $input;
	}

	public function admin_update_payment( $form, $entry_id ) {
		check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );

		//update payment information in admin, need to use this function so the lead data is updated before displayed in the sidebar info section
		$entry = GFFormsModel::get_lead( $entry_id );

		if ( $this->payment_details_editing_disabled( $entry, 'update' ) ) {
			return;
		}
        
		//get payment fields to update
		$payment_status = rgpost( 'payment_status' );
		//when updating, payment status may not be editable, if no value in post, set to lead payment status
		if ( empty( $payment_status ) ) {
			$payment_status = $entry['payment_status'];
		}

		$payment_amount      = GFCommon::to_number( rgpost( 'payment_amount' ) );
		$payment_transaction = rgpost( 'gourl_transaction_id' );
		$payment_date        = rgpost( 'payment_date' );

		$status_unchanged = $entry['payment_status'] == $payment_status;
		$amount_unchanged = $entry['payment_amount'] == $payment_amount;
		$id_unchanged     = $entry['transaction_id'] == $payment_transaction;
		$date_unchanged   = $entry['payment_date'] == $payment_date;

		if ( $status_unchanged && $amount_unchanged && $id_unchanged && $date_unchanged ) {
			return;
		}

		if ( empty( $payment_date ) ) {
			$payment_date = gmdate( 'y-m-d H:i:s' );
		} else {
			//format date entered by user
			$payment_date = date( 'Y-m-d H:i:s', strtotime( $payment_date ) );
		}

		global $current_user;
		$user_id   = 0;
		$user_name = 'System';
		if ( $current_user && $user_data = get_userdata( $current_user->ID ) ) {
			$user_id   = $current_user->ID;
			$user_name = $user_data->display_name;
		}

		$entry['payment_status'] = $payment_status;
		$entry['payment_amount'] = $payment_amount;
		$entry['payment_date']   = $payment_date;
		$entry['transaction_id'] = $payment_transaction;

		// if payment status does not equal approved/paid or the lead has already been fulfilled, do not continue with fulfillment
		if ( ( $payment_status == 'Approved' || $payment_status == 'Paid' ) && ! $entry['is_fulfilled'] ) {
			$action['id']             = $payment_transaction;
			$action['type']           = 'complete_payment';
			$action['transaction_id'] = $payment_transaction;
			$action['amount']         = $payment_amount;
			$action['entry_id']       = $entry['id'];

			$this->complete_payment( $entry, $action );
			$this->fulfill_order( $entry, $payment_transaction, $payment_amount );
		}
		//update lead, add a note
		GFAPI::update_entry( $entry );
		GFFormsModel::add_note( $entry['id'], $user_id, $user_name, sprintf( esc_html__( 'Payment information was manually updated. Status: %s. Amount: %s. Transaction ID: %s. Date: %s', 'gravityformsgourl' ), $entry['payment_status'], GFCommon::to_money( $entry['payment_amount'], $entry['currency'] ), $payment_transaction, $entry['payment_date'] ) );
	}

	public function fulfill_order( &$entry, $transaction_id, $amount, $feed = null ) {

		if ( ! $feed ) {
			$feed = $this->get_payment_feed( $entry );
		}

		$form = GFFormsModel::get_form_meta( $entry['form_id'] );
		if ( rgars( $feed, 'meta/delayPost' ) ) {
			$this->log_debug( __METHOD__ . '(): Creating post.' );
			$entry['post_id'] = GFFormsModel::create_post( $form, $entry );
			$this->log_debug( __METHOD__ . '(): Post created.' );
		}

		if ( rgars( $feed, 'meta/delayNotification' ) ) {
			//sending delayed notifications
			$notifications = $this->get_notifications_to_send( $form, $feed );
			GFCommon::send_notifications( $notifications, $form, $entry, true, 'form_submission' );
		}

		do_action( 'gform_gourl_fulfillment', $entry, $feed, $transaction_id, $amount );
		if ( has_filter( 'gform_gourl_fulfillment' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_gourl_fulfillment.' );
		}

	}

	/**
	 * Retrieve the IDs of the notifications to be sent.
	 *
	 * @param array $form The form which created the entry being processed.
	 * @param array $feed The feed which processed the entry.
	 *
	 * @return array
	 */
	public function get_notifications_to_send( $form, $feed ) {
		$notifications_to_send  = array();
		$selected_notifications = rgars( $feed, 'meta/selectedNotifications' );

		if ( is_array( $selected_notifications ) ) {
			// Make sure that the notifications being sent belong to the form submission event, just in case the notification event was changed after the feed was configured.
			foreach ( $form['notifications'] as $notification ) {
				if ( rgar( $notification, 'event' ) != 'form_submission' || ! in_array( $notification['id'], $selected_notifications ) ) {
					continue;
				}

				$notifications_to_send[] = $notification['id'];
			}
		}

		return $notifications_to_send;
	}

	private function is_valid_initial_payment_amount( $entry_id, $amount_paid ) {

		//get amount initially sent to gourl
		$amount_sent = gform_get_meta( $entry_id, 'payment_amount' );
		if ( empty( $amount_sent ) ) {
			return true;
		}

		$epsilon    = 0.00001;
		$is_equal   = abs( floatval( $amount_paid ) - floatval( $amount_sent ) ) < $epsilon;
		$is_greater = floatval( $amount_paid ) > floatval( $amount_sent );

		//initial payment is valid if it is equal to or greater than product/subscription amount
		if ( $is_equal || $is_greater ) {
			return true;
		}

		return false;

	}

	public function gourl_fulfillment( $entry, $gourl_config, $transaction_id, $amount ) {
		//no need to do anything for gourl when it runs this function, ignore
		return false;
	}

	/**
	 * Editing of the payment details should only be possible if the entry was processed by GoUrl, if the payment status is Pending or Processing, and the transaction was not a subscription.
	 *
	 * @param array $entry The current entry
	 * @param string $action The entry detail page action, edit or update.
	 *
	 * @return bool
	 */
	public function payment_details_editing_disabled( $entry, $action = 'edit' ) {
		if ( ! $this->is_payment_gateway( $entry['id'] ) ) {
			// Entry was not processed by this add-on, don't allow editing.
			return true;
		}

		$payment_status = rgar( $entry, 'payment_status' );
		if ( $payment_status == 'Approved' || $payment_status == 'Paid' || rgar( $entry, 'transaction_type' ) == 2 ) {
			// Editing not allowed for this entries transaction type or payment status.
			return true;
		}

		if ( $action == 'edit' && rgpost( 'screen_mode' ) == 'edit' ) {
			// Editing is allowed for this entry.
			return false;
		}

		if ( $action == 'update' && rgpost( 'screen_mode' ) == 'view' && rgpost( 'action' ) == 'update' ) {
			// Updating the payment details for this entry is allowed.
			return false;
		}

		// In all other cases editing is not allowed.

		return true;
	}

}