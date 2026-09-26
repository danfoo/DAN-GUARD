<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('dan_guard_settings'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php echo form_open(admin_url('dan_guard/settings')); ?>
                            <?php echo render_input('dan_guard_fcm_server_key', 'dan_guard_fcm_server_key', get_option('dan_guard_fcm_server_key')); ?>
                            <?php echo render_input('dan_guard_default_grace_days', 'dan_guard_grace_days', get_option('dan_guard_default_grace_days'), 'number'); ?>
                            <?php echo render_input('dan_guard_checkin_interval_hours', 'dan_guard_checkin_interval_hours', get_option('dan_guard_checkin_interval_hours'), 'number'); ?>
                            <?php echo render_textarea('dan_guard_lock_message', 'dan_guard_lock_message', get_option('dan_guard_lock_message')); ?>
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                            </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
</body>
</html>
