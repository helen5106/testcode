<?php
/*
Plugin Name: 免费样品
Plugin URI: https://qinprinting.com/
Description: 设置免费样品送达的国家
Author: Helen5106
Version: 1.0
Author URI: https://about.me/helen5106
*/
global $woocommerce;
define('SAMPLE_PAGEID', 24051);//页面ID
define('SAMPLE_FROMID', 24446);//表单ID
define('SAMPLE_PRODID', 24445);//产品ID

define('SAMPLE_CUR', get_option( 'cursymble' ));
define('SAMPLE_SYMBLE', get_option( 'cursymble_fuhao' ));
define('SAMPLE_GST', get_option( 'cursymble_gst' ));
define('SAMPLE_FEE', get_option( 'cursymble_fee' ));
define('SAMPLE_Q1', get_option( 'cursymble_q1' ));
define('SAMPLE_Q2', get_option( 'cursymble_q2' ));
define('SAMPLE_Q3', get_option( 'cursymble_q3' ));
define('SAMPLE_KG', get_option( 'cursymble_kg' ));

add_filter( 'wc_add_to_cart_message_html', '__return_false' );

function qin_start_session() {
    if(!session_id()) {
        session_start();
    }
}
add_action('init', 'qin_start_session', 1);

function qin_end_session() {
    session_destroy ();
}
add_action('wp_logout','qin_end_session');


//发送电邮，格式化信息
function wpf_dev_email_message( $message ) {
     
    if ( strpos( $message, '#s#' ) === false ) {
         
    } else {
        $message = preg_replace('/#s#(.*?)#s#/mi', '<s style="color:gray">\1</s>', $message);
        $message = preg_replace('/#r#(.*?)#r#/mi', '<strong style="color:red">\1</strong>', $message);
        
        //$message = replace_once("#s#", '<s style="color:gray">', $message);
        //$message = replace_once("#r#", '<strong style="color:red">', $message);
        //$message = replace_once("#s#", '</s>', $message);
        //$message = replace_once("#r#", '</strong>', $message);
    }

 
    return $message;
 
}
 
add_filter( 'wpforms_emails_notifications_message', 'wpf_dev_email_message', 4, 1 );


function replace_once($search, $replace, $subject) {
    return preg_replace('/' . preg_quote($search, '/') . '/', $replace, $subject, 1);
}

function wpf_dev_email_field( $message ) {

    if ( strpos( $message, '#s#' ) === false ) {
         
    } else {
        $message = preg_replace('/#s#(.*?)#s#/mi', '<s style="color:gray">\1</s>', $message);
        $message = preg_replace('/#r#(.*?)#r#/mi', '<strong style="color:red">\1</strong>', $message);
        
        //$message = replace_once("#s#", '<s style="color:gray">', $message);
        //$message = replace_once("#r#", '<strong style="color:red">', $message);
        //$message = replace_once("#s#", '</s>', $message);
        //$message = replace_once("#r#", '</strong>', $message);
    }

    //var_dump($message);
    return $message;
 
}

add_filter( 'wpforms_html_field_value', 'wpf_dev_email_field', 1, 1 );


//订单提交接口
add_action( 'template_redirect', 'sa_send_email', 10, 0 );
function sa_send_email(){
    
    $request_body = $_POST;
    
    if ( !isset($request_body['product_price']) ) {
        return false;
    }
    //var_dump($_POST);exit;
    //print_r($_SESSION['SAMPLESHIPPINGDATA']);exit;
    $product_id = 24288;
    $shipping_name = $request_body['shipping_name'];
    $price = $request_body['product_price'];
    $shipping = floatval($request_body['shippingcost']);
    $weight = $request_body['weight'];
    $note = $request_body['note'];
    $_SESSION['PRODUCTNOTE'] = str_replace(';', '', trim($note));
    $_SESSION['PRODUCTWEIGHT'] = $weight;
    $_SESSION['PRODUCPRICE'] = $price;
    $_SESSION['PRODUCTSHIPPINGNAME'] = $shipping_name;
    $_SESSION['SAMPLESPOST'] = $request_body['zipcode'];
    $_SESSION['SAMPLESCOUN'] = $request_body['countryname'];
    $_SESSION['PRODUCSHIPPINGCOST'] = $shipping;
    
    unset($_SESSION['SAMPLESFEE']);
    
    WC()->cart->empty_cart();
    WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'qin_product_price' => $price, 'qin_shipping_cost' => $shipping ) );

    wp_safe_redirect( wc_get_checkout_url() );
    exit;
    
}

//订单提交接口结束

function in_zipArr($zipArr, $zipcode) {
    if ( count($zipArr) ) {
        $zipcode = strtolower(trim($zipcode));
        foreach ($zipArr as $zip) {
            $code = strtolower(trim($zip));
            if ( empty($code) )
                continue;
            
            if ( strpos($code, '-') !== false ) {
                $cc = explode('-', $code);
                //var_dump($cc);exit;
                if($zipcode >= $cc[0] && $zipcode <= $cc[1]) {
                    return true;
                }

            } else if( strpos($code, '^') !== false ) {
                //var_dump(substr($zipcode, 0, strlen($code)-1));
                if( substr($zipcode, 0, strlen($code)-1) == substr($code, 1) ) {
                    return true;
                }
                
            } else {
                if ( $code == $zipcode ) {
                    return true;
                }
            }
            
        }
        
        return false;
        
    } else {
        
        return false;
    }
}

// ========== Zip Code Groups Start ==========
if ( ! function_exists('qin_get_zipgroups') ) {
    function qin_get_zipgroups() {
        $option_name = 'wp_sample_zipgroups';
        $data = get_option( $option_name );
        if ( is_serialized( $data ) ) {
            $data = unserialize( $data );
        }
        if ( ! is_array( $data ) ) {
            $data = array();
        }
        return $data;
    }
}

if ( ! function_exists('qin_save_zipgroups') ) {
    function qin_save_zipgroups( $groups ) {
        update_option( 'wp_sample_zipgroups', serialize( $groups ) );
    }
}

if ( ! function_exists('qin_build_zip_array_from_rule') ) {
    function qin_build_zip_array_from_rule( $rule, $zgs ) {
        $list = array();

        if ( ! empty($rule['zipcode']) ) {
            $tmp = preg_split("/\r\n|\n|\r/", $rule['zipcode']);
            $tmp = array_map('trim', $tmp);
            $tmp = array_filter($tmp, 'strlen');
            $list = array_merge($list, $tmp);
        }

        if ( ! empty($rule['zipgroup']) && isset($zgs[ $rule['zipgroup'] ]) ) {
            $tmp2 = preg_split("/\r\n|\n|\r/", $zgs[ $rule['zipgroup'] ]['content']);
            $tmp2 = array_map('trim', $tmp2);
            $tmp2 = array_filter($tmp2, 'strlen');
            $list = array_merge($list, $tmp2);
        }

        return $list;
    }
}

if ( ! function_exists('qin_zipgroup_page') ) {
    function qin_zipgroup_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $zipgroups = qin_get_zipgroups();

        if ( isset($_GET['action']) && $_GET['action'] === 'delete' && ! empty($_GET['id']) ) {
            $id = sanitize_text_field( $_GET['id'] );
            if ( isset($zipgroups[$id]) && check_admin_referer( 'qin-del-zipgroup_' . $id ) ) {
                unset( $zipgroups[$id] );
                qin_save_zipgroups( $zipgroups );
                echo '<div class="updated"><p>已删除邮编组。</p></div>';
            }
        }

        if ( isset($_POST['qin_zipgroup_nonce']) && wp_verify_nonce( $_POST['qin_zipgroup_nonce'], 'qin_save_zipgroup' ) ) {
            $zip_id      = isset($_POST['zip_id']) ? sanitize_text_field($_POST['zip_id']) : '';
            $zip_name    = isset($_POST['zip_name']) ? sanitize_text_field($_POST['zip_name']) : '';
            $zip_content = isset($_POST['zip_content']) ? trim(stripslashes($_POST['zip_content'])) : '';

            if ( $zip_id === '' ) {
                $zip_id = 'zg_' . wp_generate_password( 8, false, false );
            }

            $zipgroups[$zip_id] = array(
                'id'      => $zip_id,
                'name'    => $zip_name,
                'content' => $zip_content,
            );

            qin_save_zipgroups( $zipgroups );
            echo '<div class="updated"><p>邮编组已保存。</p></div>';
        }

        $editing = false;
        $edit_item = array(
            'id'      => '',
            'name'    => '',
            'content' => '',
        );
        if ( isset($_GET['action']) && $_GET['action'] === 'edit' && ! empty($_GET['id']) ) {
            $eid = sanitize_text_field( $_GET['id'] );
            if ( isset($zipgroups[$eid]) ) {
                $editing   = true;
                $edit_item = $zipgroups[$eid];
            }
        }

        echo '<div class="wrap">';
        echo '<h2>邮编列表</h2>';
        //echo '<p>这里维护的邮编组可以在“Set Shipping”里直接选择使用。保存的是固定ID，所以你后面改名字也不会影响引用。</p>';

        if ( ! empty($zipgroups) ) {
            echo '<h3>已保存的邮编组</h3>';
            echo '<table class="widefat striped" style="max-width:900px;">';
            echo '<thead><tr><th style="width:130px;">ID</th><th style="width:200px;">名称</th><th>邮编内容</th><th style="width:130px;">操作</th></tr></thead><tbody>';
            foreach ( $zipgroups as $zg ) {
                $del_url  = wp_nonce_url( admin_url('admin.php?page=sample_zipgroups&action=delete&id=' . $zg['id']), 'qin-del-zipgroup_' . $zg['id'] );
                $edit_url = admin_url('admin.php?page=sample_zipgroups&action=edit&id=' . $zg['id']);
                echo '<tr>';
                echo '<td><code>' . esc_html($zg['id']) . '</code></td>';
                echo '<td>' . esc_html($zg['name']) . '</td>';
                echo '<td><textarea readonly rows="4" style="width:100%;">' . esc_textarea($zg['content']) . '</textarea></td>';
                echo '<td><a class="button" href="' . esc_url($edit_url) . '">编辑</a> ';
                echo '<a class="button button-link-delete" href="' . esc_url($del_url) . '" onclick="return confirm(\'确定要删除这个邮编组吗？\');">删除</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '<h3>' . ( $editing ? '编辑邮编组' : '新建邮编组' ) . '</h3>';
        echo '<form method="post" action="">';
        wp_nonce_field( 'qin_save_zipgroup', 'qin_zipgroup_nonce' );

        echo '<table class="form-table" style="max-width:900px;">';
        echo '<tr>';
        echo '<th scope="row"><label for="zip_id">ID（系统生成）</label></th>';
        echo '<td><input name="zip_id" id="zip_id" type="text" value="' . esc_attr($edit_item['id']) . '" class="regular-text" ' . ( $editing ? 'readonly' : '' ) . ' /> <p class="description">运费规则索引ID，可留空。</p></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="zip_name">邮编名称</label></th>';
        echo '<td><input name="zip_name" id="zip_name" type="text" value="' . esc_attr($edit_item['name']) . '" class="regular-text" required /></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row"><label for="zip_content">邮编内容</label></th>';
        echo '<td><textarea name="zip_content" id="zip_content" rows="10" cols="50" class="large-text code" style="font-family:monospace;">' . esc_textarea($edit_item['content']) . '</textarea><p class="description">一行一个；支持区间：33000-33999；支持前缀：^ABC0。</p></td>';
        echo '</tr>';

        echo '</table>';

        submit_button( $editing ? '保存修改' : '新增邮编组' );

        echo '</form>';

        echo '</div>';
    }
}
// ========== Zip Code Groups End ==========



function deal_shipping($shippingdata) {
    $shipping_kongyun_kesong = [];
    $shipping_kongyun_busong = [];
    $shipping_haiyun_kesong  = [];
    $shipping_haiyun_busong  = [];
    
    foreach ($shippingdata as $ship) {
        $tools = $ship['tools'];
        $type = $ship['type'];
        $currate = $ship['currate'];
        $baoguanfee = $ship['baoguanfee'];
        
        
        if ( $tools == 'kongyun' && $type == 'kesong' ) {
            $shipping_kongyun_kesong[] = $ship;
        }
        
        if ( $tools == 'kongyun' && $type == 'busong' ) {
            $shipping_kongyun_busong[] = $ship;
        }
        
        if ( $tools == 'haiyun' && $type == 'kesong' ) {
            $shipping_haiyun_kesong[] = $ship;
        }
        
        if ( $tools == 'haiyun' && $type == 'busong' ) {
            $shipping_haiyun_busong[] = $ship;
        }
    }
    
    return [$shipping_kongyun_kesong, $shipping_kongyun_busong, $shipping_haiyun_kesong, $shipping_haiyun_busong];
    
}
//运费提交接口

// ================= FedEx Integration (Settings + OAuth + Rate) =================
if (!function_exists('qin_fedex_debug_enabled')) {
    function qin_fedex_debug_enabled() {
        $s = qin_fedex_get_settings();
        return !empty($s['debug']);
    }
}
if (!function_exists('qin_fedex_log')) {
    function qin_fedex_log($tag, $payload) {
        if (!qin_fedex_debug_enabled()) return;
        $line = '[QIN FedEx]['.$tag.'] ';
        if (is_string($payload)) {
            $line .= $payload;
        } else {
            // 尽量不打太长
            $json = wp_json_encode($payload);
            if (strlen($json) > 4000) $json = substr($json, 0, 4000) . ' ... (truncated)';
            $line .= $json;
        }
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info($line, array('source' => 'qin-fedex'));
        } else {
            error_log($line);
        }
    }
}

