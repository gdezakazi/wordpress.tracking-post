<?php

class GdeZakazy_Settings
{

    protected $defaults = array(
        'token' => '',
        'fields' => ['track', 'email', 'phone'],
        'status_tracking' => ['status' => null, 'notify' => false, 'notify_text' => ''],
        'status_error' => ['status' => null, 'notify' => false, 'notify_text' => ''],
        'status_department' => ['status' => null, 'notify' => false, 'notify_text' => ''],
        'status_problem' => ['status' => null, 'notify' => false, 'notify_text' => ''],
        'status_success' => ['status' => null, 'notify' => false, 'notify_text' => ''],
        'status_problem_success' => ['status' => null, 'notify' => false, 'notify_text' => '']
    );

    public function __construct()
    {
        add_action('admin_menu', array($this, 'addMenu'));
        add_action('admin_init', array($this, 'addOptions'));
        add_action('admin_print_styles', array($this, 'addStyles'));
        add_action('admin_print_scripts', array($this, 'addScripts'));
    }

    public function addMenu()
    {
        add_options_page(
            'Настройка модуля ГДЕЗАКАЗЫ.РФ',
            'ГДЕЗАКАЗЫ.РФ',
            'manage_options',
            'gdezakazy-settings',
            array($this, 'showPage')
        );
    }

    public function addStyles()
    {
        wp_enqueue_style('gdezakazy_settings_styles', plugins_url(basename(dirname(__FILE__))) . '/css/settings.css');
    }

    public function addScripts()
    {
        wp_enqueue_script('gdezakazy_settings_script', plugins_url(basename(dirname(__FILE__))) . '/js/settings.js');
    }

    protected $options;
    protected $errors = array();

