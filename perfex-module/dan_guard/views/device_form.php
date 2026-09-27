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
                            <?php if (empty($items)) { ?>
                                <div class="alert alert-warning"><?php echo _l('dan_guard_no_items'); ?></div>
                            <?php } ?>
                            <div class="form-group">
                                <label for="item_id"><span class="text-danger">* </span><?php echo _l('dan_guard_item'); ?></label>
                                <select name="item_id" id="item_id" class="form-control selectpicker" data-live-search="true" required
                                        data-none-selected-text="<?php echo _l('dan_guard_select_item'); ?>">
                                    <option value=""></option>
                                    <?php foreach ($items as $it) { ?>
                                        <option value="<?php echo (int) $it['id']; ?>"><?php echo html_escape($it['description']); ?></option>
                                    <?php } ?>
                                </select>
                                <p class="text-muted mtop5"><small><?php echo _l('dan_guard_item_help'); ?></small></p>
                            </div>

                            <!-- Aperçu (lecture seule) : valeurs reprises de l'article -->
                            <div class="row">
                                <div class="col-md-5">
                                    <?php echo render_input('preview_device', 'dan_guard_device', '', 'text', ['readonly' => true]); ?>
                                </div>
                                <div class="col-md-4">
                                    <?php echo render_input('preview_model', 'dan_guard_model', '', 'text', ['readonly' => true]); ?>
                                </div>
                                <div class="col-md-3">
                                    <?php echo render_input('preview_price', 'dan_guard_sale_price', '', 'text', ['readonly' => true]); ?>
                                </div>
                            </div>

                            <?php echo render_input('imei', 'dan_guard_imei', '', 'text', ['required' => true, 'maxlength' => 32, 'inputmode' => 'numeric', 'autocomplete' => 'off']); ?>
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
<script type="application/json" id="dg-items"><?php
    $map = [];
    foreach ($items as $it) {
        $map[(int) $it['id']] = [
            'name'  => $it['description'],
            'model' => $it['model'],
            'price' => $it['price_formatted'],
        ];
    }
    echo json_encode($map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?></script>
<script>
$(function () {
    var items = JSON.parse(document.getElementById('dg-items').textContent || '{}');
    // Remplit l'aperçu à partir de l'article choisi. Les valeurs réelles sont relues
    // côté serveur à l'enregistrement.
    $('#item_id').on('change', function () {
        var it = items[$(this).val()] || {};
        $('#preview_device').val(it.name || '');
        $('#preview_model').val(it.model || '—');
        $('#preview_price').val(it.price || '');
    });
});
</script>
</body>
</html>