if (!function_exists('qin_fedex_get_settings')) {
    function qin_fedex_get_settings() {
        $opt = get_option('wp_sample_fedex_settings');
        if (is_serialized($opt)) $opt = unserialize($opt);
        if (!is_array($opt)) $opt = array();
        $def = array(
            'env' => 'sandbox',
            'client_id' => '',
            'client_secret' => '',
            'account_number' => '',
            'shipper_country' => 'CN',
            'shipper_postal' => '200082',
            'shipper_city' => 'Shanghai',
            'shipper_state' => 'SH',
            'pickup_type' => 'DROPOFF_AT_FEDEX_LOCATION',
        );
        return array_merge($def, $opt);
    }
}
if (!function_exists('qin_fedex_save_settings')) {
    function qin_fedex_save_settings($arr) {
        update_option('wp_sample_fedex_settings', serialize($arr));
    }
}
if (!function_exists('qin_fedex_settings_page')) {
    function qin_fedex_settings_page() {
        if (!current_user_can('manage_options')) return;
        $s = qin_fedex_get_settings();
        if (!empty($_POST['qin_fedex_save'])) {
            check_admin_referer('qin_fedex_save');
            $s['env'] = in_array($_POST['env'], array('sandbox','prod')) ? $_POST['env'] : 'sandbox';
            $s['client_id'] = sanitize_text_field($_POST['client_id']);
            $s['client_secret'] = sanitize_text_field($_POST['client_secret']);
            $s['account_number'] = sanitize_text_field($_POST['account_number']);
            $s['shipper_country'] = strtoupper(sanitize_text_field($_POST['shipper_country']));
            $s['shipper_postal'] = sanitize_text_field($_POST['shipper_postal']);
            $s['shipper_city'] = sanitize_text_field($_POST['shipper_city']);
            $s['shipper_state'] = strtoupper(sanitize_text_field($_POST['shipper_state']));
            $s['pickup_type'] = sanitize_text_field($_POST['pickup_type']);
            $s['debug'] = !empty($_POST['debug']) ? 1 : 0;

            qin_fedex_save_settings($s);
            echo '<div class="updated"><p>FedEx 设置已保存。</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>FedEx 接口设置</h1>
            <form method="post">
                <?php wp_nonce_field('qin_fedex_save'); ?>
                <table class="form-table">
                    <tr><th>环境</th><td>
                        <label><input type="radio" name="env" value="sandbox" <?php if($s['env']==='sandbox') echo 'checked';?>> Sandbox</label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="env" value="prod" <?php if($s['env']==='prod') echo 'checked';?>> Production</label>
                    </td></tr>
                    <tr><th>Client ID</th><td><input type="text" name="client_id" value="<?php echo esc_attr($s['client_id']);?>" class="regular-text"></td></tr>
                    <tr><th>Client Secret</th><td><input type="password" name="client_secret" value="<?php echo esc_attr($s['client_secret']);?>" class="regular-text"></td></tr>
                    <tr><th>FedEx Account Number</th><td><input type="text" name="account_number" value="<?php echo esc_attr($s['account_number']);?>" class="regular-text"></td></tr>
                    <tr><th>发件国家</th><td><input type="text" name="shipper_country" value="<?php echo esc_attr($s['shipper_country']);?>" class="small-text"> 例：CN/US</td></tr>
                    <tr><th>发件邮编</th><td><input type="text" name="shipper_postal" value="<?php echo esc_attr($s['shipper_postal']);?>" class="regular-text"></td></tr>
                    <tr><th>发件城市</th><td><input type="text" name="shipper_city" value="<?php echo esc_attr($s['shipper_city']);?>" class="regular-text"></td></tr>
                    <tr><th>发件省州</th><td><input type="text" name="shipper_state" value="<?php echo esc_attr($s['shipper_state']);?>" class="small-text"></td></tr>
                    <tr><th>取件方式</th><td>
                        <select name="pickup_type">
                            <option value="DROPOFF_AT_FEDEX_LOCATION" <?php if($s['pickup_type']==='DROPOFF_AT_FEDEX_LOCATION') echo 'selected';?>>DROPOFF_AT_FEDEX_LOCATION</option>
                            <option value="CONTACT_FEDEX_TO_SCHEDULE" <?php if($s['pickup_type']==='CONTACT_FEDEX_TO_SCHEDULE') echo 'selected';?>>CONTACT_FEDEX_TO_SCHEDULE</option>
                        </select>
                    </td>
                    </tr>
                    <tr><th>调试日志</th><td>
                        <label><input type="checkbox" name="debug" value="1" <?php checked( !empty($s['debug']) ); ?>> 启用 FedEx 调试日志（写入 WooCommerce 日志或 /wp-content/debug.log）</label>
                    </td></tr>

                </table>
                <p><button class="button-primary" name="qin_fedex_save" value="1">保存设置</button></p>
            </form>
        </div>
        <?php
    }
}
if (!function_exists('qin_fedex_base')) {
    function qin_fedex_base() {
        $s = qin_fedex_get_settings();
        return ($s['env']==='prod') ? 'https://apis.fedex.com' : 'https://apis-sandbox.fedex.com';
    }
}
if (!function_exists('qin_fedex_get_token')) {
    function qin_fedex_get_token() {
        $s = qin_fedex_get_settings();
        if (empty($s['client_id']) || empty($s['client_secret'])) return false;
        $cache_key = 'qin_fedex_token_' . $s['env'];
        $cached = get_transient($cache_key);
        if (is_array($cached) && !empty($cached['access_token'])) return $cached['access_token'];
        $url = qin_fedex_base().'/oauth/token';
        $payload = http_build_query(array(
            'grant_type' => 'client_credentials',
            'client_id' => $s['client_id'],
            'client_secret' => $s['client_secret'],
        ));
        
        qin_fedex_log('TOKEN_REQ', array(
            'url' => $url,
            'env' => $s['env'],
            'client_id_masked' => substr($s['client_id'], 0, 6) . '****',
        ));

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => True,
            CURLOPT_POST => True,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
            CURLOPT_TIMEOUT => 20,
        ));
        $res = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (!($http>=200 && $http<300)) {
            qin_fedex_log('TOKEN_ERR', array('http' => $http, 'body_excerpt' => substr($res, 0, 1200)));
        }

        if ($http>=200 and $http<300 and $res) {
            $j = json_decode($res, true);

            qin_fedex_log('TOKEN_RES', array(
                'http' => $http,
                // 不输出纯 token，避免泄漏
                'has_access_token' => !empty($j['access_token']),
                'expires_in' => isset($j['expires_in']) ? $j['expires_in'] : null,
                'body_excerpt' => substr($res, 0, 1200),
            ));
        
            if (!empty($j['access_token'])) {
                $ttl = !empty($j['expires_in']) ? max(60, intval($j['expires_in']) - 60) : 3300;
                set_transient($cache_key, $j, $ttl);
                return $j['access_token'];
            }
        }
        return false;
    }
}
if (!function_exists('qin_lb_from_kg')) {
    function qin_lb_from_kg($kg) {
        $kg = floatval($kg);
        $lb = $kg * 2.20462262;
        return max(0.1, round($lb, 2));
    }
}
if (!function_exists('qin_fedex_service_label')) {
    function qin_fedex_service_label($code) {
        $map = array(
            'FEDEX_INTERNATIONAL_PRIORITY' => 'FedEx International Priority®',
            'FEDEX_INTERNATIONAL_ECONOMY'  => 'FedEx International Economy®',
            'FEDEX_INTERNATIONAL_FIRST'    => 'FedEx International First®',
            'FEDEX_INTERNATIONAL_CONNECT_PLUS' => 'FedEx International Connect Plus®',
            'FEDEX_GROUND'           => 'FedEx Ground®',
            'FEDEX_EXPRESS_SAVER'    => 'FedEx Express Saver®',
            'FEDEX_2_DAY'            => 'FedEx 2Day®',
            'STANDARD_OVERNIGHT'     => 'FedEx Standard Overnight®',
            'PRIORITY_OVERNIGHT'     => 'FedEx Priority Overnight®',
            'FIRST_OVERNIGHT'        => 'FedEx First Overnight®',
        );
        return isset($map[$code]) ? $map[$code] : $code;
    }
}

if (!function_exists('qin_fedex_rate_quote')) {
    function qin_fedex_rate_quote($to_country, $to_postal, $weight_kg, $args=array()) {
        $s = qin_fedex_get_settings();
        $token = qin_fedex_get_token();
        if (!$token || empty($s['account_number'])) return array();
                
        // 计算磅
        $lbs = qin_lb_from_kg($weight_kg);

        // —— A) pickupType 安全化：如为预约取件，补齐必需字段；否则默认投递到网点 —— //
        $pickupType = $s['pickup_type'];
        $processingOptions = null;
        $pickupDetail = null;

        // 场景1：你确实要看“预约取件”的报价
        if ($pickupType === 'CONTACT_FEDEX_TO_SCHEDULE') {
            // 按文档要求：需要 requestType + readyDate + latestPickupDate
            $pickupDetail = array(
                'requestType'      => 'FUTURE_DAY',
                'readyDate'        => date('Y-m-d', strtotime('+1 day')),
                'latestPickupDate' => date('Y-m-d', strtotime('+2 day')),
            );
            $processingOptions = array('INCLUDE_PICKUPRATES');
        } else {
            // 场景2：不需要预约取件报价，使用更稳的默认值
            $pickupType = 'DROPOFF_AT_FEDEX_LOCATION';
        }

        // —— B) 收件地址可选州/住宅标记（US/CA 时更稳妥） —— //
        $recipient_addr = array(
            'postalCode'  => $to_postal,
            'countryCode' => strtoupper($to_country),
        );
        if (in_array(strtoupper($to_country), array('US','CA'), true) && !empty($args['state'])) {
            $recipient_addr['stateOrProvinceCode'] = strtoupper(sanitize_text_field($args['state']));
        }
        if (isset($args['residential'])) {
            $recipient_addr['residential'] = (bool)$args['residential'];
        }

        // —— C) 构造更“贴近文档示例”的请求体 —— //
        $body = array(
            'accountNumber' => array('value' => $s['account_number']),
            'rateRequestControlParameters' => array(
                'returnTransitTimes' => true,
                'rateSortOrder'      => 'COMMITASCENDING', //'LOWEST_TO_HIGHEST',
            ),
            'requestedShipment' => array(
                'preferredCurrency' => 'USD',
                'shipDateStamp'     => date('Y-m-d'),
                'rateRequestType'   => array('ACCOUNT','LIST'), // 同时要账号价与公示价
                'shipper' => array(
                    'address' => array(
                        'postalCode'          => $s['shipper_postal'],
                        'countryCode'         => strtoupper($s['shipper_country']),
                        'city'                => $s['shipper_city'],
                        'stateOrProvinceCode' => $s['shipper_state'],
                    ),
                ),
                'recipient'     => array('address' => $recipient_addr),
                'pickupType'    => $pickupType,
                'packagingType' => 'YOUR_PACKAGING',
                'requestedPackageLineItems' => array(
                    array(
                        'weight' => array('units'=>'LB','value'=>$lbs),
                        'groupPackageCount' => 1
                    )
                ),
            ),
        );

        // 可选：只有在预约取件时才加 processingOptions + pickupDetail
        if ($processingOptions) {
            $body['processingOptions'] = $processingOptions;
        }
        if ($pickupDetail) {
            $body['requestedShipment']['pickupDetail'] = $pickupDetail;
        }
                
        // —— D) 国际清关信息（Rate 对国际件通常要求） —— //
        $is_international = (strtoupper($s['shipper_country']) !== strtoupper($to_country));
        if ($is_international) {
            $declared = isset($args['declared_value']) ? floatval($args['declared_value']) : 0;

            // quantity 取 1，更容易过沙箱；unitPrice = customsValue
            $commodity_desc = !empty($args['commodity_description']) ? $args['commodity_description'] : 'Printed books';
            $body['requestedShipment']['customsClearanceDetail'] = array(
                'dutiesPayment' => array(
                    'paymentType' => 'SENDER',
                    // payor 可选：若仍报错，再加 responsibleParty 账号
                    // 'payor' => array(
                    //     'responsibleParty' => array(
                    //         'accountNumber' => array('value' => $s['account_number']),
                    //     )
                    // )
                ),
                'commodities' => array(
                    array(
                        'numberOfPieces'        => 1,
                        'description'           => $commodity_desc,
                        'countryOfManufacture'  => strtoupper($s['shipper_country']),
                        'weight'                => array('units'=>'LB','value'=>$lbs),
                        'quantity'              => 1,
                        'quantityUnits'         => 'EA',
                        'unitPrice'             => array('amount'=>$declared,'currency'=>'USD'),
                        'customsValue'          => array('amount'=>$declared,'currency'=>'USD'),
                    )
                ),
                // 一些返回要求 NON_DOCUMENTS（文档类可不填）
                // 'documentContent' => 'NON_DOCUMENTS',
            );

            // 打日志
            qin_fedex_log('INTL_CC_ADDED', $body['requestedShipment']['customsClearanceDetail']);
        }

        // 允许外部过滤
        $body = apply_filters('qin_fedex_rate_body', $body, $to_country, $to_postal, $weight_kg);

        // —— 发送 cURL：开启自动解压，日志更清楚 —— //
        $url = qin_fedex_base().'/rate/v1/rates/quotes';

        qin_fedex_log('RATE_REQ', array('url'=>$url,'body'=>$body));

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => array(
                'Authorization: Bearer '.$token,
                'Content-Type: application/json',
                'Accept: application/json',
                'Accept-Encoding: gzip'
            ),
            CURLOPT_ENCODING       => '',   // <<<<<< 关键：自动解压 GZIP
            CURLOPT_TIMEOUT        => 25,
        ));
        $res  = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        
        qin_fedex_log('RATE_JSON_RESULT', array(
            'http'        => $http,
            'res_excerpt' => $res
        ));
        // 错误日志
        if (!($http>=200 && $http<300) || empty($res)) {
            return array();
        }

        $j = json_decode($res, true);
        if (!$j) {
            return array();
        }
        // 格式化结果
        $details = array();
        
        if (!empty($j['output']['rateReplyDetails'])) {
            $details = $j['output']['rateReplyDetails'];
        } elseif (!empty($j['rateReplyDetails'])) {
            $details = $j['rateReplyDetails'];
        } else {
            qin_fedex_log('RATE_NO_DETAILS', $j);
            return array();
        }

        $allowed = apply_filters('qin_fedex_allowed_services', array(
            'FEDEX_INTERNATIONAL_PRIORITY',
            'FEDEX_INTERNATIONAL_ECONOMY',
            'FEDEX_INTERNATIONAL_FIRST',
            'FEDEX_INTERNATIONAL_CONNECT_PLUS'
        ));

        $out = array();
        foreach ($details as $d) {
            $service = $d['serviceType'] ?? '';
            
            // 服务过滤
            if (!empty($allowed) && !in_array($service, $allowed)) {
                qin_fedex_log('RATE_SERVICE_FILTERED', array('service' => $service));
                continue;
            }

            // 提取费率（关键修复）
            $amount = null;
            $currency = 'USD';
            
            if (isset($d['ratedShipmentDetails'][0])) {
                $rsd = $d['ratedShipmentDetails'][0];
                
                // totalNetCharge 直接是数字
                if (isset($rsd['totalNetCharge']) && is_numeric($rsd['totalNetCharge'])) {
                    $amount = floatval($rsd['totalNetCharge']);
                    $currency = $rsd['currency'] ?? 'USD';
                }
                // 备用：从 shipmentRateDetail 取
                elseif (isset($rsd['shipmentRateDetail']['totalNetCharge'])) {
                    $amount = floatval($rsd['shipmentRateDetail']['totalNetCharge']);
                    $currency = $rsd['shipmentRateDetail']['currency'] ?? 'USD';
                }
            }

            if ($amount === null) {
                qin_fedex_log('RATE_AMOUNT_NOT_FOUND', array(
                    'service' => $service,
                    'structure' => $d['ratedShipmentDetails'][0] ?? 'missing'
                ));
                continue;
            }

            // 提取时效
            $days = $d['commit']['transitDays'] ?? null;
            $date = $d['commit']['dateDetail']['dayFormat'] 
                 ?? $d['commit']['commitTimestamp'] 
                 ?? null;

            $out[] = array(
                'carrier'       => 'FedEx',
                'service_code'  => $service,
                'service_name'  => qin_fedex_service_label($service),
                'amount'        => $amount,
                'currency'      => $currency,
                'transit_days'  => $days,
                'delivery_date' => $date,
                'meta'          => array(
                    'source'    => 'fedex',
                    'rate_type' => $d['ratedShipmentDetails'][0]['rateType'] ?? 'ACCOUNT',
                ),
            );
            
            qin_fedex_log('RATE_ADDED', array(
                'service' => $service,
                'amount'  => $amount,
                'currency' => $currency
            ));
        }

        return $out;
    }
}
// ================= FedEx Integration End =================

