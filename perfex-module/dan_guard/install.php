<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();

// Appareils enrôlés.
if (!$CI->db->table_exists(db_prefix() . 'dan_guard_devices')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "dan_guard_devices` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `client_id` INT(11) NOT NULL DEFAULT 0,
        `device_name` VARCHAR(191) NULL,
        `model` VARCHAR(191) NULL,
        `imei` VARCHAR(64) NULL,
        `serial` VARCHAR(191) NULL,
        `android_id` VARCHAR(191) NULL,
        `enrollment_token` VARCHAR(191) NULL,
        `api_token_hash` VARCHAR(191) NULL,
        `fcm_token` TEXT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `sale_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `grace_days` INT(11) NULL,
        `last_checkin` DATETIME NULL,
        `enrolled_at` DATETIME NULL,
        `created_at` DATETIME NULL,
        `updated_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `client_id` (`client_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Échéances de paiement.
if (!$CI->db->table_exists(db_prefix() . 'dan_guard_installments')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "dan_guard_installments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `device_id` INT(11) NOT NULL,
        `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        `due_date` DATE NOT NULL,
        `paid` TINYINT(1) NOT NULL DEFAULT 0,
        `paid_date` DATE NULL,
        `invoice_id` INT(11) NULL,
        `note` VARCHAR(191) NULL,
        `created_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `device_id` (`device_id`),
        KEY `paid` (`paid`),
        KEY `invoice_id` (`invoice_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// File d'ordres envoyés à l'appareil.
if (!$CI->db->table_exists(db_prefix() . 'dan_guard_commands')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "dan_guard_commands` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `device_id` INT(11) NOT NULL,
        `command` VARCHAR(30) NOT NULL,
        `payload` TEXT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `created_by` INT(11) NULL,
        `created_at` DATETIME NULL,
        `delivered_at` DATETIME NULL,
        `acked_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `device_id` (`device_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Journal d'événements.
if (!$CI->db->table_exists(db_prefix() . 'dan_guard_logs')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "dan_guard_logs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `device_id` INT(11) NULL,
        `event` VARCHAR(60) NOT NULL,
        `data` TEXT NULL,
        `created_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `device_id` (`device_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');
}

// Options par défaut du module.
// FCM HTTP v1 : JSON du compte de service Firebase + cache du jeton d'accès OAuth2.
add_option('dan_guard_fcm_service_account', '');
add_option('dan_guard_fcm_token_cache', '');
add_option('dan_guard_default_grace_days', '3');
add_option('dan_guard_checkin_interval_hours', '6');
add_option('dan_guard_max_offline_days', '7');
add_option('dan_guard_lock_message', 'Votre téléphone est verrouillé. Une échéance de paiement est en retard. Merci de régulariser votre situation pour le débloquer.');

// Provisioning Device Owner (QR code).
add_option('dan_guard_component_name', 'com.danguard.lock/.AdminReceiver');
add_option('dan_guard_apk_url', '');
add_option('dan_guard_apk_checksum', '');
