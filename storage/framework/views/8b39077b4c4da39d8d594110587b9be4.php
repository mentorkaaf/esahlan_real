<?php $__env->startSection('title', 'Settings'); ?>
<?php $__env->startSection('content'); ?>

<?php
    $flat = $settings->flatten()->keyBy('key');
    $tz   = $flat->get('timezone')?->value ?? 'Africa/Nairobi';
    $allTimezones = DateTimeZone::listIdentifiers();
    $tzGroups = [];
    foreach ($allTimezones as $tzId) {
        $parts = explode('/', $tzId, 2);
        $tzGroups[$parts[0]][] = $tzId;
    }
    $mapsApiKey      = $flat->get('google_maps_api_key')?->value      ?? '';
    $mapsLat         = $flat->get('google_maps_default_lat')?->value   ?? '2.0469';
    $mapsLng         = $flat->get('google_maps_default_lng')?->value   ?? '45.3182';
    $mapsZoom        = $flat->get('google_maps_default_zoom')?->value  ?? '13';
    $fbProjectId     = $flat->get('firebase_project_id')?->value       ?? '';
    $fbApiKey        = $flat->get('firebase_api_key')?->value          ?? '';
    $fbAuthDomain    = $flat->get('firebase_auth_domain')?->value      ?? '';
    $fbStorageBucket = $flat->get('firebase_storage_bucket')?->value   ?? '';
    $fbSenderId      = $flat->get('firebase_sender_id')?->value        ?? '';
    $fbAppIdWeb      = $flat->get('firebase_app_id_web')?->value       ?? '';
    $fcmEnabled      = $flat->get('fcm_enabled')?->value               ?? '1';
    $fcmOrders       = $flat->get('fcm_order_notifications')?->value   ?? '1';
    $fbJsonStored    = $flat->get('firebase_service_account_json')?->value ?? '';
?>

