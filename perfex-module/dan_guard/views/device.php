<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <!-- Colonne principale -->
            <div class="col-md-8">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="pull-right">
                            <?php if ($device->status === 'active' || $device->status === 'pending') { ?>
                                <a href="<?php echo admin_url('dan_guard/lock/' . $device->id); ?>" class="btn btn-danger"
                                   onclick="return confirm('<?php echo _l('dan_guard_lock'); ?> ?');">
                                    <i class="fa fa-lock"></i> <?php echo _l('dan_guard_lock'); ?>
                                </a>
                            <?php } ?>
                            <?php if ($device->status === 'locked') { ?>
                                <a href="<?php echo admin_url('dan_guard/unlock/' . $device->id); ?>" class="btn btn-success">
                                    <i class="fa fa-unlock"></i> <?php echo _l('dan_guard_unlock'); ?>
                                </a>
                            <?php } ?>
                            <?php if ($device->status !== 'released') { ?>
                                <a href="<?php echo admin_url('dan_guard/release/' . $device->id); ?>" class="btn btn-info"
                                   onclick="return confirm('<?php echo _l('dan_guard_release'); ?> ?');">
                                    <?php echo _l('dan_guard_release'); ?>
                                </a>
                            <?php } ?>
                        </div>
                        <h4 class="no-margin"><?php echo html_escape($device->device_name ?: ('#' . $device->id)); ?></h4>
                        <p class="text-muted"><?php echo html_escape($device->model); ?></p>
                        <hr class="hr-panel-heading" />
                        <table class="table table-borderless">
                            <tr><td><strong><?php echo _l('dan_guard_status'); ?></strong></td>
                                <td><?php echo _l('dan_guard_status_' . $device->status); ?></td></tr>
                            <tr><td><strong><?php echo _l('dan_guard_imei'); ?></strong></td><td><?php echo html_escape($device->imei); ?></td></tr>
                            <tr><td><strong><?php echo _l('dan_guard_serial'); ?></strong></td><td><?php echo html_escape($device->serial); ?></td></tr>
                            <tr><td><strong><?php echo _l('dan_guard_last_checkin'); ?></strong></td>
                                <td><?php echo $device->last_checkin ? _dt($device->last_checkin) : '-'; ?></td></tr>
                        </table>

                        <?php if ($device->status === 'pending' && $device->enrollment_token) { ?>
                        <div class="alert alert-warning">
                            <strong><?php echo _l('dan_guard_enrollment_token'); ?> :</strong>
                            <code><?php echo html_escape($device->enrollment_token); ?></code>
                            <p class="mtop10"><small><?php echo _l('dan_guard_provisioning_help'); ?></small></p>
                            <a href="<?php echo admin_url('dan_guard/provisioning/' . $device->id); ?>" class="btn btn-info btn-sm">
                                <i class="fa fa-qrcode"></i> <?php echo _l('dan_guard_provisioning'); ?>
                            </a>
                        </div>
                        <?php } ?>

                        <!-- Échéances -->
                        <h5><?php echo _l('dan_guard_installments'); ?></h5>
                        <table class="table table-striped">
                            <thead><tr>
                                <th><?php echo _l('dan_guard_due_date'); ?></th>
                                <th><?php echo _l('dan_guard_amount'); ?></th>
                                <th><?php echo _l('dan_guard_note'); ?></th>
                                <th><?php echo _l('dan_guard_paid'); ?></th>
                                <th></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($installments as $it) {
                                $overdue = !$it['paid'] && strtotime($it['due_date']) < time();
                            ?>
                                <tr class="<?php echo $overdue ? 'danger' : ''; ?>">
                                    <td><?php echo _d($it['due_date']); ?></td>
                                    <td><?php echo app_format_money($it['amount'], ''); ?></td>
                                    <td><?php echo html_escape($it['note']); ?></td>
                                    <td><?php echo $it['paid'] ? '<span class="label label-success">'._l('dan_guard_paid').'</span>' : '<span class="label label-default">—</span>'; ?></td>
                                    <td>
                                        <?php if (!$it['paid']) { ?>
                                        <a href="<?php echo admin_url('dan_guard/mark_paid/' . $it['id'] . '/' . $device->id); ?>"
                                           class="btn btn-xs btn-success"><?php echo _l('dan_guard_mark_paid'); ?></a>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>

                        <?php echo form_open(admin_url('dan_guard/add_installment/' . $device->id), ['class' => 'form-inline mtop15']); ?>
                            <input type="number" step="0.01" name="amount" class="form-control input-sm" placeholder="<?php echo _l('dan_guard_amount'); ?>" required>
                            <input type="date" name="due_date" class="form-control input-sm" required>
                            <input type="text" name="note" class="form-control input-sm" placeholder="<?php echo _l('dan_guard_note'); ?>">
                            <button type="submit" class="btn btn-sm btn-default"><?php echo _l('dan_guard_add_installment'); ?></button>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>

            <!-- Journal -->
            <div class="col-md-4">
                <div class="panel_s">
                    <div class="panel-body">
                        <h5><?php echo _l('dan_guard_activity_log'); ?></h5>
                        <ul class="list-unstyled">
                        <?php foreach ($logs as $log) { ?>
                            <li class="mbot10">
                                <small class="text-muted"><?php echo _dt($log['created_at']); ?></small><br>
                                <strong><?php echo html_escape($log['event']); ?></strong>
                                <?php if ($log['data']) { ?><br><code class="text-muted"><?php echo html_escape($log['data']); ?></code><?php } ?>
                            </li>
                        <?php } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
