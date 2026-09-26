<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dan_guard_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /* ---------------------------------------------------------------------
     * Appareils
     * ------------------------------------------------------------------- */

    public function get_devices($where = [])
    {
        $this->db->where($where);
        $this->db->order_by('created_at', 'DESC');

        return $this->db->get(db_prefix() . 'dan_guard_devices')->result_array();
    }

    public function get_device($id)
    {
        $this->db->where('id', $id);

        return $this->db->get(db_prefix() . 'dan_guard_devices')->row();
    }

    public function get_device_by_enrollment_token($token)
    {
        $this->db->where('enrollment_token', $token);

        return $this->db->get(db_prefix() . 'dan_guard_devices')->row();
    }

    /**
     * Authentifie un appareil à partir du jeton d'API brut fourni par l'app.
     */
    public function get_device_by_api_token($token)
    {
        if (empty($token)) {
            return null;
        }
        $this->db->where('api_token_hash', hash('sha256', $token));

        return $this->db->get(db_prefix() . 'dan_guard_devices')->row();
    }

    /**
     * Crée un appareil côté admin (avant enrôlement physique).
     * Retourne le jeton d'enrôlement à intégrer dans le QR code de provisioning.
     */
    public function add_device($data)
    {
        $enrollment_token = bin2hex(random_bytes(20));

        $insert = [
            'client_id'        => (int) ($data['client_id'] ?? 0),
            'device_name'      => $data['device_name'] ?? null,
            'model'            => $data['model'] ?? null,
            'imei'             => $data['imei'] ?? null,
            'sale_price'       => (float) ($data['sale_price'] ?? 0),
            'grace_days'       => isset($data['grace_days']) && $data['grace_days'] !== ''
                                    ? (int) $data['grace_days'] : null,
            'enrollment_token' => $enrollment_token,
            'status'           => 'pending',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        $this->db->insert(db_prefix() . 'dan_guard_devices', $insert);
        $device_id = $this->db->insert_id();

        if ($device_id) {
            $this->log($device_id, 'device_created', ['by_staff' => get_staff_user_id()]);
        }

        return $device_id ? ['id' => $device_id, 'enrollment_token' => $enrollment_token] : false;
    }

    public function update_device($id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->where('id', $id);

        return $this->db->update(db_prefix() . 'dan_guard_devices', $data);
    }

    public function delete_device($id)
    {
        $this->db->where('device_id', $id);
        $this->db->delete(db_prefix() . 'dan_guard_installments');
        $this->db->where('device_id', $id);
        $this->db->delete(db_prefix() . 'dan_guard_commands');
        $this->db->where('device_id', $id);
        $this->db->delete(db_prefix() . 'dan_guard_logs');
        $this->db->where('id', $id);

        return $this->db->delete(db_prefix() . 'dan_guard_devices');
    }

    /**
     * Enregistrement effectué par l'app Android lors de l'enrôlement.
     * Vérifie le jeton d'enrôlement, génère et retourne le jeton d'API en clair
     * (seul son hash est stocké).
     */
    public function register_device($enrollment_token, $info)
    {
        $device = $this->get_device_by_enrollment_token($enrollment_token);
        if (!$device) {
            return false;
        }

        $api_token = bin2hex(random_bytes(32));

        $this->update_device($device->id, [
            'imei'          => $info['imei'] ?? $device->imei,
            'serial'        => $info['serial'] ?? null,
            'android_id'    => $info['android_id'] ?? null,
            'model'         => $info['model'] ?? $device->model,
            'fcm_token'     => $info['fcm_token'] ?? null,
            'api_token_hash' => hash('sha256', $api_token),
            'status'        => 'active',
            'enrolled_at'   => date('Y-m-d H:i:s'),
            // Le jeton d'enrôlement est à usage unique.
            'enrollment_token' => null,
        ]);

        $this->log($device->id, 'device_enrolled', ['model' => $info['model'] ?? null]);

        return ['device_id' => $device->id, 'api_token' => $api_token];
    }

    /**
     * Régénère un jeton d'enrôlement (appareil non encore enrôlé uniquement).
     */
    public function regenerate_enrollment_token($device_id)
    {
        $device = $this->get_device($device_id);
        if (!$device || $device->status !== 'pending') {
            return false;
        }
        $token = bin2hex(random_bytes(20));
        $this->update_device($device_id, ['enrollment_token' => $token]);
        $this->log($device_id, 'enrollment_token_regenerated', []);

        return $token;
    }

    /* ---------------------------------------------------------------------
     * Provisioning Device Owner (QR code)
     * ------------------------------------------------------------------- */

    /**
     * Construit le bundle de provisioning Android pour un appareil, jeton inclus.
     * Retourne un tableau associatif (à encoder en JSON pour le QR code).
     */
    public function build_provisioning_payload($device)
    {
        $component = get_option('dan_guard_component_name');
        if (empty($component)) {
            $component = 'com.danguard.lock/.AdminReceiver';
        }

        $payload = [
            'android.app.extra.PROVISIONING_DEVICE_ADMIN_COMPONENT_NAME' => $component,
            'android.app.extra.PROVISIONING_LEAVE_ALL_SYSTEM_APPS_ENABLED' => true,
            'android.app.extra.PROVISIONING_ADMIN_EXTRAS_BUNDLE' => [
                'dan_guard_enrollment_token' => $device->enrollment_token,
            ],
        ];

        $apk_url = get_option('dan_guard_apk_url');
        if (!empty($apk_url)) {
            $payload['android.app.extra.PROVISIONING_DEVICE_ADMIN_PACKAGE_DOWNLOAD_LOCATION'] = $apk_url;
        }

        $checksum = get_option('dan_guard_apk_checksum');
        if (!empty($checksum)) {
            $payload['android.app.extra.PROVISIONING_DEVICE_ADMIN_SIGNATURE_CHECKSUM'] = $checksum;
        }

        return $payload;
    }

    /**
     * Vrai si les réglages nécessaires au provisioning sont renseignés.
     */
    public function provisioning_is_configured()
    {
        return !empty(get_option('dan_guard_apk_url'))
            && !empty(get_option('dan_guard_apk_checksum'));
    }

    /* ---------------------------------------------------------------------
     * Échéances
     * ------------------------------------------------------------------- */

    public function get_installments($device_id)
    {
        $this->db->where('device_id', $device_id);
        $this->db->order_by('due_date', 'ASC');

        return $this->db->get(db_prefix() . 'dan_guard_installments')->result_array();
    }

    public function add_installment($data)
    {
        $this->db->insert(db_prefix() . 'dan_guard_installments', [
            'device_id'  => (int) $data['device_id'],
            'amount'     => (float) $data['amount'],
            'due_date'   => to_sql_date($data['due_date']),
            'note'       => $data['note'] ?? null,
            'paid'       => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->db->insert_id();
    }

    /**
     * Génère automatiquement un échéancier mensuel.
     */
    public function generate_schedule($device_id, $total, $count, $first_due_date)
    {
        $count  = max(1, (int) $count);
        $amount = round(((float) $total) / $count, 2);
        $date   = new DateTime(to_sql_date($first_due_date));

        for ($i = 0; $i < $count; $i++) {
            $this->add_installment([
                'device_id' => $device_id,
                'amount'    => $amount,
                'due_date'  => $date->format('Y-m-d'),
                'note'      => _l('dan_guard_installment') . ' ' . ($i + 1) . '/' . $count,
            ]);
            $date->modify('+1 month');
        }
    }

    public function set_installment_paid($installment_id, $paid = true)
    {
        $this->db->where('id', $installment_id);
        $this->db->update(db_prefix() . 'dan_guard_installments', [
            'paid'      => $paid ? 1 : 0,
            'paid_date' => $paid ? date('Y-m-d') : null,
        ]);

        $installment = $this->db->where('id', $installment_id)
            ->get(db_prefix() . 'dan_guard_installments')->row();

        if ($installment) {
            $this->reevaluate_device($installment->device_id);
        }

        return true;
    }

    public function get_installment($id)
    {
        return $this->db->where('id', $id)
            ->get(db_prefix() . 'dan_guard_installments')->row();
    }

    /* ---------------------------------------------------------------------
     * Facturation liée aux échéances (factures Perfex natives)
     * ------------------------------------------------------------------- */

    /**
     * Crée une facture Perfex pour une échéance et lie les deux.
     * Retourne l'id de la facture, ou false.
     */
    public function create_invoice_for_installment($installment_id)
    {
        $installment = $this->get_installment($installment_id);
        if (!$installment || !empty($installment->invoice_id)) {
            return false; // introuvable ou déjà facturée
        }

        $device = $this->get_device($installment->device_id);
        if (!$device || empty($device->client_id)) {
            return false; // aucun client rattaché : facturation impossible
        }

        $this->load->model('invoices_model');
        $this->load->model('clients_model');

        $client = $this->clients_model->get($device->client_id);
        $base   = function_exists('get_base_currency') ? get_base_currency() : null;

        $description = _l('dan_guard') . ' — '
            . ($device->device_name ?: ('#' . $device->id))
            . ($installment->note ? ' (' . $installment->note . ')' : '');

        $amount = (float) $installment->amount;

        $data = [
            'clientid'                 => $device->client_id,
            'number'                   => get_option('next_invoice_number'),
            'date'                     => _d(date('Y-m-d')),
            'duedate'                  => _d($installment->due_date),
            'currency'                 => $base ? $base->id : get_option('default_currency'),
            'subtotal'                 => $amount,
            'total'                    => $amount,
            'adjustment'               => 0,
            'discount_percent'         => 0,
            'discount_total'           => 0,
            'discount_type'            => '',
            'terms'                    => '',
            'clientnote'               => '',
            'adminnote'                => 'DAN-GUARD installment #' . $installment_id,
            'billing_street'           => $client->billing_street ?? '',
            'billing_city'             => $client->billing_city ?? '',
            'billing_state'            => $client->billing_state ?? '',
            'billing_zip'              => $client->billing_zip ?? '',
            'billing_country'          => $client->billing_country ?? 0,
            'include_shipping'         => 0,
            'show_shipping_on_invoice' => 0,
            'show_quantity_as'         => 1,
            'allowed_payment_modes'    => [],
            'newitems'                 => [
                [
                    'description'      => $description,
                    'long_description' => '',
                    'qty'              => 1,
                    'unit'             => '',
                    'taxname'          => [],
                    'rate'             => $amount,
                    'order'            => 1,
                ],
            ],
        ];

        try {
            $invoice_id = $this->invoices_model->add($data);
        } catch (\Throwable $e) {
            $this->log($device->id, 'invoice_error', ['installment' => $installment_id, 'error' => $e->getMessage()]);

            return false;
        }

        if (!$invoice_id) {
            $this->log($device->id, 'invoice_error', ['installment' => $installment_id, 'error' => 'add_returned_false']);

            return false;
        }

        $this->db->where('id', $installment_id)
            ->update(db_prefix() . 'dan_guard_installments', ['invoice_id' => $invoice_id]);
        $this->log($device->id, 'invoice_created', ['installment' => $installment_id, 'invoice' => $invoice_id]);

        return $invoice_id;
    }

    /**
     * Crée les factures manquantes pour toutes les échéances impayées d'un appareil.
     * Retourne le nombre de factures créées.
     */
    public function generate_invoices_for_device($device_id)
    {
        $created = 0;
        foreach ($this->get_installments($device_id) as $it) {
            if (empty($it['invoice_id']) && !$it['paid']) {
                if ($this->create_invoice_for_installment($it['id'])) {
                    $created++;
                }
            }
        }

        return $created;
    }

    /**
     * Marque payées les échéances liées à une facture (déclenche la réévaluation
     * de l'appareil, donc le déverrouillage éventuel).
     */
    public function mark_installments_paid_for_invoice($invoice_id)
    {
        $rows = $this->db->where('invoice_id', $invoice_id)
            ->where('paid', 0)
            ->get(db_prefix() . 'dan_guard_installments')->result();

        foreach ($rows as $row) {
            $this->set_installment_paid($row->id, true);
        }
    }

    /**
     * Hook de paiement Perfex : si une facture liée à une échéance est intégralement
     * payée, l'échéance correspondante est marquée payée automatiquement.
     */
    public function handle_payment_added($payment_id)
    {
        $payment = $this->db->where('id', $payment_id)
            ->get(db_prefix() . 'invoicepaymentrecords')->row();
        if (!$payment) {
            return;
        }
        $invoice_id = $payment->invoiceid;

        // N'agit que si la facture est liée à au moins une échéance non payée.
        $linked = $this->db->where('invoice_id', $invoice_id)
            ->where('paid', 0)
            ->count_all_results(db_prefix() . 'dan_guard_installments');
        if ($linked === 0) {
            return;
        }

        $invoice = $this->db->where('id', $invoice_id)
            ->get(db_prefix() . 'invoices')->row();
        if (!$invoice) {
            return;
        }

        // Statut 2 = payée. Repli : somme des paiements >= total de la facture.
        $fully_paid = ((int) $invoice->status === 2);
        if (!$fully_paid) {
            $this->db->select_sum('amount');
            $paid_sum = (float) ($this->db->where('invoiceid', $invoice_id)
                ->get(db_prefix() . 'invoicepaymentrecords')->row()->amount ?? 0);
            $fully_paid = ($paid_sum + 0.001 >= (float) $invoice->total);
        }

        if ($fully_paid) {
            $this->mark_installments_paid_for_invoice($invoice_id);
        }
    }

    /* ---------------------------------------------------------------------
     * Notifications SMS de préavis (L'Africa Mobile — « Send via JSON »)
     * ------------------------------------------------------------------- */

    /**
     * Normalise un numéro au format international sans « + » ni « 00 » (attendu par LAM).
     */
    private function normalize_msisdn($number)
    {
        $number = preg_replace('/[^\d+]/', '', (string) $number);
        if (strpos($number, '+') === 0) {
            $number = substr($number, 1);
        } elseif (strpos($number, '00') === 0) {
            $number = substr($number, 2);
        }

        return $number;
    }

    /**
     * Envoie un SMS via l'API L'Africa Mobile (endpoint « Send via JSON »).
     *
     * NB : endpoint, identifiants et nom d'expéditeur sont configurables dans les
     * réglages. Les noms de champs suivent l'API LAM ; vérifiez-les sur le portail
     * développeur si l'envoi échoue (le retour est journalisé).
     *
     * @return bool
     */
    public function send_sms($to, $text)
    {
        $endpoint = get_option('dan_guard_sms_endpoint');
        $account  = get_option('dan_guard_sms_account_id');
        $password = get_option('dan_guard_sms_password');
        $sender   = get_option('dan_guard_sms_sender');

        if (empty($endpoint) || empty($account)) {
            return false;
        }

        $payload = [
            'accountid' => $account,
            'password'  => $password,
            'sender'    => $sender,
            'to'        => $this->normalize_msisdn($to),
            'text'      => $text,
            'dlr'       => '1',
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err || $status >= 400) {
            log_activity('DAN-GUARD SMS error: ' . ($err ?: ('HTTP ' . $status . ' ' . $result)));

            return false;
        }

        return true;
    }

    /**
     * Cron : envoie un SMS de préavis aux clients dont un appareil va être verrouillé
     * dans « sms_notice_days » jours (une seule fois par échéance).
     */
    public function notify_upcoming_locks()
    {
        if (!get_option('dan_guard_sms_enabled')) {
            return;
        }
        $notice_days = (int) get_option('dan_guard_sms_notice_days');
        $template    = get_option('dan_guard_sms_message');
        $this->load->model('clients_model');

        $this->db->where('status', 'active');
        $this->db->where('client_id >', 0);
        $devices = $this->db->get(db_prefix() . 'dan_guard_devices')->result();

        foreach ($devices as $device) {
            $grace = $this->grace_days_for($device);

            $this->db->where('device_id', $device->id);
            $this->db->where('paid', 0);
            $this->db->where('reminder_sent', 0);
            $installments = $this->db->get(db_prefix() . 'dan_guard_installments')->result();

            foreach ($installments as $it) {
                $lock_ts   = strtotime($it->due_date) + $grace * 86400;
                $notice_ts = $lock_ts - $notice_days * 86400;
                $now       = time();

                if ($now < $notice_ts || $now >= $lock_ts) {
                    continue; // hors de la fenêtre de préavis
                }

                $client = $this->clients_model->get($device->client_id);
                $phone  = $client ? $client->phonenumber : '';
                if (!empty($phone)) {
                    $msg  = str_replace('{date}', _d(date('Y-m-d', $lock_ts)), $template);
                    $sent = $this->send_sms($phone, $msg);
                    $this->log($device->id, 'sms_lock_notice', ['installment' => $it->id, 'sent' => (bool) $sent]);
                }

                // Marque comme notifiée pour ne pas renvoyer (même si le numéro manque).
                $this->db->where('id', $it->id)
                    ->update(db_prefix() . 'dan_guard_installments', ['reminder_sent' => 1]);
            }
        }
    }

    /* ---------------------------------------------------------------------
     * Ordres (commands)
     * ------------------------------------------------------------------- */

    public function queue_command($device_id, $command, $payload = [], $push = true)
    {
        $this->db->insert(db_prefix() . 'dan_guard_commands', [
            'device_id'  => (int) $device_id,
            'command'    => $command,
            'payload'    => empty($payload) ? null : json_encode($payload),
            'status'     => 'pending',
            'created_by' => get_staff_user_id() ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $command_id = $this->db->insert_id();

        $this->log($device_id, 'command_queued', ['command' => $command]);

        if ($push) {
            $this->push_wakeup($device_id, $command);
        }

        return $command_id;
    }

    public function get_pending_commands($device_id)
    {
        $this->db->where('device_id', $device_id);
        $this->db->where('status', 'pending');
        $this->db->order_by('created_at', 'ASC');

        return $this->db->get(db_prefix() . 'dan_guard_commands')->result_array();
    }

    public function mark_command_delivered($command_id)
    {
        $this->db->where('id', $command_id);
        $this->db->update(db_prefix() . 'dan_guard_commands', [
            'status'       => 'delivered',
            'delivered_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function ack_command($command_id, $device_id)
    {
        $this->db->where('id', $command_id);
        $this->db->where('device_id', $device_id);
        $this->db->update(db_prefix() . 'dan_guard_commands', [
            'status'   => 'acked',
            'acked_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /* ---------------------------------------------------------------------
     * Actions haut niveau
     * ------------------------------------------------------------------- */

    public function lock_device($device_id, $message = null, $push = true, $meta = ['source' => 'manual'])
    {
        $message = $message ?: get_option('dan_guard_lock_message');
        $this->update_device($device_id, ['status' => 'locked']);
        $this->log($device_id, 'locked', $meta);

        return $this->queue_command($device_id, 'lock', ['message' => $message], $push);
    }

    public function unlock_device($device_id, $push = true, $meta = ['source' => 'manual'])
    {
        $this->update_device($device_id, ['status' => 'active']);
        $this->log($device_id, 'unlocked', $meta);

        return $this->queue_command($device_id, 'unlock', [], $push);
    }

    /**
     * Libération définitive : solde payé, l'appareil quitte le mode Device Owner.
     */
    public function release_device($device_id, $push = true, $meta = ['source' => 'manual'])
    {
        $this->update_device($device_id, ['status' => 'released']);
        $this->log($device_id, 'released', $meta);

        return $this->queue_command($device_id, 'release', [], $push);
    }

    /* ---------------------------------------------------------------------
     * Évaluation automatique
     * ------------------------------------------------------------------- */

    /**
     * Nombre de jours de grâce applicables à un appareil.
     */
    private function grace_days_for($device)
    {
        if (isset($device->grace_days) && $device->grace_days !== null) {
            return (int) $device->grace_days;
        }

        return (int) get_option('dan_guard_default_grace_days');
    }

    public function has_overdue_installment($device)
    {
        $limit = date('Y-m-d', strtotime('-' . $this->grace_days_for($device) . ' days'));

        $this->db->where('device_id', $device->id);
        $this->db->where('paid', 0);
        $this->db->where('due_date <', $limit);

        return $this->db->count_all_results(db_prefix() . 'dan_guard_installments') > 0;
    }

    public function has_unpaid_installment($device_id)
    {
        $this->db->where('device_id', $device_id);
        $this->db->where('paid', 0);

        return $this->db->count_all_results(db_prefix() . 'dan_guard_installments') > 0;
    }

    /**
     * Vrai si l'appareil n'a pas fait de check-in depuis plus de N jours (anti-mode-avion).
     * L'enforcement réel est côté app (elle se verrouille même hors ligne) ; ici c'est
     * pour la visibilité admin et le rattrapage au retour en ligne.
     */
    public function is_offline_too_long($device)
    {
        $max = (int) get_option('dan_guard_max_offline_days');
        if ($max <= 0 || empty($device->last_checkin)) {
            return false;
        }
        $limit = strtotime('-' . $max . ' days');

        return strtotime($device->last_checkin) < $limit;
    }

    /**
     * Recalcule l'état d'un appareil après paiement (délègue à evaluate_device_state).
     */
    public function reevaluate_device($device_id)
    {
        $this->evaluate_device_state($device_id);
    }

    /**
     * Évalue et applique l'état d'un seul appareil : verrouillage (retard OU silence
     * prolongé), déverrouillage (situation régularisée) ou libération (solde payé).
     *
     * Utilisé par le cron, à la réception d'un paiement, et à chaque check-in — ce
     * dernier assure le déblocage instantané au retour en ligne.
     *
     * @param bool $push Envoyer un réveil FCM. Inutile pendant un check-in : l'appareil
     *                   est déjà en ligne et reçoit l'ordre directement dans la réponse.
     */
    public function evaluate_device_state($device_id, $push = true)
    {
        $device = $this->get_device($device_id);
        if (!$device || in_array($device->status, ['pending', 'released'], true)) {
            return;
        }

        // Solde entièrement payé -> libération définitive.
        if (!$this->has_unpaid_installment($device_id)) {
            $this->release_device($device_id, $push, ['source' => 'auto', 'reason' => 'fully_paid']);

            return;
        }

        $overdue = $this->has_overdue_installment($device);
        $offline = $this->is_offline_too_long($device);

        if (($overdue || $offline) && $device->status === 'active') {
            $reason = $overdue ? 'overdue_installment' : 'offline_too_long';
            $this->lock_device($device_id, null, $push, ['source' => 'auto', 'reason' => $reason]);
        } elseif (!$overdue && !$offline && $device->status === 'locked') {
            $this->unlock_device($device_id, $push, ['source' => 'auto', 'reason' => 'cleared']);
        }
    }

    /**
     * Appelé par le cron : évalue tous les appareils actifs/verrouillés.
     */
    public function evaluate_overdue_devices()
    {
        $this->db->where_in('status', ['active', 'locked']);
        $devices = $this->db->get(db_prefix() . 'dan_guard_devices')->result();

        foreach ($devices as $device) {
            $this->evaluate_device_state($device->id);
        }
    }

    /* ---------------------------------------------------------------------
     * FCM & journal
     * ------------------------------------------------------------------- */

    /**
     * Réveille l'appareil via Firebase Cloud Messaging (API HTTP v1) pour qu'il fasse un
     * check-in immédiat. Le contenu de l'ordre n'est jamais dans le push : l'app se
     * reconnecte pour le récupérer de façon authentifiée.
     */
    public function push_wakeup($device_id, $command)
    {
        $device  = $this->get_device($device_id);
        $account = $this->fcm_service_account();

        if (!$device || empty($device->fcm_token) || !$account) {
            return false;
        }

        $token = $this->fcm_access_token();
        if (!$token) {
            $this->log($device_id, 'fcm_error', ['error' => 'no_access_token']);

            return false;
        }

        // Format API v1 : les valeurs de "data" doivent être des chaînes.
        $body = [
            'message' => [
                'token'   => $device->fcm_token,
                'android' => ['priority' => 'HIGH'],
                'data'    => ['action' => 'checkin', 'hint' => (string) $command],
            ],
        ];

        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($account['project_id']) . '/messages:send';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $this->log($device_id, 'fcm_error', ['error' => $err]);

            return false;
        }
        if ($status >= 400) {
            // 401/403 : le jeton a pu expirer ou être révoqué — on force son renouvellement.
            if ($status === 401 || $status === 403) {
                delete_option('dan_guard_fcm_token_cache');
            }
            $this->log($device_id, 'fcm_error', ['http' => $status, 'response' => $result]);

            return false;
        }

        return $result;
    }

    /**
     * Décode et valide le JSON du compte de service Firebase (réglages du module).
     */
    private function fcm_service_account()
    {
        $raw = get_option('dan_guard_fcm_service_account');
        if (empty($raw)) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)
            || empty($data['client_email'])
            || empty($data['private_key'])
            || empty($data['project_id'])) {
            return null;
        }

        return $data;
    }

    /**
     * Retourne un jeton d'accès OAuth2 pour FCM v1, mis en cache jusqu'à son expiration.
     * Le jeton est obtenu en signant un JWT avec la clé privée du compte de service.
     */
    private function fcm_access_token()
    {
        // Cache : réutilise le jeton tant qu'il reste valable (marge de 5 min).
        $cache = json_decode((string) get_option('dan_guard_fcm_token_cache'), true);
        if (is_array($cache) && !empty($cache['token']) && ($cache['expiry'] ?? 0) > time() + 300) {
            return $cache['token'];
        }

        $account = $this->fcm_service_account();
        if (!$account) {
            return null;
        }

        $now    = time();
        $header = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64url(json_encode([
            'iss'   => $account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $signature = '';
        if (!openssl_sign($header . '.' . $claims, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
            return null;
        }
        $jwt = $header . '.' . $claims . '.' . $this->base64url($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || !$resp) {
            return null;
        }
        $decoded = json_decode($resp, true);
        if (empty($decoded['access_token'])) {
            return null;
        }

        update_option('dan_guard_fcm_token_cache', json_encode([
            'token'  => $decoded['access_token'],
            'expiry' => $now + (int) ($decoded['expires_in'] ?? 3600),
        ]));

        return $decoded['access_token'];
    }

    /**
     * Encodage base64url sans padding (pour les JWT).
     */
    private function base64url($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function log($device_id, $event, $data = [])
    {
        $this->db->insert(db_prefix() . 'dan_guard_logs', [
            'device_id'  => $device_id,
            'event'      => $event,
            'data'       => empty($data) ? null : json_encode($data),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function get_logs($device_id, $limit = 100)
    {
        $this->db->where('device_id', $device_id);
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);

        return $this->db->get(db_prefix() . 'dan_guard_logs')->result_array();
    }
}
