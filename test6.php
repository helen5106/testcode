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

function in_zipArr4($zipArr, $zipcode) {
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

function in_zipArr($zipArr, $zipcode) {
    if (empty($zipArr) || !count($zipArr)) {
        return false;
    }

    $zipcode = strtolower(trim((string)$zipcode));

    foreach ($zipArr as $zip) {
        $code = strtolower(trim((string)$zip));
        if ($code === '') continue;

        // 1) 区间：形如 "a-b"、"^p1-^p2"、"^p1-12345"、"12345-^p2"
        if (strpos($code, '-') !== false) {
            list($left, $right) = array_map('trim', explode('-', $code, 2));
            if ($left === '' || $right === '') continue;

            // 下界：若以 ^ 开头，则用其前缀作为下界（字典序）
            $lower = ($left[0] === '^') ? substr($left, 1) : $left;

            // 上界：若以 ^ 开头，则用 前缀 + "\xFF" 作为"上界最大值"
            // 这样任何以该前缀开头的字符串都 <= 这个上界（字典序）
            $upper = ($right[0] === '^') ? (substr($right, 1) . "\xFF") : $right;

            if ($zipcode >= $lower && $zipcode <= $upper) {
                return true;
            }
            continue;
        }

        // 2) 前缀：形如 "^801"
        if ($code[0] === '^') {
            $prefix = substr($code, 1);
            if ($prefix !== '' && strncmp($zipcode, $prefix, strlen($prefix)) === 0) {
                return true;
            }
            continue;
        }

        // 3) 单个邮编完全匹配
        if ($code === $zipcode) {
            return true;
        }
    }

    return false;
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
        //echo '<p>这里维护的邮编组可以在"Set Shipping"里直接选择使用。保存的是固定ID，所以你后面改名字也不会影响引用。</p>';

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
            'fedex_rate' => '6.6',
            'fedex_xishu' => '向上取整（FEDEX_AMOUNT） * 1.04 + 重量*2',
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
            $s['fedex_rate'] = sanitize_text_field($_POST['fedex_rate']);
            $s['fedex_xishu'] = sanitize_text_field($_POST['fedex_xishu']);
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
                    <tr><th>汇率</th><td><input type="text" name="fedex_rate" value="<?php echo esc_attr($s['fedex_rate']);?>" class="small-text"></td></tr>
                    <tr><th>系数</th><td><input type="text" name="fedex_xishu" value="<?php echo esc_attr($s['fedex_xishu']);?>" class="regular-text"></td></tr>
                    
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
            'FEDEX_INTERNATIONAL_PRIORITY' => 'FedEx IP',
            'INTERNATIONAL_ECONOMY'  => 'FedEx IE',
            'FEDEX_INTERNATIONAL_ECONOMY'  => 'FedEx IE',
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

function extract_ymd(?string $s): ?string {
    $s = trim((string)$s);
    if ($s === '') {
        return null; // 或者返回 '' / 'N/A'
    }
    // 先用正则截取前 10 位的 YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s, $m)) {
        return $m[0];
    }
    // 兜底：交给 DateTime 解析
    $dt = date_create($s);
    return $dt ? $dt->format('Y-m-d') : null;
}

// fedex请求报价（CNY 计价 + 提供 USD 折算；重量单位统一使用 KG）
if (!function_exists('qin_fedex_rate_quote')) {
    function qin_fedex_rate_quote($to_country, $to_postal, $weight_kg, $args=array()) {
        $s     = qin_fedex_get_settings();
        $token = qin_fedex_get_token();
        if (!$token || empty($s['account_number'])) return array();

        // ---------- 基本参数 ----------
        $to_country   = strtoupper($to_country);
        $ship_country = strtoupper($s['shipper_country']);
        $pickupType   = $s['pickup_type'];
        if ($pickupType !== 'DROPOFF_AT_FEDEX_LOCATION' && $pickupType !== 'CONTACT_FEDEX_TO_SCHEDULE') {
            $pickupType = 'DROPOFF_AT_FEDEX_LOCATION';
        }

        // ---------- 每箱 20kg，拆分包裹（单位：KG） ----------
        $kg_per_pkg = 20.0;  // ← 每箱 20kg
        $total_kg   = max(0.01, floatval($weight_kg));
        $pkg_count  = (int) ceil($total_kg / $kg_per_pkg);

        $full_ctns  = (int) floor($total_kg / $kg_per_pkg);
        $rem_kg     = $total_kg - $full_ctns * $kg_per_pkg; // 可能为 0

        // 组装 line items（KG + groupPackageCount）
        $pkg_lines = array();
        if ($full_ctns > 0) {
            $line = array(
                'weight' => array('units'=>'KG', 'value'=>round($kg_per_pkg, 2)),
                'groupPackageCount' => $full_ctns
            );
            // 尺寸（可选）
            $dims = null;
            if (!empty($args['dims']) && is_array($args['dims']) && count($args['dims'])===3) {
                $dims = $args['dims']; // [L,W,H]，单位：CM
            } elseif (!empty($args['need_dims'])) {
                $dims = array(25,25,25);
            }
            if ($dims) {
                $line['dimensions'] = array(
                    'length' => (int)$dims[0],
                    'width'  => (int)$dims[1],
                    'height' => (int)$dims[2],
                    'units'  => 'CM'
                );
            }
            $pkg_lines[] = $line;
        }
        if ($rem_kg > 0.001) {
            $line = array(
                'weight' => array('units'=>'KG', 'value'=>round($rem_kg, 2)),
                'groupPackageCount' => 1
            );
            // 尺寸（可选，同上）
            $dims = null;
            if (!empty($args['dims']) && is_array($args['dims']) && count($args['dims'])===3) {
                $dims = $args['dims'];
            } elseif (!empty($args['need_dims'])) {
                $dims = array(25,25,25);
            }
            if ($dims) {
                $line['dimensions'] = array(
                    'length' => (int)$dims[0],
                    'width'  => (int)$dims[1],
                    'height' => (int)$dims[2],
                    'units'  => 'CM'
                );
            }
            $pkg_lines[] = $line;
        }

        // ---------- 只保留 IE / IP ----------
        $allowed = apply_filters('qin_fedex_allowed_services', array(
            'FEDEX_INTERNATIONAL_ECONOMY','INTERNATIONAL_ECONOMY',
            'FEDEX_INTERNATIONAL_PRIORITY','INTERNATIONAL_PRIORITY',
        ));
        $normalize = array(
            'INTERNATIONAL_ECONOMY'  => 'FEDEX_INTERNATIONAL_ECONOMY',
            'INTERNATIONAL_PRIORITY' => 'FEDEX_INTERNATIONAL_PRIORITY',
        );

        // ---------- 清关（最小化，展示用途） ----------
        $is_international = ($ship_country !== $to_country);
        $declared_value   = isset($args['declared_value']) ? floatval($args['declared_value']) : 10.0;
        if ($declared_value <= 0) $declared_value = 10.0; // 避免报错
        $commodity_desc   = !empty($args['commodity_description']) ? $args['commodity_description'] : 'Printed Books';

        // ---------- 货币：CNY ----------
        $preferredCurrency = 'CNY';

        // ---------- 收件地址 ----------
        $recipient_addr = array(
            'postalCode'  => $to_postal,
            'countryCode' => $to_country,
        );
        if (in_array($to_country, array('US','CA'), true) && !empty($args['state'])) {
            $recipient_addr['stateOrProvinceCode'] = strtoupper(sanitize_text_field($args['state']));
        }
        if (isset($args['residential'])) {
            $recipient_addr['residential'] = (bool)$args['residential'];
        }

        // ---------- 组装请求体（不强制 serviceType） ----------
        $body = array(
            'accountNumber' => array('value' => $s['account_number']),
            'rateRequestControlParameters' => array(
                'returnTransitTimes' => true,
                'rateSortOrder'      => 'COMMITASCENDING',
            ),
            'requestedShipment' => array(
                'preferredCurrency' => $preferredCurrency,
                'shipDateStamp'     => date('Y-m-d'),
                'rateRequestType'   => array('ACCOUNT'),
                'shipper' => array(
                    'address' => array(
                        'postalCode'          => $s['shipper_postal'],
                        'countryCode'         => $ship_country,
                        'city'                => $s['shipper_city'],
                        'stateOrProvinceCode' => $s['shipper_state'],
                    ),
                ),
                'recipient'     => array('address' => $recipient_addr),
                'pickupType'    => $pickupType,
                'packagingType' => 'YOUR_PACKAGING',
                'requestedPackageLineItems' => $pkg_lines,
            ),
        );

        if ($is_international) {
            // 用包裹合计填充 commodities（单位：KG；不提供 dutiesPayment）
            $total_pcs = 0; $sum_kgs = 0;
            foreach ($pkg_lines as $ln) {
                $pcs = intval($ln['groupPackageCount']);
                $kgv = floatval($ln['weight']['value']);
                $total_pcs += $pcs;
                $sum_kgs   += ($pcs * $kgv);
            }
            $body['requestedShipment']['customsClearanceDetail'] = array(
                'commodities' => array(
                    array(
                        'numberOfPieces'       => $total_pcs,
                        'description'          => $commodity_desc,
                        'countryOfManufacture' => $ship_country,
                        'weight'               => array('units'=>'KG','value'=>round($sum_kgs,2)), // ← KG
                        'quantity'             => $total_pcs,
                        'quantityUnits'        => 'PCS',
                        'customsValue'         => array('amount'=>$declared_value,'currency'=>'USD'), // 申报值通常 USD
                    )
                ),
            );
        }

        // 允许外部过滤
        $body = apply_filters('qin_fedex_rate_body', $body, $to_country, $to_postal, $weight_kg);

        // ---------- 发送请求 ----------
        $url = qin_fedex_base().'/rate/v1/rates/quotes';
        qin_fedex_log('RATE_REQ', array(
            'url'  => $url,
            'box_split' => array(
                'total_kg' => $total_kg,
                'pkg_count'=> $pkg_count,
                'full_ctns'=> $full_ctns,
                'rem_kg'   => round($rem_kg,3),
            ),
            'body' => $body
        ));

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
            CURLOPT_ENCODING       => '',
            CURLOPT_TIMEOUT        => 25,
        ));
        $res  = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        //file_put_contents('fedex.log', $res);
        if (!($http>=200 && $http<300) || empty($res)) {
            qin_fedex_log('RATE_ERR', array('http'=>$http, 'curl_error'=>$err, 'res_excerpt'=>substr((string)$res,0,3000)));
            return array();
        }

        qin_fedex_log('RATE_JSON_RESULT', array('http'=>$http, 'res_excerpt'=>$res));
        $j = json_decode($res, true);
        if (!$j) return array();

        $details = array();
        if (!empty($j['output']['rateReplyDetails'])) $details = $j['output']['rateReplyDetails'];
        elseif (!empty($j['rateReplyDetails']))      $details = $j['rateReplyDetails'];
        if (!is_array($details)) return array();

        // ---------- 解析并仅返回 IE / IP ----------
        $out      = array();
        $usd_rate = isset($args['usd_rate']) ? floatval($args['usd_rate']) : floatval($s['fedex_rate']); // CNY→USD 折算（默认 6.6）

        foreach ($details as $d) {
            $service_raw = $d['serviceType'] ?? '';
            if (!$service_raw) continue;

            $service = isset($normalize[$service_raw]) ? $normalize[$service_raw] : $service_raw;
            if (!in_array($service, $allowed, true)) {
                qin_fedex_log('RATE_SERVICE_FILTERED', array('service'=>$service_raw));
                continue;
            }

            // ---- 提取金额与币种（兼容对象/数值 & 多重回退）----
            $amount = null;
            $currency_raw = null;

            $rsd0 = $d['ratedShipmentDetails'][0] ?? [];

            // 1) 先看 ratedShipmentDetails[0].totalNetCharge
            if (array_key_exists('totalNetCharge', $rsd0)) {
                $tnc = $rsd0['totalNetCharge'];
                if (is_array($tnc)) {
                    // 形如 ['amount'=>..., 'currency'=>'CNY']
                    $amount       = $tnc['amount']   ?? null;
                    $currency_raw = $tnc['currency'] ?? null;
                } else {
                    // 形如 17601.3
                    $amount       = floatval($tnc);
                    $currency_raw = $rsd0['shipmentRateDetail']['currency'] ?? null;
                }
            }

            // 2) 还没有？看 shipmentRateDetail.totalNetCharge（有时也是数值）
            if ($amount === null && isset($rsd0['shipmentRateDetail'])) {
                $srd = $rsd0['shipmentRateDetail'];
                if (isset($srd['totalNetCharge'])) {
                    $tnc = $srd['totalNetCharge'];
                    if (is_array($tnc)) {
                        $amount       = $tnc['amount']   ?? null;
                        $currency_raw = $tnc['currency'] ?? ($srd['currency'] ?? $currency_raw);
                    } else {
                        $amount       = floatval($tnc);
                        $currency_raw = $srd['currency'] ?? $currency_raw;
                    }
                }
                // 3) 再不行，用更宽松的字段兜底
                if ($amount === null) {
                    $amount       = $srd['netCharge']       ?? ($srd['netFedExCharge'] ?? null);
                    $currency_raw = $srd['currency']        ?? $currency_raw;
                    if ($amount !== null) $amount = floatval($amount);
                }
            }

            // 4) 仍然取不到？用顶层总额兜底
            if ($amount === null && isset($d['totalNetTransportationAndPickupCharge']['amount'])) {
                $amount       = floatval($d['totalNetTransportationAndPickupCharge']['amount']);
                $currency_raw = $d['totalNetTransportationAndPickupCharge']['currency'] ?? $currency_raw;
            }
            if ($amount === null && isset($d['totalNetFedExTransportationAndPickupCharge']['amount'])) {
                $amount       = floatval($d['totalNetFedExTransportationAndPickupCharge']['amount']);
                $currency_raw = $d['totalNetFedExTransportationAndPickupCharge']['currency'] ?? $currency_raw;
            }
                
            // 统一输出为 CNY & 提供 USD 折算（按 6.6，可由 $args['usd_rate'] 覆盖）
            if ($amount !== null) {
                $usd_rate   = isset($args['usd_rate']) ? floatval($args['usd_rate']) : floatval($s['fedex_rate']);
                $is_usd     = strtoupper((string)$currency_raw) === 'USD';
                $amount_cny = $is_usd ? $amount * $usd_rate : $amount;
                $amount_usd = $is_usd ? $amount : $amount_cny / max($usd_rate, 0.000001);
            
                // 时效/日期
                $days = $d['commit']['transitTime']
                    ?? ($d['commitmentDetails']['transitTime'] ?? null);

                $date = $d['commit']['dateDetail']['dayFormat']
                    ?? ($d['commitmentDetails']['dateDetail']['dayFormat']
                    ?? ($d['commitmentDetails']['commitDate'] ?? ''));
                    
                if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date, $m)) {
                    $date = $m[0];
                }
                
                $ruleStr = $s['fedex_xishu'];
                $ruleStr = str_replace('FEDEX_AMOUNT', $amount_usd, $ruleStr);
                $ruleStr = str_replace('重量', $weight_kg, $ruleStr);
                $ruleStr = str_replace('：', ':', $ruleStr);
                $ruleStr = str_replace('？', '?', $ruleStr);
                $ruleStr = str_replace('（', '(', $ruleStr);
                $ruleStr = str_replace('）', ')', $ruleStr);
                $ruleStr = str_ireplace('x', '*', $ruleStr);
                $ruleStr = str_replace('向上取整', 'ceil',  $ruleStr);
                $ruleStr = str_replace('进一取整', 'ceil',  $ruleStr);
                $ruleStr = str_replace('舍去取整', 'floor',  $ruleStr);
                $ruleStr = str_replace('向下取整', 'floor',  $ruleStr);
                $ruleStr = html_entity_decode($ruleStr);
                $ruleV   = @eval("return $ruleStr;");
                
                
                $out[] = array(
                    'carrier'       => 'FedEx',
                    'service_code'  => $service,
                    'service_name'  => qin_fedex_service_label($service),
                    'amount'        => round($ruleV, 0),
                    'currency'      => '$',
                    'transit_days'  => $days,
                    'delivery_date' => $date,
                    'meta'          => array(
                        'source'        => 'fedex',
                        'currency_raw'  => $currency_raw ?: 'CNY',
                        'amount_cny'    => round($amount_cny, 2),
                        'amount_usd'    => round($amount_usd, 2),
                        'usd_rate'      => $usd_rate,
                    ),
                );
            } else {
                qin_fedex_log('RATE_AMOUNT_NULL', array('service'=>$service, 'snippet'=>substr(json_encode($d),0,2000)));
            }

           
            
        }

        return $out;
    }
}


