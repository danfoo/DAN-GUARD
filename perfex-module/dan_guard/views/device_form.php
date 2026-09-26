<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('dan_guard_new_device'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php echo form_open(admin_url('dan_guard/create')); ?>
                            <div class="form-group">
                                <label for="client_id"><?php echo _l('dan_guard_client'); ?></label>
                                <select name="client_id" id="client_id" class="form-control selectpicker" data-live-search="true">
                                    <option value="0">—</option>
                                    <?php foreach ($clients as $c) { ?>
                                        <option value="<?php echo $c['userid']; ?>"><?php echo html_escape($c['company']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <?php echo render_input('device_name', 'dan_guard_device'); ?>
                            <?php echo render_input('model', 'dan_guard_model'); ?>
                            <?php echo render_input('imei', 'dan_guard_imei'); ?>
                            <?php echo render_input('sale_price', 'dan_guard_sale_price', '', 'number'); ?>
                            <?php echo render_input('grace_days', 'dan_guard_grace_days', '', 'number'); ?>
                            <hr />
                            <p class="text-muted"><?php echo _l('dan_guard_installments'); ?> (optionnel — génère un échéancier mensuel)</p>
                            <?php echo render_input('installments_count', 'dan_guard_installments_count', '', 'number'); ?>
                            <?php echo render_date_input('first_due_date', 'dan_guard_first_due_date'); ?>
                            <div class="btn-bottom-toolbar text-right">
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
