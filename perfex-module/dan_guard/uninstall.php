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
delete_option('dan_guard_app_base_url');
delete_option('dan_guard_model_custom_field');
delete_option('dan_guard_frp_accounts');
delete_option('dan_guard_db_version');
delete_option('dan_guard_sms_enabled');
delete_option('dan_guard_sms_endpoint');
delete_option('dan_guard_sms_account_id');
delete_option('dan_guard_sms_password');
delete_option('dan_guard_sms_sender');
delete_option('dan_guard_sms_ret_url');
delete_option('dan_guard_sms_notice_days');
delete_option('dan_guard_sms_message');
