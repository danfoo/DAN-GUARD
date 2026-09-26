<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: DAN-GUARD
Description: Financement de téléphones avec verrouillage à distance (lock-to-own). Gère les appareils vendus à crédit, les échéances de paiement et pilote l'application Android Device Owner.
Version: 1.0.0
Requires at least: 2.3.*
Author: DAN-GUARD
*/

define('DAN_GUARD_MODULE_NAME', 'dan_guard');

$CI = &get_instance();

/**
 * Activation : crée les tables.
 */
register_activation_hook(DAN_GUARD_MODULE_NAME, 'dan_guard_activation_hook');
function dan_guard_activation_hook()
{
    require_once __DIR__ . '/install.php';
}

/**
 * Désactivation.
 */
register_deactivation_hook(DAN_GUARD_MODULE_NAME, 'dan_guard_deactivation_hook');
function dan_guard_deactivation_hook()
{
    // Les tables sont conservées à la désactivation. La suppression se fait dans uninstall.php.
}

register_uninstall_hook(DAN_GUARD_MODULE_NAME, 'dan_guard_uninstall_hook');
function dan_guard_uninstall_hook()
{
    require_once __DIR__ . '/uninstall.php';
}

register_language_files(DAN_GUARD_MODULE_NAME, [DAN_GUARD_MODULE_NAME]);

/**
 * Menu latéral admin.
 */
hooks()->add_action('admin_init', 'dan_guard_module_init_menu_items');
function dan_guard_module_init_menu_items()
{
    $CI = &get_instance();

    if (staff_can('view', 'dan_guard') || is_admin()) {
        $CI->app_menu->add_sidebar_menu_item('dan-guard', [
            'name'     => _l('dan_guard'),
            'href'     => admin_url('dan_guard'),
            'icon'     => 'fa fa-mobile',
            'position' => 30,
        ]);
    }
}

/**
 * Déclaration des permissions du module.
 */
hooks()->add_action('admin_init', 'dan_guard_permissions');
function dan_guard_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
        'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
        'create' => _l('permission_create'),
        'edit'   => _l('permission_edit'),
        'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('dan_guard', $capabilities, _l('dan_guard'));
}

/**
 * Cron : évalue les échéances en retard et met en file les verrouillages.
 * Perfex déclenche ce hook à chaque exécution de son cron.
 */
hooks()->add_action('after_cron_run', 'dan_guard_cron_evaluate');
function dan_guard_cron_evaluate()
{
    $CI = &get_instance();
    $CI->load->model('dan_guard/dan_guard_model');
    // Préavis SMS AVANT d'évaluer/verrouiller, pour prévenir dans la fenêtre de grâce.
    $CI->dan_guard_model->notify_upcoming_locks();
    $CI->dan_guard_model->evaluate_overdue_devices();
}

/**
 * Paiement enregistré sur une facture : si elle est liée à une échéance et
 * intégralement payée, l'échéance est marquée payée (et l'appareil déverrouillé).
 */
hooks()->add_action('after_payment_added', 'dan_guard_after_payment_added');
function dan_guard_after_payment_added($payment_id)
{
    $CI = &get_instance();
    $CI->load->model('dan_guard/dan_guard_model');
    $CI->dan_guard_model->handle_payment_added($payment_id);
}
