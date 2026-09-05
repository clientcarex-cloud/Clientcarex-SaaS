<?php defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Employee "Field Punch" screen (My HR). Captures location + a LIVE camera
 * selfie and posts a single punch. Photos can only be taken here and now —
 * there is no gallery/file upload, so a stale or borrowed photo cannot be
 * passed off as proof. Every rule shown here is re-checked server side in
 * Myhr::save_field_punch — the browser only collects the evidence.
 */
$next_label = $types[$next_type]['label'];
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <style>
            .fp-card{background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.03);padding:20px;margin-bottom:20px}
            .fp-punch-btn{display:block;width:100%;border:0;border-radius:14px;color:#fff;padding:22px 16px;font-size:20px;font-weight:800;letter-spacing:.4px;box-shadow:0 8px 24px rgba(0,0,0,.12)}
            .fp-punch-btn[disabled]{opacity:.55}
            .fp-in{background:linear-gradient(135deg,#16a34a,#15803d)}
            .fp-out{background:linear-gradient(135deg,#dc2626,#b91c1c)}
            .fp-stage{position:relative;background:#0f172a;border-radius:12px;overflow:hidden;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center}
            .fp-stage video,.fp-stage img{width:100%;height:100%;object-fit:cover;display:block}
            .fp-stage .fp-placeholder{color:#94a3b8;font-size:13px;text-align:center;padding:20px}
            .fp-meta{font-size:13px;color:#475569;line-height:1.9}
            .fp-meta i{width:18px;text-align:center;color:#94a3b8}
            .fp-pill{display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;color:#fff}
            .fp-tl{list-style:none;margin:0;padding:0}
            .fp-tl li{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px dashed #e2e8f0}
            .fp-tl li:last-child{border-bottom:0}
            .fp-dot{width:10px;height:10px;border-radius:50%;flex:0 0 10px}
            .fp-thumb{width:38px;height:38px;border-radius:8px;object-fit:cover;border:1px solid #e2e8f0}
            @media (max-width:767px){ .fp-card{padding:14px} }
        </style>

        <div class="row">
            <div class="col-md-7">
                <div class="fp-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                        <h4 style="margin:0;font-weight:800;"><i class="fa fa-location-dot text-info"></i> Field Punch</h4>
                        <a href="<?php echo admin_url('hr/myhr/attendance'); ?>" class="btn btn-default btn-sm"><i class="fa fa-calendar-check-o"></i> My Attendance</a>
                    </div>
                    <p class="text-muted" style="font-size:12px;margin:6px 0 0;">
                        Mark attendance while you are away from the office. Your location<?php echo $cfg['require_photo'] ? ' and photo' : ''; ?>
                        <?php echo $cfg['require_photo'] ? 'are' : 'is'; ?> recorded with the punch.
                        <?php echo $cfg['auto_approve'] ? '' : ' Each punch is reviewed by HR before it counts.'; ?>
                    </p>
                    <hr class="hr-panel-heading" />

                    <div id="fp_alert" class="alert alert-warning" style="display:none;"></div>

                    <?php echo form_open(admin_url('hr/myhr/save_field_punch'), ['id' => 'fp_form']); ?>
                        <input type="hidden" name="punch_type" id="fp_type" value="<?php echo html_escape($next_type); ?>">
                        <input type="hidden" name="latitude" id="fp_lat" value="">
                        <input type="hidden" name="longitude" id="fp_lng" value="">
                        <input type="hidden" name="accuracy_m" id="fp_acc" value="">
                        <input type="hidden" name="address" id="fp_address" value="">
                        <input type="hidden" name="photo_data" id="fp_photo_data" value="">

                        <!-- ------------------------------------------------- live camera -->
                        <label style="font-weight:600;">Live photo <?php echo $cfg['require_photo'] ? '<span class="text-danger">*</span>' : '<span class="text-muted" style="font-weight:400;">(optional)</span>'; ?></label>
                        <div class="fp-stage" id="fp_stage">
                            <video id="fp_video" playsinline muted autoplay style="display:none;"></video>
                            <img id="fp_preview" alt="Captured photo" style="display:none;">
                            <div class="fp-placeholder" id="fp_stage_text">
                                <i class="fa fa-camera fa-2x"></i><br>
                                Start the camera to take a selfie
                            </div>
                        </div>
                        <canvas id="fp_canvas" style="display:none;"></canvas>

                        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" class="btn btn-default" id="fp_cam_start"><i class="fa fa-video-camera"></i> Start camera</button>
                            <button type="button" class="btn btn-info" id="fp_cam_shot" style="display:none;"><i class="fa fa-camera"></i> Capture</button>
                            <button type="button" class="btn btn-default" id="fp_cam_retake" style="display:none;"><i class="fa fa-rotate-right"></i> Retake</button>
                            <button type="button" class="btn btn-default" id="fp_cam_flip" style="display:none;" title="Front / back camera"><i class="fa fa-camera-rotate"></i> Flip</button>
                        </div>
                        <p class="text-muted" style="font-size:11px;margin:6px 0 0;" id="fp_photo_hint">The photo must be taken here, now — saved or gallery images cannot be used.</p>

                        <!-- ------------------------------------------------------- location -->
                        <div style="margin-top:16px;">
                            <label style="font-weight:600;">Location <?php echo $cfg['require_location'] ? '<span class="text-danger">*</span>' : ''; ?></label>
                            <div class="fp-meta" id="fp_loc_box">
                                <div><i class="fa fa-spinner fa-spin"></i> <span id="fp_loc_text">Getting your location…</span></div>
                            </div>
                            <button type="button" class="btn btn-default btn-sm" id="fp_loc_retry" style="margin-top:6px;"><i class="fa fa-location-crosshairs"></i> Refresh location</button>
                        </div>

                        <!-- --------------------------------------------------- purpose/note -->
                        <div class="row" style="margin-top:16px;">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Purpose</label>
                                    <select name="purpose" class="form-control">
                                        <?php foreach ($purposes as $k => $lbl) { ?>
                                            <option value="<?php echo html_escape($k); ?>"><?php echo html_escape($lbl); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Note <span class="text-muted" style="font-weight:400;">(optional)</span></label>
                                    <input type="text" name="note" class="form-control" maxlength="255" placeholder="e.g. Patient home visit — Sector 21">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="fp-punch-btn <?php echo $next_type === 'in' ? 'fp-in' : 'fp-out'; ?>" id="fp_submit">
                            <i class="<?php echo $types[$next_type]['icon']; ?>" id="fp_submit_icon"></i>
                            <span id="fp_submit_label"><?php echo html_escape(strtoupper($next_label)); ?></span>
                            <div style="font-size:12px;font-weight:600;opacity:.85;margin-top:4px;" id="fp_clock"><?php echo date('D, d M Y · g:i A'); ?></div>
                        </button>

                        <p class="text-center text-muted" style="font-size:12px;margin-top:8px;">
                            <?php if ($last) { ?>
                                Last punch: <?php echo strtoupper($last['punch_type']); ?> on
                                <?php echo date('d M, g:i A', strtotime($last['punch_at'])); ?>.
                            <?php } ?>
                            <a href="#" id="fp_switch">Punch <span id="fp_switch_label"><?php echo $next_type === 'in' ? 'OUT' : 'IN'; ?></span> instead</a>
                            <span class="text-muted"> — for overnight or corrected shifts.</span>
                        </p>
                    <?php echo form_close(); ?>
                </div>
            </div>

            <div class="col-md-5">
                <!-- --------------------------------------------------------- today -->
                <div class="fp-card">
                    <h5 style="margin:0 0 10px;font-weight:800;"><i class="fa fa-clock-o text-info"></i> Today</h5>
                    <?php if (!count($today_punches)) { ?>
                        <p class="text-muted" style="font-size:13px;margin:0;">No field punch yet today.</p>
                    <?php } else { ?>
                        <ul class="fp-tl">
                            <?php foreach ($today_punches as $p) {
                                $t  = $types[$p['punch_type']] ?? $types['in'];
                                $st = $statuses[$p['status']] ?? $statuses['pending']; ?>
                                <li>
                                    <span class="fp-dot" style="background:<?php echo $t['color']; ?>;"></span>
                                    <span style="font-weight:700;"><?php echo date('g:i A', strtotime($p['punch_at'])); ?></span>
                                    <span><?php echo html_escape($t['label']); ?></span>
                                    <?php if (!empty($p['photo'])) { ?>
                                        <a href="<?php echo admin_url('hr/myhr/field_punch_photo/' . (int) $p['id']); ?>" target="_blank">
                                            <img class="fp-thumb" src="<?php echo admin_url('hr/myhr/field_punch_photo/' . (int) $p['id']); ?>" alt="">
                                        </a>
                                    <?php } ?>
                                    <span class="fp-pill" style="background:<?php echo $st['color']; ?>;margin-left:auto;"><?php echo html_escape($st['label']); ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>

                <!-- ------------------------------------------------------- history -->
                <div class="fp-card">
                    <h5 style="margin:0 0 10px;font-weight:800;"><i class="fa fa-history text-info"></i> Last 30 days</h5>
                    <?php if (!count($recent)) { ?>
                        <p class="text-muted" style="font-size:13px;margin:0;">Nothing recorded yet.</p>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-striped" style="font-size:13px;">
                                <thead><tr><th>When</th><th>Type</th><th>Where</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($recent as $p) {
                                    $t  = $types[$p['punch_type']] ?? $types['in'];
                                    $st = $statuses[$p['status']] ?? $statuses['pending']; ?>
                                    <tr>
                                        <td><?php echo date('d M, g:i A', strtotime($p['punch_at'])); ?></td>
                                        <td><span style="color:<?php echo $t['color']; ?>;font-weight:700;"><?php echo strtoupper($p['punch_type']); ?></span></td>
                                        <td>
                                            <?php if ($p['latitude'] !== null && $p['longitude'] !== null) { ?>
                                                <a href="<?php echo hr_field_map_url($p['latitude'], $p['longitude']); ?>" target="_blank" rel="noopener">
                                                    <?php echo $p['address'] ? html_escape(mb_substr($p['address'], 0, 40)) : 'View map'; ?>
                                                </a>
                                            <?php } else { ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php } ?>
                                        </td>
                                        <td><span class="fp-pill" style="background:<?php echo $st['color']; ?>;"><?php echo html_escape($st['label']); ?></span></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function () {
    var CFG = <?php echo json_encode([
        'requireLocation' => (bool) $cfg['require_location'],
        'requirePhoto'    => (bool) $cfg['require_photo'],
        'maxAccuracy'     => (int) $cfg['max_accuracy_m'],
        'geofence'        => $cfg['geofence_mode'],
        'geocode'         => (bool) $cfg['reverse_geocode'],
        'sites'           => array_map(function ($s) {
            return ['name' => $s['name'], 'lat' => (float) $s['latitude'], 'lng' => (float) $s['longitude'], 'radius' => (int) $s['radius_m']];
        }, $sites),
    ]); ?>;

    var el = document.getElementById.bind(document);
    var alertBox = el('fp_alert');
    var stream = null, facing = 'user', hasPhoto = false, located = false;

    function warn(msg, kind) {
        alertBox.className = 'alert alert-' + (kind || 'warning');
        alertBox.innerHTML = msg;
        alertBox.style.display = msg ? 'block' : 'none';
    }

    /* ------------------------------------------------------------- clock */
    setInterval(function () {
        var d = new Date();
        var el = el('fp_clock');
        if (el) {
            el.textContent = d.toLocaleDateString(undefined, { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' })
                + ' · ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        }
    }, 1000);

    /* ------------------------------------------------------------ camera */
    function stopCamera() {
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        var v = el('fp_video');
        if (v) { v.srcObject = null; v.style.display = 'none'; }
    }

    function startCamera() {
        var v = el('fp_video');
        if (!v) { return; }
        if (!window.isSecureContext) {
            warn('The camera only works on a secure (https) address. Open this site over https, or ask HR to record this punch for you.', 'danger');
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            warn('This browser cannot open the camera. Try Chrome or Safari on your phone.', 'danger');
            return;
        }
        stopCamera();
        navigator.mediaDevices.getUserMedia({ video: { facingMode: facing }, audio: false }).then(function (s) {
            stream = s;
            v.srcObject = s;
            v.style.display = 'block';
            v.play();
            el('fp_stage_text').style.display = 'none';
            el('fp_preview').style.display = 'none';
            el('fp_cam_start').style.display = 'none';
            el('fp_cam_shot').style.display = '';
            el('fp_cam_flip').style.display = '';
            el('fp_cam_retake').style.display = 'none';
            warn('');
        }).catch(function () {
            warn('Camera access was blocked. Allow the camera for this site in your browser settings and try again.', 'danger');
        });
    }

    function capture() {
        var v = el('fp_video'), c = el('fp_canvas');
        if (!v || !v.videoWidth) { return; }
        var w = Math.min(v.videoWidth, 960);
        var h = Math.round(v.videoHeight * (w / v.videoWidth));
        c.width = w; c.height = h;
        c.getContext('2d').drawImage(v, 0, 0, w, h);
        var data = c.toDataURL('image/jpeg', 0.8);
        el('fp_photo_data').value = data;
        var img = el('fp_preview');
        img.src = data;
        img.style.display = 'block';
        hasPhoto = true;
        stopCamera();
        el('fp_cam_shot').style.display = 'none';
        el('fp_cam_flip').style.display = 'none';
        el('fp_cam_retake').style.display = '';
        el('fp_photo_hint').textContent = 'Photo captured.';
    }

    if (el('fp_cam_start')) { el('fp_cam_start').addEventListener('click', startCamera); }
    if (el('fp_cam_shot'))  { el('fp_cam_shot').addEventListener('click', capture); }
    if (el('fp_cam_flip'))  {
        el('fp_cam_flip').addEventListener('click', function () {
            facing = (facing === 'user') ? 'environment' : 'user';
            startCamera();
        });
    }
    if (el('fp_cam_retake')) {
        el('fp_cam_retake').addEventListener('click', function () {
            el('fp_photo_data').value = '';
            hasPhoto = false;
            el('fp_preview').style.display = 'none';
            el('fp_photo_hint').textContent = '';
            startCamera();
        });
    }

    /* ---------------------------------------------------------- location */
    function distanceM(lat1, lng1, lat2, lng2) {
        var R = 6371000, rad = Math.PI / 180;
        var dLat = (lat2 - lat1) * rad, dLng = (lng2 - lng1) * rad;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return Math.round(R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
    }

    function nearestSite(lat, lng) {
        var best = null;
        (CFG.sites || []).forEach(function (s) {
            var d = distanceM(lat, lng, s.lat, s.lng);
            if (!best || d < best.d) { best = { s: s, d: d }; }
        });
        return best;
    }

    function renderLocation(pos) {
        var lat = pos.coords.latitude, lng = pos.coords.longitude;
        var acc = Math.round(pos.coords.accuracy || 0);
        located = true;
        el('fp_lat').value = lat.toFixed(7);
        el('fp_lng').value = lng.toFixed(7);
        el('fp_acc').value = acc;

        var html = '<div><i class="fa fa-map-marker"></i> <a href="https://www.google.com/maps/search/?api=1&query='
            + lat + ',' + lng + '" target="_blank" rel="noopener">' + lat.toFixed(5) + ', ' + lng.toFixed(5) + '</a></div>'
            + '<div><i class="fa fa-bullseye"></i> Accuracy ±' + acc + ' m'
            + (CFG.maxAccuracy > 0 && acc > CFG.maxAccuracy
                ? ' <span style="color:#dc2626;font-weight:700;">(above the allowed ' + CFG.maxAccuracy + ' m)</span>' : '')
            + '</div>';

        var n = nearestSite(lat, lng);
        if (n) {
            var inside = n.d <= n.s.radius;
            html += '<div><i class="fa fa-map-pin"></i> ' + n.d + ' m from <strong>' + n.s.name + '</strong> '
                + (inside
                    ? '<span class="fp-pill" style="background:#16a34a;">In range</span>'
                    : '<span class="fp-pill" style="background:' + (CFG.geofence === 'block' ? '#dc2626' : '#d97706') + ';">Out of range</span>')
                + '</div>';
            if (!inside && CFG.geofence === 'block') {
                warn('You are outside every approved work location, so this punch will be refused. Move closer and refresh your location.', 'danger');
            }
        }
        el('fp_loc_box').innerHTML = html;

        if (CFG.geocode) { reverseGeocode(lat, lng); }
    }

    function reverseGeocode(lat, lng) {
        // Best effort only: a failed / blocked lookup never stops a punch.
        try {
            var ctrl = new AbortController();
            setTimeout(function () { ctrl.abort(); }, 6000);
            fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lng, {
                signal: ctrl.signal, headers: { 'Accept': 'application/json' }
            }).then(function (r) { return r.json(); }).then(function (j) {
                if (j && j.display_name) {
                    el('fp_address').value = j.display_name.substring(0, 255);
                    var box = el('fp_loc_box');
                    box.insertAdjacentHTML('beforeend', '<div><i class="fa fa-road"></i> ' + j.display_name + '</div>');
                }
            }).catch(function () {});
        } catch (e) {}
    }

    function locate() {
        if (!navigator.geolocation) {
            el('fp_loc_box').innerHTML = '<span class="text-danger">This browser cannot share a location.</span>';
            return;
        }
        el('fp_loc_box').innerHTML = '<div><i class="fa fa-spinner fa-spin"></i> Getting your location…</div>';
        navigator.geolocation.getCurrentPosition(renderLocation, function (err) {
            located = false;
            var msg = err && err.code === 1
                ? 'Location permission denied. Allow location for this site and tap “Refresh location”.'
                : 'Could not read your location. Move to an open area and tap “Refresh location”.';
            el('fp_loc_box').innerHTML = '<span class="text-danger">' + msg + '</span>';
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    }

    el('fp_loc_retry').addEventListener('click', locate);
    locate();

    /* ----------------------------------------------------- punch type flip */
    // Both directions stay available: an employee finishing an overnight shift
    // punches OUT on a screen that defaults to IN.
    var switcher = el('fp_switch');
    if (switcher) {
        switcher.addEventListener('click', function (e) {
            e.preventDefault();
            var btn  = el('fp_submit');
            var next = el('fp_type').value === 'in' ? 'out' : 'in';
            el('fp_type').value = next;
            btn.classList.toggle('fp-in', next === 'in');
            btn.classList.toggle('fp-out', next === 'out');
            el('fp_submit_label').textContent = 'PUNCH ' + next.toUpperCase();
            el('fp_switch_label').textContent = next === 'in' ? 'OUT' : 'IN';
            el('fp_submit_icon').className = next === 'in' ? 'fa fa-right-to-bracket' : 'fa fa-right-from-bracket';
        });
    }

    /* ---------------------------------------------------------- submitting */
    el('fp_form').addEventListener('submit', function (e) {
        if (CFG.requireLocation && !located) {
            e.preventDefault();
            warn('Your location is required for a field punch. Tap “Refresh location” and allow access.', 'danger');
            return;
        }
        if (CFG.requirePhoto && !hasPhoto) {
            e.preventDefault();
            warn('Please capture a live photo before punching.', 'danger');
            return;
        }
        var btn = el('fp_submit');
        btn.disabled = true;
        el('fp_submit_label').textContent = 'Saving…';
    });

    window.addEventListener('beforeunload', stopCamera);
})();
</script>
