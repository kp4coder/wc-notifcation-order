<?php
if( !class_exists ( 'WPN_Settings' ) ) {

    class WPN_Settings {

        private $wpn_coupon_code = '';

        function __construct(){

            global $wp_query;

            add_action( "wpn_save_settings", array( $this, "wpn_save_settings_func" ), 10 , 1 );

            add_action( 'wpn_send_notification', array( $this, 'wpn_send_notification_func' ), 10, 0 );

            add_filter( 'query_vars', array( $this, 'my_query_vars' ) );

            add_filter( "rewrite_rules_array", array( $this, "wpn_add_rewrite_rules" ), 10, 1 );

            add_action( 'init', array( $this, 'initfunction' ) );

            add_action( 'wp_head', array( $this, 'redirect_checkout' ) );

            // rewrite ^/checkout/(.*)/(.*)$ https://catandog.ussl.co.il/checkout/?add-to-cart=$1&coupon-code=$2 permanent;
            // http://misalb1.sg-host.com/checkout/?add-to-cart=10617,6899,4674&coupon-code=example
            // http://misalb1.sg-host.com/checkout/10617,6899,4674/example
            // http://misalb1.sg-host.com/checkout/10617/example
            // ?add-to-cart=12133&coupon-code=example
            if( isset($_REQUEST['add-to-cart']) && !empty($_REQUEST['add-to-cart']) ){
              
              if( isset($_REQUEST['coupon-code']) && !empty($_REQUEST['coupon-code']) && $_REQUEST['coupon-code'] != 'coupon' ) {
                $this->wpn_coupon_code = isset( $_REQUEST['coupon-code'] ) ? $_REQUEST['coupon-code'] : '';

                add_action('woocommerce_before_cart', array( $this, 'wpn_add_discount' ) );

                add_action('woocommerce_before_checkout_form', array( $this, 'wpn_add_discount') );

                add_filter('woocommerce_cart_totals_coupon_label', array( $this, 'wpn_discount_label'), 10, 2);
              }
                // Fire before the WC_Form_Handler::add_to_cart_action callback.
                add_action( 'wp_loaded', array( $this, 'woocommerce_maybe_add_multiple_products_to_cart'), 15 );
                         
            }

            add_filter( 'woocommerce_checkout_get_value', array( $this, 'populating_checkout_fields' ), 10, 2 );

        }
        
        function populating_checkout_fields ( $value, $input ) {
            
            if( false ) {
                $order_id = '17685';
                $order = wc_get_order( $order_id );
                // Define your checkout fields  values below in this array (keep the ones you need)
                $checkout_fields = array(
                    'billing_first_name'    => $order->get_billing_first_name(),
                    'billing_last_name'     => $order->get_billing_last_name(),
                    'billing_company'       => $order->get_billing_company(),
                    'billing_country'       => $order->get_billing_country(),
                    'billing_address_1'     => $order->get_billing_address_1(),
                    'billing_address_2'     => $order->get_billing_address_2(),
                    'billing_city'          => $order->get_billing_city(),
                    'billing_state'         => $order->get_billing_state(),
                    'billing_postcode'      => $order->get_billing_postcode(),
                    'billing_phone'         => $order->get_billing_phone(),
                    'billing_email'         => $order->get_billing_email(),
                    // 'shipping_first_name'   => 'John',
                    // 'shipping_last_name'    => 'Wick',
                    // 'shipping_company'      => 'Murders & co',
                    // 'shipping_country'      => 'USA',
                    // 'shipping_address_1'    => '7 Random street',
                    // 'shipping_address_2'    => 'Royal suite',
                    // 'shipping_city'         => 'Los Angeles',
                    // 'shipping_state'        => 'California',
                    // 'shipping_postcode'     => '90102',
                    // 'account_password'       => '',
                    'order_comments'        => '',
                );
                foreach( $checkout_fields as $key_field => $field_value ){
                    if( $input == $key_field && ! empty( $field_value ) ){
                        $value = $field_value;
                    }
                }
            }
            return $value;
        }

        function redirect_checkout() {
            global $post;
            if( isset( $post->post_name ) && $post->post_name == 'addtocart' ) {
                echo '<script> location.href="/checkout/"; </script>';
            }
        }

        function wpn_display_settings( ) {
            if( file_exists( WPN_INCLUDES_DIR . "wpn_settings.view.php" ) ) {
                include_once( WPN_INCLUDES_DIR . "wpn_settings.view.php" );
            }
            flush_rewrite_rules(); 
        }

        function wpn_default_setting_option() {
            return array(
                'app_limit'  => array()
            );
        }

        function my_query_vars($vars){
            $vars[] = 'add-to-cart';
            $vars[] = 'coupon-code';
            return $vars;
        }
 
        function wpn_add_rewrite_rules( $Rules ) {        
            $wpn_checkout_page = array( 'addtocart/([^/]+)/([^/]+)/?$' => 'index.php?pagename=addtocart&add-to-cart=$matches[1]&coupon-code=$matches[2]' );
            $Rules = $wpn_checkout_page + $Rules;      
            return $Rules;
        }
        
        function wpn_create_coupon( $wpn_wc_product_id, $discount_amount ) {
            
            // Create a coupon programatically
            $coupon_code = 'wpn_' . md5( $wpn_wc_product_id ); // Code

            $coupon = array(
                'post_title' => $coupon_code,
                'post_content' => '',
                'post_status' => 'publish',
                'post_author' => 1,
                'post_type' => 'shop_coupon'
            );

            $new_coupon_id = wp_insert_post( $coupon );

            // Add meta
            update_post_meta( $new_coupon_id, 'discount_type', 'fixed_cart' ); // Type: fixed_cart, percent, fixed_product, percent_product
            update_post_meta( $new_coupon_id, 'coupon_amount', $discount_amount );
            update_post_meta( $new_coupon_id, 'individual_use', 'no' );
            update_post_meta( $new_coupon_id, 'product_ids', $wpn_wc_product_id );
            update_post_meta( $new_coupon_id, 'exclude_product_ids', '' );
            update_post_meta( $new_coupon_id, 'usage_limit', '' );
            update_post_meta( $new_coupon_id, 'expiry_date', '' );
            update_post_meta( $new_coupon_id, 'apply_before_tax', 'yes' );
            update_post_meta( $new_coupon_id, 'free_shipping', 'no' );
            return $coupon_code;
        }

        function wpn_save_settings_func( $params = array() ) {
            global $wpdb, $wpn;
            if( isset( $params['wpn_setting'] ) && $params['wpn_setting'] != '') {
                // $wpn_setting = $params['wpn_setting'];
                // unset( $params['wpn_setting'] );
                // unset( $params['wpn_setting_save'] );
                
                // update_option('wpn_setting', $params);
                $category = array('מזון יבש כלב' , 'מזון יבש חתול' , 'חול לחתולים' , 'הדברה' );

                $customer_orders = array();
                $row = 1;
                if (($handle = fopen($_FILES['orderCSV']['tmp_name'], "r")) !== FALSE) {
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        
                        if( in_array($data[4], $category) ) {
                            

                            $dateArr = explode( '/', $data[0] );
                            $date = strtotime( $dateArr[1].'/'.$dateArr[0].'/'.$dateArr[2] );
                            $phone1 = $data[9];
                            $phone2 = $data[10];
                            $phone3 = $data[11];
                            $phone4 = $data[12];

                            $customers = array_keys($customer_orders);
                            $phone = '';
                            if( in_array($phone1, $customers) ) {
                                $phone = $phone1;
                            } elseif ( in_array($phone2, $customers) ) {
                                $phone = $phone2;
                            } elseif ( in_array($phone3, $customers) ) {
                                $phone = $phone3;
                            } elseif ( in_array($phone4, $customers) ) {
                                $phone = $phone4;
                            } 

                            if( !empty($phone) ) {
                                $orders = $customer_orders[$phone]['orders'];
                                $orders[] = array(
                                        'timestamp'     => $date
                                        , 'date'        => $data[0]
                                        , 'Reference'   => $data[1]
                                        , 'Product'     => $data[2]
                                        , 'Barcode'     => $data[3]
                                        , 'Category'    => $data[4]
                                        , 'Quantity'    => $data[5]
                                        , 'PriceUnit'   => $data[6]
                                        , 'Priceperline'=> $data[7]
                                );
                                $customer_orders[$phone]['orders'] = $orders;
                            } else {
                                $phone = empty( $phone ) ? $phone1 : $phone;
                                $phone = empty( $phone ) ? $phone2 : $phone;
                                $phone = empty( $phone ) ? $phone3 : $phone;
                                $phone = empty( $phone ) ? $phone4 : $phone;
                                
                                $orders = array(
                                    array(
                                        'timestamp'     => $date
                                        , 'date'        => $data[0]
                                        , 'Reference'   => $data[1]
                                        , 'Product'     => $data[2]
                                        , 'Barcode'     => $data[3]
                                        , 'Category'    => $data[4]
                                        , 'Quantity'    => $data[5]
                                        , 'PriceUnit'   => $data[6]
                                        , 'Priceperline'=> $data[7]
                                    )
                                );

                                $customer_orders[$phone] = array(
                                    'CustomerName'      => $data[8]
                                    , 'Phone1'          => $data[9]
                                    , 'Phone2'          => $data[10]
                                    , 'Phone3'          => $data[11]
                                    , 'Phone4'          => $data[12]
                                    , 'Email'           => $data[13]
                                    , 'City'            => $data[14]
                                    , 'Street'          => $data[15]
                                    , 'Home'            => $data[16]
                                    , 'Apartment'       => $data[17]
                                    , 'Floor'           => $data[18]
                                    , 'Entrance'        => $data[19]
                                    , 'ZipCode'         => $data[20]
                                    , 'InterestedinSMS' => $data[21]
                                    , 'fromKenyaDays'   => $data[22]
                                    , 'discount'        => $data[23]
                                    , 'isBlock'         => $data[24]
                                    , 'orders'          => $orders
                                );
                            }
                        }
                    }
                    fclose($handle);
                }


                $content.= '<br/><br/><b>Records which are in below category.</b>';
                $content.= '<ul style="list-style-type: decimal;">';
                $content.= '<li> מזון יבש כלב </li>';
                $content.= '<li> מזון יבש חתול </li>';
                $content.= '<li> חול לחתולים </li>';
                $content.= '<li> הדברה </li>';
                $content.= '</ul>'; 
               
                $content.= '<table class="wp-list-table widefat fixed striped table-view-list pages">';

                $content.= '<tr>';
                $content.= '<td>phone</td>';
                $content.= '<td>CustomerName</td>';
                $content.= '<td>Phone1</td>';
                $content.= '<td>Phone2</td>';
                $content.= '<td>Phone3</td>';
                $content.= '<td>Phone4</td>';
                $content.= '<td>Email</td>';
                $content.= '<td>discount</td>';
                $content.= '<td>isBlock</td>';
                $content.= '</tr>';

                if( !empty($customer_orders) ) {
                    foreach ($customer_orders as $phone => $customer) {

                        // show customer
                        $content.= '<tr>';
                        $content.= '<td>'.$phone.'</td>';
                        $content.= '<td>'.$customer['CustomerName'].'</td>';
                        $content.= '<td>'.$customer['Phone1'].'</td>';
                        $content.= '<td>'.$customer['Phone2'].'</td>';
                        $content.= '<td>'.$customer['Phone3'].'</td>';
                        $content.= '<td>'.$customer['Phone4'].'</td>';
                        $content.= '<td>'.$customer['Email'].'</td>';
                        $content.= '<td>'.$customer['discount'].'</td>';
                        $content.= '<td>'.( ($customer['isBlock'] == 'כן' ) ? 1 : 0 ).'</td>';
                        $content.= '</tr>';

                        if( count( $customer['orders'] ) > 2 ) {
                            $timestamps = array();
                            $product_name = '';
                            $product_ids = array();
                            foreach ( $customer['orders'] as $order ) {
                                $products = '';
                                $timestamps[] = $order['timestamp'];
                                $product_name = $order['Product'];

                                if( isset($order['Barcode']) && !empty($order['Barcode']) ) {
                                    $barcode = $order['Barcode'];
                                    
                                    $query = new WC_Product_Query( array(
                                        'sku' => $barcode,
                                    ) );
                                    $products = $query->get_products();
                                    if( !empty($products) ) {
                                        $product = $products[0];
                                        if( $product->is_type( 'variable' ) ){
                                            $args = array(
                                                'post_type'     => 'product_variation',
                                                'post_status'   => array( 'private', 'publish' ),
                                                'numberposts'   => -1,
                                                'orderby'       => 'menu_order',
                                                'order'         => 'asc',
                                                'post_parent'   => $product->get_id() // get parent post-ID
                                            );
                                            $variations = get_posts( $args );
                                            
                                            if( !empty($variations) ) {
                                                $product_ids[] = $variations[0]->ID;
                                            }
                                        } else {
                                            $product_ids[] = $product->get_id();
                                        }
                                    }
                                    $product = reset( $products );
                                   
                                    // $product_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1", $barcode ) ); 
                                    // if ( $product_id ) {
                                    //     $product_ids[] = $product_id;
                                    // }
                                }
                                // $query = $wpdb->prepare( 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_title = %s AND post_type = \'product\' AND post_status = \'publish\'',$product_name );
                                // $wpdb->query( $query );
                                // if ( $wpdb->num_rows ) {
                                //     $product_ids[] = $wpdb->get_var( $query );
                                // }
                            }

                            if( !empty($product_ids) ) {

                                // $product_count = array_count_values($product_ids);
                                // $max_product = max($product_count);
                                // $product_id = array_search($max_product, $product_count);
                                $product_ids = array_unique( $product_ids );
                                $product_id = implode(',', $product_ids);

                                $min_date = min($timestamps);
                                $max_date = max($timestamps);
                                $days = abs( round( ($max_date - $min_date)  / 86400) );
                                $avg_day = round( $days / count( $customer['orders'] ) );
                                $discount_amount = ( isset( $customer['discount'] ) && !empty($customer['discount']) ) ? $customer['discount'] : 0;
                                $wpn_wc_product_id = $product_id;
                                $wpn_wc_coupon = ( $discount_amount > 0 ) ? $this->wpn_create_coupon( $wpn_wc_product_id, $discount_amount ) : '0';

                                $isBlock = ($customer['isBlock'] == 'כן' ) ? 1 : 0;

                                $user_query = 'SELECT * FROM ' . $wpn->tbl_wpn_order_notification . ' WHERE wpn_phone = "'.$phone.'"';
                                $user_data = $wpdb->get_results( $user_query, ARRAY_A );
                                if( $user_data ) {

                                    // Update exists customer
                                    foreach ($user_data as $user) {
                                        $values = array( 
                                              'wpn_name' => $customer['CustomerName']
                                            , 'wpn_first_order' => date('Y-m-d', $min_date)
                                            , 'wpn_last_order' => date('Y-m-d', $max_date)
                                            , 'wpn_date_diff' => $days
                                            , 'wpn_total_order' => count( $customer['orders'] )
                                            , 'wpn_next_expect_order_date' => date('Y-m-d', strtotime('+'.$avg_day.' days', $max_date))
                                            , 'wpn_product_name' => $product_name
                                            , 'wpn_discount_amount' => $discount_amount
                                            , 'wpn_wc_product_id' => $wpn_wc_product_id
                                            , 'wpn_wc_coupon' => $wpn_wc_coupon
                                            , 'wpn_is_block' => $isBlock
                                        );
                                        $where = array( 'wpn_phone' => $phone );
                                        $wpdb->update( $wpn->tbl_wpn_order_notification, $values, $where );
                                    }

                                    $content.= '<tr>';
                                    $content.= '<td colspan="9">';
                                        $content.= '<table class="wp-list-table widefat fixed striped table-view-list pages">';
                                        $content.= '<tr>';
                                        $content.= '<th>Date</th>';
                                        $content.= '<th>Reference</th>';
                                        $content.= '<th>Product</th>';
                                        $content.= '<th>Category</th>';
                                        $content.= '</tr>';
                                        if( count( $customer['orders'] ) > 0 ) {
                                            foreach ( $customer['orders'] as $order ) {
                                                $content.= '<tr>';
                                                $content.= '<td>'.date('d-m-Y', $order['timestamp']).'</td>';
                                                $content.= '<td>'.$order['Reference'].'</td>';
                                                $content.= '<td>'.$order['Product'].'</td>';
                                                $content.= '<td>'.$order['Category'].'</td>';
                                                $content.= '</tr>';
                                            }
                                        }
                                        $content.= '<tr>';
                                        $content.= '<td colspan="3" style="color:green">ההתראה עודכנה בהצלחה</td>';
                                        $content.= '</tr>';
                                        $content.= '</table>';
                                    $content.= '</td>';
                                    $content.= '<tr>';
                                } else {

                                    // Insert new customer
                                    $values = array( 
                                          'wpn_name' => $customer['CustomerName']
                                        , 'wpn_phone' => $phone
                                        , 'wpn_first_order' => date('Y-m-d', $min_date)
                                        , 'wpn_last_order' => date('Y-m-d', $max_date)
                                        , 'wpn_date_diff' => $days
                                        , 'wpn_total_order' => count( $customer['orders'] )
                                        , 'wpn_next_expect_order_date' => date('Y-m-d', strtotime('+'.$avg_day.' days', $max_date))
                                        , 'wpn_product_name' => $product_name
                                        , 'wpn_discount_amount' => $discount_amount
                                        , 'wpn_wc_product_id' => $wpn_wc_product_id
                                        , 'wpn_wc_coupon' => $wpn_wc_coupon
                                        , 'wpn_is_block' => $isBlock
                                    );                                       
                                    $wpdb->insert( $wpn->tbl_wpn_order_notification, $values);


                                    $content.= '<tr>';
                                    $content.= '<td colspan="9">';
                                        $content.= '<table class="wp-list-table widefat fixed striped table-view-list pages">';
                                        $content.= '<tr>';
                                        $content.= '<th>Date</th>';
                                        $content.= '<th>Reference</th>';
                                        $content.= '<th>Product</th>';
                                        $content.= '<th>Category</th>';
                                        $content.= '</tr>';
                                        if( count( $customer['orders'] ) > 0 ) {
                                            foreach ( $customer['orders'] as $order ) {
                                                $content.= '<tr>';
                                                $content.= '<td>'.date('d-m-Y', $order['timestamp']).'</td>';
                                                $content.= '<td>'.$order['Reference'].'</td>';
                                                $content.= '<td>'.$order['Product'].'</td>';
                                                $content.= '<td>'.$order['Category'].'</td>';
                                                $content.= '</tr>';
                                            }
                                        }
                                        $content.= '<tr>';
                                        $content.= '<td colspan="3" style="color:green">Notification added successfully</td>';
                                        $content.= '</tr>';
                                        $content.= '</table>';
                                    $content.= '</td>';
                                    $content.= '<tr>';
                                }
                            } else {
                                $content.= '<tr>';
                                $content.= '<td colspan="9">';
                                    $content.= '<table class="wp-list-table widefat fixed striped table-view-list pages">';
                                    $content.= '<tr>';
                                    $content.= '<th>Date</th>';
                                    $content.= '<th>Reference</th>';
                                    $content.= '<th>Product</th>';
                                    $content.= '<th>Category</th>';
                                    $content.= '</tr>';
                                    if( count( $customer['orders'] ) > 0 ) {
                                        foreach ( $customer['orders'] as $order ) {
                                            $content.= '<tr>';
                                            $content.= '<td>'.date('d-m-Y', $order['timestamp']).'</td>';
                                            $content.= '<td>'.$order['Reference'].'</td>';
                                            $content.= '<td>'.$order['Product'].'</td>';
                                            $content.= '<td>'.$order['Category'].'</td>';
                                            $content.= '</tr>';
                                        }
                                    }
                                    $content.= '<tr>';
                                    $content.= '<td colspan="3" style="color:red">Not inserted because products not found in website.</td>';
                                    $content.= '</tr>';
                                    $content.= '</table>';
                                $content.= '</td>';
                                $content.= '<tr>';
                            }
                        } else {
                            $content.= '<tr>';
                            $content.= '<td colspan="9">';
                                $content.= '<table class="wp-list-table widefat fixed striped table-view-list pages">';
                                $content.= '<tr>';
                                $content.= '<th>Date</th>';
                                $content.= '<th>Reference</th>';
                                $content.= '<th>Product</th>';
                                $content.= '<th>Category</th>';
                                $content.= '</tr>';
                                if( count( $customer['orders'] ) > 0 ) {
                                    foreach ( $customer['orders'] as $order ) {
                                        $content.= '<tr>';
                                        $content.= '<td>'.date('d-m-Y', $order['timestamp']).'</td>';
                                        $content.= '<td>'.$order['Reference'].'</td>';
                                        $content.= '<td>'.$order['Product'].'</td>';
                                        $content.= '<td>'.$order['Category'].'</td>';
                                        $content.= '</tr>';
                                    }
                                }
                                $content.= '<tr>';
                                $content.= '<td colspan="3" style="color:red">ללקוח יש פחות משתי הזמנות, לא עודכנה התראה .</td>';
                                $content.= '</tr>';
                                $content.= '</table>';
                            $content.= '</td>';
                            $content.= '<tr>';
                        }
                    }
                }
                $content.= '</table>';

                echo $content;
                $_SESSION['wpn_msg_status'] = true;
                $_SESSION['wpn_msg'] = 'CSV imported successfully.';

            }
        }

        function wpn_get_settings_func( ) {
            $wpn_default_general_option = $this->wpn_default_setting_option();
            $wpn_setting_option = get_option( 'wpn_setting' );
            return shortcode_atts( $wpn_default_general_option, $wpn_setting_option );
        }

        function initfunction() {

            // global $wp_rewrite;
            // $wp_rewrite->flush_rules();
            // echo "<pre>";
            // print_r($wp_rewrite);
            // echo "</pre>";
            // die;
            
            if( isset($_REQUEST['test']) && !empty($_REQUEST['test']) && $_REQUEST['test'] == 'cron' ) {
                $this->wpn_send_notification_func();
            }
        }

        function wpn_send_notification_func() {
            global $wpdb, $wpn;
            $current = date('Y-m-d');
            $date = date('Y-m-d', strtotime('+4 days') );
            $user_query = 'SELECT * FROM ' . $wpn->tbl_wpn_order_notification . ' WHERE wpn_is_block = 0 AND wpn_next_expect_order_date="'.$date.'"';
            $user_data = $wpdb->get_results( $user_query, ARRAY_A );

            if( $user_data ) {
                foreach ($user_data as $user) {
                    if( isset($user['wpn_phone']) && !empty($user['wpn_phone']) ) {
                        $sToPhone = $user['wpn_phone'];

                        $args  = array(
                            'orderby'        => 'date',
                            'order'          => 'DESC',
                            'meta_key'       => '_billing_phone',
                            'meta_value'     => $sToPhone,
                            'meta_compare'   => '=',
                            'post_type'      => 'shop_order',
                            'post_status'    => 'any',
                            'posts_per_page' => '1'
                        );                        
                        $last_order = new WP_Query( $args );

                        $product_ids = array();
                        if( $last_order->posts ) {
                            foreach ($last_order->posts as $order) {
                                $order = wc_get_order( $order->ID );
                                foreach ( $order->get_items() as $item_id => $item ) {
                                    $product_ids[] = $item->get_product_id();
                                }
                            }
                        }

                        if( !empty($product_ids) ) {
                            $wpn_wc_product_id = implode(',', $product_ids);
                        } else {
                            $wpn_wc_product_id = ( isset($user['wpn_wc_product_id']) && !empty( $user['wpn_wc_product_id'] ) ) ? $user['wpn_wc_product_id'] : '';
                        }

                        $wpn_name = ( isset($user['wpn_name']) && !empty( $user['wpn_name'] ) ) ? $user['wpn_name'] : '';
                        $wpn_id = ( isset($user['wpn_id']) && !empty( $user['wpn_id'] ) ) ? $user['wpn_id'] : '';
                        $wpn_wc_coupon = ( isset($user['wpn_wc_coupon']) && !empty( $user['wpn_wc_coupon'] ) ) ? $user['wpn_wc_coupon'] : 'coupon';
                        $sm_username = 'eliran98887@walla.com'; // this is the username 
                        $sm_password = get_option( 'mesereser_password', '' ); // API password field is set in settings > general
                        $iUserID = '8473';
                        $sFromName = 'Catandog';    
                        $product_link = get_permalink( get_page_by_path( 'addtocart' ) ) . $wpn_wc_product_id . '/' . $wpn_wc_coupon;
                        $message = 'החתול והכלב חנות מזון לכלבים וחתולים רחוב נגבה 36 ת"א

היי '.$wpn_name.'
ע"פ רישום במערכת המזון לכלב / חתול יגמר בעוד מספר ימים בודדים
הכנו לך את הזמנתך הקבועה בקישור התחתון המופיעה למטה להזמנה
 ' . $product_link;
                        $sMessageBody = strip_tags( $message );
                        $sMessageBody = $message;

                        $post_field = '<?xml version="1.0" encoding="utf-8"?>
                        <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
                          <soap:Body>
                            <SendSingleSmsMessage xmlns="http://messagingsystem.co.il/">
                              <oLogin>
                                <UserName>'.$sm_username.'</UserName>
                                <Password>'.$sm_password.'</Password>
                              </oLogin>
                              <iUserID>'.$iUserID.'</iUserID>
                              <sMessageBody>'.$sMessageBody.'</sMessageBody>
                              <sToPhone>'.$sToPhone.'</sToPhone>
                              <sFromName>'.$sFromName.'</sFromName>
                            </SendSingleSmsMessage>
                          </soap:Body>
                        </soap:Envelope>';
                        
                        $curl = curl_init();
                        curl_setopt_array($curl, array(
                          CURLOPT_URL => 'https://heb.mesereser.com/Services/Services.asmx',
                          CURLOPT_RETURNTRANSFER => true,
                          CURLOPT_ENCODING => '',
                          CURLOPT_MAXREDIRS => 10,
                          CURLOPT_TIMEOUT => 0,
                          CURLOPT_FOLLOWLOCATION => true,
                          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                          CURLOPT_CUSTOMREQUEST => 'POST',
                          CURLOPT_POSTFIELDS => $post_field,
                          CURLOPT_HTTPHEADER => array(
                            'Host: heb.mesereser.com',
                            'Content-Type: text/xml; charset=utf-8',
                            'Content-Length: '. strlen($post_field),
                            'SOAPAction: "http://messagingsystem.co.il/SendSingleSmsMessage"'
                          ),
                        ));

                        $response = curl_exec($curl);

                        curl_close($curl);

                        echo $sToPhone;
                        echo "<br/>";
                        echo $sMessageBody;
                        echo "<br/>";
                        echo $response;
                        echo "<br/>";
                        echo "<br/>";
                        echo "<br/>";


                        /* $curl = curl_init();
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => 'https://ns.mesereser.com/Services/Services.asmx?type=unicode',
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_ENCODING => "utf-8",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 30,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "POST",
                            CURLOPT_POSTFIELDS => "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n <soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">\r\n  <soap:Body>\r\n    
                                    <SendSingleSmsMessage xmlns=\"http://messagingsystem.co.il/\">\r\n      <oLogin>\r\n        <UserName>" . $sm_username . "</UserName>\r\n        <Password>" . $sm_password . "</Password>\r\n      </oLogin>\r\n      <iUserID>" . $iUserID . "</iUserID>\r\n      <sMessageBody>" . $sMessageBody . "</sMessageBody>\r\n      <sToPhone>" . $sToPhone . "</sToPhone>\r\n      <sFromName>" . $sFromName . "</sFromName>\r\n    </SendSingleSmsMessage>\r\n  </soap:Body>\r\n</soap:Envelope>\r\n",
                            CURLOPT_HTTPHEADER => array(
                                "cache-control: no-cache",
                                "content-type: text/xml; charset=utf-8",
                            ),
                        ));
         
                        $response = curl_exec($curl);
                        $err = curl_error($curl);

                        curl_close($curl);

                        if ($err) {
                            echo "cURL Error #:" . $err;
                        } else {

                            $clean_xml = str_ireplace(['SOAP-ENV:', 'SOAP:'], '', $response);
                            $xml = json_decode( json_encode( simplexml_load_string($clean_xml) ), true);

                            /* if( isset( $xml['Body']['SendSingleSmsMessageResponse']['SendSingleSmsMessageResult']['Result'] ) 
                            && $xml['Body']['SendSingleSmsMessageResponse']['SendSingleSmsMessageResult']['Result'] == 'Success' ) {

                                $otp_val = '';
                                $return['status'] = 'success';
                                $return['msgid'] = base64_encode($otp_val);
                                $return['messageID'] = isset($xml['Body']['SendSingleSmsMessageResponse']['iMessageID']) ? $xml['Body']['SendSingleSmsMessageResponse']['iMessageID'] : '';
                                $return['msg'] = __('OTP has been sent.', RLB_txt_domain);

                            } else if( isset( $xml['Body']['SendSingleSmsMessageResponse']['SendSingleSmsMessageResult']['Result'] ) 
                            && $xml['Body']['SendSingleSmsMessageResponse']['SendSingleSmsMessageResult']['Result'] == 'ApplicationError' ) {

                                $return['status'] = 'failed';
                                $return['messageID'] = isset($xml['Body']['SendSingleSmsMessageResponse']['iMessageID']) ? $xml['Body']['SendSingleSmsMessageResponse']['iMessageID'] : '';
                                $return['msg'] = __('Please enter correct phone no.', RLB_txt_domain);

                            } else {

                                $return['status'] = 'failed';
                                $return['messageID'] = isset($xml['Body']['SendSingleSmsMessageResponse']['iMessageID']) ? $xml['Body']['SendSingleSmsMessageResponse']['iMessageID'] : '';
                                $return['msg'] = isset( $xml['Body']['SendSingleSmsMessageResponse']['SendSingleSmsMessageResult']['Description'] ) ? $xml['Body']['SendSingleSmsMessageResponse']['SendSingleSmsMessageResult']['Description'] : '';

                            }
                        } */
                    }
                }
            }die;
        }

        function wpn_add_discount() {
            if (is_admin() && !defined('DOING_AJAX')) {
                return;
            }
            
            if (WC()->cart->get_subtotal() > 0) {
                // add discount, if not added already
                if (!in_array($this->wpn_coupon_code, WC()->cart->get_applied_coupons())) {
                    WC()->cart->apply_coupon($this->wpn_coupon_code);
                }
            } else {
                // remove discount if it was previously added
                // WC()->cart->remove_coupon($this->wpn_coupon_code);
            }
        }

        function wpn_discount_label($label, $coupon) {
            if ($coupon->code == $this->wpn_coupon_code) {
                return __('Custom discount', 'txtdomain');
            }
            return $label;
        }

        function woocommerce_maybe_add_multiple_products_to_cart() {

            
            // Make sure WC is installed, and add-to-cart qauery arg exists, and contains at least one comma.
            if ( ! class_exists( 'WC_Form_Handler' ) || empty( $_REQUEST['add-to-cart'] ) || false === strpos( $_REQUEST['add-to-cart'], ',' ) ) {
                return;
            }

            // Remove WooCommerce's hook, as it's useless (doesn't handle multiple products).
            remove_action( 'wp_loaded', array( 'WC_Form_Handler', 'add_to_cart_action' ), 20 );

            $product_ids = explode( ',', $_REQUEST['add-to-cart'] );
            $count       = count( $product_ids );
            $number      = 0;

            foreach ( $product_ids as $product_id ) {
                $number++;

                $product_id        = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $product_id ) );
                $was_added_to_cart = false;
                $adding_to_cart    = wc_get_product( $product_id );

                if ( ! $adding_to_cart ) {
                    continue;
                }

                $add_to_cart_handler = apply_filters( 'woocommerce_add_to_cart_handler', $adding_to_cart->product_type, $adding_to_cart );

                /*
                 * Sorry.. if you want non-simple products, you're on your own.
                 *
                 * Related: WooCommerce has set the following methods as private:
                 * WC_Form_Handler::add_to_cart_handler_variable(),
                 * WC_Form_Handler::add_to_cart_handler_grouped(),
                 * WC_Form_Handler::add_to_cart_handler_simple()
                 *
                 * Why you gotta be like that WooCommerce?
                 */
                if ( 'simple' == $add_to_cart_handler ) {
                    // For now, quantity applies to all products.. This could be changed easily enough, but I didn't need this feature.
                    $quantity          = empty( $_REQUEST['quantity'] ) ? 1 : wc_stock_amount( $_REQUEST['quantity'] );
                    $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );

                    if ( $passed_validation && false !== WC()->cart->add_to_cart( $product_id, $quantity ) ) {
                        wc_add_to_cart_message( array( $product_id => $quantity ), true );
                    }
                } else if( 'variation' == $add_to_cart_handler ) {

                    $parent_product_id = $adding_to_cart->get_parent_id();

                    // For now, quantity applies to all products.. This could be changed easily enough, but I didn't need this feature.
                    $quantity          = empty( $_REQUEST['quantity'] ) ? 1 : wc_stock_amount( $_REQUEST['quantity'] );
                    $passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );

                    if ( $passed_validation && false !== WC()->cart->add_to_cart( $parent_product_id, $quantity, $product_id ) ) {
                        wc_add_to_cart_message( array( $product_id => $quantity ), true );
                    }
                } else {
                    continue;
                }

                // if ( $number < $count ) {
                //     // Ok, final item, let's send it back to woocommerce's add_to_cart_action method for handling.
                //     $_REQUEST['add-to-cart'] = $product_id;

                //     return WC_Form_Handler::add_to_cart_action();
                // }
            }
        }

    }

    global $wpn_settings;
    $wpn_settings = new WPN_Settings();
}

add_filter('admin_init', 'register_order_notification_settings_fields');
function register_order_notification_settings_fields()
{
    register_setting('general', 'mesereser_password', 'esc_attr');
    add_settings_field('mesereser_password', '<label for="mesereser_password">Mesereser Password</label>' , 'general_settings_order_notification_fields_html', 'general');
}

function general_settings_order_notification_fields_html()
{
    $mesereser_password = get_option( 'mesereser_password', '' );
    echo '<input type="text" id="mesereser_password" name="mesereser_password" value="' . $mesereser_password . '" />';
}


?>