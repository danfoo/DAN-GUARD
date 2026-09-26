<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-body text-center">
                        <h4 class="no-margin text-left"><?php echo _l('dan_guard_provisioning'); ?></h4>
                        <p class="text-muted text-left"><?php echo html_escape($device->device_name ?: ('#' . $device->id)); ?></p>
                        <hr class="hr-panel-heading" />

                        <?php if (!$configured) { ?>
                        <div class="alert alert-warning text-left">
                            <?php echo _l('dan_guard_provisioning_incomplete'); ?>
                            <a href="<?php echo admin_url('dan_guard/settings'); ?>"><?php echo _l('dan_guard_settings'); ?></a>
                        </div>
                        <?php } ?>

                        <div id="dg-qr" style="display:inline-block;background:#fff;padding:10px;"></div>
                        <div id="dg-qr-error" class="alert alert-danger text-left" style="display:none;">
                            <?php echo _l('dan_guard_qr_render_failed'); ?>
                        </div>

                        <p class="text-muted mtop15"><small><?php echo _l('dan_guard_provisioning_scan_hint'); ?></small></p>

                        <div class="mtop15">
                            <a href="<?php echo admin_url('dan_guard/provisioning_json/' . $device->id); ?>" class="btn btn-default btn-sm">
                                <i class="fa fa-download"></i> <?php echo _l('dan_guard_download_json'); ?>
                            </a>
                            <button type="button" id="dg-copy" class="btn btn-default btn-sm">
                                <i class="fa fa-copy"></i> <?php echo _l('dan_guard_copy_json'); ?>
                            </button>
                            <a href="<?php echo admin_url('dan_guard/regenerate_token/' . $device->id); ?>" class="btn btn-default btn-sm"
                               onclick="return confirm('<?php echo _l('dan_guard_regenerate_confirm'); ?>');">
                                <i class="fa fa-refresh"></i> <?php echo _l('dan_guard_regenerate_token'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-body">
                        <h5><?php echo _l('dan_guard_provisioning_steps'); ?></h5>
                        <ol class="text-muted">
                            <li><?php echo _l('dan_guard_step_reset'); ?></li>
                            <li><?php echo _l('dan_guard_step_tap'); ?></li>
                            <li><?php echo _l('dan_guard_step_scan'); ?></li>
                            <li><?php echo _l('dan_guard_step_auto'); ?></li>
                        </ol>
                        <hr />
                        <p><strong>JSON</strong></p>
                        <pre id="dg-json" style="white-space:pre-wrap;word-break:break-all;font-size:11px;"><?php echo html_escape($payload_pretty); ?></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>

<!-- Contenu du QR (JSON compact), transmis au générateur côté navigateur. -->
<script type="application/json" id="dg-payload"><?php echo $payload_json; ?></script>

<script>
(function () {
    function renderQr() {
        try {
            var raw = document.getElementById('dg-payload').textContent;
            var qr = qrcode(0, 'L');          // version auto, correction L (scan rapproché)
            qr.addData(raw);
            qr.make();
            document.getElementById('dg-qr').innerHTML = qr.createSvgTag({ cellSize: 5, margin: 4 });
        } catch (e) {
            document.getElementById('dg-qr-error').style.display = 'block';
        }
    }

    // Charge la bibliothèque : copie locale du module d'abord, puis CDN en repli.
    function loadScript(src, onload, onerror) {
        var s = document.createElement('script');
        s.src = src; s.onload = onload; s.onerror = onerror;
        document.head.appendChild(s);
    }

    var localSrc = '<?php echo module_dir_url(DAN_GUARD_MODULE_NAME, "assets/qrcode.js"); ?>';
    var cdnSrc   = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js';

    loadScript(localSrc, renderQr, function () {
        loadScript(cdnSrc, renderQr, function () {
            document.getElementById('dg-qr-error').style.display = 'block';
        });
    });

    // Copier le JSON.
    var copyBtn = document.getElementById('dg-copy');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            var text = document.getElementById('dg-json').textContent;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            } else {
                var r = document.createRange();
                r.selectNode(document.getElementById('dg-json'));
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(r);
                document.execCommand('copy');
            }
        });
    }
})();
</script>
</body>
</html>