function process_shipping(WP_REST_Request $request){
    $request_body = $request->get_body_params();
    
    //preg_match('/(\d+\.?\d)/', $request_body['weight'], $matches);
	
	preg_match('/(\d+(?:\.\d+)?)/', $request_body['weight'], $matches);

    $weight = floatval($matches[1]);
    
    $country = $request_body['country'];
    $zipcode = $request_body['zipcode'];
    $exwprice = $request_body['exwprice'] ?? 1000;
        

    // ===== FedEx rate prefetch =====
    $fedex_list = array();
    try {
        if (!empty($country) && !empty($zipcode) && $weight > 0) {
            $declared = $exwprice;
            if ($declared <= 0) {
                $declared = 1000;
            }

            // 调用 FedEx 报价时把 declared_value、commodity 描述、以及可选州/住宅一并传入
            $args = array(
                'declared_value'        => $declared,
                'commodity_description' => 'Printed Books',
            );

            $fedex_country = ($country === 'UK') ? 'GB' : $country;
            $fedex_list = qin_fedex_rate_quote($fedex_country, $zipcode, $weight, $args);

        }
    } catch (Exception $e) {
        $fedex_list = array(); // 静默失败
    }
    // ===== End FedEx rate prefetch =====

    $country = $country == 'UK' ? 'GB' : $country;
    $shipping_data = get_option( 'wp_sample_shipping_' . strtolower($country) );
    $sample_shipping = ( is_serialized( $shipping_data )) ? unserialize( $shipping_data ) : $shipping_data;
    $zipgroups = qin_get_zipgroups();
        
    if( !empty($shipping_data) ){
        
        list($shipping_kongyun_kesong, $shipping_kongyun_busong, $shipping_haiyun_kesong, $shipping_haiyun_busong) = deal_shipping($sample_shipping);
        
        
        $kybusong = false;
        //判断空运不送
        foreach ( $shipping_kongyun_busong as $ck => $cv ) {
            
            $type = $cv['type'];
            $currate = $cv['currate'];
            $baoguanfee = $cv['baoguanfee'];
            $zipcodeArr = qin_build_zip_array_from_rule($cv, $zipgroups);
            
            if ( in_zipArr($zipcodeArr, $zipcode) ) {
                 $kybusong = true;
            }

        }
        
        $hybusong = false;
        //判断海运不可送
        foreach ( $shipping_haiyun_busong as $ck => $cv ) {
            
            $type = $cv['type'];
            $currate = $cv['currate'];
            $baoguanfee = $cv['baoguanfee'];
            $zipcodeArr = qin_build_zip_array_from_rule($cv, $zipgroups);
            
            if ( in_zipArr($zipcodeArr, $zipcode) ) {
                $hybusong = true;
            }

        }
        
                
        // 空运 海运 都不可送
        if ( $kybusong && $hybusong ) {

            // 如果 FedEx 有可派送方式，直接返回 FedEx 结果（保持原有字段以兼容旧前端）
            if ( !empty($fedex_list) ) {
                $all_items = $fedex_list;
                usort($all_items, function($a,$b){
                    $ax = isset($a['amount']) ? $a['amount'] : 0;
                    $bx = isset($b['amount']) ? $b['amount'] : 0;
                    if ($ax == $bx) return 0;
                    return ($ax < $bx) ? -1 : 1;
                });
                $response = new WP_REST_Response(array(
                    'message' => 'ok',
                    'kongyun' => array(), // 内部空运/海运不可送
                    'haiyun'  => array(),
                    'weight'  => $weight,
                    'fedex'   => $fedex_list,
                    'items'   => $all_items,
                ));
                $response->set_status(200);
                return $response;
            }

            // FedEx 也没有，才返回不可送
            $response = new WP_REST_Response(array('message'=>'Instant shipping cost is unavailable in this area, please contact us.'));
            $response->set_status(200);
            return $response;            
        }

        
        
        //可送达
        $kyzipcode = false;
        $kychaozhong = true;//空运超重
        $kongyun = [];
        $tempkongyun = [];
        if ( !$kybusong ) {
            
            foreach ( $shipping_kongyun_kesong as $ck => $cv ) {
                
                $symbol = $cv['symbol'];
                $shippingtime = $cv['shippingtime'] ?? 'around 7 days';
                $type = $cv['type'];
                $region = $cv['region'] ?? 'normal';
                $currate = $cv['currate'];
                $baoguanfee = $cv['baoguanfee'];
                $zipcodeArr = qin_build_zip_array_from_rule($cv, $zipgroups);
                //print_r($zipcodeArr);exit;
                if ( in_zipArr($zipcodeArr, $zipcode) ) {
                    
                    $kyzipcode = true;
                    foreach ( $cv['kongyun'] as $kk => $kv ) {
                        if ( isset($kv[2]) && !empty($kv[2]) ) {
                            if ( $weight >= $kv[0] && $weight <= $kv[1] ) {
                                $rule = $kv[2];
                                $rule = str_replace('汇率', $currate, $rule);
                                $rule = str_replace('报关费', $baoguanfee, $rule);
                                $rule = str_replace('重量', $weight, $rule);
                                $rule = str_replace('出厂价', $exwprice, $rule);
                                
                                $ruleStr = $rule;

                                $ruleStr = str_replace('：', ':', $ruleStr);
                                $ruleStr = str_replace('？', '?', $ruleStr);
                                $ruleStr = str_replace('（', '(', $ruleStr);
                                $ruleStr = str_replace('）', ')', $ruleStr);
                                $ruleStr = str_ireplace('x', '*', $ruleStr);
                                $ruleStr = str_ireplace('x', '*', $ruleStr);
                                $ruleStr = str_replace('向上取整', 'ceil',  $ruleStr);
                                $ruleStr = str_replace('进一取整', 'ceil',  $ruleStr);
                                $ruleStr = str_replace('舍去取整', 'floor',  $ruleStr);
                                $ruleStr = str_replace('向下取整', 'floor',  $ruleStr);
                                $ruleStr = str_replace('最大', 'max',  $ruleStr);
                                $ruleStr = str_replace('最小', 'min',  $ruleStr);
                                $ruleStr = html_entity_decode($ruleStr);
                                $ruleStr = preg_replace('/\?\((.*?)>([^\)]*?)\)/i', '((\1 > \2) ? \1 : \2)', $ruleStr);
                                $ruleStr = str_replace('< =', '<=',  $ruleStr);
                                $ruleStr = str_replace('> =', '>=',  $ruleStr);
                                //var_dump($ruleStr);exit;
                                $ruleV   = @eval("return $ruleStr;");
                                $tempkongyun[] = ['gs' => $ruleStr, 'gv' => ceil($ruleV), 'region' => $region, 'shippingtime' => $shippingtime, 'symbol' => $symbol, 'name' => 'Air Shipping'];
                                //break 2;
                                $kychaozhong = false;
                            }
                        }
                        
                    }

                }
            
            }
            $iv = 0;
            $ik = 0;
            foreach ($tempkongyun as $tk => $tv) {
                $gv = $tv['gv'];
                
                if ( $iv  < $gv ) {
                    $iv = $gv;
                    $ik = $tk;
                }
            }
            $kongyun = $tempkongyun[$ik] ?? [];
        }

        
        //海运可送达
        $hyzipcode = false;
        $hychaozhong = true;
        $haiyun = [];
        $temphaiyun = [];
        if ( !$hybusong ) {
            foreach ( $shipping_haiyun_kesong as $ck => $cv ) {
                
                $symbol = $cv['symbol'];
                $shippingtime = $cv['shippingtime'] ?? 'around 35 days';
                $type = $cv['type'];
                $currate = $cv['currate'];
                $region = $cv['region'] ?? 'normal';
                $baoguanfee = $cv['baoguanfee'];
                $zipcodeArr = qin_build_zip_array_from_rule($cv, $zipgroups);
                
                if ( in_zipArr($zipcodeArr, $zipcode) ) {
                    
                    $hyzipcode = true;
                    foreach ( $cv['haiyun'] as $kk => $kv ) {
                        if ( isset($kv[2]) && !empty($kv[2]) ) {
                            if ( $weight >= $kv[0] && $weight <= $kv[1] ) {
                                $rule = $kv[2];
                                $rule = str_replace('汇率', $currate, $rule);
                                $rule = str_replace('报关费', $baoguanfee, $rule);
                                $rule = str_replace('重量', $weight, $rule);
                                $rule = str_replace('出厂价', $exwprice, $rule);
                                
                                $ruleStr = $rule;

                                $ruleStr = str_replace('：', ':', $ruleStr);
                                $ruleStr = str_replace('？', '?', $ruleStr);
                                $ruleStr = str_replace('（', '(', $ruleStr);
                                $ruleStr = str_replace('）', ')', $ruleStr);
                                $ruleStr = str_ireplace('x', '*', $ruleStr);
                                $ruleStr = str_ireplace('x', '*', $ruleStr);
                                $ruleStr = str_replace('向上取整', 'ceil',  $ruleStr);
                                $ruleStr = str_replace('进一取整', 'ceil',  $ruleStr);
                                $ruleStr = str_replace('舍去取整', 'floor',  $ruleStr);
                                $ruleStr = str_replace('向下取整', 'floor',  $ruleStr);
                                $ruleStr = str_replace('最大', 'max',  $ruleStr);
                                $ruleStr = str_replace('最小', 'min',  $ruleStr);
                                $ruleStr = html_entity_decode($ruleStr);
                                $ruleStr = preg_replace('/\?\((.*?)>([^\)]*?)\)/i', '((\1 > \2) ? \1 : \2)', $ruleStr);
                                $ruleStr = str_replace('< =', '<=',  $ruleStr);
                                $ruleStr = str_replace('> =', '>=',  $ruleStr);
        
                                $ruleV   = @eval("return $ruleStr;");
                                $temphaiyun[] = ['gs' => $ruleStr, 'gv' => ceil($ruleV), 'region' => $region, 'shippingtime' => $shippingtime, 'symbol' => $symbol, 'name' => 'Sea Shipping'];
                                //break 2;
                                $hychaozhong = false;
                            }
                        }
                        
                    }
                    
                    
                }
            
            }
            
            $iv = 0;
            $ik = 0;
            foreach ($temphaiyun as $tk => $tv) {
                $gv = $tv['gv'];
                
                if ( $iv  < $gv ) {
                    $iv = $gv;
                    $ik = $tk;
                }
            }
            $haiyun = $temphaiyun[$ik] ?? [];
        }

        if ( !$kyzipcode && !$hyzipcode ) {
            if ( !empty($fedex_list) ) {
                $all_items = $fedex_list;
                usort($all_items, function($a,$b){
                    $ax = isset($a['amount']) ? $a['amount'] : 0;
                    $bx = isset($b['amount']) ? $b['amount'] : 0;
                    if ($ax == $bx) return 0;
                    return ($ax < $bx) ? -1 : 1;
                });
                $response = new WP_REST_Response(array(
                    'message' => 'ok',
                    'kongyun' => array(),
                    'haiyun'  => array(),
                    'weight'  => $weight,
                    'fedex'   => $fedex_list,
                    'items'   => $all_items
                ));
                $response->set_status(200);
                return $response;
            }
            $response = new WP_REST_Response(array('message'=>'In your address, the shipping cost is unavailable in this area, please contact us.'));
            $response->set_status(200);
            unset($_SESSION['SAMPLESHIPPINGDATA']);
            return $response;            
        }

                
        if ( empty($kongyun) && empty($haiyun) ) {
            if ( !empty($fedex_list) ) {
                $all_items = $fedex_list;
                usort($all_items, function($a,$b){
                    $ax = isset($a['amount']) ? $a['amount'] : 0;
                    $bx = isset($b['amount']) ? $b['amount'] : 0;
                    if ($ax == $bx) return 0;
                    return ($ax < $bx) ? -1 : 1;
                });
                $response = new WP_REST_Response(array(
                    'message' => 'ok',
                    'kongyun' => array(),
                    'haiyun'  => array(),
                    'weight'  => $weight,
                    'fedex'   => $fedex_list,
                    'items'   => $all_items
                ));
                $response->set_status(200);
                return $response;
            }
            $response = new WP_REST_Response(array('message'=>'In your address, the shipping cost is unavailable in this area, please contact us.'));
            $response->set_status(200);
            unset($_SESSION['SAMPLESHIPPINGDATA']);
            return $response;
        }
        if ( !empty($fedex_list) ) {
            $all_items = $fedex_list;
            usort($all_items, function($a,$b){
                $ax = isset($a['amount']) ? $a['amount'] : 0;
                $bx = isset($b['amount']) ? $b['amount'] : 0;
                if ($ax == $bx) return 0;
                return ($ax < $bx) ? -1 : 1;
            });
        }
        
        $data = array('message'=>'ok', 'kongyun' => $kongyun, 'haiyun' => $haiyun, 'fedex'   => $fedex_list,);
        $_SESSION['SAMPLESHIPPINGDATA'] = $data;
        $response = new WP_REST_Response($data);
        $response->set_status(200);
        return $response;
    }
    
    $response = new WP_REST_Response(array('message'=>'Instant shipping cost is unavailable in this area, please contact us.'));
    $response->set_status(200);
    unset($_SESSION['SAMPLESHIPPINGDATA']);
    return $response;
}

function register_shipping(){
   register_rest_route('qinprinting/v1', '/shippingcost', array(
        'methods'=>'POST',
        'callback'=>'process_shipping'
   ));
}
add_action('rest_api_init', 'register_shipping');
//订单提交接口结束