    public function showPage()
    {
        $this->options = get_option('gdezakazy_options');
        if (empty($this->options)) {
            $this->options = $this->defaults;
        }
        ?>
        <div class="wrap">
            <h2>Настройки модуля ГДЕЗАКАЗЫ.РФ</h2>

            <form method="post" action="options.php">
                <div class="gdezakazy-form">
                    <?php
                    settings_fields('gdezakazy_options_group');
                    do_settings_sections('gdezakazy-settings');
                    submit_button();
                    ?>
                </div>
                <div class="gdezakazy-memo">
                    1) Данный модуль уведомляет ваших клиентов о прохождении посылки на каждом ее этапе маршрута.
                    Уменьшает количество возвратов за счет своевременных уведомлений.
                    <br />
                    <br />
                    2) Для получения ключа к API необходимо зарегистрироваться на сайте ГДЕЗАКАЗЫ.РФ и
                    сгенерировать ключ в разделе Настройки -> Настройки API.
                    <br />
                    <br />
                    3) Важно! Не забудьте также настроить имя магазина, настройки email,
                    sms для администратора магазина и ваших клиентов.
                    <br />
                    <br />
                    4) Для бесплатного тарифа доступно 20 трекингов в месяц.
                </div>
                <p class="gdezakazy-version">Текущая версия <?php echo GdeZakazy::VERSION ?></p>
            </form>
        </div>
        <?php
    }

    public function addOptions()
    {
        register_setting(
            'gdezakazy_options_group',
            'gdezakazy_options',
            array($this, 'sanitize')
        );

        add_settings_section(
            'gdezakazy-settings_section',
            '',
            array($this, 'settingsSectionHeader'),
            'gdezakazy-settings'
        );

        add_settings_field(
            'token',
            'API токен',
            array($this, 'fieldToken'),
            'gdezakazy-settings',
            'gdezakazy-settings_section'
        );

        add_settings_field(
            'fields',
            'Поля для передачи',
            array($this, 'fieldFields'),
            'gdezakazy-settings',
            'gdezakazy-settings_section'
        );

        add_settings_field(
            'status_tracking',
            'Если заказу присвоен трекинг-номер, то переносить в статус',
            array($this, 'fieldStatusTracking'),
            'gdezakazy-settings',
            'gdezakazy-settings_section'
        );

        add_settings_field(
            'status_error',
            'Переносить в статус при ошибке',
            array($this, 'fieldStatusError'),
            'gdezakazy-settings',
            'gdezakazy-settings_section'
        );

        add_settings_field(
            'status_department',
            'Если посылка "В отделении" переносить в статус',
            array($this, 'fieldStatusDepartment'),
            'gdezakazy-settings',
            'gdezakazy-settings_section'
        );

        add_settings_field(
            'status_problem',
            'Если посылка в "Проблемные" переносить в статус',
            array($this, 'fieldStatusProblem'),
            'gdezakazy-settings',
            'gdezakazy-settings_section'
        );

        add_settings_field(
            'status_success',
            'Если посылка в "Доставлены" переносить в статус',
            array($this, 'fieldStatusSuccess'),
            'gdezakazy-settings',
            'gdezakazy-settings_section'
        );

        add_settings_field(
            'status_problem_success',
            'Если посылка хоть раз попадала в "Проблемные", а сейчас в "Доставлены" переносить в статус',
            array($this, 'fieldStatusProblemSuccess'),
            'gdezakazy-settings',
            'gdezakazy-settings_section'
        );
    }

    public function sanitize($options)
    {
        $newOptions = $options;
        $newOptions['token'] = sanitize_text_field(trim($newOptions['token']));
        if (empty($newOptions['fields'])) {
            $newOptions['fields'] = array();
        }


        $this->errors = array();
        if (strlen($newOptions['token']) < 10) {
            $this->errors['token'] = 'Неправильный формат API токена';
        } elseif (!GdeZakazy::instance()->getApi()->checkApiToken($newOptions['token'])) {
            $this->errors['token'] = 'Не удалось соединиться с сервером';
        }
        if (!is_array($newOptions['fields']) || count($newOptions['fields']) == 0) {
            $this->errors['fields'] = 'Должны быть выделены для передачи трекинг и телефон или e-mail';
        } elseif (!in_array('tracking', $newOptions['fields'])) {
            $this->errors['fields'] = 'Трекинг обязателен для передачи';
        } elseif (count(array_intersect(['email', 'phone'], $newOptions['fields'])) == 0) {
            $this->errors['fields'] = 'Должен быть выделен телефон или e-mail';
        }

        if (count($this->errors) > 0) {
            add_settings_error('gdezakazy_options', 'settings_updated', implode("<br />", $this->errors), 'error');
            return get_option('gdezakazy_options');
        }
        add_settings_error('gdezakazy_options', 'settings_updated', 'Данные успешно обновлены', 'updated');
        return $newOptions;
    }

    public function settingsSectionHeader()
    {
    }

    public function fieldToken()
    {
        printf(
            '<input type="text" id="token" name="gdezakazy_options[token]" value="%s">',
            isset($this->options['token']) ? htmlentities($this->options['token']) : ''
        );
    }

    public function fieldFields()
    {
        $fields = array(
            'tracking' => 'Трекинг',
            'phone' => 'Телефон',
            'email' => 'E-mail',
            'name' => 'Имя',
            'order_number' => 'Номер заказа',
            'order_amount' => 'Сумма заказа',
        );

        echo '<div class="gdezakazy-fields">';
        foreach ($fields as $k => $v) {
            printf(
                '<label><input type="checkbox" name="gdezakazy_options[fields][]" value="%s" %s />%s</label>',
                $k,
                (is_array($this->options['fields']) && in_array($k, $this->options['fields']) ? 'checked' : ''),
                $v
            );
        }
        echo '</div>';
    }

    protected function fieldStatus($name)
    {
        echo '<div class="gdezakazy-status">';
        printf('<select name="gdezakazy_options[%s][status]">', $name);
        printf(
            '<option value="" %s>[Выкл]</option>',
            (isset($this->options[$name]['status']) && !$this->options[$name]['status'] ? 'selected' : '')
        );
        foreach (wc_get_order_statuses() as $k => $v) {
            printf(
                '<option value="%s" %s>%s</option>',
                $k,
                (isset($this->options[$name]['status']) && $k == $this->options[$name]['status'] ? 'selected' : ''),
                $v
            );
        }
        echo '</select>';
        printf(
            '<label><input type="checkbox" name="gdezakazy_options[%s][notify]" %s />Уведомить покупателя</label>',
            $name,
            (!empty($this->options[$name]['notify']) ? 'checked' : '')
        );
        echo '<div class="text">';
        printf(
            '<input type="text" placeholder="Заголовок письма" name="gdezakazy_options[%s][notify_subject]" value="%s" />',
            $name,
            (!empty($this->options[$name]['notify_subject']) ? htmlentities($this->options[$name]['notify_subject']) : '')
        );
        printf(
            '<textarea name="gdezakazy_options[%s][notify_text]">%s</textarea>',
            $name,
            (!empty($this->options[$name]['notify_text']) ? htmlentities($this->options[$name]['notify_text']) : '')
        );
        echo '<p class="small">Используйте переменную <code>[track]</code></p>';
        echo '</div>';
        echo '</div>';
    }

    public function fieldStatusTracking()
    {
        $this->fieldStatus('status_tracking');
    }
    public function fieldStatusProblemSuccess()
    {
        $this->fieldStatus('status_problem_success');
    }
    public function fieldStatusProblem()
    {
        $this->fieldStatus('status_problem');
    }
    public function fieldStatusDepartment()
    {
        $this->fieldStatus('status_department');
    }
    public function fieldStatusSuccess()
    {
        $this->fieldStatus('status_success');
    }
    public function fieldStatusError()
    {
        $this->fieldStatus('status_error');
    }

}

if (is_admin()) {
    $gdeZakazy_settings = new GdeZakazy_Settings();
}
