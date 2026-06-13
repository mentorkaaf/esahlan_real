<?php
/**
 * eSahlan — Web Installer
 * Upload to: public_html/install/index.php
 */

// Show errors only in debug mode
define('INSTALL_DEBUG', false);
if (INSTALL_DEBUG) { error_reporting(E_ALL); ini_set('display_errors',1); }

session_start();

// Paths
$ROOT     = dirname(__DIR__);   // esahlan_backend/
$LOCK     = $ROOT . '/.installed';
$ENV_FILE = $ROOT . '/.env';

// ── Locked ──────────────────────────────────────────────────────────────────
if (file_exists($LOCK) && empty($_GET['force'])) {
    page('Installed', locked_html()); exit;
}

// ── Actions ─────────────────────────────────────────────────────────────────
$act = isset($_GET['a']) ? $_GET['a'] : '';

if ($act === 'reset') {
    $_SESSION = array();
    session_destroy();
    header('Location: index.php'); exit;
}

if ($act === 'test_db') {
    header('Content-Type: application/json');
    echo json_encode(do_test_db()); exit;
}

if ($act === 'install') {
    @set_time_limit(300);
    header('Content-Type: application/json');
    ob_start();
    $r = do_install($ROOT, $LOCK, $ENV_FILE);
    ob_end_clean();
    echo json_encode($r); exit;
}

if ($act === 'post') {
    do_post($ROOT);
    header('Location: index.php'); exit;
}

// ── Render step ──────────────────────────────────────────────────────────────
$s = isset($_SESSION['step']) ? (int)$_SESSION['step'] : 1;
if ($s < 1 || $s > 5) $s = 1;
switch ($s) {
    case 2: page('Database',    step2_html(), 2); break;
    case 3: page('Application', step3_html(), 3); break;
    case 4: page('Install',     step4_html(), 4); break;
    case 5: page('Done!',       step5_html(), 5); break;
    default:page('Requirements',step1_html(), 1); break;
}

// ════════════════════════════════════════════════════════════════════════════
//  POST HANDLER
// ════════════════════════════════════════════════════════════════════════════
function do_post($root) {
    $s = isset($_POST['s']) ? (int)$_POST['s'] : 0;

    if (isset($_POST['back'])) {
        $_SESSION['step'] = max(1, $s - 1); return;
    }

    if ($s === 1) {
        $_SESSION['step'] = 2;
    }
    elseif ($s === 2) {
        // Save DB — ensure password is saved even if empty string
        $_SESSION['db_host'] = trim($_POST['db_host'] ?? '127.0.0.1');
        $_SESSION['db_port'] = trim($_POST['db_port'] ?? '3306');
        $_SESSION['db_name'] = trim($_POST['db_name'] ?? '');
        $_SESSION['db_user'] = trim($_POST['db_user'] ?? '');
        $_SESSION['db_pass'] = $_POST['db_pass'] ?? '';  // raw, no trim
        $_SESSION['step']    = 3;
    }
    elseif ($s === 3) {
        $_SESSION['app_name']      = trim($_POST['app_name']      ?? 'eSahlan');
        $_SESSION['app_url']       = rtrim(trim($_POST['app_url'] ?? ''), '/');
        $_SESSION['admin_name']    = trim($_POST['admin_name']     ?? 'Super Admin');
        $_SESSION['admin_email']   = trim($_POST['admin_email']    ?? '');
        $_SESSION['admin_phone']   = trim($_POST['admin_phone']    ?? '+252617000001');
        $_SESSION['admin_password']= $_POST['admin_password']      ?? '';
        $_SESSION['step']          = 4;
    }
}