// sample order信息
//提交完sample order后跳转购物车
function wpform_sample_order_deal( $fields, $entry, $form_data, $entry_id ) 
{
    
    $product_id = SAMPLE_PRODID;
    
    //只针对sample order
    if ( absint( $form_data[ 'id' ] ) !== SAMPLE_FROMID ) {
        return false;
    }
    
    $firstname = $entry['fields'][2]['first']; //Jaden Male
    $lastname  = $entry['fields'][2]['last']; //Jaden Male
    $useremail = $entry['fields'][3]; //qqdyfyxvxm@iubridge.com
    $company   = $entry['fields'][15] ?? ''; //Company
    $usertel   = $entry['fields'][13] ?? ''; //7405038755
    $useradd1  = $entry['fields'][43]['address1'] ?? ''; //3847  Despard Street
    $useradd2  = $entry['fields'][43]['address2'] ?? ''; //3847  Despard Street
    $city      = $entry['fields'][43]['city'] ?? ''; //BALTIMORE
    $state     = $entry['fields'][43]['state'] ?? ''; //Ohio
    $postal    = $entry['fields'][43]['postal'] ?? ''; //43105
    $country   = $entry['fields'][43]['country'] ?? ''; //US

    //只针对一些国家
    $qin_options = get_option( 'wp_sample_countrylist' );
    $sample_countrylist = ( is_serialized( $qin_options )) ? unserialize( $qin_options ) : $qin_options;

    if ( is_array($sample_countrylist) && count($sample_countrylist) ) {

        if ( in_array($country, $sample_countrylist) ) {
            //正常支付
            //把信息保存到session里面
            
            $_SESSION['SAMPLESFNAME'] = $firstname;
            $_SESSION['SAMPLESLNAME'] = $lastname;
            $_SESSION['SAMPLESEMAIL'] = $useremail;
            $_SESSION['SAMPLESCOMPA'] = $company;
            $_SESSION['SAMPLESTELEP'] = $usertel;
            $_SESSION['SAMPLESADDR1'] = $useradd1;
            $_SESSION['SAMPLESADDR2'] = $useradd2;
            $_SESSION['SAMPLESCITY'] = $city;
            $_SESSION['SAMPLESSTATE'] = $state;
            $_SESSION['SAMPLESPOST'] = $postal;
            $_SESSION['SAMPLESCOUN'] = $country;
 
        } else {

            //不需要支付
            return false;
        }
    } else {
        //不需要支付
        return false;
    }
    
    $booktype  = $entry['fields'][30] ?? [];
    /*
    $entry['fields'][30][]; //Book
    $entry['fields'][30][]; //Board Book
    $entry['fields'][30][]; //Catalog/Booklet
    $entry['fields'][30][]; //Calendar
    $entry['fields'][30][]; //Poster
    $entry['fields'][30][]; //Flyer
    $entry['fields'][30][]; //Folding Carton Box
    $entry['fields'][30][]; //Rigid Box
    */
    
    $bookbinding = $entry['fields'][31] ?? [];
    /*
    $entry['fields'][31][]; //Hardcover Binding
    $entry['fields'][31][]; //Softcover Binding
    $entry['fields'][31][]; //Saddle Stitch Binding
    $entry['fields'][31][]; //Wire-O Binding
    $entry['fields'][31][]; //Spiral Binding
    */
    
    $boardbook = $entry['fields'][32] ?? [];
    /*
    $entry['fields'][32][]; //Hardcover Board Book
    $entry['fields'][32][]; //Self-Cover Board Book
    */
    
    $catlogbinding = $entry['fields'][34] ?? [];
    /*
    $entry['fields'][34][]; //Softcover Binding
    $entry['fields'][34][]; //Saddle Stitch Binding
    $entry['fields'][34][]; //Wire-O Binding
    $entry['fields'][34][]; //Spiral Binding
    */
    
    $calendartype = $entry['fields'][33] ?? [];
    /*
    $entry['fields'][33][]; //Wire-O Wall Calendar
    $entry['fields'][33][]; //Saddle Stitch Calendar
    $entry['fields'][33][]; //Desk Calendar
    $entry['fields'][33][]; //Three-Month Calendar
    $entry['fields'][33][]; //Desk Pad Calendar
    */
    
    $boxtype = $entry['fields'][35] ?? [];
    /*
    $entry['fields'][35][]; //Collapsible Rigid Box
    $entry['fields'][35][]; //Magnetic Closure Rigid Box
    $entry['fields'][35][]; //Telescope Rigid Box
    */
    
    $productsize = $entry['fields'][17]; //product size
    $anything    = $entry['fields'][18]; //Anything

    $price = SAMPLE_FEE;
    $fq1 = SAMPLE_Q1 - 0;
    $fq2 = SAMPLE_Q2 - 0;
    $fq3 = SAMPLE_Q3 - 0;
    $wei = SAMPLE_KG - 0;
    
    if ( count($booktype) == 0 ) {
        $price = SAMPLE_FEE;
    } else {
    
        if ( count($booktype) == 1 ) {

            //只选一个产品，看其他组合
            
            $bdqty = count($bookbinding);
            if ( $bdqty == 2 ) {
                $price += $fq1 - 0;
            }
            if ( $bdqty > 2 ) {
                $price += $fq1 - 0;//第二个
                $price += ($fq2 - 0) * ($bdqty - 2);//第3个以后
            }
            if ( in_array('Hardcover Binding', $bookbinding) ) {
                $price += $fq3 - 0;
            }
            
            $bdqty = count($boardbook);
            if ( $bdqty == 2 ) {
                $price += $fq1 - 0;
            }
            if ( $bdqty > 2 ) {
                $price += $fq1 - 0;//第二个
                $price += ($fq2 - 0) * ($bdqty - 2);//第3个以后
            }
            
            $bdqty = count($catlogbinding);
            if ( $bdqty == 2 ) {
                $price += $fq1 - 0;
            }
            if ( $bdqty > 2 ) {
                $price += $fq1 - 0;//第二个
                $price += ($fq2 - 0) * ($bdqty - 2);//第3个以后
            }
            
            $bdqty = count($calendartype);
            if ( $bdqty == 2 ) {
                $price += $fq1 - 0;
            }
            if ( $bdqty > 2 ) {
                $price += $fq1 - 0;//第二个
                $price += ($fq2 - 0) * ($bdqty - 2);//第3个以后
            }
            
            $bdqty = count($boxtype);
            if ( $bdqty == 2 ) {
                $price += $fq1 - 0;
            }
            if ( $bdqty > 2 ) {
                $price += $fq1 - 0;//第二个
                $price += ($fq2 - 0) * ($bdqty - 2);//第3个以后
            }
            
        } elseif ( count($booktype) == 2 ) {

            $price += $fq1 - 0;
            //只选2个产品，看其他组合
            
            $bdqty = count($bookbinding) == 0 ? 1 : count($bookbinding);
            $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
            if ( in_array('Hardcover Binding', $bookbinding) ) {
                $price += $fq3 - 0;
            }
            
            $bdqty = count($boardbook) == 0 ? 1 : count($boardbook);
            $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
            
            $bdqty = count($catlogbinding) == 0 ? 1 : count($catlogbinding);
            $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
            
            $bdqty = count($calendartype) == 0 ? 1 : count($calendartype);
            $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
            
            $bdqty = count($boxtype) == 0 ? 1 : count($boxtype);
            $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
            
        } else {

            $price += $fq1 - 0;
            $price += ($fq2 - 0) * (count($booktype) - 2);//第2个以后
            
            //看其他组合
            $bdqty = count($bookbinding) == 0 ? 1 : count($bookbinding);
            $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
            if ( in_array('Hardcover Binding', $bookbinding) ) {
                $price += $fq3 - 0;
            }
            
            $bdqty = count($boardbook) == 0 ? 1 : count($boardbook);
            $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
            
            $bdqty = count($catlogbinding) == 0 ? 1 : count($catlogbinding);
            $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
            
            $bdqty = count($calendartype) == 0 ? 1 : count($calendartype);
            $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
            
            $bdqty = count($boxtype) == 0 ? 1 : count($boxtype);
            $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
            
        }
        
    }
    
    if ( !isset($_SESSION['SAMPLESHIPPINGDATA']) || empty($_SESSION['SAMPLESHIPPINGDATA']) ) {
        //wp_safe_redirect( site_url() );
        //exit;
        return true;
    }
    
    $checkprice = false;
    if ( isset($_SESSION['SAMPLESHIPPINGDATA']['haiyun']['gv']) && $_SESSION['SAMPLESHIPPINGDATA']['haiyun']['gv'] == $_POST['sampleshippingcost'] ) {
        $checkprice = true;
    }
    if ( isset($_SESSION['SAMPLESHIPPINGDATA']['kongyun']['gv']) && $_SESSION['SAMPLESHIPPINGDATA']['kongyun']['gv'] == $_POST['sampleshippingcost'] ) {
        $checkprice = true;
    }
    if ($checkprice == false ) {
        //wp_safe_redirect( get_permalink( SAMPLE_PAGEID ) );
        //exit;
        return true;
    }
    
    $price = (int)$_POST['sampleshippingcost'];
    $gst_price = round(SAMPLE_GST / 100 * $price, 2);
    $pg = $price + $gst_price;
                
    $_SESSION['SAMPLESTXT'] = generate_sampledetails($entry);
    $_SESSION['SAMPLESDIV'] = generate_sampledetails_div($entry);
    $_SESSION['SAMPLESFEE'] = $price;
    $_SESSION['SAMPLESGST'] = $gst_price;
    //$_SESSION['SAMPLESPIC'] = $price;

    WC()->cart->empty_cart();
    //WC()->cart->add_to_cart( $product_id, 1, 0, array(), array( 'qin_custom_price' => $price ) );
    WC()->cart->add_to_cart( $product_id );

    wp_safe_redirect( wc_get_checkout_url() );
    exit();
}

function generate_sampledetails($entry) {
    
    $booktypes  = $entry['fields'][30] ?? [];
    
    $details = 'Sample Details:' . "\r\n";
    foreach ( $booktypes as $i => $b ) {
        $details .= "\r\n" . intval($i + 1) . '. ' . $b ;
        
        if ( $b == 'Book' ) {
            $bds = $entry['fields'][31] ?? [];
            $detailsArr = [];
            foreach ($bds as $bd) {
                $detailsArr[] = $bd;
            }
            count($detailsArr) && $details .= " ( " . implode(', ', $detailsArr) . " ) \r\n";
        }
        
        if ( $b == 'Board Book' ) {
            $bds = $entry['fields'][32] ?? [];
            $detailsArr = [];
            foreach ($bds as $bd) {
                $detailsArr[] = $bd;
            }
            count($detailsArr) && $details .= " ( " . implode(', ', $detailsArr) . " ) \r\n";
        }
        
        if ( $b == 'Catalog/Booklet' ) {
            $bds = $entry['fields'][34] ?? [];
            $detailsArr = [];
            foreach ($bds as $bd) {
                $detailsArr[] = $bd;
            }
            count($detailsArr) && $details .= " ( " . implode(', ', $detailsArr) . " ) \r\n";
        }
        
        if ( $b == 'Calendar' ) {
            $bds = $entry['fields'][33] ?? [];
            $detailsArr = [];
            foreach ($bds as $bd) {
                $detailsArr[] = $bd;
            }
            count($detailsArr) && $details .= " ( " . implode(', ', $detailsArr) . " ) \r\n";
        }
        
        if ( $b == 'Rigid Box' ) {
            $bds = $entry['fields'][35] ?? [];
            $detailsArr = [];
            foreach ($bds as $bd) {
                $detailsArr[] = $bd;
            }
            count($detailsArr) && $details .= " ( " . implode(', ', $detailsArr) . " ) \r\n";
        }
        
        $details .= "\r\n";
    }


    $productsize = $entry['fields'][17]; //product size
    $anything    = $entry['fields'][18]; //Anything
    
    
    $productsize && $details .= "\r\nProduct Size: " . $productsize . "\r\n";
    $anything && $details .= "\r\nAnything:\r\n" . $anything . "\r\n";
    
    return $details;
}

function generate_sampledetails_div($entry) {
    
    $booktypes  = $entry['fields'][30] ?? [];
    
    $details = '<h3 style="line-height: 1.8rem;">Sample Details:</h3>' . "\r\n";
    
    $details .= '<div style="border:1px solid #ccc;padding: 15px;"><div style="line-height: 1.8rem;color: #00afdd;font-weight:bold;margin: 0 0 10px 0;">Product Type:</div>' . "\r\n";
    
    foreach ( $booktypes as $i => $b ) {
        
        $details .= '<p style="margin-bottom:0">' . $b;
        
        if ( $b == 'Book' ) {

            $bds = $entry['fields'][31] ?? [];
            $detailsArr = [];
            foreach ($bds as $bd) {
                $detailsArr[] = $bd;
            }
            count($detailsArr) && $details .= " ( " . implode(', ', $detailsArr) . ' ) ';

        }
        
        if ( $b == 'Board Book' ) {

            $bds = $entry['fields'][32] ?? [];
            $detailsArr = [];
            foreach ($bds as $bd) {
                $detailsArr[] = $bd;
            }
            count($detailsArr) && $details .= " ( " . implode(', ', $detailsArr) . ' ) ';

        }
        
        if ( $b == 'Catalog/Booklet' ) {

            $bds = $entry['fields'][34] ?? [];
            $detailsArr = [];
            foreach ($bds as $bd) {
                $detailsArr[] = $bd;
            }
            count($detailsArr) && $details .= " ( " . implode(', ', $detailsArr) . ' ) ';

        }
        
        if ( $b == 'Calendar' ) {

            $bds = $entry['fields'][33] ?? [];
            $detailsArr = [];
            foreach ($bds as $bd) {
                $detailsArr[] = $bd;
            }
            count($detailsArr) && $details .= " ( " . implode(', ', $detailsArr) . ' ) ';

        }
        
        if ( $b == 'Rigid Box' ) {

            $bds = $entry['fields'][35] ?? [];
            $detailsArr = [];
            foreach ($bds as $bd) {
                $detailsArr[] = $bd;
            }
            count($detailsArr) && $details .= " ( " . implode(', ', $detailsArr) . ' ) ';
            
        }
        
        $details .= "</p>\r\n";
    }


    $productsize = $entry['fields'][17]; //product size
    $anything    = $entry['fields'][18]; //Anything
    
    
    $productsize && $details .= "\r\n" . '<div style="line-height: 1.8rem;color: #00afdd;font-weight:bold;margin: 12px 0 10px 0;">Product Size: </div><p>' . $productsize . "</p>\r\n";
    $anything && $details .= "\r\n" . '<div style="line-height: 1.8rem;color: #00afdd;font-weight:bold;margin: 0 0 10px 0;">Other Requirements: </div><p>' . $anything . "</p>\r\n";
    
    $details .= '</div>';
    return $details;
}
add_action( 'wpforms_process_complete', 'wpform_sample_order_deal', 10, 4 );

//设置物流名称

add_filter( 'woocommerce_package_rates', 'override_ups_rates' );
function override_ups_rates( $rates ) {
    
    if ( isset($_SESSION['PRODUCSHIPPINGCOST']) ) :
        foreach( $rates as $rate_key => $rate ){
            // Check if the shipping method ID is UPS for example
            if( 'Flat rate' == $rate->label  ) {
                // Set cost to zero
                $rates[$rate_key]->label = $_SESSION['PRODUCTSHIPPINGNAME'];
                $rates[$rate_key]->cost = $_SESSION['PRODUCSHIPPINGCOST'];
            } 
        }
    endif;
    return $rates;        
}

//设置产品价格
function qin_custom_price_refresh( $cart_object ) {
    
	foreach ( $cart_object->get_cart() as $item ) {

		if( array_key_exists( 'qin_product_price', $item ) ) {
			$item[ 'data' ]->set_price( $item[ 'qin_product_price' ] );
            //$cart_object->add_fee( 'Shipping Cost', $item[ 'qin_shipping_cost' ], true, '' );
            return;
		}
      
	}
    if ( !isset($_SESSION['SAMPLESFEE']) ) {
        return;
    }

    $cart_object->add_fee( 'Shipping Cost', $_SESSION['SAMPLESFEE'], true, '' );
    if ( SAMPLE_GST > 0 ) {
        $cart_object->add_fee( 'GST ' . SAMPLE_GST . '%', $_SESSION['SAMPLESGST'], true, '' );
    }
    
	
}
add_action( 'woocommerce_before_calculate_totals', 'qin_custom_price_refresh' );