// ================= FedEx Integration End =================
function process_shipping(WP_REST_Request $request){
    $request_body = $request->get_body_params();
    preg_match('/(\d+(?:\.\d+)?)/', $request_body['weight'], $matches);
    $weight  = floatval($matches[1]);
    $country = $request_body['country'];
    $zipcode = $request_body['zipcode'];
    $exwprice = $request_body['exwprice'] ?? 1000;

    // ===== FedEx rate prefetch =====
    $fedex_list = array();
    try {
        if (!empty($country) && !empty($zipcode) && $weight > 0) {
            $declared = $exwprice;
            if ($declared <= 0) $declared = 1000;
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
    $shipping_data = get_option('wp_sample_shipping_' . strtolower($country));
    $sample_shipping = (is_serialized($shipping_data)) ? unserialize($shipping_data) : $shipping_data;
    $zipgroups = qin_get_zipgroups();

    if (!empty($shipping_data)) {

        list($shipping_kongyun_kesong, $shipping_kongyun_busong, $shipping_haiyun_kesong, $shipping_haiyun_busong) = deal_shipping($sample_shipping);

        $kybusong = false;
        foreach ($shipping_kongyun_busong as $cv) {
            $zipcodeArr = qin_build_zip_array_from_rule($cv, $zipgroups);
            if (in_zipArr($zipcodeArr, $zipcode)) { $kybusong = true; }
        }

        $hybusong = false;
        foreach ($shipping_haiyun_busong as $cv) {
            $zipcodeArr = qin_build_zip_array_from_rule($cv, $zipgroups);
            if (in_zipArr($zipcodeArr, $zipcode)) { $hybusong = true; }
        }

        // ★ 修改：只有在【空&海都不送 且 没有任何可送达命中】时才早退；否则继续往下算
        if ( $kybusong && $hybusong ) {
            if (!empty($fedex_list)) {
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
                    'items'   => $all_items,
                ));
                $response->set_status(200);
                return $response;
            }
            $response = new WP_REST_Response(array('message'=>'Instant shipping cost is unavailable in this area, please contact us.'));
            $response->set_status(200);
            return $response;
        }


        $hyzipcode = false; $hychaozhong = true; $haiyun = []; $temphaiyun = [];
        // ★ 修改：允许"可送达命中"时继续算海运，即使 $hybusong 为 true
        if ( !$hybusong ) {
            foreach ($shipping_haiyun_kesong as $cv) {
                $symbol = $cv['symbol'];
                $shippingtime = $cv['shippingtime'] ?? 'around 35 days';
                $currate = $cv['currate'];
                $region = $cv['region'] ?? 'normal';
                $baoguanfee = $cv['baoguanfee'];
                $zipcodeArr = qin_build_zip_array_from_rule($cv, $zipgroups);

                if (in_zipArr($zipcodeArr, $zipcode)) {
                    $hyzipcode = true;
                    foreach ($cv['haiyun'] as $hv) {
                        if (isset($hv[2]) && !empty($hv[2])) {
                            if ($weight >= $hv[0] && $weight <= $hv[1]) {
                                $rule = $hv[2];
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
                                $temphaiyun[] = ['gs' => $ruleStr, 'service_name' => 'Sea Shipping', 'currency' => $symbol, 'amount' => ceil($ruleV), 'gv' => ceil($ruleV),'transit_days' => $shippingtime, 'region' => $region, 'shippingtime' => $shippingtime, 'symbol' => $symbol, 'name' => 'Sea Shipping'];
                                $hychaozhong = false;
                            }
                        }
                    }
                }
            }

            if (!empty($temphaiyun)) {
                // 按区域分组：pianyuan 优先，其次 normal，最后其他
                $pianyuan = array_values(array_filter($temphaiyun, function($row){
                    return strtolower($row['region'] ?? '') === 'pianyuan';
                }));
                $normal = array_values(array_filter($temphaiyun, function($row){
                    return strtolower($row['region'] ?? '') === 'normal';
                }));
                $others = array_values(array_filter($temphaiyun, function($row){
                    $r = strtolower($row['region'] ?? '');
                    return $r !== 'pianyuan' && $r !== 'normal';
                }));

                // 优先级：pianyuan > normal > 其他
                $candidate = !empty($pianyuan) ? $pianyuan : (!empty($normal) ? $normal : $others);

                // 在候选组中取最小 gv
                usort($candidate, function($a, $b){
                    return floatval($a['gv']) <=> floatval($b['gv']);
                });

                $haiyun = $candidate[0]; // 最小的那个
            }
            
            //$haiyun = $temphaiyun[$ik] ?? [];
        }
        
        // ===== 原有：按旧结构求空/海 =====
        $kyzipcode = false; $kychaozhong = true; $kongyun = []; $tempkongyun = [];
        // ★ 修改：允许"可送达命中"时继续算空运，即使 $kybusong 为 true
        if ( !$kybusong ) {
            foreach ($shipping_kongyun_kesong as $cv) {
                $symbol = $cv['symbol'];
                $shippingtime = $cv['shippingtime'] ?? 'around 7 days';
                $region = $cv['region'] ?? 'normal';
                $currate = $cv['currate'];
                $baoguanfee = $cv['baoguanfee'];
                $zipcodeArr = qin_build_zip_array_from_rule($cv, $zipgroups);

                if (in_zipArr($zipcodeArr, $zipcode)) {
                    $kyzipcode = true;
                    foreach ($cv['kongyun'] as $kv) {
                        if (isset($kv[2]) && !empty($kv[2])) {
                            if ($weight >= $kv[0] && $weight <= $kv[1]) {
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
                                $tempkongyun[] = ['gs' => $ruleStr, 'service_name' => 'Air Shipping', 'currency' => $symbol, 'amount' => ceil($ruleV), 'gv' => ceil($ruleV),'transit_days' => $shippingtime,'gv' => ceil($ruleV), 'region' => $region, 'shippingtime' => $shippingtime, 'symbol' => $symbol, 'name' => 'Air Shipping'];
                                $kychaozhong = false;
                            }
                        }
                    }
                }
            }

            if (!empty($tempkongyun)) {
                // 按区域分组：pianyuan 优先，其次 normal，最后其他
                $pianyuan = array_values(array_filter($tempkongyun, function($row){
                    return strtolower($row['region'] ?? '') === 'pianyuan';
                }));
                $normal = array_values(array_filter($tempkongyun, function($row){
                    return strtolower($row['region'] ?? '') === 'normal';
                }));
                $others = array_values(array_filter($tempkongyun, function($row){
                    $r = strtolower($row['region'] ?? '');
                    return $r !== 'pianyuan' && $r !== 'normal';
                }));

                // 优先级：pianyuan > normal > 其他
                $candidate = !empty($pianyuan) ? $pianyuan : (!empty($normal) ? $normal : $others);

                // 在候选组中取最小 gv
                usort($candidate, function($a, $b){
                    return floatval($a['gv']) <=> floatval($b['gv']);
                });

                $kongyun = $candidate[0]; // 最小的那个
            }
            //$kongyun = $tempkongyun[$ik] ?? [];
        }


        // ====== ★ 把"多公司"计算提前到早退判断之前 ======
        $internal_list = array();

        // （3）海运多公司
        if ( !$hybusong ) {
            $internal_hy = empty($haiyun) ? [] : [$haiyun];
                
            foreach ($shipping_haiyun_kesong as $cv) {
                $zipcodeArr = qin_build_zip_array_from_rule($cv, $zipgroups);
                if (!in_zipArr($zipcodeArr, $zipcode)) continue;
                if (empty($cv['haiyun_multi']) || !is_array($cv['haiyun_multi'])) continue;
                $region_mv = $cv['region'] ?? 'normal';
                foreach ($cv['haiyun_multi'] as $vid => $vd) {
                    $symbol_v   = $vd['symbol'] ?? ($cv['symbol'] ?? 'USD');
                    $currate_v  = $vd['currate'] ?? ($cv['currate'] ?? 1);
                    $baoguan_v  = $vd['baoguanfee'] ?? ($cv['baoguanfee'] ?? 0);
                    $vendor_nm  = $vd['vendor_name'] ?? 'Sea Vendor';
                    $shiptime_v = $vd['shippingtime'] ?? ($cv['shippingtime'] ?? '');
                    if (!empty($vd['rules']) && is_array($vd['rules'])) {
                        foreach ($vd['rules'] as $rv) {
                            if (!isset($rv[2]) || $rv[2]==='') continue;
                            $w0 = floatval($rv[0] ?? 0);
                            $w1 = floatval($rv[1] ?? 0);
                            if ($weight >= $w0 && $weight <= $w1) {
                                $rule = $rv[2];
                                $rule = str_replace('汇率', $currate_v, $rule);
                                $rule = str_replace('报关费', $baoguan_v, $rule);
                                $rule = str_replace('重量', $weight, $rule);
                                $rule = str_replace('出厂价', $exwprice, $rule);
                                $ruleStr = $rule;
                                $ruleStr = str_replace('：', ':', $ruleStr);
                                $ruleStr = str_replace('？', '?', $ruleStr);
                                $ruleStr = str_replace('（', '(', $ruleStr);
                                $ruleStr = str_replace('）', ')', $ruleStr);
                                $ruleStr = str_ireplace('x', '*', $ruleStr);
                                $ruleStr = str_replace('向上取整', 'ceil',  $ruleStr);
                                $ruleStr = str_replace('进一取整', 'ceil',  $ruleStr);
                                $ruleStr = str_replace('舍去取整', 'floor',  $ruleStr);
                                $ruleStr = str_replace('向下取整', 'floor',  $ruleStr);
                                $ruleStr = str_replace('最大', 'max',  $ruleStr);
                                $ruleStr = str_replace('最小', 'min',  $ruleStr);
                                $ruleStr = html_entity_decode($ruleStr);
                                $ruleStr = preg_replace('/\?\((.*?)>([^\)]*?)\)/i', '((\\1 > \\2) ? \\1 : \\2)', $ruleStr);
                                $ruleStr = str_replace('< =', '<=',  $ruleStr);
                                $ruleStr = str_replace('> =', '>=',  $ruleStr);
                                $ruleV   = @eval("return $ruleStr;");
                                if ($ruleV!==false && $ruleV!==null && $ruleV!=='') {
                                    $internal_hy[] = array(
                                        'gs'           => $ruleStr,
                                        'carrier'      => 'Internal',
                                        'service_code' => 'Sea Shipping - '.$vendor_nm,
                                        'service_name' => $vendor_nm,
                                        'region'       => $region_mv,
                                        'gv'           => ceil($ruleV),
                                        'amount'       => ceil($ruleV),
                                        'currency'     => (is_string($symbol_v) && strlen($symbol_v)==3) ? $symbol_v : 'USD',
                                        'transit_days' => $shiptime_v,
                                        'delivery_date'=> null,
                                        'meta'         => array('source'=>'rule','region'=>$region_mv,'vendor_id'=>$vid),
                                    );
                                }
                                break;
                            }
                        }
                    }
                }
            }
            
            if (!empty($internal_hy)) {
                // 按区域分组：pianyuan 优先，其次 normal，最后其他
                $pianyuan = array_values(array_filter($internal_hy, function($row){
                    return strtolower($row['region'] ?? '') === 'pianyuan';
                }));
                $normal = array_values(array_filter($internal_hy, function($row){
                    return strtolower($row['region'] ?? '') === 'normal';
                }));
                $others = array_values(array_filter($internal_hy, function($row){
                    $r = strtolower($row['region'] ?? '');
                    return $r !== 'pianyuan' && $r !== 'normal';
                }));

                // 优先级：pianyuan > normal > 其他
                $candidate = !empty($pianyuan) ? $pianyuan : (!empty($normal) ? $normal : $others);
                
                foreach ($candidate as $c) {
                    $internal_list[] = $c;
                }
            }
        }
        
        // （2）空运多公司
        if ( !$kybusong ) {
            $internal_ky = empty($kongyun) ? [] : [$kongyun];
            foreach ($shipping_kongyun_kesong as $cv) {
                $zipcodeArr = qin_build_zip_array_from_rule($cv, $zipgroups);
                if (!in_zipArr($zipcodeArr, $zipcode)) continue;
                if (empty($cv['kongyun_multi']) || !is_array($cv['kongyun_multi'])) continue;
                $region_mv = $cv['region'] ?? 'normal';
                foreach ($cv['kongyun_multi'] as $vid => $vd) {
                    $symbol_v   = $vd['symbol'] ?? ($cv['symbol'] ?? 'USD');
                    $currate_v  = $vd['currate'] ?? ($cv['currate'] ?? 1);
                    $baoguan_v  = $vd['baoguanfee'] ?? ($cv['baoguanfee'] ?? 0);
                    $vendor_nm  = $vd['vendor_name'] ?? 'Air Vendor';
                    $shiptime_v = $vd['shippingtime'] ?? ($cv['shippingtime'] ?? '');
                    if (!empty($vd['rules']) && is_array($vd['rules'])) {
                        foreach ($vd['rules'] as $rv) {
                            if (!isset($rv[2]) || $rv[2]==='') continue;
                            $w0 = floatval($rv[0] ?? 0);
                            $w1 = floatval($rv[1] ?? 0);
                            if ($weight >= $w0 && $weight <= $w1) {
                                $rule = $rv[2];
                                $rule = str_replace('汇率', $currate_v, $rule);
                                $rule = str_replace('报关费', $baoguan_v, $rule);
                                $rule = str_replace('重量', $weight, $rule);
                                $rule = str_replace('出厂价', $exwprice, $rule);
                                $ruleStr = $rule;
                                $ruleStr = str_replace('：', ':', $ruleStr);
                                $ruleStr = str_replace('？', '?', $ruleStr);
                                $ruleStr = str_replace('（', '(', $ruleStr);
                                $ruleStr = str_replace('）', ')', $ruleStr);
                                $ruleStr = str_ireplace('x', '*', $ruleStr);
                                $ruleStr = str_replace('向上取整', 'ceil',  $ruleStr);
                                $ruleStr = str_replace('进一取整', 'ceil',  $ruleStr);
                                $ruleStr = str_replace('舍去取整', 'floor',  $ruleStr);
                                $ruleStr = str_replace('向下取整', 'floor',  $ruleStr);
                                $ruleStr = str_replace('最大', 'max',  $ruleStr);
                                $ruleStr = str_replace('最小', 'min',  $ruleStr);
                                $ruleStr = html_entity_decode($ruleStr);
                                $ruleStr = preg_replace('/\?\((.*?)>([^\)]*?)\)/i', '((\\1 > \\2) ? \\1 : \\2)', $ruleStr);
                                $ruleStr = str_replace('< =', '<=',  $ruleStr);
                                $ruleStr = str_replace('> =', '>=',  $ruleStr);
                                $ruleV   = @eval("return $ruleStr;");
                                if ($ruleV!==false && $ruleV!==null && $ruleV!=='') {
                                    $internal_ky[] = array(
                                        'gs'           => $ruleStr,
                                        'carrier'      => 'Internal',
                                        'service_code' => 'AIR-'.$vid,
                                        'service_name' => $vendor_nm,
                                        'region'       => $region_mv,
                                        'gv'           => ceil($ruleV),
                                        'amount'       => ceil($ruleV),
                                        'currency'     => (is_string($symbol_v) && strlen($symbol_v)==3) ? $symbol_v : 'USD',
                                        'transit_days' => $shiptime_v,
                                        'delivery_date'=> null,
                                        'meta'         => array('source'=>'rule','region'=>$region_mv,'vendor_id'=>$vid),
                                    );
                                }
                                break;
                            }
                        }
                    }
                }
            }
            
            if (!empty($internal_ky)) {
                // 按区域分组：pianyuan 优先，其次 normal，最后其他
                $pianyuan = array_values(array_filter($internal_ky, function($row){
                    return strtolower($row['region'] ?? '') === 'pianyuan';
                }));
                $normal = array_values(array_filter($internal_ky, function($row){
                    return strtolower($row['region'] ?? '') === 'normal';
                }));
                $others = array_values(array_filter($internal_ky, function($row){
                    $r = strtolower($row['region'] ?? '');
                    return $r !== 'pianyuan' && $r !== 'normal';
                }));

                // 优先级：pianyuan > normal > 其他
                $candidate = !empty($pianyuan) ? $pianyuan : (!empty($normal) ? $normal : $others);
                
                foreach ($candidate as $c) {
                    $internal_list[] = $c;
                }
            }
        }


        // ★ 成功路径：统一 items 返回（包含 FedEx + 多公司 + 旧单公司）
        $all_items = array_merge($internal_list, $fedex_list);
        
        if ( empty($all_items) ) {
            $response = new WP_REST_Response(array('message'=>'Shipping rates can not be calculated for this area.'));
            $response->set_status(200);
            return $response;
        }

        $data = array(
            'message' => 'ok',
            'kongyun' => $kongyun,
            'haiyun'  => $haiyun,
            'weight'  => $weight,
            'fedex'   => $fedex_list,
            'items'   => $all_items,      // ★ 补上 items
        );
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


// ================= New Sample: create checkout via REST  =================

add_action('rest_api_init', function () {
  register_rest_route('qinprinting/v1', '/sample_2', array(
    'methods'  => 'POST',
    'callback' => 'qin_sample_new_checkout',
    'permission_callback' => '__return_true',
  ));
});

function qin_sample_new_checkout( WP_REST_Request $req ) {
  if ( ! function_exists('WC') ) {
    return new WP_REST_Response(array('message'=>'WooCommerce not available'), 500);
  }
    
  $email  = sanitize_text_field( (string) $req->get_param('email') );  
  $zipcode  = sanitize_text_field( (string) $req->get_param('zipcode') );
  $country  = strtoupper( sanitize_text_field( (string) $req->get_param('country') ) );
  $weight   = (float) preg_replace('/[^\d.]/', '', (string)$req->get_param('weight') );

  $page_url = esc_url_raw( (string)$req->get_param('page_url') );
  $note_raw = (string)$req->get_param('note');           // window.formfields4
  $extra    = (string)$req->get_param('extra_note');     // 你想额外带过去的内容（可选）

  if ( $zipcode === '' || $country === '' || $weight <= 0 ) {
    return new WP_REST_Response(array('message'=>'Missing zipcode/country/weight'), 400);
  }

  // 1) 后端再次调用你已有的 shippingcost 端点（不信前端价格）
  $r = new WP_REST_Request('POST', '/qinprinting/v1/shippingcost');
  $r->set_param('weight', number_format($weight, 2, '.', ''));
  $r->set_param('zipcode', $zipcode);
  $r->set_param('country', $country);

  $resp = rest_do_request($r);
  $data = $resp->get_data();

  if ( empty($data) || !is_array($data) || ($data['message'] ?? '') !== 'ok' ) {
    // 运费不可用：告诉前端走 WPForms 正常提交
    return new WP_REST_Response(array(
      'message' => $data['message'] ?? 'Shipping unavailable',
      'action'  => 'wpforms'
    ), 200);
  }

  // 2) 只取 FedEx IP
  $shipping_usd = 0;
  if ( !empty($data['fedex']) && is_array($data['fedex']) ) {
    foreach ($data['fedex'] as $x) {
      if ( is_array($x) && ($x['service_code'] ?? '') === 'FEDEX_INTERNATIONAL_PRIORITY' ) {
        $shipping_usd = (float) ($x['amount'] ?? 0);
        break;
      }
    }
  }
  if ( $shipping_usd <= 0 ) {
    return new WP_REST_Response(array(
      'message' => 'FedEx IP unavailable',
      'action'  => 'wpforms'
    ), 200);
  }

  // 3) 计算 PayPal fee（5%）
  // 你如果只想按 shipping 算，就保持这样；如果未来有 sample fee，可以改成 (shipping + subtotal)*0.05
  $paypal_fee = round($shipping_usd * 0.05, 2);
  $total_usd  = round($shipping_usd + $paypal_fee, 2);
  
    $note_raw = preg_replace("/\r\n|\r/", "\n", $note_raw);
    $note_raw = preg_replace('/#s#(.*?)#s#/s', '<del>$1</del>', $note_raw);
    $note_raw = preg_replace('/#r#(.*?)#r#/s', '<span style="color:#d63638;font-weight:600;">$1</span>', $note_raw);
    $note_raw = trim($note_raw);
    
  // 4) 组织 note（建议把页面 URL、国家邮编、weight、FedEx IP 都写进去）
  $note = '';
  if ($note_raw !== '') $note .= trim($note_raw) . "\n\n";
  if ($extra !== '')    $note .= trim($extra) . "\n\n";
  $note .= "Page: {$page_url}\nShip to: {$country} {$zipcode}\nWeight(kg): " . number_format($weight,2,'.','') . "\n";
  $note .= "Shipping: FedEx IP = {$shipping_usd} USD\nPayPal fee(5%): {$paypal_fee} USD\nTotal: {$total_usd} USD\n";

    // 5) 确保 Woo session/cart 在 REST 场景可用，并强制落 cookie
    if ( null === WC()->session ) {
        WC()->initialize_session();
    }
    if ( null === WC()->cart ) {
        wc_load_cart(); // ✅ REST 必须显式加载 cart
    }

    // ✅ REST 场景：确保 customer 不为 null（修复 get_is_vat_exempt() fatal）
    if ( null === WC()->customer ) {
        WC()->customer = new WC_Customer( get_current_user_id(), true );
    }
    WC()->customer->set_billing_country( $country );
    WC()->customer->set_shipping_country( $country );
    WC()->customer->set_is_vat_exempt( false );

    // ✅ 强制设置 Woo session cookie（否则下一页 checkout 读不到 qp_newsample_ctx）
    if ( method_exists( WC()->session, 'set_customer_session_cookie' ) ) {
        WC()->session->set_customer_session_cookie( true );
    }

    // 写入 Woo session
    WC()->session->set('qp_newsample_note', $note);
    WC()->session->set('qp_newsample_ctx', array(
      'country' => $country,
      'zipcode' => $zipcode,
      'weight'  => $weight,
      'shipping_usd' => $shipping_usd,
      'paypal_fee'   => $paypal_fee,
      'total_usd'    => $total_usd,
      'page_url'     => $page_url,
      'ts'           => time(),
    ));

    //  PHP session
    $_SESSION['QP_NEWSAMPLE_NOTE'] = $note;
    $_SESSION['QP_NEWSAMPLE_CTX']  = array(
      'country' => $country,
      'zipcode' => $zipcode,
      'weight'  => $weight,
      'shipping_usd' => $shipping_usd,
      'paypal_fee'   => $paypal_fee,
      'total_usd'    => $total_usd,
      'page_url'     => $page_url,
      'ts'           => time(),
    );
    $_SESSION['SAMPLESCOUN'] = $country;
    $_SESSION['SAMPLESPOST'] = $zipcode;
    $_SESSION['SAMPLESEMAIL'] = $email;
    
    // 6) 加入购物车
    $product_id = (int) apply_filters('qin_newsample_product_id', SAMPLE_PRODID);

    WC()->cart->empty_cart();

    $added_key = WC()->cart->add_to_cart($product_id, 1, 0, array(), array(
      'qp_newsample' => 1,
      'qp_price'     => $total_usd,
      'qp_shipping'  => $shipping_usd,
      'qp_paypalfee' => $paypal_fee,
    ));

    if ( ! $added_key ) {
        return new WP_REST_Response(array(
          'message' => 'add_to_cart failed',
          'action'  => 'wpforms',
          'cart_count' => WC()->cart ? count(WC()->cart->get_cart()) : 0,
        ), 200);
    }

    // ✅ 把 cart 写入 session & 写 cookie（先 set_session 再 totals）
    WC()->cart->set_session();
    WC()->cart->calculate_totals();

    if ( method_exists( WC()->cart, 'maybe_set_cart_cookies' ) ) {
        WC()->cart->maybe_set_cart_cookies();
    }

    if ( method_exists( WC()->session, 'save_data' ) ) {
        WC()->session->save_data();
    }

    // ✅ 防 Cloudflare 缓存 REST
    nocache_headers();

    return new WP_REST_Response(array(
      'message' => 'ok',
      'action'  => 'checkout',
      'checkout_url' => wc_get_checkout_url(),
      'added_key' => $added_key,
      'cart_count' => WC()->cart ? count(WC()->cart->get_cart()) : 0,
      'total_usd' => $total_usd,
      'shipping_usd' => $shipping_usd,
      'paypal_fee' => $paypal_fee,
    ), 200);

}

// 让购物车里这件“新 sample”商品使用自定义价格（total）
add_action('woocommerce_before_calculate_totals', function($cart){
  if ( is_admin() && !defined('DOING_AJAX') ) return;
  if ( !$cart ) return;
  foreach ( $cart->get_cart() as $key => $item ) {
    if ( !empty($item['qp_newsample']) && isset($item['qp_price']) ) {
      $price = (float)$item['qp_price'];
      if ($price > 0 && isset($item['data']) && is_object($item['data'])) {
        $item['data']->set_price($price);
      }
    }
  }
}, 20);

// 把 note / breakdown 写入订单行项目 meta（后台可见）
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order){
  if ( empty($values['qp_newsample']) ) return;
  
  // ✅ 隐藏 meta，不会显示在前台
  $item->add_meta_data('_qp_newsample', '1', true);
  
  $note = WC()->session ? WC()->session->get('qp_newsample_note') : '';
  if ($note) $item->add_meta_data('Sample Note', $note);

  if (!empty($values['qp_shipping']))  $item->add_meta_data('Shipping (FedEx IP, USD)', (string)$values['qp_shipping']);
  if (!empty($values['qp_paypalfee'])) $item->add_meta_data('PayPal Fee (5%, USD)', (string)$values['qp_paypalfee']);
  if (!empty($values['qp_price']))     $item->add_meta_data('Total (USD)', (string)$values['qp_price']);

}, 10, 4);

