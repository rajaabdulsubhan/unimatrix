<?php
include_once('../common/init.loader.php');

$page_header = "Member CP Login";
$_SESSION['clistview'] = $_SESSION['clisti'] = $_SESSION['vlistview'] = '';

if ($FORM['ucpunlock'] != '') {
    $seskey = verifylog_sess('admin');
    if ($seskey != '') {
        addlog_sess($FORM['ucpunlock'], 'member');
        $redirval = "index.php?hal=dashboard";
        $waitme = 0;
    } else {
        $_SESSION['show_msg'] = showalert('danger', 'Error!', 'Invalid login session');
        $redirval = "?res=errucp";
        $waitme = mt_rand(9, 33);
        echo "<div class='text-center text-small'>Verifying, please wait... <i class='fas fa-cog fa-spin'></i></div>";
    }
    redirpageto($redirval, $waitme);
    exit;
}

if (verifylog_sess('member') != '') {
    redirpageto('index.php?hal=dashboard');
    exit;
}

if (isset($FORM['dosubmit']) && $FORM['dosubmit'] == '1') {
    extract($FORM);

    $username = mystriptag($username, 'user');

    // Get member details
    $rowstr = getmbrinfo($username, 'username');

    if ($rowstr['id'] > 0 && $rowstr['mbrstatus'] <= 3 && password_verify(md5($password), $rowstr['password'])) {
        if (!dumbtoken($dumbtoken)) {
            $_SESSION['show_msg'] = showalert('danger', 'Error!', $LANG['g_invalidtoken']);
            $redirval = "?res=errtoken";
            redirpageto($redirval, 1);
            exit;
        }

        $in_date = date('Y-m-d H:i:s', time() + (3600 * $cfgrow['time_offset']));
        addlog_sess($username, 'member', $remember);
        $db->update(DB_TBLPREFIX . '_mbrs', array('log_date' => $in_date), array('username' => $username));

        redirpageto('index.php?hal=dashboard');
        exit;
    } else {
        $_SESSION['show_msg'] = showalert('danger', 'Invalid Login', 'Username and Password are case sensitive. Please try it again.');
        redirpageto('login.php?err=' . $username);
        exit;
    }
}

$show_msg = $_SESSION['show_msg'];
$_SESSION['show_msg'] = '';

include('../common/pub.header.php');
?>
<section class="section">
    <div class="container mt-4">
        <div class="row">
            <div class="col-12 col-sm-8 offset-sm-2 col-md-6 offset-md-3 col-lg-6 offset-lg-3 col-xl-4 offset-xl-4">
                <div class="login-brand">
                    <img src="<?php echo myvalidate($site_logo); ?>" alt="logo" width="100" class="shadow-light rounded-circle">
                    <div><?php echo myvalidate($cfgrow['site_name']); ?></div>
                </div>

                <?php echo myvalidate($show_msg); ?>

                <div class="card card-primary">
                    <div class="card-header"><h4><?php echo myvalidate($page_header); ?></h4></div>

                    <div class="card-body">
                        <form method="POST" class="needs-validation" novalidate="">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input id="username" type="text" class="form-control" name="username" tabindex="1" required autofocus>
                                <div class="invalid-feedback">
                                    Please fill in your username
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="d-block">
                                    <label for="password" class="control-label">Password</label>
                                    <div class="float-right">
                                        <a href="forgot-password.php" class="text-small">
                                            <?php echo myvalidate($LANG['g_forgotpass']); ?>?
                                        </a>
                                    </div>
                                </div>
                                <input id="password" type="password" class="form-control" name="password" tabindex="2" required>
                                <div class="invalid-feedback">
                                    please fill in your password
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="remember" class="custom-control-input" tabindex="3" id="remember-me">
                                    <label class="custom-control-label" for="remember-me"><?php echo myvalidate($LANG['g_rememberme']); ?></label>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-lg btn-block" tabindex="4">
                                    Login
                                </button>
                                <input type="hidden" name="dosubmit" value="1">
                                <input type="hidden" name="dumbtoken" value="<?php echo myvalidate($_SESSION['dumbtoken']); ?>">
                            </div>
                        </form>
                        <div class="mt-2 text-muted text-center">
                            <?php echo myvalidate($LANG['g_donothaveacc']); ?> <a href="register.php"><?php echo myvalidate($LANG['g_createacc']); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php
include('../common/pub.footer.php');