function qin_checkout_fields( $checkout_fields ) {
    
    $readonly = ['readonly' => 'readonly'];

    $checkout_fields['billing']['billing_country']['custom_attributes'] = $readonly;
    $checkout_fields['billing']['billing_postcode']['custom_attributes'] = $readonly;

    $checkout_fields['shipping']['shipping_country']['custom_attributes'] = $readonly;
    $checkout_fields['shipping']['shipping_postcode']['custom_attributes'] = $readonly;
        
	$checkout_fields[ 'billing' ][ 'billing_phone' ][ 'priority' ] = 30;
	$checkout_fields[ 'billing' ][ 'billing_company' ][ 'priority' ] = 100;
	return $checkout_fields;
}
add_filter( 'woocommerce_checkout_fields' , 'qin_checkout_fields' );

//文件上传
add_action( 'wp_ajax_mishaupload', 'misha_file_upload' );
add_action( 'wp_ajax_nopriv_mishaupload', 'misha_file_upload' );
function misha_file_upload(){

	$upload_dir = wp_upload_dir();
    //print_r($_FILES);exit;
    
	if ( isset( $_FILES[ 'misha_file' ][ 'name' ] ) ) {
        $c = count($_FILES[ 'misha_file' ][ 'name' ]);
        $data = [];
        $_SESSION['UPLOADFILES'] = [];
        for ( $i = 0; $i < $c; $i++) {
        
            $path = $upload_dir[ 'path' ] . '/' . basename( $_FILES[ 'misha_file' ][ 'name' ][$i] );
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)); // "exe"
            //过滤php exe
            if ( in_array($ext, ['php','exe','html','html','js','txt','shtml','mp3','mp4','sh','py','zip','rar','7z','tar','tar.gz']) ) {
                continue;
            }
            $filename = date('YmdHis') . $i . '.' . $ext;
            $newPath = $upload_dir[ 'path' ] . '/' . $filename;

            if( move_uploaded_file( $_FILES[ 'misha_file' ][ 'tmp_name' ][$i], $newPath ) ) {
                $data[] = [$_FILES[ 'misha_file' ][ 'name' ][$i], $upload_dir[ 'url' ] . '/' . $filename];
                $_SESSION['UPLOADFILES'][] = [$_FILES[ 'misha_file' ][ 'name' ][$i], $upload_dir[ 'url' ] . '/' . $filename];
            }
        }
        echo empty($data) ? json_encode(['state' => 'error']) : json_encode(['state' => 'ok', 'data' => $data]);
	} else {
        echo json_encode(['state' => 'error']);
    }
    
	die;
}

//后台订单显示上传的信息
add_action( 'woocommerce_admin_order_data_after_order_details', 'misha_order_meta_general' );

function misha_order_meta_general( $order ){

	$files = get_post_meta( $order->get_id(), 'misha_file_field', true );
    $filedata = ( is_serialized( $files )) ? unserialize( $files ) : $files;
	if( !empty($filedata) ) {
        foreach ($filedata as $fk => $file) {
            $i = $fk + 1;
            echo '<br /><a target="_blank" href="' . esc_url( $file ) . '">File ' . $i .'</a>';
        }
		
	}

}

//保存上传文件信息到订单
add_action( 'woocommerce_checkout_update_order_meta', 'misha_save_what_we_added' );

function misha_save_what_we_added( $order_id ){

	if( ! empty( $_POST[ 'misha_file_field' ] ) ) {
		update_post_meta( $order_id, 'misha_file_field', serialize( $_POST[ 'misha_file_field' ] ) );
	}

}

//支付页面显示sample信息
function qin_sampleorder_checkbox( $checkout ) {

    //支付页面显示报价信息
    if ( isset($_SESSION['PRODUCTNOTE'])) {
        //echo '<div><h3>Quote Details</h3></div>';
        //echo '<div>' . str_replace("\r\n", '<br/>', $_SESSION['PRODUCTNOTE']) . '</div>';
        echo '<div style="display:none;">';
        woocommerce_form_field( 
            'sampledetails', 
            array(
                'type'	=> 'textarea',
                'class'	=> array( 'woocommerce-additional-fields__field-wrapper' ),
                'label'	=> 'Details',
            'custom_attributes' => ['readonly' => 'readonly'],
            ),
            $_SESSION['PRODUCTNOTE']
        );
        echo '</div>'; 
        
        //显示上传按钮
        echo '
        <style>
        #misha_file{
            position: absolute;
            height: 1px;
            width: 1px;
            overflow: hidden;
            clip: rect(1px, 1px, 1px, 1px);
            margin-top:10px;
            
        }
        #misha_filelist{border:1px solid #fff;}
        .uploada{
            padding: 15px 35px;
            color: white !important;
            background-color: #00aedc;
            cursor:pointer;
        }
        .uploada:hover{
            background-color: #098ec7;
        }
        </style>
        
		<div class="form-row form-row-wide">
            <div><h3>Upload Design</h3></div>
			<div style="margin-bottom:20px;" id="misha_filelist"></div>
			<div id="qin_filelist" style="display:hidden;"></div>
            <div style="margin-bottom:15px;">
			<input type="file" id="misha_file" name="misha_file" multiple />
			<label for="misha_file"><a class="uploada">Select your design file</a></label>
            </div>
            <div style="margin-bottom:35px;">
            * Preferred File Format: PDF<br/>
            * We recommend that images have a resolution of 300dpi<br/>
            * Be sure to add a 0.125" / 3mm bleed<br/>
            * Multiple files are available<br/>
            </div>
		</div>
        
        ';
        return true;
    }   
    
    //查看session是否过期
    if ( !isset($_SESSION['SAMPLESTXT'])) {
    
        if ( is_admin() ) {
            //return false;
        } else {
            wp_safe_redirect( site_url() );
            exit;
        }
    }
    
    echo '<div>' . $_SESSION['SAMPLESDIV'] . '</div>';
    echo '<div style="display:none;">';
	woocommerce_form_field( 
		'sampledetails', 
		array(
			'type'	=> 'textarea',
			'class'	=> array( 'woocommerce-additional-fields__field-wrapper' ),
			'label'	=> 'Sample Details',
        'custom_attributes' => ['readonly' => 'readonly'],
		),
		$_SESSION['SAMPLESTXT']
	);
    echo '</div>'; 
}
add_action( 'woocommerce_after_order_notes', 'qin_sampleorder_checkbox' );

//保存sample信息到订单
function qin_save_what_we_added( $order_id ){

	if( ! empty( $_POST[ 'sampledetails' ] ) ) {
		update_post_meta( $order_id, 'sampledetails', $_POST[ 'sampledetails' ] );
	}
    
}
add_action( 'woocommerce_checkout_update_order_meta', 'qin_save_what_we_added' );

//后台显示sample信息
function qin_editable_order_meta_general( $order ){

	?>
		<br class="clear" />
		<h3>Sample Order Details<a href="#" style="display:none;" class="edit_address">Edit</a></h3>
		<?php
			$sampledetails = $order->get_meta( 'sampledetails' );
		?>
		<div class="address">
        <div><?php echo wpautop( esc_html( $sampledetails ) ) ?></div>
		</div>
		<div class="edit_address">
			<?php

				woocommerce_wp_textarea_input( array(
					'id' => 'sampledetails',
					'label' => 'Details:',
					'value' => $sampledetails,
					'wrapper_class' => 'form-field-wide'
				) );

			?>
		</div>
	<?php 
}
add_action( 'woocommerce_admin_order_data_after_order_details', 'qin_editable_order_meta_general' );

add_filter( 'woocommerce_default_address_fields' , 'bbloomer_rename_state_province', 10 );
function bbloomer_rename_state_province( $fields ) {
    $fields['address_2']['label'] = 'Address 2';
    return $fields;
}

function qin_add_field( $fields ) {
	
    $fields[ 'shipping_address_2' ]['label'] = 'Address 2';
	$fields[ 'shipping_phone' ]   = array(
        'type'         => 'tel',
		'label'        => 'Shipping Phone',
		'required'     => true,
		'class'        => array( 'form-row-wide', 'form-row-full' ),
		'priority'     => 20,
		'placeholder'  => '',
	);
	
	return $fields;
}
add_filter( 'woocommerce_shipping_fields', 'qin_add_field' );

//my-account显示sample
function qin_order_details( $order ) {

	$sampledetails = get_post_meta( $order->get_id(), 'sampledetails', true );

	if( $sampledetails ) {
    
		?>
        <section class="woocommerce-customer-details" style="margin-top:15px;">
        <h2 class="woocommerce-column__title"><?php echo isset($_SESSION['PRODUCPRICE']) ? 'Quote Details' : 'Sample Order Details';?></h2>
        <address><?php echo wpautop( esc_html( $sampledetails ) ) ?></address>
        </section>
		<?php

    } else {
    
        echo '<p class="woocommerce-order-overview woocommerce-thankyou-order-details">No sample details</p>';
        
	}

    if ( isset($_SESSION['UPLOADFILES']) ) {
        echo '<section class="woocommerce-customer-details" style="margin-top:15px;">';
        echo '<h2 class="woocommerce-column__title">Upload Files</h2>';
        //print_r($_SESSION['UPLOADFILES']);
        foreach ($_SESSION['UPLOADFILES'] as $i => $upfiles) {
            echo '<div>File ' . ($i + 1) . '. ' . $upfiles[0] . '</div>'; 
        }
        echo '</section>';
    }

}
add_action( 'woocommerce_after_order_details', 'qin_order_details' );

// remove menu link
function qin_remove_my_account_dashboard( $menu_links ){
	
	unset( $menu_links[ 'dashboard' ] );
	return $menu_links;
	
}
add_filter( 'woocommerce_account_menu_items', 'qin_remove_my_account_dashboard' );
add_filter( 'woocommerce_order_item_permalink', '__return_false' );

// perform a redirect
function qin_redirect_to_orders_from_dashboard(){
	
	if( is_account_page() && empty( WC()->query->get_current_endpoint() ) ){
		wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
		exit;
	}
	
}
add_action( 'template_redirect', 'qin_redirect_to_orders_from_dashboard' );

//访客下单支付查找是否存在该用户
function action_woocommerce_new_order( $order_id ) {
    $order = new WC_Order($order_id);
    $user = $order->get_user();
    if( !$user ){
        //guest order
        $userdata = get_user_by( 'email', $order->get_billing_email() );

        if(isset( $userdata->ID )){
            //registered
            update_post_meta($order_id, '_customer_user', $userdata->ID );
        } else {
            //Guest
        }
    }
}
add_action( 'woocommerce_new_order', 'action_woocommerce_new_order', 10, 1 );

