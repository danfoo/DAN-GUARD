<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

$tables = [
    'dan_guard_logs',
    'dan_guard_commands',
    'dan_guard_installments',
    'dan_guard_devices',
];

foreach ($tables as $table) {
    if ($CI->db->table_exists(db_prefix() . $table)) {
        $CI->db->query('DROP TABLE ' . db_prefix() . $table);
    }
}

delete_option('dan_guard_fcm_service_account');
delete_option('dan_guard_fcm_token_cache');
delete_option('dan_guard_default_grace_days');
delete_option('dan_guard_checkin_interval_hours');
delete_option('dan_guard_max_offline_days');
delete_option('dan_guard_lock_message');
delete_option('dan_guard_component_name');
delete_option('dan_guard_apk_url');
delete_option('dan_guard_apk_checksum');