add_filter('woocommerce_order_item_get_formatted_meta_data', function($formatted_meta, $item){

  // 后台不隐藏（你们内部仍要看到）
  if ( is_admin() ) return $formatted_meta;

  // 只针对 sample_2：看隐藏标记
  if ( ! $item->get_meta('_qp_newsample', true) ) return $formatted_meta;

  // 要隐藏的 meta key
  $hide_keys = array(
    'Sample Note',
    'Shipping (FedEx IP, USD)',
    'PayPal Fee (5%, USD)',
    'Total (USD)',
  );

  foreach ($formatted_meta as $id => $meta) {
    if ( isset($meta->key) && in_array($meta->key, $hide_keys, true) ) {
      unset($formatted_meta[$id]);
    }
  }

  return $formatted_meta;
}, 10, 2);


// 下单后清理 session，避免串到下一单
add_action('woocommerce_thankyou', function($order_id){
  if (WC()->session) {
    WC()->session->__unset('qp_newsample_note');
    WC()->session->__unset('qp_newsample_ctx');
  }
}, 10, 1);


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
    
    $bookbinding = $entry['fields'][31] ?? [];
    
    $boardbook = $entry['fields'][32] ?? [];
    
    $catlogbinding = $entry['fields'][34] ?? [];
    
    $calendartype = $entry['fields'][33] ?? [];
    
    $boxtype = $entry['fields'][35] ?? [];
    
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
        return true;
    }
    
    // ★ 修改：支持多渠道运费验证
    $checkprice = false;
    $submitted_price = floatval($_POST['sampleshippingcost']);
    
    // 检查是否在 items 列表中
    if (isset($_SESSION['SAMPLESHIPPINGDATA']['items']) && is_array($_SESSION['SAMPLESHIPPINGDATA']['items'])) {
        foreach ($_SESSION['SAMPLESHIPPINGDATA']['items'] as $item) {
            $item_price = isset($item['amount']) ? floatval($item['amount']) : (isset($item['gv']) ? floatval($item['gv']) : 0);
            if (abs($item_price - $submitted_price) < 0.01) { // 允许小数点误差
                $checkprice = true;
                break;
            }
        }
    }
    
    // 兼容旧的验证方式
    if (!$checkprice) {
        if (isset($_SESSION['SAMPLESHIPPINGDATA']['haiyun']['gv']) && 
            abs(floatval($_SESSION['SAMPLESHIPPINGDATA']['haiyun']['gv']) - $submitted_price) < 0.01) {
            $checkprice = true;
        }
        if (isset($_SESSION['SAMPLESHIPPINGDATA']['kongyun']['gv']) && 
            abs(floatval($_SESSION['SAMPLESHIPPINGDATA']['kongyun']['gv']) - $submitted_price) < 0.01) {
            $checkprice = true;
        }
    }
    
    if ($checkprice == false) {
        return true;
    }
    
    $price = (int)$_POST['sampleshippingcost'];
    $gst_price = round(SAMPLE_GST / 100 * $price, 2);
    $pg = $price + $gst_price;
                
    $_SESSION['SAMPLESTXT'] = generate_sampledetails($entry);
    $_SESSION['SAMPLESDIV'] = generate_sampledetails_div($entry);
    $_SESSION['SAMPLESFEE'] = $price;
    $_SESSION['SAMPLESGST'] = $gst_price;

    WC()->cart->empty_cart();
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
    
    $details .= '<div style="border:1px solid #ccc;padding: 15px;"><div style="line-height: 1.8rem;color: #00afdd;font-weight:bold;margin: 0 0 10px 0;">Product Type:</div>' . "\r\n";
    
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
    $anything && $details .= "\r\n" . '<div style="line-height: 1.8rem;color: #00afdd;font-weight:bold;margin: 0 0 10px 0;">Other Requirements: </div><p>' . $anything . "</p>\r\n";
    
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
    
    // 查看 session 是否过期（兼容 New Sample）
    $has_old_sample = isset($_SESSION['SAMPLESTXT']);

    $has_new_sample = (
      function_exists('WC') && WC()->session && WC()->session->get('qp_newsample_ctx')
    ) || isset($_SESSION['QP_NEWSAMPLE_CTX']);

    if ( $has_new_sample && !$has_old_sample ) {
        $note = '';
        if ( function_exists('WC') && WC()->session ) {
            $note = WC()->session->get('qp_newsample_note');
        }
        if ( !$note && isset($_SESSION['QP_NEWSAMPLE_NOTE']) ) {
            $note = $_SESSION['QP_NEWSAMPLE_NOTE'];
        }

        echo '<h3 style="display:none;">Sample Details</h3>';
        echo '<div style="display:none">';
        woocommerce_form_field('sampledetails', array(
            'type' => 'textarea',
            'class' => array('woocommerce-additional-fields__field-wrapper'),
            'label' => 'Details',
            'custom_attributes' => array('readonly' => 'readonly'),
        ), $note ? $note : '');
        echo '</div>';
        return true;
    }


    // === 输出内容：旧 sample 继续用原逻辑；新 sample 显示 qp_newsample_note ===
    if ( $has_new_sample && !$has_old_sample ) {
        $note = WC()->session->get('qp_newsample_note');
        echo '<h3>Sample Details</h3>';
        woocommerce_form_field('sampledetails', array(
            'type' => 'textarea',
            'class' => array('woocommerce-additional-fields__field-wrapper'),
            'label' => 'Details',
            'custom_attributes' => array('readonly' => 'readonly'),
        ), $note ? $note : '');
        return true;
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
function qin_order_is_sample2($order){
  if ( ! $order instanceof WC_Order ) return false;
  foreach ($order->get_items() as $item) {
    if ( $item->get_meta('_qp_newsample', true) ) return true;
    // 兼容旧单（没加标记时）可用 Sample Note 兜底：
    // if ( $item->get_meta('Sample Note', true) ) return true;
  }
  return false;
}

function qin_order_details( $order ) {

    // ✅ sample_2 不显示 Sample Order Details 区块
    if ( qin_order_is_sample2($order) ) {
        return;
    }
  
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

// ✅ 统一预填 checkout 字段：billing + shipping
add_filter('woocommerce_checkout_get_value', function($value, $input){
    if (!is_checkout() || is_order_received_page()) return $value;

    // 没有样品表单 session 就不管
    if (empty($_SESSION['SAMPLESEMAIL']) && empty($_SESSION['SAMPLESFNAME']) && empty($_SESSION['SAMPLESPOST'])) {
        return $value;
    }

    // 你的 session -> Woo 字段映射
    $map = [
        // billing
        'billing_first_name' => $_SESSION['SAMPLESFNAME'] ?? '',
        'billing_last_name'  => $_SESSION['SAMPLESLNAME'] ?? '',
        'billing_company'    => $_SESSION['SAMPLESCOMPA'] ?? '',
        'billing_email'      => $_SESSION['SAMPLESEMAIL'] ?? '',
        'billing_phone'      => $_SESSION['SAMPLESTELEP'] ?? '',
        'billing_country'    => $_SESSION['SAMPLESCOUN'] ?? '',
        'billing_address_1'  => $_SESSION['SAMPLESADDR1'] ?? '',
        'billing_address_2'  => $_SESSION['SAMPLESADDR2'] ?? '',
        'billing_city'       => $_SESSION['SAMPLESCITY'] ?? '',
        'billing_state'      => $_SESSION['SAMPLESSTATE'] ?? '',
        'billing_postcode'   => $_SESSION['SAMPLESPOST'] ?? '',

        // shipping
        'shipping_first_name' => $_SESSION['SAMPLESFNAME'] ?? '',
        'shipping_last_name'  => $_SESSION['SAMPLESLNAME'] ?? '',
        'shipping_company'    => $_SESSION['SAMPLESCOMPA'] ?? '',
        'shipping_country'    => $_SESSION['SAMPLESCOUN'] ?? '',
        'shipping_address_1'  => $_SESSION['SAMPLESADDR1'] ?? '',
        'shipping_address_2'  => $_SESSION['SAMPLESADDR2'] ?? '',
        'shipping_city'       => $_SESSION['SAMPLESCITY'] ?? '',
        'shipping_state'      => $_SESSION['SAMPLESSTATE'] ?? '',
        'shipping_postcode'   => $_SESSION['SAMPLESPOST'] ?? '',

        // 你自己加的 shipping_phone
        'shipping_phone'      => $_SESSION['SAMPLESTELEP'] ?? '',
    ];

    // 只有当 Woo 原值为空时才填，避免覆盖用户手动输入
    if ((string)$value === '' && isset($map[$input]) && $map[$input] !== '') {
        return $map[$input];
    }

    return $value;
}, 10, 2);


// ★★★ 修改后的 switch_billing_shipping 函数 - 支持多渠道运费选择 ★★★
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
            var zipcode = "<?php echo addslashes($_SESSION['SAMPLESPOST'] ?? '');?>";
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
                "<?php echo addslashes($_SESSION['SAMPLESFNAME'] ?? '');?>" && $('#billing_first_name').val("<?php echo addslashes($_SESSION['SAMPLESFNAME'] ?? '');?>");
                "<?php echo addslashes($_SESSION['SAMPLESLNAME'] ?? '');?>" && $('#billing_last_name').val("<?php echo addslashes($_SESSION['SAMPLESLNAME'] ?? '');?>");
                "<?php echo addslashes($_SESSION['SAMPLESCOMPA'] ?? '');?>" && $('#billing_company').val("<?php echo addslashes($_SESSION['SAMPLESCOMPA'] ?? '');?>");
                "<?php echo addslashes($_SESSION['SAMPLESEMAIL'] ?? '');?>" && $('#billing_email').val("<?php echo addslashes($_SESSION['SAMPLESEMAIL'] ?? '');?>");
                "<?php echo addslashes($_SESSION['SAMPLESCOUN'] ?? '');?>" && $('#billing_country').val("<?php echo addslashes($_SESSION['SAMPLESCOUN'] ?? '');?>");
                "<?php echo addslashes($_SESSION['SAMPLESCOUN'] ?? '');?>" && $('#shipping_country').val("<?php echo addslashes($_SESSION['SAMPLESCOUN'] ?? '');?>");
                "<?php echo addslashes($_SESSION['SAMPLESADDR1'] ?? '');?>" && $('#billing_address_1').val("<?php echo addslashes($_SESSION['SAMPLESADDR1'] ?? '');?>");
                "<?php echo addslashes($_SESSION['SAMPLESADDR2'] ?? '');?>" && $('#billing_address_2').val("<?php echo addslashes($_SESSION['SAMPLESADDR2'] ?? '');?>");
                "<?php echo addslashes($_SESSION['SAMPLESCITY'] ?? '');?>" && $('#billing_city').val("<?php echo addslashes($_SESSION['SAMPLESCITY'] ?? '');?>");
                "<?php echo addslashes($_SESSION['SAMPLESPOST'] ?? '');?>" && $('#billing_postcode').val("<?php echo addslashes($_SESSION['SAMPLESPOST'] ?? '');?>");
                "<?php echo addslashes($_SESSION['SAMPLESPOST'] ?? '');?>" && $('#shipping_postcode').val("<?php echo addslashes($_SESSION['SAMPLESPOST'] ?? '');?>");
                "<?php echo addslashes($_SESSION['SAMPLESTELEP'] ?? '');?>" && $('#billing_phone').val("<?php echo addslashes($_SESSION['SAMPLESTELEP'] ?? '');?>");
                "<?php echo addslashes($_SESSION['SAMPLESSTATE'] ?? '');?>" && $('#billing_state').val("<?php echo addslashes($_SESSION['SAMPLESSTATE'] ?? '');?>");
                
                <?php if (isset($_SESSION['PRODUCPRICE'])) :?>
                
                $('#billing_postcode').prop('readonly', true);
                $('#shipping_postcode').prop('readonly', true);
                $('#billing_country').attr('readonly', true);
                $('#shipping_country').attr('readonly', true);

                $('#billing_country').find('option').each((i, n) => {
                    let cv = $(n).attr('value');
                    !in_array(cv, ["<?php echo addslashes($_SESSION['SAMPLESCOUN'] ?? '');?>"]) && $(n).remove();
                });
                
                $('#shipping_country').find('option').each((i, n) => {
                    let cv = $(n).attr('value');
                    !in_array(cv, ["<?php echo addslashes($_SESSION['SAMPLESCOUN'] ?? '');?>"]) && $(n).remove();
                });
                
                <?php endif;?>
                
                $('#billing_postcode, #shipping_postcode').change(function () {
                    return false;
                });
                
                //触发事件
                $('#billing_country').trigger('change');
                $('#billing_state option').length && $('#billing_state option').filter(function(){return $(this).attr('value').toUpperCase() == "<?php $ss = ($_SESSION['SAMPLESSTATE'] ?? '') == '' ? 'AL' : strtolower(addslashes($_SESSION['SAMPLESSTATE'] ?? ''));echo $ss;?>";}).attr("selected",true);  
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

    $qin_options = get_option( 'wp_sample_countrylist' );
    $sample_countrylist = ( is_serialized( $qin_options )) ? unserialize( $qin_options ) : $qin_options;
    if (!is_array($sample_countrylist)) {
        $sample_countrylist = [];
    }

    ?>
    <style>
    /* 多渠道运费选择样式 */
    .sample-shipping-options {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        margin: 15px 0;
        background: #f9f9f9;
    }
    .sample-shipping-options h4 {
        margin: 0 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 2px solid #00afdd;
        color: #333;
    }
    .shipping-option-item {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        margin: 8px 0;
        background: #fff;
        border: 2px solid #e0e0e0;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .shipping-option-item:hover {
        border-color: #00afdd;
        box-shadow: 0 2px 8px rgba(0,175,221,0.15);
    }
    .shipping-option-item.selected {
        border-color: #00afdd;
        background: #f0fafd;
    }
    .shipping-option-item input[type="radio"] {
        margin-right: 12px;
        transform: scale(1.2);
    }
    .shipping-option-info {
        flex: 1;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .shipping-option-name {
        font-weight: 600;
        color: #333;
    }
    .shipping-option-carrier {
        font-size: 12px;
        color: #666;
        margin-left: 8px;
        padding: 2px 8px;
        background: #eee;
        border-radius: 3px;
        display:none;
    }
    .shipping-option-details {
        text-align: right;
    }
    .shipping-option-price {
        font-size: 18px;
        font-weight: 700;
        color: #00afdd;
    }
    .shipping-option-time {
        font-size: 12px;
        color: #888;
        margin-top: 2px;
    }
    .shipping-option-date {
        font-size: 11px;
        color: #999;
    }
    .shipping-summary {
        margin-top: 20px;
        padding: 15px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 6px;
    }
    .shipping-summary-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px dashed #eee;
    }
    .shipping-summary-row:last-child {
        border-bottom: none;
    }
    .shipping-summary-row.total {
        font-weight: 700;
        font-size: 16px;
        color: #00afdd;
        border-top: 2px solid #00afdd;
        margin-top: 10px;
        padding-top: 15px;
    }
    .no-shipping-available {
        padding: 20px;
        text-align: center;
        color: #e74c3c;
        background: #fdf2f2;
        border-radius: 6px;
        display:none;
    }
    </style>
    <script>
    const $sample_countrylist = ['<?php echo implode("', '", $sample_countrylist);?>'];
    
    // 辅助函数：检查数组是否包含某个值
    function in_array(needle, haystack) {
        for (var i = 0; i < haystack.length; i++) {
            if (haystack[i] == needle) return true;
        }
        return false;
    }
    
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
        
        // 生成唯一ID
        function generateOptionId(item, index) {
            if (item.meta && item.meta.vendor_id) {
                return item.meta.vendor_id;
            }
            if (item.service_code) {
                return item.service_code + '_' + index;
            }
            return 'option_' + index;
        }
        
        // 获取显示的货币符
        // 获取显示的货币符号
        function getCurrencySymbol(item) {
            if (item.currency && item.currency !== '') {
                return item.currency;
            }
            if (item.symbol && item.symbol !== '') {
                return item.symbol;
            }
            return '$';
        }
        
        // 获取承运商标签
        function getCarrierLabel(item) {
            if (item.carrier === 'FedEx') {
                return '<span class="shipping-option-carrier">FedEx</span>';
            }
            if (item.carrier === 'Internal') {
                if (item.service_code && item.service_code.indexOf('Sea') !== -1) {
                    return '<span class="shipping-option-carrier">Sea</span>';
                }
                return '<span class="shipping-option-carrier">Air</span>';
            }
            return '';
        }
        
        // 获取时效显示
        function getTransitDisplay(item) {
            let html = '';
            if (item.transit_days) {
                html += '<div class="shipping-option-time">' + item.transit_days + '</div>';
            } else if (item.shippingtime) {
                html += '<div class="shipping-option-time">' + item.shippingtime + '</div>';
            }
            if (item.delivery_date) {
                html += '<div class="shipping-option-date">Est. Delivery: ' + item.delivery_date + '</div>';
            }
            return html;
        }

        // 渲染运费选项
        function renderShippingOptions(items) {
            if (!items || items.length === 0) {
                return '';
            }
            
            // 按价格排序
            items.sort((a, b) => {
                let priceA = a.amount || a.gv || 0;
                let priceB = b.amount || b.gv || 0;
                return priceA - priceB;
            });
                        
            // ★ 找到第一个 Internal 渠道的索引（价格最小的）
            let firstInternalIndex = -1;
            for (let i = 0; i < items.length; i++) {
                if (items[i].carrier != 'FedEx') {
                    firstInternalIndex = i;
                    break;
                }
            }
            if ( firstInternalIndex == -1 ) {
                return '';
            }
            
            let html = '<div class="sample-shipping-options">';
            html += '<h4>Select Shipping Method</h4>';
            items.forEach((item, index) => {
                if (item.carrier != 'FedEx') {
                    let optionId = generateOptionId(item, index);
                    let price = item.amount || item.gv || 0;
                    let symbol = getCurrencySymbol(item);
                    let serviceName = item.service_name || item.name || 'Shipping';
                    let carrierLabel = getCarrierLabel(item);
                    let transitDisplay = getTransitDisplay(item);
                    
                    // ★ 只有第一个 Internal 渠道被选中
                    let isFirst = (index === firstInternalIndex) ? 'checked' : '';
                    let selectedClass = (index === firstInternalIndex) ? 'selected' : '';
                    
                    // 存储完整的item数据用于提交
                    let itemDataStr = JSON.stringify(item).replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    
                    html += '<label class="shipping-option-item ' + selectedClass + '" data-option-id="' + optionId + '">';
                    html += '<input type="radio" name="sampleshippingcost" value="' + price + '" ';
                    html += 'data-symbol="' + symbol + '" ';
                    html += 'data-name="' + serviceName + '" ';
                    html += 'data-carrier="' + (item.carrier || '') + '" ';
                    html += 'data-service-code="' + (item.service_code || '') + '" ';
                    html += 'data-item="' + itemDataStr + '" ';
                    html += isFirst + '>';
                    html += '<div class="shipping-option-info">';
                    html += '<div>';
                    html += '<span class="shipping-option-name">' + serviceName + '</span>';
                    html += carrierLabel;
                    html += '</div>';
                    html += '<div class="shipping-option-details">';
                    html += '<div class="shipping-option-price">' + symbol + price + '</div>';
                    html += transitDisplay;
                    html += '</div>';
                    html += '</div>';
                    html += '</label>';
                }
            });
            
            html += '</div>';
            return html;
        }

        
        // 更新价格汇总
        function updatePriceSummary() {
            let selectedOption = $('input[name="sampleshippingcost"]:checked');
            if (selectedOption.length === 0) return;
            
            let price = parseFloat(selectedOption.val()) || 0;
            let symbol = selectedOption.data('symbol') || '$';
            let serviceName = selectedOption.data('name') || 'Shipping';
            
            let gstPrice = ($gst / 100 * price);
            let totalPrice = price + gstPrice;
            
            $('span.shippingcur').text(symbol);
            $('span.shippingtotal').text(price.toFixed(2));
            $('span.shippinggst').text($gst);
            $('span.shippinggstfee').text(gstPrice.toFixed(2));
            $('span.grandtotal').text(totalPrice.toFixed(2));
            
            // 更新隐藏字段
            if ($('.wpform-shipping-cost input').length) {
                $('.wpform-shipping-cost input').val(serviceName + ' : ' +　symbol + totalPrice.toFixed(2));
            }
            
            // 更新选中样式
            $('.shipping-option-item').removeClass('selected');
            selectedOption.closest('.shipping-option-item').addClass('selected');
        }
        
        var doing = false;
        
        var changeEve = function() {
            var $booktype = $('.producttype').find("input[type='checkbox']:checked");
            var $bookbinding = $('.bookbinding').find("input[type='checkbox']:checked");
            var $boardbook = $('.boardbook').find("input[type='checkbox']:checked");
            var $calendartype = $('.calendartype').find("input[type='checkbox']:checked");
            var $catlogbinding = $('.catlogbinding').find("input[type='checkbox']:checked");
            var $boxtype = $('.boxtype').find("input[type='checkbox']:checked");
            
            var $price = $fee;
            var $bdqty = 0;
            
            var totalcount = count($booktype);
            var bv = $bookbinding.map((c, i, b) => ($(i).val()));
            var postcode = $('input.wpforms-field-address-postal');
            
            // 计算总数量的逻辑
            if (count($booktype) == 0) {
                $price = $fee;
                totalcount = 1;
            } else {
                if (count($booktype) == 1) {
                    $bdqty = count($bookbinding);
                    if ($bdqty == 2) { $price += $fq1 - 0; }
                    if ($bdqty > 2) {
                        $price += $fq1 - 0;
                        $price += ($fq2 - 0) * ($bdqty - 2);
                    }
                    if (in_array('Hardcover Binding', bv)) {
                        $price += $fq3 - 0;
                        totalcount += $bdqty == 1 ? 1 : $bdqty + 1 - 1;
                    } else {
                        totalcount += (count($bookbinding) > 1) ? ($bdqty - 1) : 0;
                    }
                    
                    $bdqty = count($boardbook);
                    totalcount += (count($boardbook) > 1) ? ($bdqty - 1) : 0;
                    if ($bdqty == 2) { $price += $fq1 - 0; }
                    if ($bdqty > 2) {
                        $price += $fq1 - 0;
                        $price += ($fq2 - 0) * ($bdqty - 2);
                    }
                    
                    $bdqty = count($catlogbinding);
                    totalcount += (count($catlogbinding) > 1) ? ($bdqty - 1) : 0;
                    if ($bdqty == 2) { $price += $fq1 - 0; }
                    if ($bdqty > 2) {
                        $price += $fq1 - 0;
                        $price += ($fq2 - 0) * ($bdqty - 2);
                    }
                    
                    $bdqty = count($calendartype);
                    totalcount += (count($calendartype) > 1) ? ($bdqty - 1) : 0;
                    if ($bdqty == 2) { $price += $fq1 - 0; }
                    if ($bdqty > 2) {
                        $price += $fq1 - 0;
                        $price += ($fq2 - 0) * ($bdqty - 2);
                    }
                    
                    $bdqty = count($boxtype);
                    totalcount += (count($boxtype) > 1) ? ($bdqty - 1) : 0;
                    if ($bdqty == 2) { $price += $fq1 - 0; }
                    if ($bdqty > 2) {
                        $price += $fq1 - 0;
                        $price += ($fq2 - 0) * ($bdqty - 2);
                    }
                    
                } else if (count($booktype) == 2) {
                    $price += $fq1 - 0;
                    
                    $bdqty = count($bookbinding) == 0 ? 1 : count($bookbinding);
                    $price += ($fq2 - 0) * ($bdqty - 1);
                    if (in_array('Hardcover Binding', bv)) {
                        $price += $fq3 - 0;
                        totalcount += $bdqty == 1 ? 1 : $bdqty + 1 - 1;
                    } else {
                        totalcount += (count($bookbinding) > 1) ? ($bdqty - 1) : 0;
                    }
                    
                    $bdqty = count($boardbook) == 0 ? 1 : count($boardbook);
                    totalcount += (count($boardbook) > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);
                    
                    $bdqty = count($catlogbinding) == 0 ? 1 : count($catlogbinding);
                    totalcount += (count($catlogbinding) > 0) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);
                    
                    $bdqty = count($calendartype) == 0 ? 1 : count($calendartype);
                    totalcount += (count($calendartype) > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);
                    
                    $bdqty = count($boxtype) == 0 ? 1 : count($boxtype);
                    totalcount += (count($boxtype) > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);
                    
                } else {
                    $price += $fq1 - 0;
                    $price += ($fq2 - 0) * (count($booktype) - 2);
                    
                    $bdqty = count($bookbinding) == 0 ? 1 : count($bookbinding);
                    $price += ($fq2 - 0) * ($bdqty - 1);
                    if (in_array('Hardcover Binding', bv)) {
                        $price += $fq3 - 0;
                        totalcount += $bdqty == 1 ? 1 : $bdqty + 1 - 1;
                    } else {
                        totalcount += (count($bookbinding) > 1) ? ($bdqty - 1) : 0;
                    }
                    
                    $bdqty = count($boardbook) == 0 ? 1 : count($boardbook);
                    totalcount += (count($boardbook) > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);
                    
                    $bdqty = count($catlogbinding) == 0 ? 1 : count($catlogbinding);
                    totalcount += (count($catlogbinding) > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);
                    
                    $bdqty = count($calendartype) == 0 ? 1 : count($calendartype);
                    totalcount += (count($calendartype) > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);
                    
                    $bdqty = count($boxtype) == 0 ? 1 : count($boxtype);
                    totalcount += (count($boxtype) > 1) ? ($bdqty - 1) : 0;
                    $price += ($fq2 - 0) * ($bdqty - 1);
                }
            }

            if (postcode.val() == "") {
                div.hide();
                $('body').find('.sample-shiping-cost-text').remove();
                if ($('.wpform-shipping-cost input').length) {
                    $('.wpform-shipping-cost input').val('');
                }
                return true;
            }
            
            if (doing) {
                return true;
            }
            
            if (country.val() && in_array(country.val(), $sample_countrylist)) {
                let _form = $(this).closest('form');
                _form.find('.wpforms-submit-container').busyLoad("show", {animation: "fade"});
                doing = true;
                
                $.post('/wp-json/qinprinting/v1/shippingcost', {
                    'weight': ($wei * totalcount).toFixed(2),
                    'zipcode': postcode.val(),
                    'country': country.val()
                }, function(d) {
                    $('body').find('.sample-shiping-cost-text').remove();
                    
                    if (d.message == 'ok' && d.items && d.items.length > 0) {
                        // 使用新的items数组渲染多渠道选项
                        let optionstxt = renderShippingOptions(d.items);
                        if ( optionstxt == '' ) {
                            div.hide();
                            $('body').find('.sample-shiping-cost-text').remove();
                            if ($('.wpform-shipping-cost input').length) {
                                $('.wpform-shipping-cost input').val('');
                            }
                        } else {
                            let shippingHtml = '<div class="sample-shiping-cost-text">';
                            shippingHtml += '<hr style="margin-bottom: 10px;background-color: #00afdd">';
                            shippingHtml += optionstxt;
                            shippingHtml += '<hr style="margin-bottom: 10px;background-color: #00afdd">';
                            shippingHtml += '</div>';
                            
                            div.before(shippingHtml);
                            
                            // 绑定选项点击事件
                            $('.shipping-option-item').on('click', function() {
                                $(this).find('input[type="radio"]').prop('checked', true);
                                updatePriceSummary();
                            });
                            
                            // 初始化价格显示
                            updatePriceSummary();
                            div.show();
                        }
                        
                    } else if (d.message != 'ok') {
                        // 显示错误信息
                        let errorHtml = '<div class="sample-shiping-cost-text">';
                        errorHtml += '<div class="no-shipping-available">' + d.message + '</div>';
                        errorHtml += '</div>';
                        div.before(errorHtml);
                        div.hide();
                        if ($('.wpform-shipping-cost input').length) {
                            $('.wpform-shipping-cost input').val('');
                        }
                    } else {
                        div.hide();
                        if ($('.wpform-shipping-cost input').length) {
                            $('.wpform-shipping-cost input').val('');
                        }
                    }
                    
                    _form.find('.wpforms-submit-container').busyLoad("hide", {animation: "fade", animationDuration: "slow"});
                    doing = false;
                });
                
                return true;
            } else {
                div.hide();
                $('body').find('.sample-shiping-cost-text').remove();
                if ($('.wpform-shipping-cost input').length) {
                    $('.wpform-shipping-cost input').val('');
                }
            }
        };
        
        // 仅限特定页面
        if (country.length) {
            $(document).on('change', country, function() {
                changeEve();
                console.log(country);
            });
            $('.wpforms-form input:checkbox').change(changeEve);
            $('input.wpforms-field-address-postal').change(changeEve);
        }

        // 动态绑定运费选项点击事件（使用事件委托）
        $(document).on('click', 'input[name="sampleshippingcost"]', function() {
            updatePriceSummary();
        });

        var lt = null;
        var is_login = '<?php echo is_user_logged_in() ? '1' : '';?>';
    
        if (is_login) {
            $('.account-login a').click(function(event) {
                event.stopPropagation();
                this.blur();
                $('.my-account-indicator-dropdown').toggle();
            });
            $('body').click(function(e) {
                $(e.target).attr('class') == 'far fa-user' ? $(e.target).attr('class') : $('.my-account-indicator-dropdown').hide();
            });
            setTimeout(() => $('.account-login a').attr('href', 'javascript:void(0)'), 586);
        } else {
            $('.account-login a').attr('href', '/my-account');
        }
    });
    
    </script>
    <?php    
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
                            
                            // 2) 新结构：多公司（新增）
                            if ( !empty($cv['kongyun_multi']) && is_array($cv['kongyun_multi']) ) {
                                foreach ( $cv['kongyun_multi'] as $vid => $vd ) {
                                    $vname   = isset($vd['vendor_name']) ? $vd['vendor_name'] : ('Air Vendor ' . $vid);
                                    $stime   = isset($vd['shippingtime']) ? $vd['shippingtime'] : '';
                                    $symbol  = isset($vd['symbol']) ? $vd['symbol'] : '';
                                    $currate = isset($vd['currate']) ? $vd['currate'] : '';
                                    $baoguan = isset($vd['baoguanfee']) ? $vd['baoguanfee'] : '';

                                    echo '<div style="margin:6px 0;padding:6px;border-top:1px dashed #ddd;">';
                                    echo '<strong>' . esc_html($vname) . '</strong>';
                                    if ($stime !== '') {
                                        echo ' <em style="color:#666;">(' . esc_html($stime) . ')</em>';
                                    }
                                    // 小字显示符号/汇率/报关费（有就显示）
                                    $metaBits = array();
                                    if ($symbol !== '')  $metaBits[] = '符号: ' . esc_html($symbol);
                                    if ($currate !== '') $metaBits[] = '汇率: ' . esc_html($currate);
                                    if ($baoguan !== '') $metaBits[] = '报关费: ' . esc_html($baoguan);
                                    if ($metaBits) {
                                        echo '<div style="color:#888;font-size:12px;margin:4px 0;">' . implode('，', $metaBits) . '</div>';
                                    }

                                    if ( !empty($vd['rules']) && is_array($vd['rules']) ) {
                                        foreach ($vd['rules'] as $rk => $rv) {
                                            // $rv 结构与 show_rule 兼容：[0]=起重,[1]=止重,[2]=公式
                                            if (!empty($rv[2])) {
                                                echo ucfirst($rk) . '. ' . show_rule($rv);
                                            }
                                        }
                                    }
                                    echo '</div>';
                                }
                            }

                            echo '</td>';

                            echo '<td>'; 
                            
                            foreach ( $cv['haiyun'] as $hk => $hv ) {
                                //$xx = $hk + 1;
                                echo empty($hv[2]) ? '' : ucfirst($hk) . '. ' . show_rule($hv);
                            }
                            

                            // 2) 新结构：多公司（新增）
                            if ( !empty($cv['haiyun_multi']) && is_array($cv['haiyun_multi']) ) {
                                foreach ( $cv['haiyun_multi'] as $vid => $vd ) {
                                    $vname   = isset($vd['vendor_name']) ? $vd['vendor_name'] : ('Sea Vendor ' . $vid);
                                    $stime   = isset($vd['shippingtime']) ? $vd['shippingtime'] : '';
                                    $symbol  = isset($vd['symbol']) ? $vd['symbol'] : '';
                                    $currate = isset($vd['currate']) ? $vd['currate'] : '';
                                    $baoguan = isset($vd['baoguanfee']) ? $vd['baoguanfee'] : '';

                                    echo '<div style="margin:6px 0;padding:6px;border-top:1px dashed #ddd;">';
                                    echo '<strong>' . esc_html($vname) . '</strong>';
                                    if ($stime !== '') {
                                        echo ' <em style="color:#666;">(' . esc_html($stime) . ')</em>';
                                    }
                                    // 小字显示符号/汇率/报关费（有就显示）
                                    $metaBits = array();
                                    if ($symbol !== '')  $metaBits[] = '符号: ' . esc_html($symbol);
                                    if ($currate !== '') $metaBits[] = '汇率: ' . esc_html($currate);
                                    if ($baoguan !== '') $metaBits[] = '报关费: ' . esc_html($baoguan);
                                    if ($metaBits) {
                                        echo '<div style="color:#888;font-size:12px;margin:4px 0;">' . implode('，', $metaBits) . '</div>';
                                    }

                                    if ( !empty($vd['rules']) && is_array($vd['rules']) ) {
                                        foreach ($vd['rules'] as $rk => $rv) {
                                            if (!empty($rv[2])) {
                                                echo ucfirst($rk) . '. ' . show_rule($rv);
                                            }
                                        }
                                    }
                                    echo '</div>';
                                }
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

		
    <!-- QIN MULTI-VENDOR (inside form) BEGIN -->
    <div class="postbox" style="margin-top:16px;">
      <h3 style="margin:0;padding:12px 16px;border-bottom:1px solid #eee;">海运 / 空运（多物流渠道可选，按重量区间与公式）</h3>
      <div style="padding:12px 16px;">
        <p style="color:#666;margin:0 0 10px;">为当前国家与邮编组新增多家渠道。每家渠道支持 1~8 条区间公式，变量：<code>重量</code>、<code>汇率</code>、<code>报关费</code>、<code>出厂价</code>。</p>
        <div id="multi-vendor-wrap-inform">
          <div style="display:flex;gap:24px;flex-wrap:wrap;">
            <div style="flex:1;min-width:420px;">
              <h3>海运（多渠道）</h3>
              <div id="multi-sea-box">
                <?php if( !empty($edit_shipping['haiyun_multi']) && is_array($edit_shipping['haiyun_multi']) ): ?>
                  <?php foreach($edit_shipping['haiyun_multi'] as $vid => $vd): ?>
                  <div class="vendor-card" data-kind="sea" style="border:1px solid #ddd;border-radius:6px;padding:12px;margin:12px 0;">
                    <div style="display:flex;gap:12px;align-items:center;justify-content:space-between;">
                      <div style="flex:1;display:flex;">
                        <div><label>物流渠道：</label><input type="text" value="<?php echo esc_attr($vd['vendor_name'] ?? '');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][haiyun_multi][<?php echo esc_attr($vid); ?>][vendor_name]" style="width:220px;"></div>
                        <div>&nbsp;&nbsp;<label>时效备注：</label><input type="text" value="<?php echo esc_attr($vd['shippingtime'] ?? '');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][haiyun_multi][<?php echo esc_attr($vid); ?>][shippingtime]" style="width:180px;"></div>
                        <div>&nbsp;&nbsp;<label>币符：</label><input type="text" value="<?php echo esc_attr($vd['symbol'] ?? ($symbol ?? '$'));?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][haiyun_multi][<?php echo esc_attr($vid); ?>][symbol]" style="width:60px;"></div>
                        <div>&nbsp;&nbsp;<label>汇率：</label><input type="text" value="<?php echo esc_attr($vd['currate'] ?? ($currate ?? ''));?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][haiyun_multi][<?php echo esc_attr($vid); ?>][currate]" style="width:90px;"></div>
                        <div>&nbsp;&nbsp;<label>报关费：</label><input type="text" value="<?php echo esc_attr($vd['baoguanfee'] ?? ($baoguanfee ?? ''));?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][haiyun_multi][<?php echo esc_attr($vid); ?>][baoguanfee]" style="width:90px;"></div>
                      </div>
                      <button class="button button-link-delete" type="button" onclick="this.closest('.vendor-card').remove()">删除</button>
                    </div>
                    <table class="widefat striped" style="margin-top:10px;">
                      <thead><tr><th style="width:90px;">规则</th><th style="width:120px;">起(kg)</th><th style="width:120px;">止(kg)</th><th>价格公式</th></tr></thead>
                      <tbody>
                        <?php for($i=1;$i<=8;$i++): $rk='rule'.$i; $rv=$vd['rules'][$rk] ?? array('','',''); ?>
                        <tr>
                          <td><?php echo $rk; ?></td>
                          <td><input type="text" value="<?php echo esc_attr($rv[0] ?? '');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][haiyun_multi][<?php echo esc_attr($vid); ?>][rules][<?php echo $rk; ?>][0]" style="width:100%;"></td>
                          <td><input type="text" value="<?php echo esc_attr($rv[1] ?? '');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][haiyun_multi][<?php echo esc_attr($vid); ?>][rules][<?php echo $rk; ?>][1]" style="width:100%;"></td>
                          <td><input type="text" value="<?php echo esc_attr($rv[2] ?? '');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][haiyun_multi][<?php echo esc_attr($vid); ?>][rules][<?php echo $rk; ?>][2]" style="width:100%;"></td>
                        </tr>
                        <?php endfor; ?>
                      </tbody>
                    </table>
                  </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <p><button type="button" class="button" onclick="addVendor('sea')">+ 新增海运渠道</button></p>
            </div>
            <div style="flex:1;min-width:420px;">
              <h3>空运（多渠道）</h3>
              <div id="multi-air-box">
                <?php if( !empty($edit_shipping['kongyun_multi']) && is_array($edit_shipping['kongyun_multi']) ): ?>
                  <?php foreach($edit_shipping['kongyun_multi'] as $vid => $vd): ?>
                  <div class="vendor-card" data-kind="air" style="border:1px solid #ddd;border-radius:6px;padding:12px;margin:12px 0;">
                    <div style="display:flex;gap:12px;align-items:center;justify-content:space-between;">
                      <div style="flex:1;display:flex;">
                        <div><label>物流渠道：</label><input type="text" value="<?php echo esc_attr($vd['vendor_name'] ?? '');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][kongyun_multi][<?php echo esc_attr($vid); ?>][vendor_name]" style="width:220px;"></div>
                        <div>&nbsp;&nbsp;<label>时效备注：</label><input type="text" value="<?php echo esc_attr($vd['shippingtime'] ?? '');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][kongyun_multi][<?php echo esc_attr($vid); ?>][shippingtime]" style="width:180px;"></div>
                        <div>&nbsp;&nbsp;<label>币符：</label><input type="text" value="<?php echo esc_attr($vd['symbol'] ?? ($symbol ?? '$'));?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][kongyun_multi][<?php echo esc_attr($vid); ?>][symbol]" style="width:60px;"></div>
                        <div>&nbsp;&nbsp;<label>汇率：</label><input type="text" value="<?php echo esc_attr($vd['currate'] ?? ($currate ?? ''));?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][kongyun_multi][<?php echo esc_attr($vid); ?>][currate]" style="width:90px;"></div>
                        <div>&nbsp;&nbsp;<label>报关费：</label><input type="text" value="<?php echo esc_attr($vd['baoguanfee'] ?? ($baoguanfee ?? ''));?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][kongyun_multi][<?php echo esc_attr($vid); ?>][baoguanfee]" style="width:90px;"></div>
                      </div>
                      <button class="button button-link-delete" type="button" onclick="this.closest('.vendor-card').remove()">删除</button>
                    </div>
                    <table class="widefat striped" style="margin-top:10px;">
                      <thead><tr><th style="width:90px;">规则</th><th style="width:120px;">起(kg)</th><th style="width:120px;">止(kg)</th><th>价格公式</th></tr></thead>
                      <tbody>
                        <?php for($i=1;$i<=8;$i++): $rk='rule'.$i; $rv=$vd['rules'][$rk] ?? array('','',''); ?>
                        <tr>
                          <td><?php echo $rk; ?></td>
                          <td><input type="text" value="<?php echo esc_attr($rv[0] ?? '');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][kongyun_multi][<?php echo esc_attr($vid); ?>][rules][<?php echo $rk; ?>][0]" style="width:100%;"></td>
                          <td><input type="text" value="<?php echo esc_attr($rv[1] ?? '');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][kongyun_multi][<?php echo esc_attr($vid); ?>][rules][<?php echo $rk; ?>][1]" style="width:100%;"></td>
                          <td><input type="text" value="<?php echo esc_attr($rv[2] ?? '');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][kongyun_multi][<?php echo esc_attr($vid); ?>][rules][<?php echo $rk; ?>][2]" style="width:100%;"></td>
                        </tr>
                        <?php endfor; ?>
                      </tbody>
                    </table>
                  </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <p><button type="button" class="button" onclick="addVendor('air')">+ 新增空运渠道</button></p>
            </div>
          </div>
        </div>
        <small style="color:#999;display:none;">保存后将写入 <code>sample_shipping[国家][haiyun_multi]/[kongyun_multi]</code> 结构。</small>
      </div>
    </div>

    <template id="tpl-vendor">
      <div class="vendor-card" data-kind="__KIND__" style="border:1px solid #ddd;border-radius:6px;padding:12px;margin:12px 0;">
        <div style="display:flex;gap:12px;align-items:center;justify-content:space-between;">
          <div style="flex:1;display:flex;">
            <div><label>物流渠道：</label><input type="text" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][__KEY__][__VID__][vendor_name]" style="width:220px;"></div>
            <div>&nbsp;&nbsp;<label>时效备注：</label><input type="text" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][__KEY__][__VID__][shippingtime]" style="width:180px;"></div>
            <div>&nbsp;&nbsp;<label>币符：</label><input type="text" value="<?php echo esc_attr($symbol ?? '$');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][__KEY__][__VID__][symbol]" style="width:60px;"></div>
            <div>&nbsp;&nbsp;<label>汇率：</label><input type="text" value="<?php echo esc_attr($currate ?? '');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][__KEY__][__VID__][currate]" style="width:90px;"></div>
            <div>&nbsp;&nbsp;<label>报关费：</label><input type="text" value="<?php echo esc_attr($baoguanfee ?? '');?>" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][__KEY__][__VID__][baoguanfee]" style="width:90px;"></div>
          </div>
          <button class="button button-link-delete" type="button" onclick="this.closest('.vendor-card').remove()">删除</button>
        </div>
        <table class="widefat striped" style="margin-top:10px;">
          <thead><tr><th style="width:90px;">规则</th><th style="width:120px;">起(kg)</th><th style="width:120px;">止(kg)</th><th>价格公式</th></tr></thead>
          <tbody>
            <?php for($i=1;$i<=8;$i++): $r='rule'.$i; ?>
            <tr>
              <td><?php echo $r; ?></td>
              <td><input type="text" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][__KEY__][__VID__][rules][<?php echo $r; ?>][0]" style="width:100%;"></td>
              <td><input type="text" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][__KEY__][__VID__][rules][<?php echo $r; ?>][1]" style="width:100%;"></td>
              <td><input type="text" name="sample_shipping[<?php echo esc_attr($_GET['country']); ?>][__KEY__][__VID__][rules][<?php echo $r; ?>][2]" style="width:100%;"></td>
            </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </template>

    <script>
    function uuid() { return 'v' + Math.random().toString(16).slice(2) + Date.now().toString(16); }
    function addVendor(kind) {
      var tpl = document.getElementById('tpl-vendor').innerHTML;
      var vid = uuid();
      var key = (kind === 'sea') ? 'haiyun_multi' : 'kongyun_multi';
      tpl = tpl.replaceAll('__KIND__', kind)
               .replaceAll('__KEY__', key)
               .replaceAll('__VID__', vid);
      var box = document.getElementById(kind === 'sea' ? 'multi-sea-box' : 'multi-air-box');
      box.insertAdjacentHTML('beforeend', tpl);
    }
    </script>
    <!-- QIN MULTI-VENDOR END -->
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