function switch_billing_shipping() {
	if(is_checkout()) {
	?>
    <style>
    .disabled-select {
      background-color: #d5d5d5;
      opacity: 0.5;
      border-radius: 3px;
      cursor: not-allowed;
      position: absolute;
      top: 0;
      bottom: 0;
      right: 0;
      left: 0;
    }

    select[readonly].select2-hidden-accessible + .select2-container {
      pointer-events: none;
      touch-action: none;
    }

    select[readonly].select2-hidden-accessible + .select2-container .select2-selection, .woocommerce input[readonly], .woocommerce select[readonly] {
      background: #eee;
      box-shadow: none;
    }

    select[readonly].select2-hidden-accessible + .select2-container .select2-selection__arrow,
    select[readonly].select2-hidden-accessible + .select2-container .select2-selection__clear {
      display: none;
    }
    </style>
    <script>
    
		jQuery( document ).ready( ($) => {
            var zipcode = "<?php echo addslashes($_SESSION['SAMPLESPOST']);?>";
            setTimeout( () => {
            
                $('#billing_country').find('option').each((i, n) => {
                    let cv = $(n).attr('value');
                    !in_array(cv, $sample_countrylist) && $(n).remove();
                });
                $('#shipping_country').find('option').each((i, n) => {
                    let cv = $(n).attr('value');
                    !in_array(cv, $sample_countrylist) && $(n).remove();
                });    
                //bill
                "<?php echo addslashes($_SESSION['SAMPLESFNAME']);?>" && $('#billing_first_name').val("<?php echo addslashes($_SESSION['SAMPLESFNAME']);?>");
                "<?php echo addslashes($_SESSION['SAMPLESLNAME']);?>" && $('#billing_last_name').val("<?php echo addslashes($_SESSION['SAMPLESLNAME']);?>");
                "<?php echo addslashes($_SESSION['SAMPLESCOMPA']);?>" && $('#billing_company').val("<?php echo addslashes($_SESSION['SAMPLESCOMPA']);?>");
                "<?php echo addslashes($_SESSION['SAMPLESEMAIL']);?>" && $('#billing_email').val("<?php echo addslashes($_SESSION['SAMPLESEMAIL']);?>");
                "<?php echo addslashes($_SESSION['SAMPLESCOUN']);?>" && $('#billing_country').val("<?php echo addslashes($_SESSION['SAMPLESCOUN']);?>");
                "<?php echo addslashes($_SESSION['SAMPLESCOUN']);?>" && $('#shipping_country').val("<?php echo addslashes($_SESSION['SAMPLESCOUN']);?>");
                "<?php echo addslashes($_SESSION['SAMPLESADDR1']);?>" && $('#billing_address_1').val("<?php echo addslashes($_SESSION['SAMPLESADDR1']);?>");
                "<?php echo addslashes($_SESSION['SAMPLESADDR2']);?>" && $('#billing_address_2').val("<?php echo addslashes($_SESSION['SAMPLESADDR2']);?>");
                "<?php echo addslashes($_SESSION['SAMPLESCITY']);?>" && $('#billing_city').val("<?php echo addslashes($_SESSION['SAMPLESCITY']);?>");
                "<?php echo addslashes($_SESSION['SAMPLESPOST']);?>" && $('#billing_postcode').val("<?php echo addslashes($_SESSION['SAMPLESPOST']);?>");
                "<?php echo addslashes($_SESSION['SAMPLESPOST']);?>" && $('#shipping_postcode').val("<?php echo addslashes($_SESSION['SAMPLESPOST']);?>");
                "<?php echo addslashes($_SESSION['SAMPLESTELEP']);?>" && $('#billing_phone').val("<?php echo addslashes($_SESSION['SAMPLESTELEP']);?>");
                "<?php echo addslashes($_SESSION['SAMPLESSTATE']);?>" && $('#billing_state').val("<?php echo addslashes($_SESSION['SAMPLESSTATE']);?>");
                
                <?php if (isset($_SESSION['PRODUCPRICE'])) :?>
                
                $('#billing_postcode').prop('readonly', true);
                $('#shipping_postcode').prop('readonly', true);
                $('#billing_country').attr('readonly', true);
                $('#shipping_country').attr('readonly', true);

                $('#billing_country').find('option').each((i, n) => {
                    let cv = $(n).attr('value');
                    !in_array(cv, ["<?php echo addslashes($_SESSION['SAMPLESCOUN']);?>"]) && $(n).remove();
                });
                
                $('#shipping_country').find('option').each((i, n) => {
                    let cv = $(n).attr('value');
                    !in_array(cv, ["<?php echo addslashes($_SESSION['SAMPLESCOUN']);?>"]) && $(n).remove();
                });
                
                <?php endif;?>
                
                $('#billing_postcode, #shipping_postcode').change(function () {
                    return false;
                });
                
                //触发事件
                $('#billing_country').trigger('change');
                $('#billing_state option').length && $('#billing_state option').filter(function(){return $(this).attr('value').toUpperCase() == "<?php $ss = $_SESSION['SAMPLESSTATE'] == '' ? 'AL' : strtolower(addslashes($_SESSION['SAMPLESSTATE']));echo $ss;?>";}).attr("selected",true);  
                $('#billing_state').trigger('change');
                
                let n = document.querySelector("#billing_email");
                window.validateInlineEmail && window.validateInlineEmail();

                $('select#shipping_country, select#billing_country, input#shipping_postcode, input#billing_postcode').change( function () {return false;});
                $('select#shipping_country, select#billing_country, input#shipping_postcode, input#billing_postcode').click( function () {return false;});
        
            }, 789);
        });
      
	</script>
	<?php
	}	
    //echo '<!--';
    //var_dump( SAMPLE_PAGEID );
    //var_dump( is_page( SAMPLE_PAGEID ) );
    //echo '-->';
    //if ( is_page( SAMPLE_PAGEID ) ) {
        $qin_options = get_option( 'wp_sample_countrylist' );
        $sample_countrylist = ( is_serialized( $qin_options )) ? unserialize( $qin_options ) : $qin_options;

    ?>
    <script>
    const $sample_countrylist = ['<?php echo implode("', '", $sample_countrylist);?>'];
    jQuery(document).ready( ($) => {
        const page_id = <?php echo SAMPLE_PAGEID;?>;
        const $fee =  <?php echo (float)SAMPLE_FEE;?>;
        const $gst =  <?php echo (float)SAMPLE_GST;?>;
        const $fq1 =  <?php echo (float)SAMPLE_Q1;?>;
        const $fq2 =  <?php echo (float)SAMPLE_Q2;?>;
        const $fq3 =  <?php echo (float)SAMPLE_Q3;?>;
        const $cur =  '<?php echo SAMPLE_CUR;?>';
        const $wei =  '<?php echo SAMPLE_KG;?>' - 0;
        const count = (arr) => arr.length;
        
        const div = $('.totalresult');
        const country = $('select.wpforms-field-address-country');
        
        var changeEve =  function() {
            //console.log($('.producttype').find("input[type='checkbox']:checked").length)
            var $booktype = $('.producttype').find("input[type='checkbox']:checked");
            var $bookbinding = $('.bookbinding').find("input[type='checkbox']:checked");
            var $boardbook = $('.boardbook').find("input[type='checkbox']:checked");
            var $calendartype = $('.calendartype').find("input[type='checkbox']:checked");
            var $catlogbinding = $('.catlogbinding').find("input[type='checkbox']:checked");
            var $boxtype = $('.boxtype').find("input[type='checkbox']:checked");
            
            var $price =  $fee;
            var $bdqty = 0;
            
            var totalcount = count($booktype);
            var bv = $bookbinding.map( (c,i,b) => ($(i).val()) );
            var postcode = $('input.wpforms-field-address-postal');
            
            var doing = false;
            //console.log(totalcount);
            if ( count($booktype) == 0 ) {
                $price = $fee;
                totalcount = 1;
            } else {
                
                if ( count($booktype) == 1 ) {

                    //只选一个产品，看其他组合
                    
                    $bdqty = count($bookbinding);
                    
                    if ( $bdqty == 2 ) {
                        $price += $fq1 - 0;
                    }
                    if ( $bdqty > 2 ) {
                        $price += $fq1 - 0;//第二个
                        $price += ($fq2 - 0) * ($bdqty - 2);//第3个以后
                    }
                    if ( in_array('Hardcover Binding', bv) ) {
                        $price += $fq3 - 0;
                        totalcount += $bdqty == 1 ? 1 : $bdqty + 1 - 1;
                    } else {
                        totalcount += (count($bookbinding)  > 1) ? ($bdqty - 1) : 0;
                    }
                    
                    $bdqty = count($boardbook);
                    totalcount += (count($boardbook)  > 1) ? ($bdqty - 1) : 0;
                    if ( $bdqty == 2 ) {
                        $price += $fq1 - 0;
                    }
                    if ( $bdqty > 2 ) {
                        $price += $fq1 - 0;//第二个
                        $price += ($fq2 - 0) * ($bdqty - 2);//第3个以后
                    }
                    
                    $bdqty = count($catlogbinding);
                    totalcount += (count($catlogbinding)  > 1) ? ($bdqty - 1) : 0;
                    if ( $bdqty == 2 ) {
                        $price += $fq1 - 0;
                    }
                    if ( $bdqty > 2 ) {
                        $price += $fq1 - 0;//第二个
                        $price += ($fq2 - 0) * ($bdqty - 2);//第3个以后
                    }
                    
                    $bdqty = count($calendartype);
                    totalcount += (count($calendartype)  > 1) ? ($bdqty - 1) : 0;
                    if ( $bdqty == 2 ) {
                        $price += $fq1 - 0;
                    }
                    if ( $bdqty > 2 ) {
                        $price += $fq1 - 0;//第二个
                        $price += ($fq2 - 0) * ($bdqty - 2);//第3个以后
                    }
                    
                    $bdqty = count($boxtype);
                    totalcount += (count($boxtype)  > 1) ? ($bdqty - 1) : 0;
                    if ( $bdqty == 2 ) {
                        $price += $fq1 - 0;
                    }
                    if ( $bdqty > 2 ) {
                        $price += $fq1 - 0;//第二个
                        $price += ($fq2 - 0) * ($bdqty - 2);//第3个以后
                    }
                    
                } else if ( count($booktype) == 2 ) {

                    $price += $fq1 - 0;
                    //只选2个产品，看其他组合
                    
                    $bdqty = count($bookbinding) == 0 ? 1 : count($bookbinding);
                    
                    $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
                    if ( in_array('Hardcover Binding', bv) ) {
                        $price += $fq3 - 0;
                        totalcount += $bdqty == 1 ? 1 : $bdqty + 1 - 1;
                    } else {
                        totalcount += (count($bookbinding)  > 1) ? ($bdqty - 1)  : 0;
                    }
                    
                    $bdqty = count($boardbook) == 0 ? 1 : count($boardbook);
                    totalcount += (count($boardbook)  > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
                    
                    $bdqty = count($catlogbinding) == 0 ? 1 : count($catlogbinding);
                    totalcount += (count($catlogbinding)  > 0) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
                    
                    $bdqty = count($calendartype) == 0 ? 1 : count($calendartype);
                    totalcount += (count($calendartype)  > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
                    
                    $bdqty = count($boxtype) == 0 ? 1 : count($boxtype);
                    totalcount += (count($boxtype)  > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
                    
                } else {

                    $price += $fq1 - 0;
                    $price += ($fq2 - 0) * (count($booktype) - 2);//第2个以后
                    
                    //看其他组合
                    $bdqty = count($bookbinding) == 0 ? 1 : count($bookbinding);
                    $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
                    if ( in_array('Hardcover Binding', bv) ) {
                        $price += $fq3 - 0;
                        totalcount += $bdqty == 1 ? 1 : $bdqty + 1 - 1;
                    } else {
                        totalcount += (count($bookbinding)  > 1) ? ($bdqty - 1) : 0;
                    }
                    
                    $bdqty = count($boardbook) == 0 ? 1 : count($boardbook);
                    totalcount += (count($boardbook)  > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
                    
                    $bdqty = count($catlogbinding) == 0 ? 1 : count($catlogbinding);
                    totalcount += (count($catlogbinding)  > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
                    
                    $bdqty = count($calendartype) == 0 ? 1 : count($calendartype);
                    totalcount += (count($calendartype)  > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
                    
                    $bdqty = count($boxtype) == 0 ? 1 : count($boxtype);
                    totalcount += (count($boxtype)  > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);//第2个以后
                    
                }

            }

            if ( postcode.val() == "" ) {
                //layer.alert("please input your address first.", {title:'Reminding', btn:['OK']});
                div.hide();
                $('body').find('.sample-shiping-cost-text').remove();
                $('.wpform-shipping-cost input').length && $('.wpform-shipping-cost input').val('');
                return true;
            } 
            
            if ( doing ) {
                return true;
            }
            
            if ( country.val() && in_array(country.val(), $sample_countrylist) ) {
                
                let _form = $(this).closest('form');
                //$.busyLoadFull("show");
                _form.find('.wpforms-submit-container').busyLoad("show", {animation: "fade"});
                //$(this).closest('form').find('.wpforms-submit-container button').attr('disabled', true);
                //$(this).closest('form').find('.wpforms-submit-container img').show();
                doing = true;
                
                $.post('/wp-json/qinprinting/v1/shippingcost', {'weight': ($wei * totalcount).toFixed(2), 'zipcode': postcode.val(), 'country': country.val()}, function(d) {

                    var shippingcost = 0;
                    var shippingtext = '<div class="sample-shiping-cost-text" style="display:none;"><hr style="margin-bottom: 10px;background-color: #00afdd"><div></div><hr style="margin-bottom: 10px;background-color: #00afdd"></div>';
                    $('body').find('.sample-shiping-cost-text').remove();
                    div.before(shippingtext);
                    
                    if ( d.message == 'ok') {
                        let html = '';
                        let selected = ' checked="checked"';
                        
                        //if ( typeof d.haiyun.gv != 'undefined' ) {
                        //    html += '<div style="margin-bottom:8px;"><input type="radio" data-name="' + d.haiyun.name + '" name="sampleshippingcost" value="' + d.haiyun.gv + '" ' + selected + '>&nbsp;&nbsp;Sea Shipping ' + d.haiyun.symbol + d.haiyun.gv + ' (Transit time: around 35 days)</div>';
                        //    selected = '';
                        //    shippingcost = d.haiyun.gv - 0;
                        //}
                        
                        if ( typeof d.kongyun.gv != 'undefined' ) {
                            html += '<div style="margin-bottom:8px;"><input type="radio" data-name="' + d.kongyun.name + '" name="sampleshippingcost" value="' + d.kongyun.gv + '" ' + selected + '>&nbsp;&nbsp;Air Shipping ' + d.kongyun.symbol + d.kongyun.gv + ' (Transit time: around 7 days)</div>';
                            shippingcost = shippingcost == 0 ? parseInt(d.kongyun.gv - 0) : shippingcost;
                        }

                        if ( shippingcost ) {
                            $('body').find('.sample-shiping-cost-text div').html(html);
                            $price = shippingcost;
                            $gst_price = ($gst / 100 * $price).toFixed(2) - 0;
                            $price = ($price-0).toFixed(2);
                            let pg = (($price - 0) + ($gst_price - 0)).toFixed(2);
                            
                            $('span.shippingcur').text($cur);
                            $('span.shippingtotal').text($price);
                            $('span.shippinggst').text($gst);
                            $('span.shippinggstfee').text($gst_price);
                            $('span.grandtotal').text(pg);
                            $('.wpform-shipping-cost input').length && $('.wpform-shipping-cost input').val($cur + pg);
                        
                            div.show();
                        } else {
                            div.hide();
                            $('body').find('.sample-shiping-cost-text').remove();
                            $('.wpform-shipping-cost input').length && $('.wpform-shipping-cost input').val('');
                        }

                        
                    } else {
                        div.hide();
                        $('body').find('.sample-shiping-cost-text').remove();
                        $('.wpform-shipping-cost input').length && $('.wpform-shipping-cost input').val('');
                    }
                    
                    //_form.find('.wpforms-submit-container button').attr('disabled', false);
                    //_form.find('.wpforms-submit-container img').hide();
                    _form.find('.wpforms-submit-container').busyLoad("hide", {animation: "fade", animationDuration: "slow"});
                    doing = false;
                                
                }); 
                return true;

            } else {
                div.hide();
                $('.wpform-shipping-cost input').length && $('.wpform-shipping-cost input').val('');
            }
        };
        
        //仅限特定页面
        if ( country.length ) {
            country.change( changeEve );
            $('.wpforms-form input:checkbox').change( changeEve );
            //$('.wpforms-form input:checkbox').trigger('change');
            
            $(document).on('click', 'input[name="sampleshippingcost"]', function () {
                let $price = $(this).val();
                let $gst_price = ($gst / 100 * $price).toFixed(2) - 0;
                $price = ($price-0).toFixed(2);
                let pg = (($price - 0) + ($gst_price - 0)).toFixed(2);
                
                $('span.shippingcur').text($cur);
                $('span.shippingtotal').text($price);
                $('span.shippinggst').text($gst);
                $('span.shippinggstfee').text($gst_price);
                $('span.grandtotal').text(pg);
                $('.wpform-shipping-cost input').length && $('.wpform-shipping-cost input').val($cur + pg);
            
                //div.show();
            });
            
            $('input.wpforms-field-address-postal').change(changeEve);
        }


        var lt = null;
        var is_login = '<?php echo is_user_logged_in();?>';
    
        if ( is_login ) {
            $('.account-login a').click(function (event) {
                event.stopPropagation()
                this.blur();
                //console.log($('.my-account-indicator-dropdown').is(':visible'));
                //lt && clearTimeout(lt);
                //$('.my-account-indicator-dropdown').is(':visible') ? $('.my-account-indicator-dropdown').hide() : $('.my-account-indicator-dropdown').show();
                //lt = setTimeout(() => $('.my-account-indicator-dropdown').hide(), 2024);
                $('.my-account-indicator-dropdown').toggle();
            });
            $('body').click(function (e) {
                //console.log($(e.target).attr('class'));
                //console.log( $('.account-login').closest('section').attr('class'));
                //$(e.target).is($('.account-login').closest('section'))
                $(e.target).attr('class') == 'far fa-user' ?  $(e.target).attr('class') : $('.my-account-indicator-dropdown').hide();
            });
            setTimeout( () => $('.account-login a').attr('href', 'javascript:void(0)'), 586);
        } else {
            $('.account-login a').attr('href', '/my-account');
        }

    });
    
    </script>
    <?php    
    //}
}
add_action( 'wp_footer', 'switch_billing_shipping' );

function qin_logout_redirect() {
    //var_dump(is_wc_endpoint_url('customer-logout'));var_dump($_SESSION);exit;
    if( is_wc_endpoint_url('customer-logout') ) {
        wp_logout(); // Logout
 
        // Shop redirection url
        $redirect_url = home_url();
 
        wp_redirect($redirect_url); // Redirect to shop
 
        exit(); // Always exit
    }
}
add_action( 'template_redirect', 'qin_logout_redirect' );

class WPSampleCountryList {
    

	public $sample_countrylist = array();

    public $all_country = [
    
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AS' => 'American Samoa',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AI' => 'Anguilla',
        'AQ' => 'Antarctica',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AW' => 'Aruba',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BM' => 'Bermuda',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia (Plurinational State of)',
        'BQ' => 'Bonaire, Saint Eustatius and Saba',
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BV' => 'Bouvet Island',
        'BR' => 'Brazil',
        'IO' => 'British Indian Ocean Territory',
        'BN' => 'Brunei Darussalam',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'CV' => 'Cabo Verde',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'KY' => 'Cayman Islands',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CX' => 'Christmas Island',
        'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CG' => 'Congo',
        'CD' => 'Congo (Democratic Republic of the)',
        'CK' => 'Cook Islands',
        'CR' => 'Costa Rica',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CW' => 'Curaçao',
        'CY' => 'Cyprus',
        'CZ' => 'Czech Republic',
        'CI' => 'Côte d\'Ivoire',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'SZ' => 'Eswatini (Kingdom of)',
        'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands (Malvinas)',
        'FO' => 'Faroe Islands',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GF' => 'French Guiana',
        'PF' => 'French Polynesia',
        'TF' => 'French Southern Territories',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GR' => 'Greece',
        'GL' => 'Greenland',
        'GD' => 'Grenada',
        'GP' => 'Guadeloupe',
        'GU' => 'Guam',
        'GT' => 'Guatemala',
        'GG' => 'Guernsey',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HM' => 'Heard Island and McDonald Islands',
        'HN' => 'Honduras',
        'HK' => 'Hong Kong',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran (Islamic Republic of)',
        'IQ' => 'Iraq',
        'IE' => 'Ireland (Republic of)',
        'IM' => 'Isle of Man',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JE' => 'Jersey',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KP' => 'Korea (Democratic People\'s Republic of)',
        'KR' => 'Korea (Republic of)',
        'XK' => 'Kosovo',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Lao People\'s Democratic Republic',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MO' => 'Macao',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'YT' => 'Mayotte',
        'MX' => 'Mexico',
        'FM' => 'Micronesia (Federated States of)',
        'MD' => 'Moldova (Republic of)',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
        'MS' => 'Montserrat',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'NC' => 'New Caledonia',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'NU' => 'Niue',
        'NF' => 'Norfolk Island',
        'MK' => 'North Macedonia (Republic of)',
        'MP' => 'Northern Mariana Islands',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PS' => 'Palestine (State of)',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PN' => 'Pitcairn',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'PR' => 'Puerto Rico',
        'QA' => 'Qatar',
        'RO' => 'Romania',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'RE' => 'Réunion',
        'BL' => 'Saint Barthélemy',
        'SH' => 'Saint Helena, Ascension and Tristan da Cunha',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'MF' => 'Saint Martin (French part)',
        'PM' => 'Saint Pierre and Miquelon',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SX' => 'Sint Maarten (Dutch part)',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'GS' => 'South Georgia and the South Sandwich Islands',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SJ' => 'Svalbard and Jan Mayen',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan, Republic of China',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania (United Republic of)',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TK' => 'Tokelau',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TM' => 'Turkmenistan',
        'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu',
        'TR' => 'Türkiye',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom of Great Britain and Northern Ireland',
        'UM' => 'United States Minor Outlying Islands',
        'US' => 'United States of America',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City State',
        'VE' => 'Venezuela (Bolivarian Republic of)',
        'VN' => 'Vietnam',
        'VG' => 'Virgin Islands (British)',
        'VI' => 'Virgin Islands (U.S.)',
        'WF' => 'Wallis and Futuna',
        'EH' => 'Western Sahara',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
        'AX' => 'Åland Islands',
            
    ];
    
    
	function __construct() {
		add_action( 'admin_menu', array(&$this, 'qin_menu' ));
		$qin_options = get_option( 'wp_sample_countrylist' );
		$this->sample_countrylist = ( is_serialized( $qin_options )) ? unserialize( $qin_options ) : $qin_options;
		add_action( 'init', array( &$this, 'qin_add_all_country' ));
	}

	function qin_menu() {
		// Add a new top-level menu since this is a tool all on it's own
		add_menu_page( 'Sample Country', 'Sample Country', 'manage_options', 'sample_country', 'qin_edit_page' );
		// Add a submenu 
		add_submenu_page( 'sample_country', 'Set Currency', 'Set Currency', 'manage_options', 'sample_editcur', 'qin_edit_cur' );
        
            if ( true ) {
                add_submenu_page( 'sample_country', 'Set Shipping', 'Set Shipping', 'manage_options', 'sample_shipping', 'qin_shippingcost' );

                // ===== FedEx 设置菜单 =====
                add_submenu_page(
                    'sample_country',
                    'FedEx 接口设置',
                    'FedEx 接口设置',
                    'manage_options',
                    'sample_fedex_settings',
                    'qin_fedex_settings_page'
                );
                add_submenu_page( 'sample_country', '邮编列表', '邮编列表', 'manage_options', 'sample_zipgroups', 'qin_zipgroup_page' );
            }
	}

	function qin_add_all_country(){

		update_option( 'wp_sample_countrylist', serialize( $this->sample_countrylist ));
	}

	function qin_add_new_server( $new_server_id ) {

		// check if server_id is unique
		if( ! empty( $this->sample_countrylist )) {
			foreach( $this->sample_countrylist as $countrylist ) {
				if( $countrylist == $new_server_id ) {
					return false;
				}
			}
		}
		
		$this->sample_countrylist[] = $new_server_id;
		$this->qin_add_all_country();
		return true;
	}

	function qin_delete_server( $server_id ) {		
		if( ! empty( $this->sample_countrylist )) {
			foreach( $this->sample_countrylist as $key => $value ) {
				if( $value == $server_id ) {
					unset( $this->sample_countrylist[$key] );
                    sort($this->sample_countrylist);
					$this->qin_add_all_country(true);
					return true;
				}
			}
		}
	}

	/* formatting helpers */
	function qin_build_server_select( $title_shown, $selected_server = "" ){
		echo "<option value=''>  $title_shown </option>";

		foreach( $this->all_country as $key => $value ) {
			if ( $selected_server == $key )
				$selected = "selected";
			echo "<option value='" . $key . "' " . $selected . " >" . $value . "</option>";
			$selected = "";
		}
	}
    
    function qin_add_cursymble( $arr ) {
        $cursymble      = $arr['cursymble'];
        $cursymble_fuhao      = $arr['cursymble_fuhao'];
        $cursymble_gst  = $arr['cursymble_gst'];
        $cursymble_fee  = $arr['cursymble_fee'];
        $cursymble_q1   = $arr['cursymble_q1'];
        $cursymble_q2   = $arr['cursymble_q2'];
        $cursymble_q3   = $arr['cursymble_q3'];
        $cursymble_kg   = $arr['cursymble_kg'];
        
        update_option( 'cursymble', $cursymble);
        update_option( 'cursymble_fuhao', $cursymble_fuhao);
        update_option( 'cursymble_gst', $cursymble_gst);
        update_option( 'cursymble_fee', $cursymble_fee);
        update_option( 'cursymble_q1', $cursymble_q1);
        update_option( 'cursymble_q2', $cursymble_q2);
        update_option( 'cursymble_q3', $cursymble_q3);
        update_option( 'cursymble_kg', $cursymble_kg);
        return true;

    }
}

function get_shippingtools($t) {
    $arr = ['kongyun' => 'Air Shipping', 'haiyun' => 'Sea Shipping'];
    return $arr[$t];
}

function get_shippingtype($t) {
    $arr = ['kesong' => '可送达', 'busong' => '不送'];
    return $arr[$t];
}

function get_shippingregion($t) {
    $arr = ['pianyuan' => '偏远', 'normal' => '普通'];
    return $arr[$t];
}

function show_rule( $e ) {
    return $e[0] . 'kg - ' . $e[1] . 'kg: ' . $e[2] . ';<br/>';
}

function qin_shippingcost() {
	global $qin_manager;
	$all_zipgroups = qin_get_zipgroups();


	//if we are adding a new server
	if (isset($_POST['_wpnonce'])) {
		$country = $_POST['shipping_country'];
        if ( $country ) {
            $data = $_POST['sample_shipping'][$country];
            $shipping_data = get_option( 'wp_sample_shipping_' . strtolower($country) );
            $sample_shipping = ( is_serialized( $shipping_data )) ? unserialize( $shipping_data ) : $shipping_data;
            
            if ( isset($_POST['edit_id']) ) {
                $sample_shipping[$_POST['edit_id']] = $data;
            } else {
                if ( !isset($sample_shipping[$_POST['_wpnonce']]) )
                    $sample_shipping[$_POST['_wpnonce']] = $data;
            }

            //print_r($sample_shipping);
            update_option( 'wp_sample_shipping_' . strtolower($country), serialize($sample_shipping));

            ?>
                <div id="message" class="updated fade" style="color:green">
                <p>操作成功.</p>
                </div>
            <?php
        }
	}
    
	//if we are deleting an existing server
	if ( isset($_GET['delete']) ) {
		if ( check_admin_referer( 'qin-delete-shipping-nonce' )) {
            $country = $_GET['country'];
            $k = $_GET['delete'];
            $shipping_data = get_option( 'wp_sample_shipping_' . strtolower($country) );
            $sample_shipping = ( is_serialized( $shipping_data )) ? unserialize( $shipping_data ) : $shipping_data;
            
            if ( isset($sample_shipping[$k]) ) {
                unset($sample_shipping[$k]);
                update_option( 'wp_sample_shipping_' . strtolower($country), serialize($sample_shipping));
                header('Location: /wp-admin/admin.php?page=sample_shipping&country=' . $country);
                exit;
            }
           
            
				?>
					<div id="message" class="updated fade" style="color:green">
					<p>操作成功.</p>
					</div>
					<?php

		}	
	}
    ?>
    <div class="wrap">
        <h3>已设置的运费规则</h3>
        <table class="widefat">
		<thead>
		<tr>
		<th scope="col">序号</th>
		<th scope="col">方式</th>
		<th scope="col">类型</th>
		<th scope="col">区域</th>
		<th scope="col">时间</th>
		<th scope="col">符号</th>
		<th scope="col">汇率</th>
		<th scope="col">报关费</th>
		<th scope="col">邮编</th>
		<th scope="col">空运规则</th>
		<th scope="col">海运规则</th>
		<th scope="col">操作</th>
		</tr>
		</thead>
            <?php
                $shipping_data = get_option( 'wp_sample_shipping_' . strtolower($_GET['country']) );
                $sample_shipping = ( is_serialized( $shipping_data )) ? unserialize( $shipping_data ) : $shipping_data;
                $edit_shipping = [];
                if ( true ) {
                    if ( !empty($sample_shipping) ) {
                        
                        $i = 1;
                        foreach ( $sample_shipping as $ck => $cv ) {
                            if ( isset($_GET['edit']) && $ck == $_GET['edit'] ) {
                                $edit_shipping = $cv;
                            }
                            $re = $cv['region'] ?? 'normal';
                            echo '<tr>';
                            echo '<td>' . $i . '</td>';
                            echo '<td>' . get_shippingtools($cv['tools']) . '</td>';
                            echo '<td>' . get_shippingtype($cv['type']) . '</td>';
                            echo '<td>' . get_shippingregion($re) . '</td>';
                            echo '<td>' . ($cv['shippingtime'] ?? '') . '</td>';
                            echo '<td>' . $cv['symbol'] . '</td>';
                            echo '<td>' . $cv['currate'] . '</td>';
                            echo '<td>' . $cv['baoguanfee'] . '</td>';
                            echo '<td><div class="zipcodes" style="max-height:210px;overflow-y:scroll;">' . str_replace("\n", '<br/>', $cv['zipcode']) . '</div></td>';
                            
                            echo '<td>'; 
                            
                            foreach ( $cv['kongyun'] as $kk => $kv ) {
                                //$xx = intval($kk) + 1;
                                echo empty($kv[2]) ? '' : ucfirst($kk) . '. ' . show_rule($kv);
                            }
                            echo '</td>';

                            echo '<td>'; 
                            
                            foreach ( $cv['haiyun'] as $hk => $hv ) {
                                //$xx = $hk + 1;
                                echo empty($hv[2]) ? '' : ucfirst($hk) . '. ' . show_rule($hv);
                            }
                            echo '</td>';
                            $delete_server_link = '?page=sample_shipping&delete=' . $ck . '&country=' . strtoupper($_GET['country']);
                            $delete_server_link = wp_nonce_url($delete_server_link, 'qin-delete-shipping-nonce');
                            
                            $edit_server_link = '?page=sample_shipping&edit=' . $ck . '&country=' . strtoupper($_GET['country']);
                            $edit_server_link = wp_nonce_url($edit_server_link, 'qin-edit-shipping-nonce');
                            
                            echo '<td><a href="' . $edit_server_link . '">Edit</a>&nbsp;&nbsp;<a href="' . $delete_server_link . '">Delete</a></td>';
                            
                            echo '</tr>';
                            $i = $i + 1;
                        }

                        
                    } else {
                        echo '<tr><td colspan="8">未发现规则，请在下方添加</td></tr>';
                    }
                }
            ?>  
        </table>     
        <br />
        <h3>规则设置</h3>
		<form action="?page=sample_shipping&country=<?php echo $_GET['country'];?>" method="post">
		<?php

        wp_nonce_field('qin-shipping' . date('YmdHis'));
        
        if ( isset($_GET['edit']) ) {
            echo '<input type="hidden" name="edit_id" value="' . $_GET['edit'] . '" />';
        }
        
        $tools = '';
        $type = '';
        $zipcode = '
33300-33399
23233
        ';
        $zipgroup = '';
        $symbol = '';
        $currate = '';
        $baoguanfee = '';
        $kongyun = [];
        $haiyun = [];
        $region = '';
        $shippingtime = '';
        if ( !empty($edit_shipping) ) {
            $tools = $edit_shipping['tools'];
            $type = $edit_shipping['type'];
            $region = $edit_shipping['region'];
            $zipcode = $edit_shipping['zipcode'];
            $symbol = $edit_shipping['symbol'];
            $currate = $edit_shipping['currate'];
            $baoguanfee = $edit_shipping['baoguanfee'];
            $kongyun = $edit_shipping['kongyun'];
            $haiyun = $edit_shipping['haiyun'];
            $shippingtime = $edit_shipping['shippingtime'];
            $zipgroup = isset($edit_shipping['zipgroup']) ? $edit_shipping['zipgroup'] : '';
        }
        ?>
        <input type="hidden" name="shipping_country" value="<?php echo $_GET['country'];?>">
		<table class="form-table">
            <tr>
            <th scope="row"><label>配送国家</label></th>
            <td><?php echo $_GET['country'];?></td>
            </tr>
            <tr>
            <th scope="row"><label for="shipping_tool">配送方式</label></th>
            <td><select id="shipping_tool" name="sample_shipping[<?php echo $_GET['country']; ?>][tools]"><option value="kongyun" <?php echo $tools == 'kongyun' ? 'selected' : '';?>>Air Shipping</option><option value="haiyun" <?php echo $tools == 'haiyun' ? 'selected' : '';?>>Sea Shipping</option></select></td>
            </tr>
            <tr>
            <th scope="row"><label for="shipping_type">配送类型</label></th>
            <td><select id="shipping_type" name="sample_shipping[<?php echo $_GET['country']; ?>][type]"><option value="kesong" <?php echo $type == 'kesong' ? 'selected' : '';?>>可送达</option><option value="busong" <?php echo $type == 'busong' ? 'selected' : '';?>>不送</option></select></td>
            </tr>
            <tr>
            <th scope="row"><label>配送时间<br/>(参考：around 35 days)</label></th>
            <td><input name="sample_shipping[<?php echo $_GET['country']; ?>][shippingtime]" id="shippingtime" value="<?php echo $shippingtime;?>" ></td>
            </tr>
            <tr>
            <th scope="row"><label for="shipping_region">区域类型<br/>(偏远运费会覆盖普通运费)</label></th>
            <td><select id="shipping_region" name="sample_shipping[<?php echo $_GET['country']; ?>][region]"><option value="normal" <?php echo $region == 'normal' ? 'selected' : '';?>>普通</option><option value="pianyuan" <?php echo $region == 'pianyuan' ? 'selected' : '';?>>偏远</option></select></td>
            </tr>
            <tr>
            <th scope="row"><label for="shipping_zipgroup">选择邮编组</label></th>
            <td>
                <select id="shipping_zipgroup" name="sample_shipping[<?php echo $_GET['country']; ?>][zipgroup]">
                    <option value="">— 不使用 —</option>
                    <?php if ( ! empty($all_zipgroups) ) : ?>
                        <?php foreach ( $all_zipgroups as $gid => $g ) : ?>
                            <option value="<?php echo esc_attr($gid); ?>" <?php selected( $zipgroup, $gid ); ?>>
                                <?php echo esc_html( $g['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <p class="description">邮编组和下方邮编框是合并计算，没有优先级</p>
            </td>
            </tr>

            <tr>
            <th scope="row"><label for="shipping_code">邮编<br/>(每行一个邮编，支持区间，区间用横线连接，<br/>支持批量邮编开头格式：^ABC0,表示ABC0开头的邮编 )</label></th>
            <td>
            <textarea id="shipping_code" name="sample_shipping[<?php echo $_GET['country']; ?>][zipcode]" rows="8" cols="30">
            <?php echo $zipcode;?>
            </textarea>
            </td>
            </tr>
            <tr>
            <th scope="row"><label for="symbol">货币符号</label></th>
            <td>
                <input name="sample_shipping[<?php echo $_GET['country']; ?>][symbol]" id="symbol" value="<?php echo $symbol;?>" >
            </td>
            </tr>            
            <tr>
            <th scope="row"><label for="currate">汇率<br/>(这是一个变量，可以下面公式中使用)</label></th>
            <td>
                <input name="sample_shipping[<?php echo $_GET['country']; ?>][currate]" id="currate" value="<?php echo $currate;?>" >
            </td>
            </tr>
            
            <tr>
            <th scope="row"><label for="baoguanfee">报关费<br/>(这是一个变量，可以下面公式中使用)</label></th>
            <td>
                <input name="sample_shipping[<?php echo $_GET['country']; ?>][baoguanfee]" id="baoguanfee" value="<?php echo $baoguanfee;?>">
            </td>
            </tr>
            <tr>
            <th scope="row"><label>空运规则</label><br/><input type="button" class="add_morerule" value="新增规则"></th>
            <td class="airshipping">
            <?php 
                if ( !empty($kongyun) ) {
                    
                    $i = 1;
                    foreach ($kongyun as $rk => $rv) {
                    ?>

                <div style="margin-bottom:2px;">
                重量<?=$i?>：<input value="<?php echo $rv[0] ?? '';?>" size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][<?=$rk?>][]"> kg - <input value="<?php echo $rv[1] ?? '';?>" size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][<?=$rk?>][]"> kg,
                规则<?=$i?>：<input value="<?php echo $rv[2] ?? '';?>" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][<?=$rk?>][]">
                </div>
                
                    <?php    
                        $i = $i + 1;
                    }
                } else {
            ?>
                <div style="margin-bottom:2px;">
                重量1：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule1][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule1][]"> kg,
                规则1：<input name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule1][]">
                </div>
                <div style="margin-bottom:2px;">
                重量2：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule2][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule2][]"> kg,
                规则2：<input name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule2][]">
                </div>
                <div style="margin-bottom:2px;">
                重量3：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule3][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule3][]"> kg,
                规则3：<input name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule3][]">
                </div>
                <div style="margin-bottom:2px;">
                重量4：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule4][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule4][]"> kg,
                规则4：<input name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule4][]">
                </div>
                <div style="margin-bottom:2px;">
                重量5：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule5][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule5][]"> kg,
                规则5：<input name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule5][]">
                </div>
                <div style="margin-bottom:2px;">
                重量6：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule6][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule6][]"> kg,
                规则6：<input name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule6][]">
                </div>
                <div style="margin-bottom:2px;">
                重量7：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule7][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule7][]"> kg,
                规则7：<input name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule7][]">
                </div>
                <div style="margin-bottom:2px;">
                重量8：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule8][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule8][]"> kg,
                规则8：<input name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][rule8][]">
                </div> 
            <?php
                }
            ?>        
            </td>
            </tr>
            <tr>
            <th scope="row"><label for="cursymble_q1">海运规则</label></th>
            <td>
            <?php 
                if ( !empty($haiyun) ) {
                    
                    $i = 1;
                    foreach ($haiyun as $rk => $rv) {
                    ?>

                <div style="margin-bottom:2px;">
                重量<?=$i?>：<input value="<?php echo $rv[0] ?? '';?>" size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][<?=$rk?>][]"> kg - <input value="<?php echo $rv[1] ?? '';?>" size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][<?=$rk?>][]"> kg,
                规则<?=$i?>：<input value="<?php echo $rv[2] ?? '';?>" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][<?=$rk?>][]">
                </div>
                
                    <?php    
                        $i = $i + 1;
                    }
                } else {
            ?>
                <div style="margin-bottom:2px;">
                重量1：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule1][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule1][]"> kg,
                规则1：<input name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule1][]">
                </div>
                <div style="margin-bottom:2px;">
                重量2：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule2][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule2][]"> kg,
                规则2：<input name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule2][]">
                </div>
                <div style="margin-bottom:2px;">
                重量3：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule3][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule3][]"> kg,
                规则3：<input name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule3][]">
                </div>
                <div style="margin-bottom:2px;">
                重量4：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule4][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule4][]"> kg,
                规则4：<input name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule4][]">
                </div>
                <div style="margin-bottom:2px;">
                重量5：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule5][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule5][]"> kg,
                规则5：<input name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule5][]">
                </div>
                <div style="margin-bottom:2px;">
                重量6：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule6][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule6][]"> kg,
                规则6：<input name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule6][]">
                </div>
                <div style="margin-bottom:2px;">
                重量7：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule7][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule7][]"> kg,
                规则7：<input name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule7][]">
                </div>
                <div style="margin-bottom:2px;">
                重量8：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule8][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule8][]"> kg,
                规则8：<input name="sample_shipping[<?php echo $_GET['country']; ?>][haiyun][rule8][]">
                </div>   
            <?php
                }
            ?>                   
            </td>
            </tr>
		</table>

		<input type="submit" name="Submit" value="提交" />
		</form>
        <script>

        jQuery( ($) => {
            
            $('.add_morerule').click(function () {
                var html = '\
                <div style="margin-bottom:2px;">\
                重量XX：<input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][ruleXX][]"> kg - <input size="3" name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][ruleXX][]"> kg,\
                规则XX：<input name="sample_shipping[<?php echo $_GET['country']; ?>][kongyun][ruleXX][]">\
                </div>\
                ';
                var tdhtml = $('td.airshipping').html();
                var div = $('td.airshipping').find('div').length + 1;
                $('td.airshipping').html( tdhtml + html.replace(/XX/g, div));
            });
        });
        </script>
    </div>    
    <?php

}

