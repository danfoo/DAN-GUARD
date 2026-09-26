<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <ul class="nav nav-tabs mbot20" role="tablist">
                    <li role="presentation">
                        <a href="<?php echo admin_url('dan_guard'); ?>"><?php echo _l('dan_guard_dashboard'); ?></a>
                    </li>
                    <li role="presentation" class="active">
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
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="_buttons">
                            <a href="<?php echo admin_url('dan_guard/create'); ?>" class="btn btn-primary pull-left">
                                <?php echo _l('dan_guard_new_device'); ?>
                            </a>
                        </div>
                        <hr class="hr-panel-heading" />
                        <div class="clearfix"></div>
                        <table class="table dt-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th><?php echo _l('dan_guard_device'); ?></th>
                                    <th><?php echo _l('dan_guard_model'); ?></th>
                                    <th><?php echo _l('dan_guard_imei'); ?></th>
                                    <th><?php echo _l('dan_guard_status'); ?></th>
                                    <th><?php echo _l('dan_guard_last_checkin'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($devices as $d) {
                                $labels = [
                                    'pending'  => 'label-default',
                                    'active'   => 'label-success',
                                    'locked'   => 'label-danger',
                                    'released' => 'label-info',
                                ];
                                $cls = $labels[$d['status']] ?? 'label-default';
                            ?>
                                <tr>
                                    <td><?php echo $d['id']; ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('dan_guard/device/' . $d['id']); ?>">
                                            <?php echo html_escape($d['device_name'] ?: ('#' . $d['id'])); ?>
                                        </a>
                                    </td>
                                    <td><?php echo html_escape($d['model']); ?></td>
                                    <td><?php echo html_escape($d['imei']); ?></td>
                                    <td><span class="label <?php echo $cls; ?>"><?php echo _l('dan_guard_status_' . $d['status']); ?></span></td>
                                    <td>
                                        <?php echo $d['last_checkin'] ? _dt($d['last_checkin']) : '-'; ?>
                                        <?php
                                        $max_off = (int) get_option('dan_guard_max_offline_days');
                                        if ($max_off > 0 && !empty($d['last_checkin'])
                                            && in_array($d['status'], ['active', 'locked'], true)
                                            && strtotime($d['last_checkin']) < strtotime('-' . $max_off . ' days')) { ?>
                                            <span class="label label-warning"><?php echo _l('dan_guard_offline'); ?></span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
