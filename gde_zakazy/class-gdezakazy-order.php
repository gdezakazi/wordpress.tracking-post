<?php

class GdeZakazy_Order
{

    protected $options;

    public function __construct()
    {
        $this->options = get_option('gdezakazy_options');
        add_action('add_meta_boxes', array($this, 'addTrackingMetaBoxes'), 60);
        add_action('admin_print_styles', array($this, 'addStyles'));
        add_action('admin_print_scripts', array($this, 'addScripts'));
        add_action('wp_ajax_gdezakazy_add', array($this, 'ajaxAddTrack'));
        add_action('wp_ajax_gdezakazy_archive', array($this, 'ajaxArchiveTrack'));
        if (defined('DOING_CRON') && DOING_CRON) {
            add_action('gdezakazy_hourly_event', array($this, 'cron'));
        }
    }

    public function addStyles()
    {
        wp_enqueue_style('gdezakazy_order_styles', plugins_url(basename(dirname(__FILE__))) . '/css/order.css');
    }

    public function addScripts()
    {
        wp_enqueue_script('gdezakazy_order_script', plugins_url(basename(dirname(__FILE__))) . '/js/order.js');
    }

    public function addTrackingMetaBoxes()
    {
        if (empty($this->options['token'])) {
            return;
        }
        foreach (wc_get_order_types( 'order-meta-boxes' ) as $type) {
            add_meta_box(
                'gdezakazy-order-tracking',
                'ГДЕЗАКАЗЫ.РФ',
                array($this, 'showTrackingInfo'),
                $type,
                'normal',
                'low'
            );
        }
    }

    public function cron()
    {
        global $wpdb;
        $time = time() - 15 * 60;
        $results = $wpdb->get_results("SELECT p1.post_id FROM {$wpdb->prefix}postmeta AS p1 LEFT JOIN {$wpdb->prefix}postmeta AS p2 ON p1.post_id = p2.post_id AND p2.meta_key = '_gdezakazy_updated' WHERE p1.meta_key = '_gdezakazy_is_active' AND p1.meta_value = '1' AND p2.meta_value + 1 < $time", ARRAY_A);
        $time = time();
        foreach ($results as $item) {
            $order = wc_get_order($item['post_id']);
            $this->updateWcOrder($order);
            if (time() - $time > 5) {
                break;
            }
        }
    }

