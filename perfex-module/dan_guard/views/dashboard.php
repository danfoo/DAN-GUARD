<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">

        <!-- Navigation module -->
        <div class="row">
            <div class="col-md-12">
                <ul class="nav nav-tabs mbot20" role="tablist">
                    <li role="presentation" class="active">
                        <a href="<?php echo admin_url('dan_guard'); ?>"><?php echo _l('dan_guard_dashboard'); ?></a>
                    </li>
                    <li role="presentation">
                        <a href="<?php echo admin_url('dan_guard/devices'); ?>"><?php echo _l('dan_guard_devices'); ?></a>
                    </li>
                    <?php if (is_admin()) { ?>
                    <li role="presentation" class="pull-right">
                        <a href="<?php echo admin_url('dan_guard/settings'); ?>"><i class="fa fa-cog"></i> <?php echo _l('dan_guard_settings'); ?></a>
                    </li>
                    <?php } ?>
                </ul>
            </div>
        </div>

        <!-- Tuiles de synthèse -->
        <div class="row">
            <?php
            $tiles = [
                ['label' => _l('dan_guard_overdue_amount'),   'value' => app_format_money($stats['overdue_amount'], ''),    'sub' => _l('dan_guard_overdue_devices_n', $stats['overdue_devices']), 'panel' => 'panel-danger'],
                ['label' => _l('dan_guard_total_outstanding'),'value' => app_format_money($stats['total_outstanding'], ''), 'sub' => _l('dan_guard_all_unpaid'),  'panel' => 'panel-warning'],
                ['label' => _l('dan_guard_status_locked'),    'value' => (int) $stats['by_status']['locked'],               'sub' => _l('dan_guard_devices'),    'panel' => 'panel-default'],
                ['label' => _l('dan_guard_offline'),          'value' => (int) $stats['offline_devices'],                   'sub' => _l('dan_guard_devices'),    'panel' => 'panel-default'],
            ];
            foreach ($tiles as $t) { ?>
                <div class="col-md-3 col-sm-6">
                    <div class="panel_s <?php echo $t['panel']; ?>">
                        <div class="panel-body">
                            <p class="text-muted no-mtop no-mbot"><?php echo $t['label']; ?></p>
                            <h2 class="bold no-mbot"><?php echo $t['value']; ?></h2>
                            <small class="text-muted"><?php echo $t['sub']; ?></small>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <!-- Répartition par état -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <?php
                        $labels = ['pending' => 'label-default', 'active' => 'label-success', 'locked' => 'label-danger', 'released' => 'label-info'];
                        foreach ($stats['by_status'] as $st => $count) { ?>
                            <span class="label <?php echo $labels[$st]; ?>" style="font-size:13px;margin-right:8px;">
                                <?php echo _l('dan_guard_status_' . $st); ?> : <strong><?php echo (int) $count; ?></strong>
                            </span>
                        <?php } ?>
                        <span class="pull-right text-muted"><?php echo _l('dan_guard_total_devices', $stats['total_devices']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Appareils en retard -->
            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body">
                        <h5 class="bold"><?php echo _l('dan_guard_overdue_devices'); ?></h5>
                        <hr class="hr-panel-heading" />
                        <?php if (empty($overdue)) { ?>
                            <p class="text-muted"><?php echo _l('dan_guard_no_overdue'); ?></p>
                        <?php } else { ?>
                        <table class="table table-striped">
                            <thead><tr>
                                <th><?php echo _l('dan_guard_device'); ?></th>
                                <th><?php echo _l('dan_guard_client'); ?></th>
                                <th><?php echo _l('dan_guard_overdue_amount'); ?></th>
                                <th><?php echo _l('dan_guard_days_late'); ?></th>
                                <th><?php echo _l('dan_guard_status'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($overdue as $o) {
                                $days_late = floor((time() - strtotime($o['oldest_due'])) / 86400);
                                $labels2 = ['pending' => 'label-default', 'active' => 'label-success', 'locked' => 'label-danger', 'released' => 'label-info'];
                            ?>
                                <tr>
                                    <td><a href="<?php echo admin_url('dan_guard/device/' . $o['id']); ?>"><?php echo html_escape($o['device_name'] ?: ('#' . $o['id'])); ?></a></td>
                                    <td>
                                        <?php if (!empty($o['client_id'])) { ?>
                                            <a href="<?php echo admin_url('clients/client/' . $o['client_id']); ?>"><?php echo html_escape($o['company'] ?: ('#' . $o['client_id'])); ?></a>
                                        <?php } else { echo '—'; } ?>
                                    </td>
                                    <td class="text-danger bold"><?php echo app_format_money($o['overdue_amount'], ''); ?></td>
                                    <td><?php echo (int) $days_late; ?> j</td>
                                    <td><span class="label <?php echo $labels2[$o['status']] ?? 'label-default'; ?>"><?php echo _l('dan_guard_status_' . $o['status']); ?></span></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Échéances à venir (7 jours) -->
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h5 class="bold"><?php echo _l('dan_guard_upcoming_7d'); ?></h5>
                        <hr class="hr-panel-heading" />
                        <?php if (empty($upcoming)) { ?>
                            <p class="text-muted"><?php echo _l('dan_guard_no_upcoming'); ?></p>
                        <?php } else { ?>
                        <table class="table">
                            <thead><tr>
                                <th><?php echo _l('dan_guard_due_date'); ?></th>
                                <th><?php echo _l('dan_guard_device'); ?></th>
                                <th><?php echo _l('dan_guard_amount'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($upcoming as $u) { ?>
                                <tr>
                                    <td><?php echo _d($u['due_date']); ?></td>
                                    <td><a href="<?php echo admin_url('dan_guard/device/' . $u['device_id']); ?>"><?php echo html_escape($u['device_name'] ?: ('#' . $u['device_id'])); ?></a></td>
                                    <td><?php echo app_format_money($u['amount'], ''); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
