<?php

GFForms::include_feed_addon_framework();

class GFKlaviyoAPI extends GFFeedAddOn {

	protected $_version = GF_KLAVIYO_API_VERSION;
	protected $_min_gravityforms_version = '1.9.16';
	protected $_slug = 'klaviyoaddon';
	protected $_path = 'klaviyoaddon/klaviyoaddon.php';
	protected $_full_path = __FILE__;
	protected $_title = 'Gravity Forms Klaviyo Feed Add-On';
	protected $_short_title = 'Klaviyo';

	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFKlaviyoAPI
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFKlaviyoAPI();
		}

		return self::$_instance;
	}

	/**
	 * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
	 */
	public function init() {

		parent::init();

		$this->add_delayed_payment_support (
			array(
				'option_label' => esc_html__( 'Subscribe contact to service x only when payment is received.', 'klaviyoaddon' )
			)
		);

	}


	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process the feed e.g. subscribe the user to a list.
	 *
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return bool|void
	 */
	public function process_feed( $feed, $entry, $form ) {
		$feedName  = $feed['meta']['feedName'];
		$list_id = $feed['meta']['list'];

		// Retrieve the name => value pairs for all fields mapped in the 'mappedFields' field map.
		$field_map = $this->get_field_map_fields( $feed, 'mappedFields' );

		// Loop through the fields from the field map setting building an array of values to be passed to the third-party service.
		$merge_vars = array();
		foreach ( $field_map as $name => $field_id ) {

			// Get the field value for the specified field id
			$merge_vars[ $name ] = $this->get_field_value( $form, $entry, $field_id );

		}

		// Send the values to the third-party service.
        if ($this->get_plugin_setting('api_key')) {
            $tracker = new Klaviyo($this->get_plugin_setting('api_key'));
            $tracker->track (
                'Active on Site',
                array('$email' => $merge_vars['email'], '$first_name' => $merge_vars['first_name'], '$last_name' => $merge_vars['last_name'])
            // array('Item SKU' => 'ABC123', 'Payment Method' => 'Credit Card'),
            // 1354913220
            );
        }

        if ($this->get_plugin_setting('private_api_key')) {
        	$url = 'https://a.klaviyo.com/api/v1/list/' .$list_id. '/members';

        	wp_remote_post($url,array(
        		'body' => array(
        			'api_key' => $this->get_plugin_setting('private_api_key'),
        			'email' => $merge_vars['email'],
        			'properties' => json_encode(array(
        				'$first_name' => $merge_vars['first_name'],
        				'$last_name' => $merge_vars['last_name']
        			)),
        			'confirm_optin' => 'false'
        		)
        	));
        }
	}

	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------


	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	 public function plugin_settings_fields() {
	 	return array(
	 		array(
	 			'title'  => esc_html__( 'Insert your Klaviyo API keys below to connect. You can find them on your Klaviyo account page.', 'klaviyoaddon' ),
	 			'fields' => array(
	 				array(
	 					'name'    => 'api_key',
	 					'label'   => esc_html__( 'Public API Key', 'klaviyoaddon' ),
	 					'type'    => 'text',
	 					'class'   => 'small',
	 				),
	 				array(
	 					'name'    => 'private_api_key',
	 					'label'   => esc_html__( 'Private API Key', 'klaviyoaddon' ),
	 					'type'    => 'text',
	 					'class'   => 'medium',
	 				),
	 			),
	 		),
	 	);
	 }

	/**
	 * Configures the settings which should be rendered on the feed edit page in the Form Settings > Klaviyo area.
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Klaviyo Feed Settings', 'klaviyoaddon' ),
				'fields' => array(
					array(
						'label'   => esc_html__( 'Feed name', 'klaviyoaddon' ),
						'type'    => 'text',
						'name'    => 'feedName',
						'class'   => 'small',
						'tooltip'  => '<h6>' . esc_html__( 'Name', 'klaviyoaddon' ) . '</h6>' . esc_html__( 'Enter a feed name to uniquely identify this setup.', 'klaviyoaddon' )
					),
					 array(
					 	'name'     => 'list',
					 	'label'    => esc_html__('Klaviyo List', 'klaviyoaddon' ),
					 	'type'     => 'select',
					 	'required' => true,
					 	'choices'  => $this->lists_for_feed_setting(),
					 	'tooltip'  => '<h6>' . esc_html__( 'Klaviyo List', 'klaviyoaddon' ) . '</h6>' . esc_html__( 'Select which Klaviyo list this feed will add contacts to.', 'klaviyoaddon' )
				 	),
					array(
						'name'      => 'mappedFields',
						'label'     => esc_html__( 'Map Fields', 'klaviyoaddon' ),
						'type'      => 'field_map',
						'field_map' => array(
							array(
								'name'       => 'email',
								'label'      => esc_html__( 'Email', 'klaviyoaddon' ),
								'required'   => true,
								'field_type' => array( 'email', 'hidden' ),
							),
							array(
                                'name'     => 'first_name',
                                'label'    => esc_html__( 'First Name', 'klaviyoaddon' ),
                                'required' => true
                            ),
                            array(
                                'name'     => 'last_name',
                                'label'    => esc_html__( 'Last Name', 'klaviyoaddon' ),
                                'required' => true
                            ),
						),
					),
					array(
						'name'           => 'condition',
						'label'          => esc_html__( 'Condition', 'klaviyoaddon' ),
						'type'           => 'feed_condition',
						'checkbox_label' => esc_html__( 'Enable Condition', 'klaviyoaddon' ),
						'instructions'   => esc_html__( 'Process this feed if', 'klaviyoaddon' ),
					),
				),
			),
		);
	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName'  => esc_html__( 'Name', 'klaviyoaddon' ),
			 'list' => esc_html__( 'Klaviyo List', 'klaviyoaddon' ),
		);
	}

	/**
	 * Format the value to be displayed in the mytextbox column.
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_mytextbox( $feed ) {
		return '<b>' . rgars( $feed, 'meta/mytextbox' ) . '</b>';
	}

	/**
	 * Prevent feeds being listed or created if an api key isn't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		// Get the plugin settings.
		$settings = $this->get_plugin_settings();

		// Access a specific setting e.g. an api key
		$key = rgar( $settings, 'apiKey' );

		return true;
	}

	public function lists_for_feed_setting() {
        $lists = array(
            array(
                'label' => '',
                'value' => ''
            )
        );

        /* If Klaviyo API credentials are invalid, return the lists array. */
        //        if ( ! $this->initialize_api() ) {
        //            return $lists;
        //        }

        $private_key = $this->get_plugin_setting('private_api_key');

        if ($private_key) {
			$url = 'https://a.klaviyo.com/api/v1/lists?api_key=' . $private_key;
	       	$response = wp_remote_get($url);

	       	$data = json_decode($response['body']);

            /* Get available Klaviyo lists. */
	        $ac_lists = $data->data;

	        /* Add Klaviyo lists to array and return it. */
	        $lists = array();
            foreach ( $ac_lists as $list ) {
                if ($list->list_type == 'list') {
                    $lists[] = array(
                        'label' => $list->name,
                        'value' => $list->id
                    );
                }
            }
        }

       return $lists;
    }
}