    public function showTrackingInfo($post)
    {
        global $theorder;
        if (!is_object($theorder)) {
            $theorder = wc_get_order($post->ID);
        }
        $order = $theorder;
        $error = null;
        try {
            $this->updateWcOrder($order);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        echo '<div id="gdezakazy_order_wrap">';
        $this->output($order, $error);
        echo '</div>';
    }

    protected function updateWcOrder(WC_Order $order)
    {
        if (!$order->get_meta('_gdezakazy_track') || $order->get_meta('_gdezakazy_is_active') == 0) {
            return;
        }
        $updated = intval($order->get_meta('_gdezakazy_updated'));
        if (time() - $updated  < 15 * 60) {
            return;
        }
        $requestData = GdeZakazy::instance()->getApi()->request($this->options['token'], 'GET', 'track/'.$order->get_meta('_gdezakazy_track'));
        if ($requestData['code'] != 200) {
            $message = isset($requestData['data']['message']) ? $requestData['data']['message'] : 'Request error';
            throw new Exception($message);
        }
        $oldStatus = $order->get_meta('_gdezakazy_status');
        $oldError = $order->get_meta('_gdezakazy_error');
        $isActive = 1;
        $isProblem = ($requestData['data']['was_problem'] ? 1 : 0);
        $newStatus = $requestData['data']['status'];
        if ($newStatus == 'archive' || $newStatus == 'delivered') {
            $isActive = 0;
        }
        $updatedAt = strtotime($requestData['data']['updated_at']);
        $error = $requestData['data']['had_error'] ? 'ERROR' : '';
        $now = time();

        $order->update_meta_data('_gdezakazy_status', $newStatus);
        $order->update_meta_data('_gdezakazy_updated', $now);
        $order->update_meta_data('_gdezakazy_is_active', $isActive);
        $order->update_meta_data('_gdezakazy_is_problem', $isProblem);
        $order->update_meta_data('_gdezakazy_updated_on_server', $updatedAt);
        $order->update_meta_data('_gdezakazy_error', $error);
        $order->save_meta_data();

        if ($newStatus == 'delivered' && $oldStatus != 'delivered' && $isProblem) {
            $this->updateOrderStatus($order, 'problem_success');
        }
        if ($newStatus == 'delivered' && $oldStatus != 'delivered' && !$isProblem) {
            $this->updateOrderStatus($order, 'success');
        }
        if ($newStatus == 'department' && $oldStatus != 'department') {
            $this->updateOrderStatus($order, 'department');
        }
        if ($newStatus == 'problem' && $oldStatus != 'problem') {
            $this->updateOrderStatus($order, 'problem');
        }
        if ($error && !$oldError) {
            $this->updateOrderStatus($order, 'error');
        }
    }

    protected function updateOrderStatus(WC_Order $order, $status)
    {
        $statusOptions = $this->options['status_'.$status];
        if (!is_array($statusOptions) || empty($statusOptions['status'])) {
            return;
        }
        $order->update_status($statusOptions['status']);
        $orderData = $order->get_data();
        if (!empty($statusOptions['notify']) && !empty($statusOptions['notify_text']) && !empty($orderData['billing']['email'])) {
            $subject = strtr($statusOptions['notify_subject'], array(
                '[track]' => $order->get_meta('_gdezakazy_track'),
            ));
            $message = strtr($statusOptions['notify_text'], array(
                '[track]' => $order->get_meta('_gdezakazy_track'),
            ));

            add_filter('wp_mail_from', array($this, 'wpMailFromFilter'), 10, 1);
            add_filter('wp_mail_from_name', array($this, 'wpMailFromNameFilter'), 10, 1);
            wp_mail($orderData['billing']['email'], $subject, $message, array('content-type' => 'text/html'));
            remove_filter('wp_mail_from', array($this, 'wpMailFromFilter'), 10, 1);
            remove_filter('wp_mail_from_name', array($this, 'wpMailFromNameFilter'), 10, 1);
        }
    }

    public function wpMailFromFilter($from)
    {
        return get_option('admin_email', $from);
    }

    public function wpMailFromNameFilter($fromName)
    {
        return get_option('blogname', $fromName);
    }

    public function ajaxArchiveTrack()
    {
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        if (empty($order)) {
            wp_die('Order is not found');
        }
        $error = null;
        try {
            $requestData = GdeZakazy::instance()->getApi()->request($this->options['token'], 'POST', 'track/' . $order->get_meta('_gdezakazy_track') . '/stop');
            if ($requestData['code'] != 200 || !isset($requestData['data']['success']) || $requestData['data']['success'] != true) {
                $message = isset($requestData['data']['message']) ? $requestData['data']['message'] : 'Request error';
                throw new Exception($message);
            }
            $order->update_meta_data('_gdezakazy_status', 'archive');
            $order->update_meta_data('_gdezakazy_updated', time());
            $order->update_meta_data('_gdezakazy_is_active', 0);
            $order->save_meta_data();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
        $this->output($order, $error);
        wp_die();
    }

    public function ajaxAddTrack()
    {
        $track = sanitize_text_field($_POST['track']);
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        if (empty($order)) {
            wp_die('Order is not found');
        }
        $orderData = $order->get_data();
        $error = null;
        try {
            if (!preg_match('/^[\w\-_]{5,40}$/', $track)) {
                throw new Exception('Неправильный формат трека');
            }
            if ($order->get_meta('_gdezakazy_track') && $order->get_meta('_gdezakazy_status') != 'archive') {
                throw new Exception('Трек уже добавлен');
            }
            $phone = preg_replace('/\D+/', '', $orderData['billing']['phone']);
            if (strlen($phone) > 10) {
                $phone = preg_replace('/^[87]/', '', $phone);
            }
            $requestPayload = array(
                'track_code' => $track,
                'phone' => (in_array('phone', $this->options['fields']) ? '8'.$phone : ''),
                'email' => (in_array('email', $this->options['fields']) ? $orderData['billing']['email'] : ''),
                'name' => (in_array('name', $this->options['fields']) ? $orderData['billing']['first_name'] : ''),
                'order_number' => (in_array('order_number', $this->options['fields']) ? $orderData['id'] : ''),
                'order_amount' => (in_array('order_amount', $this->options['fields']) ? $orderData['total'] : ''),
            );
            $requestData = GdeZakazy::instance()->getApi()->request($this->options['token'], 'POST', 'track', $requestPayload);
            if ($requestData['code'] != 200
                && isset($requestData['data']['message'])
                && $requestData['data']['message'] == 'Invalid input data'
                && isset($requestData['data']['errors'])
                && is_array($requestData['data']['errors'])
                && count($requestData['data']['errors']) == 1
                && $requestData['data']['errors'][0]['field'] == 'phone') {
                unset($requestPayload['phone']);
                $requestData = GdeZakazy::instance()->getApi()->request($this->options['token'], 'POST', 'track', $requestPayload);
                $error = 'Ошибка в телефоне. Трек добавлен без телефона';
            }
            if ($requestData['code'] != 200) {
                $message = isset($requestData['data']['message']) ? $requestData['data']['message'] : 'Request error';
                if (isset($requestData['data']['errors']) && is_array($requestData['data']['errors'])) {
                    foreach ($requestData['data']['errors'] as $v) {
                        $message .= "\n- {$v['field']}: {$v['message']}";
                    }
                }
                throw new Exception($message);
            }
            $order->update_meta_data('_gdezakazy_track', $track);
            $order->update_meta_data('_gdezakazy_status', 'new');
            $order->update_meta_data('_gdezakazy_updated', 0);
            $order->update_meta_data('_gdezakazy_is_active', 1);
            $order->update_meta_data('_gdezakazy_is_problem', 0);
            $order->update_meta_data('_gdezakazy_updated_on_server', 0);
            $order->update_meta_data('_gdezakazy_error', '');
            $order->save_meta_data();
            $this->updateWcOrder($order);
            $this->updateOrderStatus($order, 'tracking');
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
        $this->output($order, $error);
        wp_die();
    }

    protected function output(WC_Order $order, $error = false)
    {
        if ($error) {
            echo '<p class="gdezakazy-error">'.nl2br(htmlentities($error)).'</p>';
        }
        echo <<<EOT
<p><b>Отслеживание посылок, уведомления клиентам, смена статуса.</b></p>
<p>Для настройки модуля перейдите в раздел: Модули/Расширения - ГДЕЗАКАЗЫ.РФ</p>
<p>Полную информацию отслеживания трекинга вы можете посмотреть в личном кабинете на сайте <a href="https://гдезаказы.рф/" target="_blank">ГДЕЗАКАЗЫ.РФ</a></p>
<p>&nbsp;</p>
EOT;
        $apiStatus = GdeZakazy::instance()->getApi()->getStatus($this->options['token']);
        printf(
            '<p>Подключение по API: %s</p>',
            ($apiStatus['status'] ? 'Подключено' : 'Нет подключения. Перейдите в личный кабинет на сайт <a href="https://гдезаказы.рф/" target="_blank">ГДЕЗАКАЗЫ.РФ</a> для генерации ключа API, который необходимо ввести в настройках модуля Модули/Расширения - ГДЕЗАКАЗЫ.РФ')
        );
        if ($apiStatus['status']) {
            if ($apiStatus['expired']) {
                printf(
                    '<p>Тариф: подписка действует до %s</p>',
                    $apiStatus['expired']
                );
            } else {
                printf(
                    '<p>Ограничения использования Бесплатный тариф, осталось трекингов: %d</p><p>Выберите подписку для снятия ограничения по количеству отслеживаемых трекингов в личном кабинете на сайте <a href="https://xn--80aahefmcw9m.xn--p1ai/api/settings" target="_blank">ГДЕЗАКАЗЫ.РФ</a></p>',
                    $apiStatus['limit']
                );
            }
        }
        echo '<p>&nbsp;</p>';
        $track = $order->get_meta('_gdezakazy_track');
        $status = $order->get_meta('_gdezakazy_status');
        $updated = $order->get_meta('_gdezakazy_updated_on_server');

        if ($track && $status != 'archive') {
            echo '<div style="background: #ddd;padding: 10px;"><p>Трек отслеживается: <b>'.htmlentities($track).'</b>';
            if ($status != 'archive') {
                echo '&nbsp;&nbsp;&nbsp;<button data-id="'.$order->get_id().'" onclick="return false;" class="button button-primary" id="gdezakazy_archive_btn">Перенести в архив</button>';
            }
            echo '</p>';
            if ($updated) {
                printf('<p>Последнее обновление: <b>%s</b></p>', date('d.m.Y H:i', $updated));
            } else {
                if ($order->get_meta('_gdezakazy_updated')) {
                    echo '<p>Информация о статусе еще не обновлялась</p>';
                } else {
                    echo '<p>Информация с сервера еще не поступала</p>';
                }
            }
            $statusTranslations = array(
                'Новый' => 'new',
                'Незарегистрированный трекинг' => 'notregistered',
                'В пути' => 'ontheway',
                'Проблема' => 'problem',
                'В отделении' => 'department',
                'Доставлено' => 'delivered',
                'Архив' => 'archive',
            );
            printf('<p>Текущий статус <b>%s</b></p>', array_search($status, $statusTranslations));
            echo '</div>';
        } elseif ($apiStatus['canAdd']) {
            echo '
<div style="background: #ddd;padding: 10px;">
    <p>Добавить трекинг к этому заказу:</p>
    <div id="gdezakazy_add_form">
        <input type="hidden" name="order_id" value="'.$order->get_id().'">
        <label>Трекинг номер Почта России</label>
        &nbsp;&nbsp;&nbsp;
        <input type="text" name="track" value="">
        <button class="button button-primary" onclick="return false;">Добавить</button>
    </div>
</div>';
        }
    }

}

$gdeZakazy_order = new GdeZakazy_Order();