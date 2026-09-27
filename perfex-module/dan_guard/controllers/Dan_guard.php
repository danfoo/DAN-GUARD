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
     * Refuse tout accès qui n'est pas un POST (protégé CSRF par Perfex).
     * Empêche le déclenchement d'actions destructrices par simple lien GET.
     */
    private function require_post()
    {
        if (strtolower((string) $this->input->server('REQUEST_METHOD')) !== 'post') {
            show_404();
        }
    }

    /**
     * Décode une chaîne base64url (sans padding).
     */
    private function b64url_decode($s)
    {
        $s   = strtr((string) $s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }

        return base64_decode($s, true);
    }

    /**
     * Lit un champ de formulaire en privilégiant sa variante encodée `<nom>__b64`
     * (envoyée par le JS des réglages pour contourner les WAF qui bloquent les URLs
     * dans les arguments POST — ex. règle Atomicorp 340162). Repli sur le champ brut
     * si le JS n'a pas tourné.
     *
     * @param string $name Nom du champ.
     * @param bool   $xss  Applique le nettoyage XSS de CodeIgniter à la valeur.
     */
    private function post_field($name, $xss = false)
    {
        $b64 = $this->input->post($name . '__b64', false);
        if ($b64 !== null) {
            $decoded = $this->b64url_decode($b64);
            if ($decoded === false) {
                $decoded = '';
            }

            return $xss ? $this->security->xss_clean($decoded) : $decoded;
        }

        return $this->input->post($name, $xss);
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
            // Nom, modèle et prix viennent de l'article Perfex relu en base :
            // on ne fait pas confiance aux valeurs affichées côté navigateur.
            $item = $this->dan_guard_model->get_item_for_device((int) $this->input->post('item_id'));
            $imei = trim((string) $this->input->post('imei', true));

            if (!$item) {
                set_alert('danger', _l('dan_guard_item_required'));
                redirect(admin_url('dan_guard/create'));
            }
            if ($imei === '') {
                set_alert('danger', _l('dan_guard_imei_required'));
                redirect(admin_url('dan_guard/create'));
            }

            $result = $this->dan_guard_model->add_device([
                'client_id'   => $this->input->post('client_id'),
                'item_id'     => $item['item_id'],
                'device_name' => $item['name'],
                'model'       => $item['model'],
                'sale_price'  => $item['price'],
                'imei'        => $imei,
                'grace_days'  => $this->input->post('grace_days'),
            ]);
            if ($result) {
                // Génération optionnelle de l'échéancier, sur le prix de l'article.
                $count = (int) $this->input->post('installments_count');
                $first = $this->input->post('first_due_date');
                if ($item['price'] > 0 && $count > 0 && $first) {
                    $this->dan_guard_model->generate_schedule($result['id'], $item['price'], $count, $first);
                }
                set_alert('success', _l('dan_guard_device_created'));
                redirect(admin_url('dan_guard/device/' . $result['id']));
            }
            set_alert('danger', _l('problem_add'));
        }

        $items = $this->dan_guard_model->get_items_for_devices();
        foreach ($items as &$it) {
            $it['price_formatted'] = app_format_money($it['rate'], '');
        }
        unset($it);

        $data['clients'] = $this->clients_model->get();
        $data['items']   = $items;
        $data['title']   = _l('dan_guard_new_device');
        $this->load->view('device_form', $data);
    }

    /* ------------------------------------------------------------------ */
    /* Actions                                                            */
    /* ------------------------------------------------------------------ */

    public function lock($id)
    {
        $this->require_post();
        if (!staff_can('edit', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        // Ne jamais verrouiller un appareil non encore enrôlé : cela casserait le
        // provisioning (le jeton d'enrôlement ne pourrait plus être utilisé).
        $device = $this->dan_guard_model->get_device($id);
        if (!$device || $device->status !== 'active') {
            set_alert('warning', _l('dan_guard_lock_only_active'));
            redirect(admin_url('dan_guard/device/' . $id));
        }
        $this->dan_guard_model->lock_device($id, $this->input->post('message') ?: null);
        set_alert('success', _l('dan_guard_lock_queued'));
        redirect(admin_url('dan_guard/device/' . $id));
    }

    public function unlock($id)
    {
        $this->require_post();
        if (!staff_can('edit', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        $this->dan_guard_model->unlock_device($id);
        set_alert('success', _l('dan_guard_unlock_queued'));
        redirect(admin_url('dan_guard/device/' . $id));
    }

    public function release($id)
    {
        $this->require_post();
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
        $this->require_post();
        if (!staff_can('edit', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        $this->dan_guard_model->set_installment_paid($installment_id, true);
        set_alert('success', _l('dan_guard_installment_paid'));
        redirect(admin_url('dan_guard/device/' . $device_id));
    }

    public function create_invoice($installment_id, $device_id)
    {
        $this->require_post();
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
        $this->require_post();
        if (!staff_can('edit', 'dan_guard') && !is_admin()) {
            access_denied('dan_guard');
        }
        $count = $this->dan_guard_model->generate_invoices_for_device($device_id);
        set_alert('success', _l('dan_guard_invoices_generated', $count));
        redirect(admin_url('dan_guard/device/' . $device_id));
    }

    public function delete($id)
    {
        $this->require_post();
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
        $this->require_post();
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
            // Champs pouvant contenir des URLs / motifs bloqués par les WAF : lus via
            // post_field() qui décode la variante base64url envoyée par le JS des réglages.
            $new_account = $this->post_field('dan_guard_fcm_service_account', false);
            if ($new_account !== get_option('dan_guard_fcm_service_account')) {
                // Le compte de service a changé : le jeton d'accès mis en cache n'est plus valable.
                update_option('dan_guard_fcm_token_cache', '');
            }
            update_option('dan_guard_fcm_service_account', $new_account);
            update_option('dan_guard_default_grace_days', (int) $this->input->post('dan_guard_default_grace_days'));
            update_option('dan_guard_checkin_interval_hours', (int) $this->input->post('dan_guard_checkin_interval_hours'));
            update_option('dan_guard_max_offline_days', (int) $this->input->post('dan_guard_max_offline_days'));
            update_option('dan_guard_lock_message', $this->post_field('dan_guard_lock_message', true));
            update_option('dan_guard_component_name', $this->post_field('dan_guard_component_name', true));
            update_option('dan_guard_model_custom_field', (int) $this->input->post('dan_guard_model_custom_field'));
            update_option('dan_guard_app_base_url', $this->post_field('dan_guard_app_base_url', true));
            update_option('dan_guard_apk_url', $this->post_field('dan_guard_apk_url', true));
            update_option('dan_guard_apk_checksum', $this->input->post('dan_guard_apk_checksum', true));
            update_option('dan_guard_frp_accounts', $this->input->post('dan_guard_frp_accounts', true));
            update_option('dan_guard_sms_enabled', $this->input->post('dan_guard_sms_enabled') ? 1 : 0);
            update_option('dan_guard_sms_endpoint', $this->post_field('dan_guard_sms_endpoint', false));
            update_option('dan_guard_sms_account_id', $this->input->post('dan_guard_sms_account_id', false));
            update_option('dan_guard_sms_password', $this->input->post('dan_guard_sms_password', false));
            update_option('dan_guard_sms_sender', $this->input->post('dan_guard_sms_sender', true));
            update_option('dan_guard_sms_ret_url', $this->post_field('dan_guard_sms_ret_url', false));
            update_option('dan_guard_sms_notice_days', (int) $this->input->post('dan_guard_sms_notice_days'));
            update_option('dan_guard_sms_message', $this->post_field('dan_guard_sms_message', false));
            set_alert('success', _l('settings_updated'));
            redirect(admin_url('dan_guard/settings'));
        }

        $data['item_custom_fields'] = $this->dan_guard_model->get_item_custom_fields();
        $data['title']              = _l('dan_guard_settings');
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
