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
                            <?php echo render_textarea('dan_guard_fcm_service_account', 'dan_guard_fcm_service_account', get_option('dan_guard_fcm_service_account'), ['rows' => 6]); ?>
                            <p class="text-muted"><small><?php echo _l('dan_guard_fcm_service_account_help'); ?></small></p>
                            <?php echo render_input('dan_guard_default_grace_days', 'dan_guard_grace_days', get_option('dan_guard_default_grace_days'), 'number'); ?>
                            <?php echo render_input('dan_guard_checkin_interval_hours', 'dan_guard_checkin_interval_hours', get_option('dan_guard_checkin_interval_hours'), 'number'); ?>
                            <?php echo render_input('dan_guard_max_offline_days', 'dan_guard_max_offline_days', get_option('dan_guard_max_offline_days'), 'number'); ?>
                            <p class="text-muted"><small><?php echo _l('dan_guard_max_offline_days_help'); ?></small></p>
                            <?php echo render_textarea('dan_guard_lock_message', 'dan_guard_lock_message', get_option('dan_guard_lock_message')); ?>
                            <hr />
                            <h5><?php echo _l('dan_guard_provisioning'); ?></h5>
                            <?php echo render_input('dan_guard_component_name', 'dan_guard_component_name', get_option('dan_guard_component_name')); ?>
                            <?php echo render_input('dan_guard_apk_url', 'dan_guard_apk_url', get_option('dan_guard_apk_url')); ?>
                            <?php echo render_input('dan_guard_apk_checksum', 'dan_guard_apk_checksum', get_option('dan_guard_apk_checksum')); ?>
                            <p class="text-muted"><small><?php echo _l('dan_guard_apk_checksum_help'); ?></small></p>
                            <hr />
                            <h5><?php echo _l('dan_guard_sms_notice'); ?></h5>
                            <?php echo render_yes_no_option('dan_guard_sms_enabled', 'dan_guard_sms_enabled'); ?>
                            <?php echo render_input('dan_guard_sms_endpoint', 'dan_guard_sms_endpoint', get_option('dan_guard_sms_endpoint')); ?>
                            <?php echo render_input('dan_guard_sms_account_id', 'dan_guard_sms_account_id', get_option('dan_guard_sms_account_id')); ?>
                            <?php echo render_input('dan_guard_sms_password', 'dan_guard_sms_password', get_option('dan_guard_sms_password'), 'password'); ?>
                            <?php echo render_input('dan_guard_sms_sender', 'dan_guard_sms_sender', get_option('dan_guard_sms_sender')); ?>
                            <?php echo render_input('dan_guard_sms_notice_days', 'dan_guard_sms_notice_days', get_option('dan_guard_sms_notice_days'), 'number'); ?>
                            <?php echo render_textarea('dan_guard_sms_message', 'dan_guard_sms_message', get_option('dan_guard_sms_message')); ?>
                            <p class="text-muted"><small><?php echo _l('dan_guard_sms_message_help'); ?></small></p>
                            <div class="text-right">
                                <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                            </div>
                        <?php echo form_close(); ?>

                        <hr />
                        <p class="text-muted"><small><?php echo _l('dan_guard_sms_test'); ?></small></p>
                        <?php echo form_open(admin_url('dan_guard/test_sms'), ['class' => 'form-inline']); ?>
                            <input type="text" name="to" class="form-control input-sm" placeholder="+221771234567">
                            <button type="submit" class="btn btn-sm btn-default"><?php echo _l('dan_guard_sms_test_send'); ?></button>
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