function qin_edit_cur(){
	global $qin_manager;
    

	//if we are adding a new server
	if (isset($_POST['cursymble'])) {
		//if (check_admin_referer('qin-options-add-server-nonce')) {
			if ( $qin_manager->qin_add_cursymble( $_POST )) {
				?>
					<div id="message" class="updated fade" style="color:green">
					<p>操作成功.</p>
					</div>
					<?php
			} else {
				?>
					<div id="message" class="error fade" style="color:red">
					<p>操作失败，已经存在.</p>
					</div>
					<?php
			}
		//}
	}

    $cursymble = get_option( 'cursymble' ) ?: 'C$';
    $cursymble_fuhao = get_option( 'cursymble_fuhao' ) ?: 'USD';
    $cursymble_gst = get_option( 'cursymble_gst' ) ?: '0';
    $cursymble_fee = get_option( 'cursymble_fee' ) ?: '25';
    $cursymble_q1 = get_option( 'cursymble_q1' ) ?: '6.8';
    $cursymble_q2 = get_option( 'cursymble_q2' ) ?: '13.6';
    $cursymble_q3 = get_option( 'cursymble_q3' ) ?: '5';
    $cursymble_kg = get_option( 'cursymble_kg' ) ?: '1';
    ?>
    <div class="wrap">
    <h2>样品货币设置</h2>
		<form action="?page=sample_editcur" method="post">
		<?php

        wp_nonce_field('qin-nonce');

        ?>
		<table class="form-table">
            <tr>
            <th scope="row"><label for="cursymble">货币符号</label></th>
            <td><input name="cursymble" style="width:50px;" type="text" id="cursymble" value="<?=$cursymble?>" class="regular-text ltr"></td>
            </tr>
            <tr>
            <th scope="row"><label for="cursymble_fuhao">国际货币代码</label></th>
            <td><input name="cursymble_fuhao" style="width:50px;" type="text" id="cursymble_fuhao" value="<?=$cursymble_fuhao?>" class="regular-text ltr"></td>
            </tr>
            <tr>
            <th scope="row"><label for="cursymble_gst">GST</label></th>
            <td><input name="cursymble_gst" style="width:50px;" type="text" id="cursymble_gst" value="<?=$cursymble_gst?>" class="regular-text ltr">%</td>
            </tr>
            <tr>
            <th scope="row"><label for="cursymble_fee">基础运费</label></th>
            <td><input name="cursymble_fee" style="width:50px;" type="text" id="cursymble_fee" value="<?=$cursymble_fee?>" class="regular-text ltr"></td>
            </tr>
            <tr>
            <th scope="row"><label for="cursymble_q1">叠加费1</label></th>
            <td><input name="cursymble_q1" style="width:50px;" type="text" id="cursymble_q1" value="<?=$cursymble_q1?>" class="regular-text ltr"></td>
            </tr>
            <tr>
            <th scope="row"><label for="cursymble_q2">叠加费2</label></th>
            <td><input name="cursymble_q2" style="width:50px;" type="text" id="cursymble_q2" value="<?=$cursymble_q2?>" class="regular-text ltr"></td>
            </tr>
            <tr>
            <th scope="row"><label for="cursymble_q3">Hardcover叠加费</label></th>
            <td><input name="cursymble_q3" style="width:50px;" type="text" id="cursymble_q3" value="<?=$cursymble_q3?>" class="regular-text ltr"></td>
            </tr>
            <tr>
            <th scope="row"><label for="cursymble_kg">SAMPLE重量（前台选择一种书）</label></th>
            <td><input name="cursymble_kg" style="width:50px;" type="text" id="cursymble_kg" value="<?=$cursymble_kg?>" class="regular-text ltr">Kg</td>
            </tr>
		</table>

		<input type="submit" name="Submit" value="提交" />
		</form>
    </div>    
    <?php
    
}    