// ════════════════════════════════════════════════════════════════════════════
//  INSTALL
// ════════════════════════════════════════════════════════════════════════════
function do_install($root, $lock, $envFile) {
    // Read from flat session keys (not nested arrays)
    $dbHost = isset($_SESSION['db_host']) ? $_SESSION['db_host'] : '';
    $dbPort = isset($_SESSION['db_port']) ? $_SESSION['db_port'] : '3306';
    $dbName = isset($_SESSION['db_name']) ? $_SESSION['db_name'] : '';
    $dbUser = isset($_SESSION['db_user']) ? $_SESSION['db_user'] : '';
    $dbPass = isset($_SESSION['db_pass']) ? $_SESSION['db_pass'] : '';

    $appName   = isset($_SESSION['app_name'])    ? $_SESSION['app_name']    : 'eSahlan';
    $appUrl    = isset($_SESSION['app_url'])     ? $_SESSION['app_url']     : '';
    $admName   = isset($_SESSION['admin_name'])  ? $_SESSION['admin_name']  : 'Super Admin';
    $admEmail  = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : '';
    $admPhone  = isset($_SESSION['admin_phone']) ? $_SESSION['admin_phone'] : '+252617000001';
    $admPass   = isset($_SESSION['admin_password'])? $_SESSION['admin_password']:'';

    if (!$dbName || !$dbUser || !$admEmail) {
        return array('ok'=>false,'msg'=>'Missing data. Please go back and complete all steps.','steps'=>array());
    }

    $steps = array();

    // 1. Write .env
    try {
        $key = 'base64:' . base64_encode(random_bytes(32));
        $env = "APP_NAME=\"{$appName}\"\n"
             . "APP_ENV=production\n"
             . "APP_KEY={$key}\n"
             . "APP_DEBUG=false\n"
             . "APP_URL={$appUrl}\n\n"
             . "LOG_CHANNEL=stack\nLOG_LEVEL=error\n\n"
             . "DB_CONNECTION=mysql\n"
             . "DB_HOST={$dbHost}\nDB_PORT={$dbPort}\n"
             . "DB_DATABASE={$dbName}\nDB_USERNAME={$dbUser}\nDB_PASSWORD={$dbPass}\n\n"
             . "BROADCAST_CONNECTION=log\nCACHE_STORE=file\n"
             . "FILESYSTEM_DISK=local\nQUEUE_CONNECTION=sync\n"
             . "SESSION_DRIVER=file\nSESSION_LIFETIME=120\n\n"
             . "MAIL_MAILER=log\n";
        $ok = file_put_contents($envFile, $env) !== false;
        $steps[] = array('ok'=>$ok, 'label'=>'.env file written', 'detail'=> $ok ? 'App key generated.' : 'FAILED to write .env — check folder permissions!');
        if (!$ok) return array('ok'=>false,'msg'=>'Cannot write .env file.','steps'=>$steps);
    } catch(Throwable $e) {
        return array('ok'=>false,'msg'=>'env error: '.$e->getMessage(),'steps'=>$steps);
    }

    // 2a. Clear bootstrap/cache — removes any Windows-path-cached files that
    //     would crash on Linux (e.g. config.php with C:\Users\... paths).
    $cacheDir = $root . '/bootstrap/cache';
    if (is_dir($cacheDir)) {
        foreach (glob($cacheDir . '/*.php') ?: array() as $cf) {
            @unlink($cf);
        }
    }
    // Also clear compiled views and framework caches
    foreach (array(
        $root.'/storage/framework/cache/data',
        $root.'/storage/framework/views',
        $root.'/storage/framework/sessions',
    ) as $dir) {
        if (is_dir($dir)) {
            foreach (glob($dir.'/*') ?: array() as $f) {
                if (is_file($f)) @unlink($f);
            }
        }
    }
    $steps[] = array('ok'=>true,'label'=>'Bootstrap cache cleared','detail'=>'Removed cached config/route/view files (prevents Windows→Linux path errors)');

    // 2b. Ensure storage directories are writable
    $storageDirs = array(
        $root.'/storage/app/public',
        $root.'/storage/framework/cache/data',
        $root.'/storage/framework/sessions',
        $root.'/storage/framework/views',
        $root.'/storage/logs',
        $root.'/bootstrap/cache',
    );
    foreach ($storageDirs as $dir) {
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        else @chmod($dir, 0755);
    }

    // 2c. Boot Laravel
    $kernel = null; $app = null; $bootErr = '';
    try {
        if (!file_exists($root.'/vendor/autoload.php')) throw new Exception('vendor/autoload.php missing');
        require_once $root.'/vendor/autoload.php';
        if (!file_exists($root.'/bootstrap/app.php')) throw new Exception('bootstrap/app.php missing');
        $app    = require $root.'/bootstrap/app.php';
        $kernel = $app->make('Illuminate\Contracts\Console\Kernel');
        $kernel->bootstrap();
        $steps[] = array('ok'=>true, 'label'=>'Laravel framework loaded', 'detail'=>'');
    } catch(Throwable $e) {
        $steps[] = array('ok'=>false,'label'=>'Laravel framework load','detail'=>$e->getMessage());
        return array('ok'=>false,'msg'=>'Laravel boot failed: '.$e->getMessage(),'steps'=>$steps);
    }

    // 3. Drop ALL existing tables first (using direct PDO + FK checks off)
    //    This avoids migrate:fresh's internal db:wipe failing on FK constraints.
    try {
        $dropPdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
            $dbUser, $dbPass,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
        $dropPdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $tables = $dropPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $tbl) {
            $dropPdo->exec("DROP TABLE IF EXISTS `{$tbl}`");
        }
        $dropPdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $dropPdo = null; // close connection
        $steps[] = array('ok'=>true,'label'=>'Database cleared','detail'=>'Dropped '.count($tables).' existing table(s)');
    } catch(Throwable $e) {
        // Non-fatal — if DB is already empty this is fine
        $steps[] = array('ok'=>true,'label'=>'Database cleared','detail'=>'(nothing to drop: '.$e->getMessage().')');
    }

    // 3b. Remove any duplicate/old migration files that conflict
    $migPath = $root . '/database/migrations/';
    $obsoleteFiles = array(
        '2024_01_01_000006_b_create_orders_wallets_tables.php',
        '2024_01_01_000006_create_orders_wallets_tables.php',
    );
    foreach ($obsoleteFiles as $fn) {
        $fp = $migPath . $fn;
        if (file_exists($fp)) { @unlink($fp); }
    }

    // 4. Run migrations — disable FK checks on the EXACT connection Laravel will use
    try {
        // Purge any stale connection, then open a fresh one and disable FK checks on it.
        // All migrations reuse this same cached connection, so FK_CHECKS stays 0 throughout.
        $app->make('db')->purge('mysql');
        $app->make('db')->statement('SET FOREIGN_KEY_CHECKS=0');

        $out = artisan_call($kernel,'migrate',array('--force'=>true));
        $ok  = stripos($out,'error')===false && stripos($out,'exception')===false && stripos($out,'failed')===false;
        $steps[] = array('ok'=>$ok,'label'=>'Database migration','detail'=>substr(trim($out),0,600));
        if (!$ok) return array('ok'=>false,'msg'=>'Migration failed: '.substr($out,0,500),'steps'=>$steps);
    } catch(Throwable $e) {
        $steps[] = array('ok'=>false,'label'=>'Database migration','detail'=>$e->getMessage());
        return array('ok'=>false,'msg'=>'Migration exception: '.$e->getMessage(),'steps'=>$steps);
    }

    // 4b. Run seeders — restore FK checking
    try {
        $app->make('db')->statement('SET FOREIGN_KEY_CHECKS=1');
        $app->make('db')->purge('mysql');

        $out2 = artisan_call($kernel,'db:seed',array('--force'=>true));
        $ok2  = stripos($out2,'error')===false && stripos($out2,'exception')===false;
        $steps[] = array('ok'=>$ok2,'label'=>'Database seeded','detail'=>substr(trim($out2),0,300));
        if (!$ok2) return array('ok'=>false,'msg'=>'Seeder failed: '.substr($out2,0,300),'steps'=>$steps);
    } catch(Throwable $e) {
        $steps[] = array('ok'=>false,'label'=>'Database seed','detail'=>$e->getMessage());
        return array('ok'=>false,'msg'=>'Seed exception: '.$e->getMessage(),'steps'=>$steps);
    }

    // 5. Create admin (direct PDO, independent of Laravel)
    try {
        $pdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
            $dbUser, $dbPass,
            array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION)
        );
        $role = $pdo->query("SELECT id FROM roles WHERE slug='super_admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$role) throw new Exception('super_admin role not found — seeders may have failed');

        $pdo->prepare("DELETE FROM users WHERE email=? OR phone=?")->execute(array($admEmail, $admPhone));
        $uuid = sprintf('%08x-%04x-%04x-%04x-%012x',
            random_int(0,0xffffffff), random_int(0,0xffff),
            random_int(0x4000,0x4fff), random_int(0x8000,0xbfff),
            random_int(0,0xffffffffffff));
        $ref  = strtoupper(substr(md5(uniqid('',true)),0,8));
        $hash = password_hash($admPass, PASSWORD_BCRYPT);
        $now  = date('Y-m-d H:i:s');

        $pdo->prepare(
            "INSERT INTO users (uuid,name,email,phone,password,role_id,status,referral_code,phone_verified_at,email_verified_at,preferred_language,created_at,updated_at)
             VALUES (?,?,?,?,?,?,'active',?,?,?,'en',?,?)"
        )->execute(array($uuid,$admName,$admEmail,$admPhone,$hash,$role['id'],$ref,$now,$now,$now,$now));

        $uid = $pdo->lastInsertId();
        $pdo->prepare(
            "INSERT INTO wallets (owner_type,owner_id,balance,currency,created_at,updated_at) VALUES (?,?,0,'USD',?,?)"
        )->execute(array('App\\Models\\User',$uid,$now,$now));

        $steps[] = array('ok'=>true,'label'=>"Admin account created ({$admEmail})",'detail'=>'');
    } catch(Throwable $e) {
        $steps[] = array('ok'=>false,'label'=>'Admin account','detail'=>$e->getMessage());
    }

    // 6. Clear all caches (do NOT re-cache — let Laravel regenerate on first request)
    try {
        artisan_call($kernel,'cache:clear',array());
        artisan_call($kernel,'config:clear',array());
        artisan_call($kernel,'route:clear',array());
        artisan_call($kernel,'view:clear',array());
        $steps[] = array('ok'=>true,'label'=>'All caches cleared','detail'=>'Config, route, view caches cleared. Laravel will warm on first request.');
    } catch(Throwable $e) {
        $steps[] = array('ok'=>true,'label'=>'Cache clear (warnings)','detail'=>$e->getMessage());
    }

    // 7. Lock
    file_put_contents($lock, date('Y-m-d H:i:s'));
    $steps[] = array('ok'=>true,'label'=>'Installation locked (.installed)','detail'=>'');

    $_SESSION['step']       = 5;
    $_SESSION['done_url']   = $appUrl;
    $_SESSION['done_email'] = $admEmail;

    return array('ok'=>true,'steps'=>$steps);
}

