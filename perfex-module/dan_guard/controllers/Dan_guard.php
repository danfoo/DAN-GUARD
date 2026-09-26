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
     * Liste des appareils.
     */
    public function index()
    {
        if (!staff_can('view', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }

        $data['devices'] = $this->dan_guard_model->get_devices();
        $data['title']   = _l('dan_guard');
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
    /* Réglages                                                           */
    /* ------------------------------------------------------------------ */

    public function settings()
    {
        if (!is_admin()) {
            access_denied('dan_guard');
        }

        if ($this->input->post()) {
            update_option('dan_guard_fcm_server_key', $this->input->post('dan_guard_fcm_server_key', true));
            update_option('dan_guard_default_grace_days', (int) $this->input->post('dan_guard_default_grace_days'));
            update_option('dan_guard_checkin_interval_hours', (int) $this->input->post('dan_guard_checkin_interval_hours'));
            update_option('dan_guard_lock_message', $this->input->post('dan_guard_lock_message', true));
            set_alert('success', _l('settings_updated'));
            redirect(admin_url('dan_guard/settings'));
        }

        $data['title'] = _l('dan_guard_settings');
        $this->load->view('settings', $data);
    }
}
