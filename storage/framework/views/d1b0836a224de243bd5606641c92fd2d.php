<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eSahlan Employee — Sign In</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh; background: #0c0148;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif; padding: 20px;
            position: relative; overflow: hidden;
        }
        body::before {
            content: ''; position: absolute; width: 600px; height: 600px; border-radius: 50%;
            background: radial-gradient(circle, rgba(255,138,0,0.14), transparent 70%);
            top: -200px; right: -200px;
        }
        body::after {
            content: ''; position: absolute; width: 400px; height: 400px; border-radius: 50%;
            background: radial-gradient(circle, rgba(59,130,246,0.08), transparent 70%);
            bottom: -100px; left: -100px;
        }
        .login-wrap { width: 100%; max-width: 420px; position: relative; z-index: 1; }
        .login-card { background: #fff; border-radius: 20px; padding: 40px; box-shadow: 0 30px 80px rgba(0,0,0,0.4); }
        .brand { text-align: center; margin-bottom: 30px; }
        .brand-logo {
            width: 64px; height: 64px; background: linear-gradient(135deg, #FF8A00, #ff6200);
            border-radius: 16px; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px; font-size: 26px; color: #fff; box-shadow: 0 8px 24px rgba(255,138,0,0.4);
        }
        .brand h1 { font-size: 22px; font-weight: 800; color: #07003B; margin-bottom: 4px; }
        .brand p  { color: #7b7fa8; font-size: 13px; }
        .alert-error {
            background: rgba(239,68,68,0.08); color: #dc2626; border: 1.5px solid #fecaca;
            border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 13.5px;
            display: flex; align-items: center; gap: 8px;
        }
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; margin-bottom: 7px; font-size: 12.5px; font-weight: 700; color: #4b5563; }
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 14px; }
        .form-control {
            width: 100%; padding: 11px 13px 11px 38px; border: 1.5px solid #e5e7eb; border-radius: 10px;
            font-size: 14px; color: #1a1a2e; background: #fff; outline: none; transition: border-color .2s, box-shadow .2s;
            font-family: inherit;
        }
        .form-control:focus { border-color: #FF8A00; box-shadow: 0 0 0 3px rgba(255,138,0,0.12); }
        .form-control::placeholder { color: #9ca3af; }
        .btn-login {
            width: 100%; padding: 13px; background: linear-gradient(135deg, #FF8A00, #ff6200);
            border: none; border-radius: 11px; font-size: 14px; font-weight: 700; color: #fff; cursor: pointer;
            transition: all .2s; box-shadow: 0 4px 16px rgba(255,138,0,0.35); font-family: inherit; margin-top: 4px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(255,138,0,0.45); }
        .hint { text-align:center; margin-top:18px; font-size:12px; color:#9ca3af; }
        .footer-note { text-align: center; margin-top: 24px; font-size: 12px; color: rgba(255,255,255,0.25); }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="brand">
                <div class="brand-logo"><i class="fas fa-user-tie"></i></div>
                <h1>eSahlan Staff</h1>
                <p>Module Management Portal</p>
            </div>

            <?php if($errors->any()): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo e($errors->first()); ?>

            </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo e(route('employee.login.post')); ?>">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label class="form-label">Phone or Email</label>
                    <div class="input-wrap">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="login" class="form-control"
                               value="<?php echo e(old('login')); ?>" placeholder="+2526… or you@example.com" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">PIN / Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" name="password" class="form-control" placeholder="Your app PIN" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <p class="hint">Use the same phone &amp; PIN as your eSahlan app account.</p>
        </div>
        <p class="footer-note">&copy; <?php echo e(date('Y')); ?> eSahlan Super App · Somalia</p>
    </div>
</body>
</html>
<?php /**PATH /var/www/esahlan/backend/resources/views/employee/auth/login.blade.php ENDPATH**/ ?>