<style>
:root{--pri:#140465;--pri-l:rgba(20,4,101,.08);}
.sp-wrap{display:flex;gap:0;min-height:calc(100vh - 140px);}
.sp-sb{width:210px;flex-shrink:0;background:#fff;border:1px solid #e8ecf2;border-radius:14px;padding:10px 8px;display:flex;flex-direction:column;gap:2px;align-self:flex-start;position:sticky;top:20px;}
.sn{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;cursor:pointer;font-size:13px;font-weight:600;color:#64748b;border:none;background:none;width:100%;text-align:left;transition:all .15s;}
.sn:hover{background:#f4f5f8;color:#1a1d2e;}
.sn.active{background:var(--pri-l);color:var(--pri);}
.sn i{width:18px;text-align:center;font-size:13px;}
.sn-dot{margin-left:auto;width:7px;height:7px;border-radius:50%;}
.sn-div{height:1px;background:#f0f2f6;margin:5px 4px;}
.sp-ct{flex:1;min-width:0;padding-left:20px;}
.sp-pn{display:none;}
.sp-pn.active{display:block;}
.ph{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid #f0f2f6;}
.ph-t{display:flex;align-items:center;gap:12px;}
.ph-ic{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:17px;}
.ph-t h2{margin:0;font-size:16px;font-weight:800;color:#1a1d2e;}
.ph-t p{margin:2px 0 0;font-size:12px;color:#94a3b8;}
.sc{background:#fff;border:1px solid #e8ecf2;border-radius:12px;overflow:hidden;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,.04);}
.sc-h{padding:10px 16px;background:#fafbfd;border-bottom:1px solid #f0f2f6;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;}
.sr{display:grid;grid-template-columns:190px 1fr;gap:14px;align-items:start;padding:13px 16px;border-bottom:1px solid #f4f5f8;}
.sr:last-child{border-bottom:none;}
.sl{font-size:13px;font-weight:600;color:#2d3748;}
.sh{font-size:11px;color:#94a3b8;margin-top:2px;line-height:1.4;}
.si{width:100%;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;color:#1a1d2e;background:#fff;transition:border-color .2s,box-shadow .2s;box-sizing:border-box;}
.si:focus{outline:none;border-color:var(--pri);box-shadow:0 0 0 3px var(--pri-l);}
select.si{cursor:pointer;}
.tw{display:flex;align-items:center;gap:10px;}
.ts{position:relative;display:inline-block;width:40px;height:22px;}
.ts input{opacity:0;width:0;height:0;}
.tsl{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:22px;transition:.2s;}
.tsl:before{content:'';position:absolute;width:16px;height:16px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;}
.ts input:checked+.tsl{background:var(--pri);}
.ts input:checked+.tsl:before{transform:translateX(18px);}
.tw-l{font-size:12px;color:#64748b;}
.pw{position:relative;}
.pw .si{padding-right:40px;}
.pb{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;padding:3px;}
.pb:hover{color:var(--pri);}
.sg3{display:grid;grid-template-columns:1fr 1fr 100px;gap:9px;}
.badge{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:6px;font-size:11px;font-weight:700;}
.bok{background:rgba(16,185,129,.1);color:#059669;}
.bwn{background:rgba(245,158,11,.1);color:#d97706;}
.ber{background:rgba(239,68,68,.1);color:#dc2626;}
.bif{background:var(--pri-l);color:var(--pri);}
.ib{border-radius:9px;padding:12px 15px;font-size:12px;margin-top:4px;}
.ib.bl{background:#f0f7ff;border:1px solid #bfdbfe;color:#1e40af;}
.ib.am{background:#fffbeb;border:1px solid #fcd34d;color:#78350f;}
.ib h4{margin:0 0 6px;font-size:12px;font-weight:700;}
.ib ol{margin:0;padding-left:15px;line-height:1.8;}
.jt{width:100%;min-height:150px;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:12px;font-family:'Courier New',monospace;background:#f8fafc;resize:vertical;box-sizing:border-box;transition:border-color .2s;}
.jt:focus{outline:none;border-color:var(--pri);background:#fff;}
.bsave{display:inline-flex;align-items:center;gap:7px;padding:9px 20px;border-radius:9px;font-size:13px;font-weight:700;cursor:pointer;border:none;background:var(--pri);color:#fff;transition:opacity .2s;}
.bsave:hover{opacity:.87;}
.btest{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid;transition:all .2s;}
.bm{background:rgba(66,133,244,.08);color:#4285F4;border-color:#4285F4;}
.bm:hover{background:rgba(66,133,244,.15);}
.ba{background:rgba(255,160,0,.08);color:#FFA000;border-color:#FFA000;}
.ba:hover{background:rgba(255,160,0,.15);}
.sa-ok{display:flex;align-items:center;gap:10px;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.3);border-radius:10px;padding:11px 15px;margin-bottom:16px;font-size:13px;color:#065f46;}
.lp{height:38px;border-radius:7px;border:1px solid #e2e8f0;padding:3px;background:#f8fafc;margin-top:7px;}
#mapPreview{width:100%;height:170px;border-radius:9px;border:1px solid #e2e8f0;overflow:hidden;margin-top:9px;display:none;}
@media(max-width:768px){.sp-wrap{flex-direction:column;}.sp-sb{width:100%;position:static;flex-direction:row;flex-wrap:wrap;padding:6px;}.sp-ct{padding-left:0;padding-top:14px;}.sr{grid-template-columns:1fr;gap:5px;}.sg3{grid-template-columns:1fr 1fr;}}
</style>

<form method="POST" action="<?php echo e(route('admin.settings.update')); ?>" enctype="multipart/form-data" id="sForm">
<?php echo csrf_field(); ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
    <div>
        <h1 style="margin:0;font-size:19px;font-weight:800;color:#1a1d2e;">⚙️ Settings</h1>
        <p style="margin:2px 0 0;font-size:12px;color:#94a3b8;">Configure your application and services</p>
    </div>
    <button type="submit" class="bsave"><i class="fas fa-save"></i> Save All Settings</button>
</div>

<?php if(session('success')): ?>
<div class="sa-ok"><i class="fas fa-check-circle" style="font-size:15px;color:#10b981;"></i> <?php echo e(session('success')); ?></div>
<?php endif; ?>

<div class="sp-wrap">
<div class="sp-sb">
    <button type="button" class="sn active" data-tab="general"><i class="fas fa-mobile-alt"></i> App Identity</button>
    <button type="button" class="sn" data-tab="system"><i class="fas fa-globe"></i> System</button>
    <?php if($settings->get('commission', collect())->isNotEmpty()): ?>
    <button type="button" class="sn" data-tab="commission"><i class="fas fa-percentage"></i> Commission</button>
    <?php endif; ?>
    <?php if($settings->get('delivery', collect())->isNotEmpty()): ?>
    <button type="button" class="sn" data-tab="delivery"><i class="fas fa-motorcycle"></i> Delivery</button>
    <?php endif; ?>
    <?php if($settings->get('payment', collect())->isNotEmpty()): ?>
    <button type="button" class="sn" data-tab="payment"><i class="fas fa-credit-card"></i> Payment</button>
    <?php endif; ?>
    <?php $loyaltyGroup = $settings->get('loyalty', collect())->merge($settings->get('referral', collect())); ?>
    <?php if($loyaltyGroup->isNotEmpty()): ?>
    <button type="button" class="sn" data-tab="loyalty"><i class="fas fa-gift"></i> Loyalty</button>
    <?php endif; ?>
    <div class="sn-div"></div>
    <button type="button" class="sn" data-tab="maps"><i class="fas fa-map-marked-alt"></i> Google Maps <?php if(!$mapsApiKey): ?><span class="sn-dot" style="background:#f59e0b;"></span><?php endif; ?></button>
    <button type="button" class="sn" data-tab="firebase"><i class="fas fa-bell"></i> Firebase <?php if(!$fbProjectId): ?><span class="sn-dot" style="background:#ef4444;"></span><?php endif; ?></button>
    <?php $knownGroups=['general','commission','delivery','payment','loyalty','referral','maps','firebase'];$otherSettings=$settings->filter(fn($g,$k)=>!in_array($k,$knownGroups))->flatten(); ?>
    <?php if($otherSettings->isNotEmpty()): ?>
    <button type="button" class="sn" data-tab="other"><i class="fas fa-cog"></i> Other</button>
    <?php endif; ?>
</div>

<div class="sp-ct">


<div class="sp-pn active" id="tab-general">
    <div class="ph"><div class="ph-t"><div class="ph-ic" style="background:var(--pri-l);color:var(--pri);"><i class="fas fa-mobile-alt"></i></div><div><h2>App Identity</h2><p>Name, logo, currency and contact info</p></div></div></div>
    <div class="sc"><div class="sp-card-body">
    <?php $__currentLoopData = $settings->get('general', collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php if($s->key === 'timezone'): ?> <?php continue; ?> <?php endif; ?>
    <?php $label=ucwords(str_replace('_',' ',str_replace('app_','',$s->key)));$isFile=str_contains($s->key,'logo')||str_contains($s->key,'image');?>
    <div class="sr">
        <div><div class="sl"><?php echo e($label); ?></div><div class="sh"><?php echo e($s->key); ?></div></div>
        <div><?php if($isFile): ?><input type="file" name="<?php echo e($s->key); ?>" class="si" accept="image/*"><?php if($s->value): ?><img src="<?php echo e(asset('storage/'.$s->value)); ?>" class="lp"><?php endif; ?>@else<input type="text" name="<?php echo e($s->key); ?>" class="si" value="<?php echo e($s->value); ?>"><?php endif; ?></div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div></div>
</div>


<div class="sp-pn" id="tab-system">
    <div class="ph"><div class="ph-t"><div class="ph-ic" style="background:var(--pri-l);color:var(--pri);"><i class="fas fa-globe"></i></div><div><h2>System & Timezone</h2><p>Controls open/close schedules and time comparisons</p></div></div></div>
    <div class="sc"><div class="sp-card-body">
    <div class="sr">
        <div><div class="sl">Application Timezone</div><div class="sh">Used for all date/time operations</div></div>
        <div>
            <select name="timezone" class="si" id="tzSelect">
                <?php $__currentLoopData = $tzGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $continent => $tzList): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><optgroup label="<?php echo e($continent); ?>"><?php $__currentLoopData = $tzList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tzId): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($tzId); ?>" <?php echo e($tzId===$tz?'selected':''); ?>><?php echo e(str_replace('_',' ',$tzId)); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></optgroup><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <div class="badge bif" style="margin-top:7px;" id="tzNow"><i class="fas fa-clock"></i> <?php echo e($tz); ?>: <?php echo e(\Carbon\Carbon::now($tz)->format('D, d M Y  H:i')); ?></div>
        </div>
    </div>
    </div></div>
</div>


<?php if($settings->get('commission', collect())->isNotEmpty()): ?>
<div class="sp-pn" id="tab-commission">
    <div class="ph"><div class="ph-t"><div class="ph-ic" style="background:rgba(16,185,129,.1);color:#10b981;"><i class="fas fa-percentage"></i></div><div><h2>Commission Settings</h2><p>Platform commission rates</p></div></div></div>
    <div class="sc"><div class="sp-card-body">
    <?php $__currentLoopData = $settings->get('commission', collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="sr"><div><div class="sl"><?php echo e(ucwords(str_replace('_',' ',$s->key))); ?></div><div class="sh"><?php echo e($s->key); ?></div></div><div><input type="text" name="<?php echo e($s->key); ?>" class="si" value="<?php echo e($s->value); ?>"></div></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div></div>
</div>
<?php endif; ?>


<?php if($settings->get('delivery', collect())->isNotEmpty()): ?>
<div class="sp-pn" id="tab-delivery">
    <div class="ph"><div class="ph-t"><div class="ph-ic" style="background:rgba(20,184,166,.1);color:#14b8a6;"><i class="fas fa-motorcycle"></i></div><div><h2>Delivery Settings</h2><p>Default fees and delivery radius</p></div></div></div>
    <div class="sc"><div class="sp-card-body">
    <?php $__currentLoopData = $settings->get('delivery', collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="sr"><div><div class="sl"><?php echo e(ucwords(str_replace('_',' ',$s->key))); ?></div><div class="sh"><?php echo e($s->key); ?></div></div><div><input type="text" name="<?php echo e($s->key); ?>" class="si" value="<?php echo e($s->value); ?>"></div></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div></div>
</div>
<?php endif; ?>


<?php if($settings->get('payment', collect())->isNotEmpty()): ?>
<div class="sp-pn" id="tab-payment">
    <div class="ph"><div class="ph-t"><div class="ph-ic" style="background:rgba(239,68,68,.1);color:#ef4444;"><i class="fas fa-credit-card"></i></div><div><h2>Payment Settings</h2><p>API keys and gateway configuration</p></div></div></div>
    <div class="sc"><div class="sp-card-body">
    <?php $__currentLoopData = $settings->get('payment', collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php $isp=str_contains($s->key,'key')||str_contains($s->key,'secret')||str_contains($s->key,'password'); ?>
    <div class="sr">
        <div><div class="sl"><?php echo e(ucwords(str_replace('_',' ',$s->key))); ?></div><div class="sh"><?php echo e($s->key); ?></div></div>
        <div><?php if($isp): ?><div class="pw"><input type="password" name="<?php echo e($s->key); ?>" class="si" value="<?php echo e($s->value); ?>" id="pp_<?php echo e($loop->index); ?>"><button type="button" class="pb" onclick="tp('pp_<?php echo e($loop->index); ?>',this)"><i class="fas fa-eye"></i></button></div><?php else: ?><input type="text" name="<?php echo e($s->key); ?>" class="si" value="<?php echo e($s->value); ?>"><?php endif; ?></div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div></div>
</div>
<?php endif; ?>


<?php if($loyaltyGroup->isNotEmpty()): ?>
<div class="sp-pn" id="tab-loyalty">
    <div class="ph"><div class="ph-t"><div class="ph-ic" style="background:rgba(245,158,11,.1);color:#f59e0b;"><i class="fas fa-gift"></i></div><div><h2>Loyalty & Referral</h2><p>Points, rewards and referral bonuses</p></div></div></div>
    <div class="sc"><div class="sp-card-body">
    <?php $__currentLoopData = $loyaltyGroup; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="sr"><div><div class="sl"><?php echo e(ucwords(str_replace('_',' ',$s->key))); ?></div><div class="sh"><?php echo e($s->key); ?></div></div><div><input type="text" name="<?php echo e($s->key); ?>" class="si" value="<?php echo e($s->value); ?>"></div></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div></div>
</div>
<?php endif; ?>


<div class="sp-pn" id="tab-maps">
    <div class="ph">
        <div class="ph-t"><div class="ph-ic" style="background:rgba(66,133,244,.1);color:#4285F4;"><i class="fas fa-map-marked-alt"></i></div><div><h2>Google Maps</h2><p>API key and default map center</p></div></div>
        <?php if($mapsApiKey): ?><span class="badge bok"><i class="fas fa-check-circle"></i> Key Set</span><?php else: ?><span class="badge bwn"><i class="fas fa-exclamation-circle"></i> Key Missing</span><?php endif; ?>
    </div>
    <div class="sc"><div class="sc-h">API Key</div><div class="sp-card-body">
    <div class="sr">
        <div><div class="sl">Maps API Key</div><div class="sh">Enable Maps SDK + Places + Geocoding APIs</div></div>
        <div>
            <div class="pw"><input type="password" name="google_maps_api_key" id="mapsApiKey" class="si" value="<?php echo e($mapsApiKey); ?>" placeholder="AIzaSy..."><button type="button" class="pb" onclick="tp('mapsApiKey',this)"><i class="fas fa-eye"></i></button></div>
            <div style="display:flex;align-items:center;gap:10px;margin-top:9px;flex-wrap:wrap;">
                <button type="button" class="btest bm" onclick="testMapsKey()"><i class="fas fa-map"></i> Test Map</button>
                <a href="https://console.cloud.google.com/google/maps-apis/credentials" target="_blank" style="font-size:12px;color:#4285F4;"><i class="fas fa-external-link-alt"></i> Get API Key</a>
            </div>
            <div id="mapPreview"></div><div id="mapsTestResult" style="margin-top:7px;font-size:13px;display:none;"></div>
        </div>
    </div>
    </div></div>
    <div class="sc"><div class="sc-h">Default Map Center</div><div class="sp-card-body">
    <div class="sr">
        <div><div class="sl">Coordinates & Zoom</div><div class="sh">Shown when user location is unavailable</div></div>
        <div>
            <div class="sg3">
                <div><label style="font-size:11px;color:#94a3b8;display:block;margin-bottom:3px;">Latitude</label><input type="text" name="google_maps_default_lat" class="si" value="<?php echo e($mapsLat); ?>" placeholder="2.0469"></div>
                <div><label style="font-size:11px;color:#94a3b8;display:block;margin-bottom:3px;">Longitude</label><input type="text" name="google_maps_default_lng" class="si" value="<?php echo e($mapsLng); ?>" placeholder="45.3182"></div>
                <div><label style="font-size:11px;color:#94a3b8;display:block;margin-bottom:3px;">Zoom</label><input type="number" name="google_maps_default_zoom" class="si" value="<?php echo e($mapsZoom); ?>" min="1" max="20"></div>
            </div>
            <a href="https://www.latlong.net/" target="_blank" style="font-size:12px;color:#4285F4;display:inline-flex;align-items:center;gap:4px;margin-top:7px;"><i class="fas fa-crosshairs"></i> Find coordinates</a>
        </div>
    </div>
    </div></div>
    <div class="ib bl"><h4><i class="fas fa-info-circle"></i> How to get Google Maps API Key</h4><ol><li>Go to <a href="https://console.cloud.google.com" target="_blank">console.cloud.google.com</a></li><li>APIs &amp; Services → Library → Enable Maps SDK (Android/iOS), Geocoding, Places API</li><li>APIs &amp; Services → Credentials → Create API Key → paste above</li></ol></div>
</div>


<div class="sp-pn" id="tab-firebase">
    <div class="ph">
        <div class="ph-t"><div class="ph-ic" style="background:rgba(255,160,0,.1);color:#FFA000;"><i class="fas fa-bell"></i></div><div><h2>Firebase & Push</h2><p>FCM push notifications for the customer app</p></div></div>
        <?php if($firebaseFileExists && $fbProjectId): ?><span class="badge bok"><i class="fas fa-check-circle"></i> Configured</span><?php elseif($fbProjectId || $firebaseFileExists): ?><span class="badge bwn"><i class="fas fa-exclamation-circle"></i> Incomplete</span><?php else: ?><span class="badge ber"><i class="fas fa-times-circle"></i> Not Set</span><?php endif; ?>
    </div>
    <div class="sc"><div class="sc-h">Toggles</div><div class="sp-card-body">
    <div class="sr"><div><div class="sl">Enable Push Notifications</div><div class="sh">Send FCM push to customer devices</div></div><div class="tw"><label class="ts"><input type="hidden" name="fcm_enabled" value="0"><input type="checkbox" name="fcm_enabled" value="1" <?php echo e($fcmEnabled=='1'?'checked':''); ?>><span class="tsl"></span></label><span class="tw-l">Active on order status change</span></div></div>
    <div class="sr"><div><div class="sl">Order Status Notifications</div><div class="sh">Notify at every order step</div></div><div class="tw"><label class="ts"><input type="hidden" name="fcm_order_notifications" value="0"><input type="checkbox" name="fcm_order_notifications" value="1" <?php echo e($fcmOrders=='1'?'checked':''); ?>><span class="tsl"></span></label><span class="tw-l">Enabled for all modules</span></div></div>
    </div></div>
    <div class="sc"><div class="sc-h">Project Config</div><div class="sp-card-body">
    <div class="sr"><div><div class="sl">Project ID</div><div class="sh">Project Settings → General</div></div><div><input type="text" name="firebase_project_id" class="si" value="<?php echo e($fbProjectId); ?>" placeholder="esahlan-xxxxx"><?php if($fbProjectId): ?><div class="badge bif" style="margin-top:6px;"><i class="fas fa-fire"></i> <?php echo e($fbProjectId); ?></div><?php endif; ?></div></div>
    <div class="sr"><div><div class="sl">API Key</div><div class="sh">Web API key</div></div><div class="pw"><input type="password" name="firebase_api_key" id="fbApiKey" class="si" value="<?php echo e($fbApiKey); ?>" placeholder="AIzaSy..."><button type="button" class="pb" onclick="tp('fbApiKey',this)"><i class="fas fa-eye"></i></button></div></div>
    <div class="sr"><div><div class="sl">Sender ID</div><div class="sh">Cloud Messaging → Sender ID</div></div><div><input type="text" name="firebase_sender_id" class="si" value="<?php echo e($fbSenderId); ?>" placeholder="30724696826"></div></div>
    <div class="sr"><div><div class="sl">Web App ID</div><div class="sh">Project Settings → General → Web app</div></div><div><input type="text" name="firebase_app_id_web" class="si" value="<?php echo e($fbAppIdWeb); ?>" placeholder="1:XXXXX:web:XXXXX"></div></div>
    <div class="sr"><div><div class="sl">Auth Domain</div><div class="sh">project-id.firebaseapp.com</div></div><div><input type="text" name="firebase_auth_domain" class="si" value="<?php echo e($fbAuthDomain); ?>" placeholder="esahlan.firebaseapp.com"></div></div>
    <div class="sr"><div><div class="sl">Storage Bucket</div><div class="sh">project-id.firebasestorage.app</div></div><div><input type="text" name="firebase_storage_bucket" class="si" value="<?php echo e($fbStorageBucket); ?>" placeholder="esahlan.firebasestorage.app"></div></div>
    </div></div>
    <div class="sc"><div class="sc-h">Service Account JSON</div><div class="sp-card-body">
    <div class="sr">
        <div><div class="sl">JSON Content</div><div class="sh">Project Settings → Service Accounts → Generate new private key</div></div>
        <div>
            <?php if($firebaseFileExists): ?><div class="badge bok" style="margin-bottom:7px;"><i class="fas fa-check-circle"></i> File saved on server</div><br><?php endif; ?>
            <textarea name="firebase_service_account_json" class="jt" placeholder='{"type":"service_account","project_id":"..."}'><?php echo e($fbJsonStored); ?></textarea>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:7px;flex-wrap:wrap;gap:7px;">
                <span style="font-size:11px;color:#94a3b8;"><i class="fas fa-shield-alt"></i> Stored securely, never exposed via API</span>
                <button type="button" class="btest ba" onclick="validateJson()"><i class="fas fa-check-double"></i> Validate JSON</button>
            </div>
            <div id="jsonValidResult" style="margin-top:7px;font-size:13px;display:none;"></div>
        </div>
    </div>
    </div></div>
    <div class="sc"><div class="sc-h">Test Notification</div><div class="sp-card-body">
    <div class="sr">
        <div><div class="sl">Send Test Push</div><div class="sh">Verify FCM with a device token</div></div>
        <div>
            <div style="display:flex;gap:8px;"><input type="text" id="testFcmToken" class="si" placeholder="Paste FCM device token…" style="flex:1;"><button type="button" class="btest ba" onclick="sendTest()"><i class="fas fa-paper-plane"></i> Send</button></div>
            <div id="fcmTestResult" style="margin-top:7px;font-size:13px;display:none;"></div>
        </div>
    </div>
    </div></div>
    <div class="ib am"><h4><i class="fas fa-fire-alt"></i> How to setup Firebase</h4><ol><li>Go to <a href="https://console.firebase.google.com" target="_blank">console.firebase.google.com</a> → Create/Select project</li><li>Project Settings → General → copy Project ID → paste above</li><li>Project Settings → Service Accounts → Generate new private key → paste JSON above</li><li>Add Android app with package: <code style="background:#fef3c7;padding:1px 3px;border-radius:3px;">com.esahlan.esahlan_customer</code></li><li>Download google-services.json → place in <code style="background:#fef3c7;padding:1px 3px;border-radius:3px;">customer_app/android/app/</code></li><li>Save settings → Send Test to verify</li></ol></div>
</div>


<?php if($otherSettings->isNotEmpty()): ?>
<div class="sp-pn" id="tab-other">
    <div class="ph"><div class="ph-t"><div class="ph-ic" style="background:#f4f5f8;color:#64748b;"><i class="fas fa-cog"></i></div><div><h2>Other Settings</h2><p>Additional configuration</p></div></div></div>
    <div class="sc"><div class="sp-card-body">
    <?php $__currentLoopData = $otherSettings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if($s->key!=='timezone'): ?>
    <div class="sr"><div><div class="sl"><?php echo e(ucwords(str_replace('_',' ',$s->key))); ?></div><div class="sh"><?php echo e($s->key); ?></div></div><div><input type="text" name="<?php echo e($s->key); ?>" class="si" value="<?php echo e($s->value); ?>"></div></div>
    <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div></div>
</div>
<?php endif; ?>

</div></div>

<div style="display:flex;justify-content:flex-end;margin-top:14px;margin-bottom:30px;">
    <button type="submit" class="bsave"><i class="fas fa-save"></i> Save All Settings</button>
</div>
</form>

<script>
document.querySelectorAll('.sn').forEach(b=>{
    b.addEventListener('click',()=>{
        const t=b.dataset.tab;
        document.querySelectorAll('.sn').forEach(x=>x.classList.remove('active'));
        document.querySelectorAll('.sp-pn').forEach(x=>x.classList.remove('active'));
        b.classList.add('active');
        document.getElementById('tab-'+t)?.classList.add('active');
        history.replaceState(null,'','#'+t);
    });
});
const h=window.location.hash.replace('#','');
if(h){const b=document.querySelector('[data-tab="'+h+'"]');if(b)b.click();}

function tp(id,btn){const i=document.getElementById(id);const ic=btn.querySelector('i');i.type=i.type==='password'?'text':'password';ic.classList.toggle('fa-eye');ic.classList.toggle('fa-eye-slash');}
function showResult(id,ok,msg){const el=document.getElementById(id);el.style.display='block';el.innerHTML=`<span class="badge ${ok?'bok':'ber'}" style="padding:6px 11px;"><i class="fas fa-${ok?'check-circle':'times-circle'}"></i> ${msg}</span>`;}
function toast(m){const t=document.createElement('div');t.textContent=m;t.style.cssText='position:fixed;bottom:22px;right:22px;background:#140465;color:#fff;padding:9px 18px;border-radius:9px;font-size:13px;font-weight:600;z-index:9999;';document.body.appendChild(t);setTimeout(()=>t.remove(),2300);}
document.getElementById('tzSelect')?.addEventListener('change',function(){document.getElementById('tzNow').innerHTML='<i class="fas fa-clock"></i> Selected: <strong>'+this.value.replace(/_/g,' ')+'</strong> — save to apply';});
function testMapsKey(){const k=document.getElementById('mapsApiKey').value;if(!k){showResult('mapsTestResult',false,'Enter API key first.');return;}const lat=document.querySelector('[name="google_maps_default_lat"]').value||'2.0469';const lng=document.querySelector('[name="google_maps_default_lng"]').value||'45.3182';const z=document.querySelector('[name="google_maps_default_zoom"]').value||'13';const p=document.getElementById('mapPreview');p.style.display='block';p.innerHTML=`<img src="https://maps.googleapis.com/maps/api/staticmap?center=${lat},${lng}&zoom=${z}&size=600x180&maptype=roadmap&markers=color:red|${lat},${lng}&key=${k}" style="width:100%;height:170px;object-fit:cover;" onload="showResult('mapsTestResult',true,'API key valid!')" onerror="showResult('mapsTestResult',false,'Invalid key or Maps SDK not enabled.')">`;}
function validateJson(){const v=document.querySelector('[name="firebase_service_account_json"]').value.trim();if(!v){showResult('jsonValidResult',false,'Paste JSON first.');return;}try{const p=JSON.parse(v);const m=['type','project_id','private_key','client_email'].filter(k=>!p[k]);m.length?showResult('jsonValidResult',false,'Missing: '+m.join(', ')):showResult('jsonValidResult',true,'Valid JSON for project: <strong>'+p.project_id+'</strong>');}catch(e){showResult('jsonValidResult',false,'Invalid JSON: '+e.message);}}
function sendTest(){const tk=document.getElementById('testFcmToken').value.trim();if(!tk){showResult('fcmTestResult',false,'Paste FCM token first.');return;}const b=event.target.closest('button');b.disabled=true;b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Sending…';fetch('<?php echo e(route("admin.settings.test-firebase")); ?>',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'<?php echo e(csrf_token()); ?>'},body:JSON.stringify({test_token:tk})}).then(r=>r.json()).then(d=>showResult('fcmTestResult',d.success,d.message)).catch(()=>showResult('fcmTestResult',false,'Network error.')).finally(()=>{b.disabled=false;b.innerHTML='<i class="fas fa-paper-plane"></i> Send';});}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/esahlan/backend/resources/views/admin/settings/index.blade.php ENDPATH**/ ?>