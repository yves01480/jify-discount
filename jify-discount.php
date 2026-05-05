<?php
/**
 * Plugin Name: Jify Discount
 * Description: Total-amount based discount manager with actual fee calculation (Fixed or Percentage), date scheduling, and data sharing.
 * Version: 2.1.0 (Threshold-based per-item discount)
 * Author: jify cloud
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

class Jify_Discount_Upgrade {

    public static function init() {
        load_plugin_textdomain( 'jify-discount', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'add_product_data_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_tab_data' ) );
        
        // Calculate very early
        add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'calculate_discount_logic' ), 5 );

        // Step 2: Add Fee to Cart (Priority 20 - Standard)
        add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply_discount_fee' ), 20 );
        
        add_filter( 'woocommerce_cart_totals_fee_html', array( __CLASS__, 'display_marketing_msg' ), 10, 2 );
        
        // Use the correct WooCommerce filter to control fee display order
        add_filter( 'woocommerce_sort_fees_callback', array( __CLASS__, 'sort_fees_callback' ), 10, 3 );
    }
    
    public static function sort_fees_callback( $default_sort, $a, $b ) {
        // Define custom sort order: Discount should come before Tax
        $a_is_discount = (
            strpos( $a->name, '優惠折扣' ) !== false ||
            strpos( $a->name, 'Marketing Discount' ) !== false ||
            strpos( $a->name, 'Discount' ) !== false
        );
        $b_is_discount = (
            strpos( $b->name, '優惠折扣' ) !== false ||
            strpos( $b->name, 'Marketing Discount' ) !== false ||
            strpos( $b->name, 'Discount' ) !== false
        );
        $a_is_tax = ( strpos( $a->name, '稅金' ) !== false || strpos( $a->name, 'Tax' ) !== false );
        $b_is_tax = ( strpos( $b->name, '稅金' ) !== false || strpos( $b->name, 'Tax' ) !== false );
        
        // If one is discount and the other is tax, discount comes first
        if ( $a_is_discount && $b_is_tax ) return -1;
        if ( $b_is_discount && $a_is_tax ) return 1;
        
        // If one is discount, it comes first
        if ( $a_is_discount ) return -1;
        if ( $b_is_discount ) return 1;
        
        // If one is tax, it comes last
        if ( $a_is_tax ) return 1;
        if ( $b_is_tax ) return -1;
        
        // Otherwise use default sort
        return $default_sort;
    }

    public static function add_product_tab( $tabs ) {
        $tabs['jify_discount'] = array(
            'label'    => __( 'Jify Discount', 'jify-discount' ),
            'target'   => 'jify_discount_product_data',
            'class'    => array( 'show_if_simple', 'show_if_variable' ),
            'priority' => 61,
        );
        return $tabs;
    }

    public static function add_product_data_panel() {
        global $post;
        $product = wc_get_product( $post->ID );
        if ( ! $product ) return;

        $products_to_configure = array();
        $products_to_configure[] = array('id' => $post->ID, 'name' => __('Main Product / Default', 'jify-discount'));

        if ( $product->is_type( 'variable' ) ) {
            $variations = $product->get_available_variations();
            foreach ( $variations as $variation ) {
                $var_obj = wc_get_product($variation['variation_id']);
                $products_to_configure[] = array(
                    'id' => $variation['variation_id'],
                    'name' => strip_tags( $var_obj->get_formatted_name() )
                );
            }
        }

        echo '<div id="jify_discount_product_data" class="panel woocommerce_options_panel hidden">';
        echo '<div id="jify-discount-app">';
        
        foreach ( $products_to_configure as $item ) {
            $id = $item['id'];
            $name = $item['name'];
            $is_enabled = get_post_meta( $id, '_jify_discount_enabled', true );
            $msg = get_post_meta( $id, '_jify_discount_msg', true );
            $inc_ship = get_post_meta( $id, '_jify_discount_inc_shipping', true );
            $rules_str = get_post_meta( $id, '_jify_discount_rules', true );
            $start_date = get_post_meta( $id, '_jify_discount_start_date', true );
            $end_date = get_post_meta( $id, '_jify_discount_end_date', true );
            
            $rules = array();
            if ( ! empty( $rules_str ) ) {
                $pairs = explode( '|', $rules_str );
                foreach ( $pairs as $pair ) {
                    $parts = explode( ':', $pair );
                    if ( count( $parts ) >= 2 ) {
                        $type = isset($parts[2]) ? $parts[2] : 'fixed';
                        $rules[] = array( 'amt' => $parts[0], 'disc' => $parts[1], 'type' => $type );
                    }
                }
            }
            ?>
            <div class="jify-product-row" style="border: 1px solid #ccd0d4; padding: 15px; margin: 10px 10px 20px 10px; background: #fff;">
                <h3 style="margin-top:0; border-bottom: 1px solid #eee; padding-bottom: 10px; font-size: 14px;"><?php echo esc_html( $name ); ?></h3>
                <p class="form-field">
                    <label><input type="checkbox" name="jify_disc_enabled[<?php echo $id; ?>]" value="yes" <?php checked( $is_enabled, 'yes' ); ?>> <?php esc_html_e( 'Enable Jify Discount', 'jify-discount' ); ?></label>
                </p>
                <div class="jify-disc-container" style="<?php echo $is_enabled === 'yes' ? '' : 'display:none;'; ?>; padding-left:20px;">
                    <div style="margin-bottom:15px; padding:10px; background:#f0f8ff; border-left:4px solid #2271b1;">
                        <p style="margin:0 0 10px 0;"><strong>Schedule (Optional):</strong></p>
                        <p style="margin:5px 0;">
                            <label style="display:inline-block; width:100px;">Start Date:</label>
                            <input type="date" name="jify_disc_start_date[<?php echo $id; ?>]" value="<?php echo esc_attr($start_date); ?>" style="width:150px;">
                            <small style="color:#666; margin-left:10px;">Leave empty for no start restriction</small>
                        </p>
                        <p style="margin:5px 0;">
                            <label style="display:inline-block; width:100px;">End Date:</label>
                            <input type="date" name="jify_disc_end_date[<?php echo $id; ?>]" value="<?php echo esc_attr($end_date); ?>" style="width:150px;">
                            <small style="color:#666; margin-left:10px;">Leave empty for no end restriction</small>
                        </p>
                    </div>
                    <textarea name="jify_disc_msg[<?php echo $id; ?>]" rows="2" style="width:100%;" placeholder="Marketing Msg"><?php echo esc_textarea( $msg ); ?></textarea>
                    <p><label><input type="checkbox" name="jify_disc_inc_ship[<?php echo $id; ?>]" value="yes" <?php checked( $inc_ship, 'yes' ); ?>> Include Shipping?</label></p>
                    <table class="widefat">
                        <thead><tr><th>Threshold</th><th>Value</th><th>Type</th><th>Example</th><th></th></tr></thead>
                        <tbody class="jify-disc-rules-list">
                            <?php foreach ( $rules as $rule ) : 
                                $example = '';
                                if ($rule['type'] === 'percent') {
                                    $example = sprintf('e.g., Cart $%s → Discount $%s', 
                                        number_format($rule['amt'], 0), 
                                        number_format($rule['amt'] * $rule['disc'] / 100, 2));
                                } else {
                                    $example = sprintf('e.g., Cart $%s → Discount $%s', 
                                        number_format($rule['amt'], 0), 
                                        number_format($rule['disc'], 2));
                                }
                            ?>
                            <tr class="jify-disc-rule-row">
                                <td><input type="number" step="0.01" class="short jify-input-threshold" name="jify_disc_amt[<?php echo $id; ?>][]" value="<?php echo esc_attr($rule['amt']); ?>"></td>
                                <td><input type="number" step="0.01" class="short jify-input-val" name="jify_disc_val[<?php echo $id; ?>][]" value="<?php echo esc_attr($rule['disc']); ?>"></td>
                                <td><select class="jify-input-type" name="jify_disc_type[<?php echo $id; ?>][]"><option value="fixed" <?php selected($rule['type'], 'fixed'); ?>>Fixed</option><option value="percent" <?php selected($rule['type'], 'percent'); ?>>Percent</option></select></td>
                                <td><small class="jify-example" style="color:#666;font-style:italic;"><?php echo esc_html($example); ?></small></td>
                                <td><button type="button" class="button remove-row">x</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" class="button add-jify-disc-rule" data-id="<?php echo $id; ?>">+ Add Rule</button>
                </div>
            </div>
            <?php
        }
        echo '</div>';
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Toggle visibility
            $('#jify-discount-app').on('change', 'input[type="checkbox"]', function() { 
                $(this).closest('.jify-product-row').find('.jify-disc-container').toggle($(this).is(':checked')); 
            });
            
            // Add Rule
            $('.add-jify-disc-rule').on('click', function() {
                var id = $(this).data('id');
                var row = `<tr class="jify-disc-rule-row">
                    <td><input type="number" step="0.01" class="short jify-input-threshold" name="jify_disc_amt[${id}][]"></td>
                    <td><input type="number" step="0.01" class="short jify-input-val" name="jify_disc_val[${id}][]"></td>
                    <td><select name="jify_disc_type[${id}][]"><option value="fixed">Fixed</option><option value="percent">Percent</option></select></td>
                    <td><small class="jify-example" style="color:#666;font-style:italic;">-</small></td>
                    <td><button type="button" class="button remove-row">x</button></td>
                </tr>`;
                $(this).closest('.jify-product-row').find('tbody').append(row);
            });
            
            // Remove Rule
            $('#jify-discount-app').on('click', '.remove-row', function() { 
                $(this).closest('tr').remove(); 
            });
            
            // Update example calculation on input change
            function updateExample(row) {
                var threshold = parseFloat(row.find('.jify-input-threshold').val()) || 0;
                var value = parseFloat(row.find('.jify-input-val').val()) || 0;
                var type = row.find('.jify-input-type').val();
                var example = '';
                
                if (threshold > 0 && value > 0) {
                    if (type === 'percent') {
                        var discount = (threshold * value / 100).toFixed(2);
                        example = 'e.g., Cart $' + threshold.toFixed(0) + ' → Discount $' + discount;
                    } else {
                        example = 'e.g., Cart $' + threshold.toFixed(0) + ' → Discount $' + value.toFixed(2);
                    }
                } else {
                    example = '-';
                }
                
                row.find('.jify-example').text(example);
            }
            
            // Update examples on input change
            $('#jify-discount-app').on('input change', '.jify-input-threshold, .jify-input-val, .jify-input-type', function() {
                updateExample($(this).closest('tr'));
            });
        });
        </script>
        <?php
        echo '</div>';
    }

    public static function save_tab_data( $post_id ) {
        $all_ids = array();
        if ( isset( $_POST['jify_disc_amt'] ) ) $all_ids = array_keys( $_POST['jify_disc_amt'] );
        foreach ( array_unique($all_ids) as $id ) {
            update_post_meta( $id, '_jify_discount_enabled', isset( $_POST['jify_disc_enabled'][$id] ) ? 'yes' : 'no' );
            update_post_meta( $id, '_jify_discount_msg', sanitize_textarea_field( $_POST['jify_disc_msg'][$id] ) );
            update_post_meta( $id, '_jify_discount_inc_shipping', isset( $_POST['jify_disc_inc_ship'][$id] ) ? 'yes' : 'no' );
            
            // Save date range
            if ( isset( $_POST['jify_disc_start_date'][$id] ) ) {
                $start_date = sanitize_text_field( $_POST['jify_disc_start_date'][$id] );
                // Validate date format (Y-m-d)
                if ( ! empty( $start_date ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
                    update_post_meta( $id, '_jify_discount_start_date', $start_date );
                } else {
                    delete_post_meta( $id, '_jify_discount_start_date' );
                }
            }
            if ( isset( $_POST['jify_disc_end_date'][$id] ) ) {
                $end_date = sanitize_text_field( $_POST['jify_disc_end_date'][$id] );
                // Validate date format (Y-m-d)
                if ( ! empty( $end_date ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
                    update_post_meta( $id, '_jify_discount_end_date', $end_date );
                } else {
                    delete_post_meta( $id, '_jify_discount_end_date' );
                }
            }
            
            $pairs = array();
            if ( isset( $_POST['jify_disc_amt'][$id] ) ) {
                for ( $i = 0; $i < count( $_POST['jify_disc_amt'][$id] ); $i++ ) {
                    $pairs[] = $_POST['jify_disc_amt'][$id][$i] . ':' . $_POST['jify_disc_val'][$id][$i] . ':' . $_POST['jify_disc_type'][$id][$i];
                }
            }
            update_post_meta( $id, '_jify_discount_rules', implode( '|', $pairs ) );
        }
    }

    public static function calculate_discount_logic( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        $cart_total = floatval( $cart->cart_contents_total );
        $discount_total = 0;
        $item_discounts = array();
        $message = '';

        foreach ( $cart->get_cart() as $item_key => $item ) {
            $vid = $item['variation_id'];
            $pid = $item['product_id'];
            $target_id = 0;

            // Check variation first, then fallback to parent product
            if ( $vid && get_post_meta( $vid, '_jify_discount_enabled', true ) === 'yes' ) {
                $target_id = $vid;
            } elseif ( get_post_meta( $pid, '_jify_discount_enabled', true ) === 'yes' ) {
                $target_id = $pid;
            }

            if ( ! $target_id ) {
                continue;
            }

            $start_date = get_post_meta( $target_id, '_jify_discount_start_date', true );
            $end_date = get_post_meta( $target_id, '_jify_discount_end_date', true );
            $current_date = current_time( 'Y-m-d' );
            if ( ! empty( $start_date ) && $current_date < $start_date ) {
                continue;
            }
            if ( ! empty( $end_date ) && $current_date > $end_date ) {
                continue;
            }

            $rules_str = get_post_meta( $target_id, '_jify_discount_rules', true );
            if ( empty( $rules_str ) ) {
                continue;
            }

            $line_total = isset( $item['line_total'] ) ? floatval( $item['line_total'] ) : 0;
            if ( $line_total <= 0 ) {
                continue;
            }

            $discount = self::calculate_item_discount( $rules_str, $cart_total, $line_total );
            if ( $discount <= 0 ) {
                continue;
            }

            $item_discounts[ $item_key ] = $discount;
            $discount_total += $discount;

            if ( $message === '' ) {
                $msg = get_post_meta( $target_id, '_jify_discount_msg', true );
                if ( $msg ) {
                    $message = $msg;
                }
            }
        }

        if ( function_exists( 'WC' ) && WC()->session ) {
            WC()->session->set( 'jify_discount_item_amounts', $item_discounts );
            WC()->session->set( 'jify_discount_amount', $discount_total );
            WC()->session->set( 'jify_discount_msg', $message );
        }
    }

    public static function apply_discount_fee( $cart ) {
        $d = WC()->session->get( 'jify_discount_amount' );
        if ( $d > 0 ) {
            // Add fee with specific ID to control sorting
            $cart->add_fee( __( '優惠折扣', 'jify-discount' ), -$d, false, '', 'jify_discount_fee' );
        }
    }

    public static function display_marketing_msg( $html, $fee ) {
        if ( strpos( $fee->name, '優惠折扣' ) !== false || strpos( $fee->name, 'Marketing Discount' ) !== false ) {
            $msg = WC()->session->get( 'jify_discount_msg' );
            if ( $msg ) $html .= '<br><small style="color:#666;font-style:italic;">' . esc_html($msg) . '</small>';
        }
        return $html;
    }

    private static function calculate_item_discount( $rules_str, $cart_total, $line_total ) {
        $best = 0;
        foreach ( explode( '|', $rules_str ) as $pair ) {
            $p = explode( ':', $pair );
            if ( count( $p ) < 3 ) {
                continue;
            }
            $threshold = floatval( $p[0] );
            $value = floatval( $p[1] );
            $type = $p[2];

            if ( $cart_total < $threshold ) {
                continue;
            }

            if ( $type === 'percent' ) {
                $discount = $line_total * ( min( $value, 100 ) / 100 );
            } else {
                $discount = $value;
            }

            if ( $discount > $best ) {
                $best = $discount;
            }
        }

        if ( $best <= 0 ) {
            return 0;
        }

        if ( $best > $line_total ) {
            $best = $line_total;
        }

        return $best;
    }
}
Jify_Discount_Upgrade::init();
