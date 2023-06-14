<?php
global $wpdb, $wpn, $wpn_settings, $wpn_woocommerce;
if( isset( $_REQUEST['wpn_setting_save'] ) && isset( $_REQUEST['wpn_setting'] ) && $_REQUEST['wpn_setting'] != '' ) {
    do_action( 'wpn_save_settings', $_POST );
    return;
}

echo '<div class="wrap wpn_content">';

if( isset($_SESSION['wpn_msg_status']) && $_SESSION['wpn_msg_status'] ) { 
    echo '<div id="message" class="updated notice notice-success is-dismissible">';
    echo '<p>';
    echo (isset($_SESSION['wpn_msg']) && $_SESSION['wpn_msg']!='') ? $_SESSION['wpn_msg'] : 'Something went wrong.';
    echo '</p>';
    echo '<button type="button" class="notice-dismiss"><span class="screen-reader-text">'.__('Dismiss this notice.',WPN_txt_domain).'</span></button>';
    echo '</div>';
    unset($_SESSION['wpn_msg_status']);
    unset($_SESSION['wpn_msg']);
} 



echo '<form name="wpn_settings" id="wpn_settings" method="post" enctype="multipart/form-data">';
    
    global $wpn, $wpn_settings;

    $general_option = $wpn_settings->wpn_get_settings_func( );

    extract($general_option);
    
    echo '<div class="cmrc-table">';

        /********************* Shortcode Section Start **********************/
        echo '<div class="setting-fb-config" >';
            echo '<h2>' . __('Import CSV', WPN_txt_domain) . '</h2>';
            echo '<table class="form-table wpn-setting-form">';
            echo '<tbody>';
                echo '<tr>';
                echo '<th><label for="wpn_create_new_app">' . __('Upload CSV', WPN_txt_domain) . '</label></th>';
                echo '<td><input type="file" name="orderCSV" id="orderCSV" accept=".csv"/></td>';
                echo '</tr>';

            echo '</tbody>';
            echo '</table>';
        echo '</div>';
        /********************* Shortcode Section End **********************/

    echo '</div>';
    echo '<p class="submit">';
    echo '<input type="hidden" name="wpn_setting" id="wpn_setting" value="wpn_setting" />';
    echo '<input name="wpn_setting_save" class="button-primary wpn_setting_save" type="submit" value="Save changes"/>';
    echo '</p>';

echo '</form>';
echo '</div>';
?>


<?php
$date = date('Y-m-d', strtotime('+4 days') );
//echo $user_query = 'SELECT * FROM ' . $wpn->tbl_wpn_order_notification . ' WHERE wpn_is_block = 0 AND wpn_next_expect_order_date="'.$date.'"';


$user_query = 'SELECT * FROM ' . $wpn->tbl_wpn_order_notification . '';
$user_data = $wpdb->get_results( $user_query, ARRAY_A );

if( $user_data ) { 
    ?>
    <table class="wp-list-table widefat fixed striped table-view-list pages">
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Next Order Expected Date</th>
                <th>Discount Amount</th>
                <th>Product Ids</th>
                <th>Coupon Name</th>
                <th>מעוניין בSMS</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($user_data as $user) { ?>
            <tr>
                <td><?php echo $user['wpn_name']; ?></td>
                <td><?php echo $user['wpn_phone']; ?></td>
                <td><?php echo $user['wpn_next_expect_order_date']; ?></td>
                <td><?php echo $user['wpn_discount_amount']; ?></td>
                <td><?php echo $user['wpn_wc_product_id']; ?></td>
                <td><?php echo $user['wpn_wc_coupon']; ?></td>
                <td><?php echo ($user['wpn_is_block'] == 1) ? 'לא' : 'כן'; ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    <?php
}
?>