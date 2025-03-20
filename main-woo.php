<?php

if (!defined('ABSPATH')) {
    exit; // Exit jika diakses langsung
}

class WC_Shipping_RajaOngkir extends WC_Shipping_Method {
    /**
     * Constructor untuk metode pengiriman RajaOngkir.
     *
     * @access public
     * @return void
     */
    public function __construct($instance_id = 0) {
        $this->id                 = 'rajaongkir';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('RajaOngkirs', 'woocommerce');
        $this->method_description = __('Metode pengiriman kustom menggunakan RajaOngkir API.', 'woocommerce');
        $this->title              = $this->get_option('title', __('RajaOngkir', 'woocommerce')); // Tambahkan properti $title

        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->init_settings();
        $this->init_form_fields();

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Inisialisasi pengaturan formulir untuk admin.
     *
     * @access public
     * @return void
     */
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title'       => __('Judul Metode', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Judul yang ditampilkan kepada pengguna selama checkout dan di tabel metode pengiriman.', 'woocommerce'),
                'default'     => __('RajaOngkir', 'woocommerce'),
            ),
            'api_key' => array(
                'title'       => __('API Key RajaOngkir', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Masukkan API Key RajaOngkir Anda.', 'woocommerce'),
                'default'     => '',
            ),
            'jne' => array(
                'title'   => __('JNE', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Aktifkan JNE', 'woocommerce'),
                'default' => 'yes',
            ),
            'jnt' => array(
                'title'   => __('J&T Express', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Aktifkan J&T Express', 'woocommerce'),
                'default' => 'yes',
            ),
            'sicepat' => array(
                'title'   => __('SiCepat', 'woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Aktifkan SiCepat', 'woocommerce'),
                'default' => 'yes',
            ),
            // Tambahkan field lain sesuai kebutuhan.
        );
    }

        /**
     * Menghitung ongkos kirim berdasarkan item dalam keranjang.
     *
     * @access public
     * @param array $package Paket yang akan dikirim.
     * @return void
     */
    public function calculate_shipping($package = array()) {
        $origin = WC()->countries->get_base_postcode(); // Mengambil kode pos toko dari pengaturan WooCommerce
        $destination = WC()->customer->get_shipping_postcode();
    
        $weight = 0;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            $weight += $product->get_weight() * $quantity; // Menghitung total berat dari semua produk di keranjang
        }
    
        if ($weight <= 0) {
            $weight = 1000; // Jika berat tidak terdeteksi atau 0, gunakan 1000 gram sebagai default
        } else {
            $weight = wc_get_weight($weight, 'g'); // Konversi berat ke gram
        }
        
        $couriers = array();

        if ($this->get_option('jne') === 'yes') {
            $couriers[] = 'jne';
        }
        if ($this->get_option('jnt') === 'yes') {
            $couriers[] = 'jnt';
        }
        if ($this->get_option('sicepat') === 'yes') {
            $couriers[] = 'sicepat';
        }

        if (empty($couriers)) {
            return; // Tidak ada kurir yang dipilih
        }

        $courier_string = implode(':', $couriers);

        $api_key = $this->get_option('api_key');
        $api_url = 'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost';

        $body = array(
            'origin'      => $origin,
            'destination' => $destination,
            'weight'      => $weight,
            'courier'     => $courier_string,
        );

        $args = array(
            'headers' => array(
                'key'          => $api_key,
            ),
            'body'    => $body, // Kirim data sebagai array untuk x-www-form-urlencoded
        );

        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            error_log('Error RajaOngkir API: ' . $response->get_error_message());
            return;
        }

        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);

        if ($result && isset($result['data']) && is_array($result['data'])) {
            foreach ($result['data'] as $rate_data) {
                $rate = array(
                    'id'       => $this->id . ':' . $rate_data['code'] . ':' . $rate_data['service'],
                    'label'    => $rate_data['name'] . ' - ' . $rate_data['service'] . ' (' . $rate_data['description'] . ')',
                    'cost'     => $rate_data['cost'],
                    'calc_tax' => 'per_item',
                );
                $this->add_rate($rate);
            }
        } else {
            error_log('Invalid RajaOngkir API response: ' . $response_body);
        }
    }
}

add_filter('woocommerce_shipping_methods', 'add_rajaongkir_shipping_method');
function add_rajaongkir_shipping_method($methods) {
    $methods['rajaongkir'] = 'WC_Shipping_RajaOngkir';
    return $methods;
}
