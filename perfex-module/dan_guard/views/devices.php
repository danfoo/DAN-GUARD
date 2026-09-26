<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="_buttons">
                            <a href="<?php echo admin_url('dan_guard/create'); ?>" class="btn btn-primary pull-left">
                                <?php echo _l('dan_guard_new_device'); ?>
                            </a>
                            <?php if (is_admin()) { ?>
                            <a href="<?php echo admin_url('dan_guard/settings'); ?>" class="btn btn-default pull-right">
                                <i class="fa fa-cog"></i> <?php echo _l('dan_guard_settings'); ?>
                            </a>
                            <?php } ?>
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
                                    <td><?php echo $d['last_checkin'] ? _dt($d['last_checkin']) : '-'; ?></td>
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