function artisan_call($kernel, $cmd, $params) {
    $out = new \Symfony\Component\Console\Output\BufferedOutput();
    $kernel->call($cmd, $params, $out);
    return $out->fetch();
}

function do_test_db() {
    $h  = isset($_POST['h'])  ? $_POST['h']  : '127.0.0.1';
    $p  = isset($_POST['p'])  ? $_POST['p']  : '3306';
    $n  = isset($_POST['n'])  ? $_POST['n']  : '';
    $u  = isset($_POST['u'])  ? $_POST['u']  : '';
    $pw = isset($_POST['pw']) ? $_POST['pw'] : '';
    try {
        $pdo = new PDO("mysql:host={$h};port={$p};dbname={$n};charset=utf8mb4", $u, $pw,
            array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT=>5));
        $pdo->query('SELECT 1');
        return array('ok'=>true, 'msg'=>'Connection successful!');
    } catch(Exception $e) {
        return array('ok'=>false,'msg'=>$e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════════════════════
//  REQUIREMENTS
// ════════════════════════════════════════════════════════════════════════════
function get_reqs() {
    $r = dirname(__DIR__);
    return array(
        array('n'=>'PHP >= 8.2',               'ok'=>version_compare(PHP_VERSION,'8.2.0','>='),'v'=>PHP_VERSION),
        array('n'=>'PDO MySQL',                'ok'=>extension_loaded('pdo_mysql'),  'v'=>extension_loaded('pdo_mysql')?'Enabled':'MISSING'),
        array('n'=>'OpenSSL',                  'ok'=>extension_loaded('openssl'),    'v'=>extension_loaded('openssl')?'Enabled':'MISSING'),
        array('n'=>'Mbstring',                 'ok'=>extension_loaded('mbstring'),   'v'=>extension_loaded('mbstring')?'Enabled':'MISSING'),
        array('n'=>'Tokenizer',                'ok'=>extension_loaded('tokenizer'),  'v'=>extension_loaded('tokenizer')?'Enabled':'MISSING'),
        array('n'=>'Fileinfo',                 'ok'=>extension_loaded('fileinfo'),   'v'=>extension_loaded('fileinfo')?'Enabled':'MISSING'),
        array('n'=>'JSON',                     'ok'=>extension_loaded('json'),       'v'=>extension_loaded('json')?'Enabled':'MISSING'),
        array('n'=>'GD / ImageMagick',         'ok'=>extension_loaded('gd')||extension_loaded('imagick'),'v'=>extension_loaded('gd')?'GD':(extension_loaded('imagick')?'Imagick':'MISSING')),
        array('n'=>'shell_exec (optional)',    'ok'=>true,'v'=>function_exists('shell_exec')?'Enabled':'Disabled — OK'),
        array('n'=>'storage/ writable',        'ok'=>is_writable($r.'/storage'),         'v'=>is_writable($r.'/storage')?'Writable':'NOT WRITABLE'),
        array('n'=>'bootstrap/cache/ writable','ok'=>is_writable($r.'/bootstrap/cache'), 'v'=>is_writable($r.'/bootstrap/cache')?'Writable':'NOT WRITABLE'),
        array('n'=>'.env writable',            'ok'=>is_writable($r),                    'v'=>is_writable($r)?'Writable':'NOT WRITABLE'),
        array('n'=>'vendor/ exists',           'ok'=>is_dir($r.'/vendor'),               'v'=>is_dir($r.'/vendor')?'Found':'MISSING'),
    );
}

// ════════════════════════════════════════════════════════════════════════════
//  HTML STEPS
// ════════════════════════════════════════════════════════════════════════════
function step1_html() {
    $reqs = get_reqs();
    $pass = true;
    foreach ($reqs as $r) { if (!$r['ok']) { $pass=false; break; } }
    ob_start();
    echo '<div class="sh"><div class="si" style="background:#eef2ff">&#9989;</div><h2>Server Requirements</h2><p>Checking your server is ready.</p></div>';
    echo '<table class="rt"><thead><tr><th>Check</th><th>Value</th><th></th></tr></thead><tbody>';
    foreach ($reqs as $r) {
        $opt = strpos($r['n'],'optional')!==false;
        $bg  = $r['ok']?'':'background:'.($opt?'#fffbeb':'#fef2f2');
        $ic  = $r['ok']?'<span style="color:#22c55e">&#10004;</span>':($opt?'<span style="color:#f59e0b">&#9888;</span>':'<span style="color:#ef4444">&#10008;</span>');
        echo "<tr style=\"{$bg}\"><td>".htmlspecialchars($r['n'])."</td><td style=\"color:#6b7280\">".htmlspecialchars($r['v'])."</td><td>{$ic}</td></tr>";
    }
    echo '</tbody></table>';
    if ($pass) {
        echo '<div class="al al-ok">&#10003; All requirements met</div>';
    } else {
        echo '<div class="al al-warn">Fix the failed items above, then refresh this page.</div>';
    }
    echo '<form method="POST" action="index.php?a=post">';
    echo '<input type="hidden" name="s" value="1">';
    echo '<button type="submit" class="btn"'.($pass?'':' disabled').'>Continue to Database Setup &#8250;</button>';
    echo '</form>';
    return ob_get_clean();
}

function step2_html() {
    $h = htmlspecialchars(isset($_SESSION['db_host'])?$_SESSION['db_host']:'127.0.0.1');
    $p = htmlspecialchars(isset($_SESSION['db_port'])?$_SESSION['db_port']:'3306');
    $n = htmlspecialchars(isset($_SESSION['db_name'])?$_SESSION['db_name']:'');
    $u = htmlspecialchars(isset($_SESSION['db_user'])?$_SESSION['db_user']:'');
    ob_start(); ?>
    <div class="sh"><div class="si" style="background:#eff6ff">&#128449;</div><h2>Database Setup</h2><p>MySQL credentials from Hostinger hPanel &rarr; Databases.</p></div>
    <div id="dbmsg" style="display:none;padding:10px 14px;border-radius:10px;margin-bottom:12px;font-size:13px"></div>
    <form id="dbf" method="POST" action="index.php?a=post">
        <input type="hidden" name="s" value="2">
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px">
            <div class="fw"><label>Host</label><input type="text" name="db_host" value="<?= $h ?>" class="fi" required></div>
            <div class="fw"><label>Port</label><input type="text" name="db_port" value="<?= $p ?>" class="fi" required></div>
        </div>
        <div class="fw"><label>Database Name</label><input type="text" name="db_name" value="<?= $n ?>" placeholder="u123456789_esahlan" class="fi" required></div>
        <div class="fw"><label>Username</label><input type="text" name="db_user" value="<?= $u ?>" placeholder="u123456789_esahlan" class="fi" required></div>
        <div class="fw"><label>Password</label><input type="password" name="db_pass" id="dbpw" class="fi" autocomplete="new-password"></div>
        <div style="display:flex;gap:10px;margin-top:16px">
            <button type="button" onclick="tdb()" id="tbtn" class="btn-o">Test Connection</button>
            <button type="submit" class="btn" style="flex:1">Next &#8250;</button>
        </div>
    </form>
    <script>
    function tdb(){
        var f=document.getElementById('dbf'),m=document.getElementById('dbmsg'),b=document.getElementById('tbtn');
        b.disabled=true;b.textContent='Testing...';
        fetch('index.php?a=test_db',{method:'POST',body:new URLSearchParams({
            h:f.db_host.value,p:f.db_port.value,n:f.db_name.value,u:f.db_user.value,pw:document.getElementById('dbpw').value
        })}).then(r=>r.json()).then(r=>{
            m.style.display='block';
            m.style.cssText='display:block;padding:10px 14px;border-radius:10px;margin-bottom:12px;font-size:13px;background:'+(r.ok?'#dcfce7':'#fee2e2')+';color:'+(r.ok?'#166534':'#991b1b');
            m.textContent=(r.ok?'✓ ':'✗ ')+r.msg;
        }).finally(()=>{b.disabled=false;b.textContent='Test Connection';});
    }
    </script>
    <?php return ob_get_clean();
}

function step3_html() {
    $proto = (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https://':'http://';
    $host  = isset($_SERVER['HTTP_HOST'])?strtok($_SERVER['HTTP_HOST'],':'):'yourdomain.com';
    $defUrl= $proto.$host;
    $url   = htmlspecialchars(isset($_SESSION['app_url'])?$_SESSION['app_url']:$defUrl);
    ob_start(); ?>
    <div class="sh"><div class="si" style="background:#faf5ff">&#9881;</div><h2>Application Settings</h2><p>Configure your app and create the super admin.</p></div>
    <form method="POST" action="index.php?a=post">
        <input type="hidden" name="s" value="3">
        <div class="sec"><div class="sl">App</div>
            <div class="fw"><label>Application Name</label><input type="text" name="app_name" value="eSahlan" class="fi" required></div>
            <div class="fw"><label>URL (no trailing slash)</label><input type="url" name="app_url" value="<?= $url ?>" class="fi" required></div>
        </div>
        <div class="sec"><div class="sl">Super Admin Account</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div class="fw"><label>Name</label><input type="text" name="admin_name" placeholder="Super Admin" class="fi" required></div>
                <div class="fw"><label>Phone</label><input type="text" name="admin_phone" placeholder="+252617000001" class="fi"></div>
            </div>
            <div class="fw"><label>Email</label><input type="email" name="admin_email" placeholder="admin@yourdomain.com" class="fi" required></div>
            <div class="fw"><label>Password</label><input type="password" name="admin_password" placeholder="Min 8 characters" class="fi" required minlength="8" autocomplete="new-password"></div>
        </div>
        <div style="display:flex;gap:10px;margin-top:16px">
            <button type="button" onclick="back()" class="btn-o">&#8249; Back</button>
            <button type="submit" class="btn" style="flex:1">Next &#8250;</button>
        </div>
    </form>
    <script>
    function back(){
        var f=document.createElement('form');f.method='POST';f.action='index.php?a=post';
        [['s','3'],['back','1']].forEach(function(x){var i=document.createElement('input');i.type='hidden';i.name=x[0];i.value=x[1];f.appendChild(i);});
        document.body.appendChild(f);f.submit();
    }
    </script>
    <?php return ob_get_clean();
}

function step4_html() {
    $dbName = htmlspecialchars(isset($_SESSION['db_name'])?$_SESSION['db_name']:'—');
    $appUrl = htmlspecialchars(isset($_SESSION['app_url'])?$_SESSION['app_url']:'—');
    $email  = htmlspecialchars(isset($_SESSION['admin_email'])?$_SESSION['admin_email']:'—');
    $dbHost = htmlspecialchars(isset($_SESSION['db_host'])?$_SESSION['db_host']:'—');
    ob_start(); ?>
    <div class="sh"><div class="si" style="background:#fffbeb">&#9889;</div><h2>Ready to Install</h2><p>Review then click <strong>Install Now</strong>.</p></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">
        <div class="ib"><div class="il">Database</div><div class="iv"><?= $dbName ?></div><div style="font-size:11px;color:#9ca3af"><?= $dbHost ?></div></div>
        <div class="ib"><div class="il">App URL</div><div class="iv" style="font-size:12px;word-break:break-all"><?= $appUrl ?></div></div>
        <div class="ib" style="grid-column:span 2"><div class="il">Admin Email</div><div class="iv"><?= $email ?></div></div>
    </div>
    <div id="lg" style="display:none;background:#0f172a;color:#e2e8f0;border-radius:12px;padding:14px;font-size:12px;font-family:monospace;max-height:200px;overflow-y:auto;margin-bottom:14px;white-space:pre-wrap"></div>
    <div id="act">
        <button onclick="go()" id="ibtn" class="btn-inst">&#9889; Install Now</button>
        <p style="text-align:center;font-size:12px;color:#9ca3af;margin-top:8px">Takes 30–60 seconds. Do not refresh.</p>
    </div>
    <script>
    function go(){
        var btn=document.getElementById('ibtn'),lg=document.getElementById('lg'),act=document.getElementById('act');
        btn.disabled=true;btn.textContent='Installing...';
        lg.style.display='block';lg.textContent='Starting...\n';
        fetch('index.php?a=install',{method:'POST'}).then(function(r){return r.json();}).then(function(res){
            if(res.steps){res.steps.forEach(function(s){
                lg.textContent+=(s.ok?'✓ ':'✗ ')+s.label+'\n'+(s.detail?'  '+s.detail+'\n':'');
                lg.scrollTop=lg.scrollHeight;
            });}
            if(res.ok){
                lg.textContent+='✓ Installation complete!\n';
                act.innerHTML='<a href="index.php" style="display:block;text-align:center;text-decoration:none;background:#16a34a;color:#fff;padding:14px;border-radius:12px;font-size:16px;font-weight:700">✓ Finish &rarr;</a>';
            }else{
                lg.textContent+='✗ ERROR: '+(res.msg||'Unknown')+'\n';
                btn.disabled=false;btn.textContent='⚡ Retry';
            }
        }).catch(function(e){
            lg.textContent+='Network error: '+e+'\n';
            btn.disabled=false;btn.textContent='⚡ Retry';
        });
    }
    </script>
    <?php return ob_get_clean();
}

function step5_html() {
    $url   = htmlspecialchars(isset($_SESSION['done_url'])  ?$_SESSION['done_url']  :'#');
    $email = htmlspecialchars(isset($_SESSION['done_email'])?$_SESSION['done_email']:'—');
    ob_start(); ?>
    <div style="text-align:center;padding:16px 0 8px">
        <div style="font-size:56px">&#127881;</div>
        <h2 style="font-size:22px;margin:10px 0 4px;color:#1e1b4b">Installation Complete!</h2>
        <p style="color:#6b7280;font-size:14px">eSahlan has been successfully installed.</p>
    </div>
    <div class="sec" style="margin:16px 0">
        <div class="sl">Login Credentials</div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:14px">
            <span style="color:#6b7280">Admin Panel</span>
            <a href="<?= $url ?>/admin" target="_blank" style="color:#4f46e5;font-weight:700"><?= $url ?>/admin</a>
        </div>
        <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:14px">
            <span style="color:#6b7280">Email</span>
            <strong><?= $email ?></strong>
        </div>
    </div>
    <div class="al al-warn">&#9888; <strong>Delete</strong> the <code style="background:#fef9c3;padding:1px 4px;border-radius:4px">install/</code> folder from your server for security!</div>
    <a href="<?= $url ?>" target="_blank" class="btn" style="display:block;text-align:center;text-decoration:none;margin-top:12px">Go to Admin Panel &rarr;</a>
    <?php return ob_get_clean();
}

function locked_html() {
    ob_start(); ?>
    <div style="text-align:center;padding:30px 0">
        <div style="font-size:50px">&#128274;</div>
        <h2 style="margin:12px 0 8px">Already Installed</h2>
        <p style="color:#6b7280;font-size:14px">eSahlan is installed. The installer is locked.</p>
        <a href="../" style="display:inline-block;margin-top:16px;background:#4f46e5;color:#fff;padding:11px 24px;border-radius:10px;text-decoration:none;font-weight:700">Go to Dashboard</a>
    </div>
    <?php return ob_get_clean();
}

// ════════════════════════════════════════════════════════════════════════════
//  STEP BAR
// ════════════════════════════════════════════════════════════════════════════
function stepbar($cur) {
    $labels = array('Welcome','Database','Application','Install','Finish');
    $out = '<div class="sb">';
    foreach ($labels as $i => $lbl) {
        $n = $i+1;
        if ($n > 1) $out .= '<div class="sl2'.($n<=$cur?' sl2-d':'').'"></div>';
        $cls = $n<$cur ? 'sc sc-d' : ($n===$cur ? 'sc sc-a' : 'sc');
        $ic  = $n<$cur ? '&#10003;' : (string)$n;
        $lc  = $n===$cur ? 'lb lb-a' : ($n<$cur ? 'lb lb-d' : 'lb');
        $out .= '<div class="si2"><div class="'.$cls.'">'.$ic.'</div><span class="'.$lc.'">'.$lbl.'</span></div>';
    }
    $out .= '</div>';
    return $out;
}

// ════════════════════════════════════════════════════════════════════════════
//  PAGE LAYOUT
// ════════════════════════════════════════════════════════════════════════════
function page($title, $content, $step=0) {
    $bar  = $step > 0 ? stepbar($step) : '';
    $yr   = date('Y');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>eSahlan Installer &mdash; '.htmlspecialchars($title).'</title>';
    echo '<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:linear-gradient(135deg,#f0f4ff,#fdf4ff);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:36px 16px}
.wrap{width:100%;max-width:520px}
.brand{text-align:center;margin-bottom:20px}
.brand h1{font-size:26px;font-weight:900}.e{color:#4f46e5}.s{color:#1e1b4b}
.brand p{font-size:12px;color:#9ca3af;margin-top:2px}
.card{background:#fff;border-radius:22px;box-shadow:0 8px 32px rgba(79,70,229,.12);padding:26px;border:1px solid #e8e5ff}
.foot{text-align:center;font-size:12px;color:#9ca3af;margin-top:14px}.foot a{color:#9ca3af}
/* stepbar */
.sb{display:flex;align-items:center;margin-bottom:22px}
.si2{display:flex;flex-direction:column;align-items:center;gap:3px}
.sl2{flex:1;height:2px;background:#e5e7eb}
.sl2-d{background:#4f46e5}
.sc{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;background:#f3f4f6;color:#9ca3af}
.sc-a{background:#4f46e5;color:#fff;box-shadow:0 0 0 4px #e0e7ff}
.sc-d{background:#22c55e;color:#fff}
.lb{font-size:10px;white-space:nowrap;color:#9ca3af}
.lb-a{color:#4f46e5;font-weight:700}
.lb-d{color:#22c55e}
/* step ui */
.sh{text-align:center;margin-bottom:18px}
.si{width:52px;height:52px;border-radius:14px;font-size:24px;display:flex;align-items:center;justify-content:center;margin:0 auto 8px}
.sh h2{font-size:19px;font-weight:800;color:#1e1b4b}
.sh p{font-size:13px;color:#6b7280;margin-top:3px}
/* form */
.fw{margin-bottom:12px}.fw label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px}
.fi{width:100%;padding:9px 12px;border:1.5px solid #e5e7eb;border-radius:10px;font-size:14px;outline:none;transition:.15s}
.fi:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.1)}
.sec{background:#f8fafc;border-radius:12px;padding:14px;margin-bottom:12px}
.sl{font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px}
/* buttons */
.btn{width:100%;padding:12px;background:#4f46e5;color:#fff;border:none;border-radius:11px;font-size:15px;font-weight:700;cursor:pointer;transition:.15s;text-decoration:none;display:block;text-align:center}
.btn:hover{background:#4338ca}.btn:disabled{background:#d1d5db;cursor:not-allowed}
.btn-o{padding:12px 18px;background:#fff;color:#4f46e5;border:2px solid #4f46e5;border-radius:11px;font-size:14px;font-weight:700;cursor:pointer}
.btn-o:hover{background:#f5f3ff}
.btn-inst{width:100%;padding:15px;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none;border-radius:13px;font-size:17px;font-weight:800;cursor:pointer;box-shadow:0 6px 20px rgba(79,70,229,.3)}
/* table */
.rt{width:100%;border-collapse:collapse;margin-bottom:12px;font-size:13px;border:1px solid #f1f5f9;border-radius:10px;overflow:hidden}
.rt thead{background:#f8fafc}.rt th{padding:9px 12px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;text-transform:uppercase}
.rt td{padding:9px 12px;border-top:1px solid #f1f5f9}
/* alerts */
.al{padding:11px 14px;border-radius:9px;font-size:13px;font-weight:500;margin-bottom:14px}
.al-ok{background:#dcfce7;color:#166534}.al-warn{background:#fef9c3;color:#854d0e}
/* info boxes */
.ib{background:#f8fafc;border-radius:10px;padding:11px}
.il{font-size:11px;color:#9ca3af;font-weight:700;text-transform:uppercase;margin-bottom:3px}
.iv{font-size:14px;font-weight:700;color:#1e1b4b}
</style></head><body>';
    echo '<div class="wrap">';
    echo '<div class="brand"><h1><span class="e">e-</span><span class="s">Sahlan</span></h1><p>Installation Wizard</p></div>';
    echo '<div class="card">'.$bar.$content.'</div>';
    echo '<div class="foot">eSahlan &copy; '.$yr.' &nbsp;&middot;&nbsp; <a href="index.php?a=reset" onclick="return confirm(\'Restart from beginning?\')">Restart</a></div>';
    echo '</div></body></html>';
}