//Headings, etc
function qin_page_start(){
	global $qin_manager;

    echo '<div class="wrap"><h2>样品送达的国家设置</h2>';

}

function qin_edit_page(){
	global $qin_manager;

	qin_page_start();

	//if we are adding a new server
	if (isset($_POST['countryname'])) {
		if (check_admin_referer('qin-options-add-server-nonce')) {
			if ( $qin_manager->qin_add_new_server( $_POST['countryname'] )) {
				?>
					<div id="message" class="updated fade" style="color:green">
					<p>操作成功.</p>
					</div>
					<?php
			} else {
				?>
					<div id="message" class="error fade" style="color:red">
					<p>操作失败，已经存在.</p>
					</div>
					<?php
			}
		}
	}

	//if we are deleting an existing server
	if ( isset($_GET['delete']) ) {
		if ( check_admin_referer( 'qin-delete-server-nonce' )) {
			if($qin_manager->qin_delete_server( $_GET['delete'] )) {
				?>
					<div id="message" class="updated fade" style="color:green">
					<p>操作成功.</p>
					</div>
					<?php
			} else {
				?>
					<div id="message" class="error fade" style="color:red">
					<p>操作失败, 未知错误.</p>
					</div>
					<?php
			}
		}	
	}	

	?>
		<h3>
		添加送达国家
		</h3>
        <style>table.sampletable tr:hover{background-color:aquamarine;}</style>
		<form action="?page=sample_country" method="post">
		<?php

        wp_nonce_field('qin-options-add-server-nonce');

        ?>
		<table class="form-table">

		<tr valign="top">

		<td>
        <select name="countryname">
        <?php echo $qin_manager->qin_build_server_select( "-请选择- ", 'US' );?>
        </select>
		</td>
		</tr>

		</table>

		<input type="submit" name="Submit" value="添加" />
		</form>

		<h3>已设置送达国家</h3>
		<table class="widefat sampletable">
		<thead>
		<tr>
		<th scope="col">Name</th>
		<th scope="col">&nbsp;</th>
		</tr>
		</thead>
		<?php

	if ( ! empty( $qin_manager->sample_countrylist )) {
		foreach( $qin_manager->sample_countrylist as $k => $countrylist ) {


			print('<tr>');
			print('</td><td>' . $qin_manager->all_country[$countrylist] . '</td>');
			$delete_server_link = '?page=sample_country&delete=' . $countrylist;
			$delete_server_link = wp_nonce_url($delete_server_link, 'qin-delete-server-nonce');
            
			print('<td>');
            
            if ( true ) {
                
                $sample_shipping = '?page=sample_shipping&country=' . $countrylist;
                $sample_shipping = wp_nonce_url($sample_shipping, 'sampleshipping-nonce');
                print('<a href="' . $sample_shipping . '"> Shipping </a>&nbsp;');
            }
            
			print('<a href="' . $delete_server_link . '"> Delete </a>');
			print('</td>');
			print('</tr>');

		}
	}

	?>
		</table>
        <script>
        jQuery(($) => {
            
        });
        </script>
		<?php
		
	qin_page_end();
}


function qin_page_end(){
	echo "</div>";
}

if ( is_admin() ) {
	$qin_manager = new WPSampleCountryList();
}