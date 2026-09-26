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

    public function lock_device($device_id, $message = null)
    {
        $message = $message ?: get_option('dan_guard_lock_message');
        $this->update_device($device_id, ['status' => 'locked']);
        $this->log($device_id, 'locked', ['manual' => true]);

        return $this->queue_command($device_id, 'lock', ['message' => $message]);
    }

    public function unlock_device($device_id)
    {
        $this->update_device($device_id, ['status' => 'active']);
        $this->log($device_id, 'unlocked', ['manual' => true]);

        return $this->queue_command($device_id, 'unlock');
    }

    /**
     * Libération définitive : solde payé, l'appareil quitte le mode Device Owner.
     */
    public function release_device($device_id)
    {
        $this->update_device($device_id, ['status' => 'released']);
        $this->log($device_id, 'released', []);

        return $this->queue_command($device_id, 'release');
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
     * Recalcule l'état d'un appareil après paiement.
     */
    public function reevaluate_device($device_id)
    {
        $device = $this->get_device($device_id);
        if (!$device || in_array($device->status, ['pending', 'released'], true)) {
            return;
        }

        // Solde entièrement payé -> libération définitive.
        if (!$this->has_unpaid_installment($device_id)) {
            if ($device->status !== 'released') {
                $this->release_device($device_id);
            }

            return;
        }

        // Plus d'échéance en retard -> déverrouillage.
        if ($device->status === 'locked' && !$this->has_overdue_installment($device)) {
            $this->unlock_device($device_id);
        }
    }

    /**
     * Appelé par le cron : verrouille les appareils en retard au-delà du délai de grâce.
     */
    public function evaluate_overdue_devices()
    {
        $this->db->where_in('status', ['active', 'locked']);
        $devices = $this->db->get(db_prefix() . 'dan_guard_devices')->result();

        foreach ($devices as $device) {
            // Solde entièrement payé -> libération, rien d'autre à faire.
            if (!$this->has_unpaid_installment($device->id)) {
                if ($device->status !== 'released') {
                    $this->release_device($device->id);
                }
                continue;
            }

            $overdue = $this->has_overdue_installment($device);
            $offline = $this->is_offline_too_long($device);

            // Motif de verrouillage : retard de paiement OU silence prolongé.
            if (($overdue || $offline) && $device->status === 'active') {
                $reason = $overdue ? 'overdue_installment' : 'offline_too_long';
                $this->lock_device($device->id);
                $this->log($device->id, 'auto_locked', ['reason' => $reason]);
            } elseif (!$overdue && !$offline && $device->status === 'locked') {
                // Ni retard ni silence : régularisé -> déverrouillage.
                $this->unlock_device($device->id);
                $this->log($device->id, 'auto_unlocked', ['reason' => 'cleared']);
            }
        }
    }

    /* ---------------------------------------------------------------------
     * FCM & journal
     * ------------------------------------------------------------------- */

    /**
     * Réveille l'appareil via Firebase Cloud Messaging pour qu'il fasse un check-in
     * immédiat. Le contenu de l'ordre n'est jamais dans le push : l'app se reconnecte
     * pour le récupérer de façon authentifiée.
     */
    public function push_wakeup($device_id, $command)
    {
        $device     = $this->get_device($device_id);
        $server_key = get_option('dan_guard_fcm_server_key');

        if (!$device || empty($device->fcm_token) || empty($server_key)) {
            return false;
        }

        $body = [
            'to'           => $device->fcm_token,
            'priority'     => 'high',
            'data'         => ['action' => 'checkin', 'hint' => $command],
            'content_available' => true,
        ];

        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: key=' . $server_key,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $result = curl_exec($ch);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $this->log($device_id, 'fcm_error', ['error' => $err]);

            return false;
        }

        return $result;
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
