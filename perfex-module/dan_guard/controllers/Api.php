<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Point d'entrée REST appelé par l'application Android.
 *
 * Base : {perfex_url}/dan_guard/api/<action>
 *   POST /enroll   { enrollment_token, imei, serial, android_id, model, fcm_token }
 *   POST /checkin  (en-tête Authorization: Bearer <api_token>) { status, battery? }
 *   POST /ack      (en-tête Authorization: Bearer <api_token>) { command_id }
 */
class Api extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('dan_guard/dan_guard_model');
    }

    private function body()
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        return is_array($json) ? $json : $this->input->post();
    }

    private function respond($data, $code = 200)
    {
        $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }

    private function bearer_token()
    {
        $header = $this->input->get_request_header('Authorization', true);
        if ($header && stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return $this->input->get_request_header('X-Api-Token', true);
    }

    private function authenticate()
    {
        $device = $this->dan_guard_model->get_device_by_api_token($this->bearer_token());
        if (!$device) {
            $this->respond(['error' => 'unauthorized'], 401);

            return null;
        }

        return $device;
    }

    /**
     * Enrôlement : échange un jeton d'enrôlement à usage unique contre un jeton d'API.
     */
    public function enroll()
    {
        $body  = $this->body();
        $token = $body['enrollment_token'] ?? '';

        if (empty($token)) {
            return $this->respond(['error' => 'missing_enrollment_token'], 400);
        }

        $result = $this->dan_guard_model->register_device($token, [
            'imei'       => $body['imei'] ?? null,
            'serial'     => $body['serial'] ?? null,
            'android_id' => $body['android_id'] ?? null,
            'model'      => $body['model'] ?? null,
            'fcm_token'  => $body['fcm_token'] ?? null,
        ]);

        if (!$result) {
            return $this->respond(['error' => 'invalid_or_used_token'], 403);
        }

        return $this->respond([
            'device_id'         => $result['device_id'],
            'api_token'         => $result['api_token'],
            'checkin_interval'  => (int) get_option('dan_guard_checkin_interval_hours'),
        ]);
    }

    /**
     * Check-in périodique. Retourne l'état à appliquer et les ordres en attente.
     */
    public function checkin()
    {
        $device = $this->authenticate();
        if (!$device) {
            return;
        }

        $body = $this->body();

        // Mise à jour du dernier contact + éventuel jeton FCM renouvelé.
        $update = ['last_checkin' => date('Y-m-d H:i:s')];
        if (!empty($body['fcm_token'])) {
            $update['fcm_token'] = $body['fcm_token'];
        }
        $this->dan_guard_model->update_device($device->id, $update);

        // L'appareil vient de prouver qu'il est en ligne : réévaluation immédiate.
        // Déblocage instantané si le retard est régularisé / plus hors ligne. Pas de
        // push (inutile) : l'ordre éventuel part directement dans cette réponse.
        $this->dan_guard_model->evaluate_device_state($device->id, false);

        // Recharge l'état à jour pour la réponse et les ordres.
        $device = $this->dan_guard_model->get_device($device->id);

        // Ordres en attente.
        $commands = [];
        foreach ($this->dan_guard_model->get_pending_commands($device->id) as $cmd) {
            $commands[] = [
                'id'      => (int) $cmd['id'],
                'command' => $cmd['command'],
                'payload' => $cmd['payload'] ? json_decode($cmd['payload'], true) : null,
            ];
            $this->dan_guard_model->mark_command_delivered($cmd['id']);
        }

        // État de référence (source de vérité côté serveur).
        $should_lock = ($device->status === 'locked');

        return $this->respond([
            'device_id'        => (int) $device->id,
            'status'           => $device->status,
            'should_lock'      => $should_lock,
            'lock_message'     => $should_lock ? get_option('dan_guard_lock_message') : null,
            'checkin_interval' => (int) get_option('dan_guard_checkin_interval_hours'),
            'max_offline_days' => (int) get_option('dan_guard_max_offline_days'),
            'frp_accounts'     => array_values(array_filter(array_map('trim',
                explode(',', (string) get_option('dan_guard_frp_accounts'))))),
            'commands'         => $commands,
        ]);
    }

    /**
     * Accusé de réception d'un ordre.
     */
    public function ack()
    {
        $device = $this->authenticate();
        if (!$device) {
            return;
        }

        $body       = $this->body();
        $command_id = (int) ($body['command_id'] ?? 0);
        if ($command_id) {
            $this->dan_guard_model->ack_command($command_id, $device->id);
        }

        return $this->respond(['ok' => true]);
    }
}
