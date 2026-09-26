<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dan_guard extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('dan_guard_model');
    }

    /**
     * Tableau de bord des impayés (page d'accueil du module).
     */
    public function index()
    {
        if (!staff_can('view', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }

        $data['stats']    = $this->dan_guard_model->dashboard_stats();
        $data['overdue']  = $this->dan_guard_model->overdue_devices_list();
        $data['upcoming'] = $this->dan_guard_model->upcoming_installments(7);
        $data['title']    = _l('dan_guard_dashboard');
        $this->load->view('dashboard', $data);
    }

    /**
     * Liste des appareils.
     */
    public function devices()
    {
        if (!staff_can('view', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }

        $data['devices'] = $this->dan_guard_model->get_devices();
        $data['title']   = _l('dan_guard_devices');
        $this->load->view('devices', $data);
    }

    /**
     * Fiche d'un appareil (échéances, ordres, journal).
     */
    public function device($id)
    {
        if (!staff_can('view', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }

        $device = $this->dan_guard_model->get_device($id);
        if (!$device) {
            show_404();
        }

        $data['device']       = $device;
        $data['installments'] = $this->dan_guard_model->get_installments($id);
        $data['logs']         = $this->dan_guard_model->get_logs($id);
        $data['title']        = $device->device_name ?: _l('dan_guard_device');
        $this->load->view('device', $data);
    }

    /**
     * Création d'un appareil + génération du jeton d'enrôlement.
     */
    public function create()
    {
        if (!staff_can('create', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }

        if ($this->input->post()) {
            $result = $this->dan_guard_model->add_device($this->input->post());
            if ($result) {
                // Génération optionnelle de l'échéancier.
                $total = (float) $this->input->post('sale_price');
                $count = (int) $this->input->post('installments_count');
                $first = $this->input->post('first_due_date');
                if ($total > 0 && $count > 0 && $first) {
                    $this->dan_guard_model->generate_schedule($result['id'], $total, $count, $first);
                }
                set_alert('success', _l('dan_guard_device_created'));
                redirect(admin_url('dan_guard/device/' . $result['id']));
            }
            set_alert('danger', _l('problem_add'));
        }

        $data['clients'] = $this->clients_model->get();
        $data['title']   = _l('dan_guard_new_device');
        $this->load->view('device_form', $data);
    }

    /* ------------------------------------------------------------------ */
    /* Actions                                                            */
    /* ------------------------------------------------------------------ */

    public function lock($id)
    {
        if (!staff_can('edit', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        $this->dan_guard_model->lock_device($id, $this->input->post('message') ?: null);
        set_alert('success', _l('dan_guard_lock_queued'));
        redirect(admin_url('dan_guard/device/' . $id));
    }

    public function unlock($id)
    {
        if (!staff_can('edit', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        $this->dan_guard_model->unlock_device($id);
        set_alert('success', _l('dan_guard_unlock_queued'));
        redirect(admin_url('dan_guard/device/' . $id));
    }

    public function release($id)
    {
        if (!staff_can('edit', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        $this->dan_guard_model->release_device($id);
        set_alert('success', _l('dan_guard_release_queued'));
        redirect(admin_url('dan_guard/device/' . $id));
    }

    public function add_installment($device_id)
    {
        if (!staff_can('edit', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        if ($this->input->post()) {
            $this->dan_guard_model->add_installment([
                'device_id' => $device_id,
                'amount'    => $this->input->post('amount'),
                'due_date'  => $this->input->post('due_date'),
                'note'      => $this->input->post('note'),
            ]);
            set_alert('success', _l('added_successfully'));
        }
        redirect(admin_url('dan_guard/device/' . $device_id));
    }

    public function mark_paid($installment_id, $device_id)
    {
        if (!staff_can('edit', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        $this->dan_guard_model->set_installment_paid($installment_id, true);
        set_alert('success', _l('dan_guard_installment_paid'));
        redirect(admin_url('dan_guard/device/' . $device_id));
    }

    public function create_invoice($installment_id, $device_id)
    {
        if (!staff_can('edit', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        $invoice_id = $this->dan_guard_model->create_invoice_for_installment($installment_id);
        if ($invoice_id) {
            set_alert('success', _l('dan_guard_invoice_created'));
        } else {
            set_alert('danger', _l('dan_guard_invoice_failed'));
        }
        redirect(admin_url('dan_guard/device/' . $device_id));
    }

    public function generate_invoices($device_id)
    {
        if (!staff_can('edit', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        $count = $this->dan_guard_model->generate_invoices_for_device($device_id);
        set_alert('success', _l('dan_guard_invoices_generated', $count));
        redirect(admin_url('dan_guard/device/' . $device_id));
    }

    public function delete($id)
    {
        if (!staff_can('delete', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        $this->dan_guard_model->delete_device($id);
        set_alert('success', _l('deleted', _l('dan_guard_device')));
        redirect(admin_url('dan_guard'));
    }

    /* ------------------------------------------------------------------ */
    /* Provisioning QR                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Affiche le QR code de provisioning Device Owner d'un appareil.
     */
    public function provisioning($device_id)
    {
        if (!staff_can('view', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }

        $device = $this->dan_guard_model->get_device($device_id);
        if (!$device) {
            show_404();
        }
        if ($device->status !== 'pending' || empty($device->enrollment_token)) {
            set_alert('warning', _l('dan_guard_provisioning_only_pending'));
            redirect(admin_url('dan_guard/device/' . $device_id));
        }

        $payload = $this->dan_guard_model->build_provisioning_payload($device);

        $data['device']       = $device;
        $data['configured']   = $this->dan_guard_model->provisioning_is_configured();
        // JSON compact pour le QR, JSON lisible pour l'affichage.
        $data['payload_json'] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $data['payload_pretty'] = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        $data['title'] = _l('dan_guard_provisioning');
        $this->load->view('provisioning', $data);
    }

    /**
     * Télécharge le bundle de provisioning au format JSON.
     */
    public function provisioning_json($device_id)
    {
        if (!staff_can('view', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        $device = $this->dan_guard_model->get_device($device_id);
        if (!$device || $device->status !== 'pending' || empty($device->enrollment_token)) {
            show_404();
        }

        $payload = $this->dan_guard_model->build_provisioning_payload($device);
        $json    = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $this->output
            ->set_content_type('application/json')
            ->set_header('Content-Disposition: attachment; filename="provisioning-device-' . (int) $device_id . '.json"')
            ->set_output($json);
    }

    /**
     * Régénère le jeton d'enrôlement (invalide l'ancien QR).
     */
    public function regenerate_token($device_id)
    {
        if (!staff_can('edit', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        if ($this->dan_guard_model->regenerate_enrollment_token($device_id)) {
            set_alert('success', _l('dan_guard_token_regenerated'));
        } else {
            set_alert('danger', _l('dan_guard_provisioning_only_pending'));
        }
        redirect(admin_url('dan_guard/provisioning/' . $device_id));
    }

    /* ------------------------------------------------------------------ */
    /* Réglages                                                           */
    /* ------------------------------------------------------------------ */

    public function settings()
    {
        if (!is_admin()) {
            access_denied('dan_guard');
        }

        if ($this->input->post()) {
            $new_account = $this->input->post('dan_guard_fcm_service_account', false);
            if ($new_account !== get_option('dan_guard_fcm_service_account')) {
                // Le compte de service a changé : le jeton d'accès mis en cache n'est plus valable.
                update_option('dan_guard_fcm_token_cache', '');
            }
            update_option('dan_guard_fcm_service_account', $new_account);
            update_option('dan_guard_default_grace_days', (int) $this->input->post('dan_guard_default_grace_days'));
            update_option('dan_guard_checkin_interval_hours', (int) $this->input->post('dan_guard_checkin_interval_hours'));
            update_option('dan_guard_max_offline_days', (int) $this->input->post('dan_guard_max_offline_days'));
            update_option('dan_guard_lock_message', $this->input->post('dan_guard_lock_message', true));
            update_option('dan_guard_component_name', $this->input->post('dan_guard_component_name', true));
            update_option('dan_guard_apk_url', $this->input->post('dan_guard_apk_url', true));
            update_option('dan_guard_apk_checksum', $this->input->post('dan_guard_apk_checksum', true));
            update_option('dan_guard_sms_enabled', $this->input->post('dan_guard_sms_enabled') ? 1 : 0);
            update_option('dan_guard_sms_endpoint', $this->input->post('dan_guard_sms_endpoint', false));
            update_option('dan_guard_sms_account_id', $this->input->post('dan_guard_sms_account_id', false));
            update_option('dan_guard_sms_password', $this->input->post('dan_guard_sms_password', false));
            update_option('dan_guard_sms_sender', $this->input->post('dan_guard_sms_sender', true));
            update_option('dan_guard_sms_ret_url', $this->input->post('dan_guard_sms_ret_url', false));
            update_option('dan_guard_sms_notice_days', (int) $this->input->post('dan_guard_sms_notice_days'));
            update_option('dan_guard_sms_message', $this->input->post('dan_guard_sms_message', false));
            set_alert('success', _l('settings_updated'));
            redirect(admin_url('dan_guard/settings'));
        }

        $data['title'] = _l('dan_guard_settings');
        $this->load->view('settings', $data);
    }

    /**
     * Envoi d'un SMS de test vers un numéro, pour valider les réglages LAM.
     */
    public function test_sms()
    {
        if (!is_admin()) {
            access_denied('dan_guard');
        }
        $to = $this->input->post('to');
        if (empty($to)) {
            set_alert('warning', _l('dan_guard_sms_test_no_number'));
            redirect(admin_url('dan_guard/settings'));
        }
        $ok = $this->dan_guard_model->send_sms($to, 'DAN-GUARD: SMS de test.');
        set_alert($ok ? 'success' : 'danger', $ok ? _l('dan_guard_sms_test_ok') : _l('dan_guard_sms_test_failed'));
        redirect(admin_url('dan_guard/settings'));
    }
}
