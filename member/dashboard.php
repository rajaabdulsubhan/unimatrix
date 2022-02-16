<?php
if (!defined('OK_LOADME')) {
    die('o o p s !');
}

// create tx for manual renewal in advance
if ($FORM['renewHash'] == md5($mbrstr['mpid'] . $mbrstr['reg_expd']) && $FORM['renewId'] == $mbrstr['mpid'] && $FORM['redir']) {
    $utctime = date('Y-m-d H:i:s', time() + (3600 * $cfgrow['time_offset']));
    do_renewtx($utctime, $mbrstr);
    redirpageto('index.php?hal=planpay');
    exit;
}

$condition = ' AND sprlist LIKE "%:' . $mbrstr['mpid'] . '|%" ';
$row = $db->getAllRecords(DB_TBLPREFIX . '_mbrplans', 'COUNT(*) as totref', $condition);
$myreftotal = $row[0]['totref'];

$condition = ' AND idref = "' . $mbrstr['id'] . '" ';
$row = $db->getAllRecords(DB_TBLPREFIX . '_mbrplans', 'COUNT(*) as totref', $condition);
$myrefonly = $row[0]['totref'];

$condition = ' AND txtoid = "' . $mbrstr['id'] . '" AND txstatus = "1" AND txtoken LIKE "%|LCM:%" ';
$row = $db->getAllRecords(DB_TBLPREFIX . '_transactions', 'SUM(txamount) as totincome', $condition);
$myincometotal = floatval($row[0]['totincome']);

$hitratio = $LANG['g_performance'] . ': ';
$adjthits = ($mbrstr['hits'] > 0) ? $mbrstr['hits'] : 1;
$prcnthit = $myrefonly / $adjthits * 100;
if ($prcnthit > 100000) {
    $hitratio .= "<i class='fas fa-star fa-fw text-warning'></i><i class='fas fa-star fa-fw text-warning'></i><i class='fas fa-star fa-fw text-warning'></i>";
} else if ($prcnthit > 10000) {
    $hitratio .= "<i class='fas fa-star fa-fw text-warning'></i><i class='fas fa-star fa-fw text-warning'></i>";
} else if ($prcnthit > 1000) {
    $hitratio .= "<i class='fas fa-star fa-fw text-warning'></i>";
} else {
    $hitratio = $LANG['m_ibconversion'] . ': ';
    $hitratio .= ($myrefonly > 0) ? number_format($prcnthit, 2) . '%' : '0%';
}
$myewallet = floatval($mbrstr['ewallet']);

// ---

$condition = ' AND txtoid = "' . $mbrstr['id'] . '" AND txstatus = "1" AND txtoken NOT LIKE "%|REG:%" AND (txtoken LIKE "%|LCM:%" OR txtoken LIKE "%|WALT:IN|%") ';
$row = $db->getAllRecords(DB_TBLPREFIX . '_transactions', 'SUM(txamount) as totincome', $condition);
$mytxintotal = sprintf("%0.2f", $row[0]['totincome']);

$condition = ' AND txfromid = "' . $mbrstr['id'] . '" AND txstatus = "1" AND txtoken NOT LIKE "%|REG:%" AND (txtoken LIKE "%|WIDR:OUT|%" OR txtoken LIKE "%|WALT:OUT|%") ';
$row = $db->getAllRecords(DB_TBLPREFIX . '_transactions', 'SUM(txamount) as totincome', $condition);
$mytxouttotal = sprintf("%0.2f", $row[0]['totincome']);

$condition = ' AND (txtoid = "' . $mbrstr['id'] . '" OR txfromid = "' . $mbrstr['id'] . '") AND txstatus = "1" AND txtoken NOT LIKE "%|REG:%" ';
$sql = $db->getRecFrmQry("SELECT * FROM " . DB_TBLPREFIX . "_transactions WHERE 1 " . $condition . "");
$mytottrx = count($sql);

$mydiftrx = $mytxintotal - $mytxouttotal - floatval($mbrstr['ewallet']);
$mydiftrx = sprintf("%0.2f", $mydiftrx);
// ---

$mbrimgstr = ($mbrstr['mbr_image']) ? $mbrstr['mbr_image'] : $cfgrow['mbr_defaultimage'];

switch ($mbrstr['mbrstatus']) {
    case "1":
        $regbadge_class = "badge-success";
        $regbadge_text = $LANG['g_active'];
        break;
    case "2":
        $regbadge_class = "badge-warning";
        $regbadge_text = $LANG['g_limited'];
        break;
    case "3":
        $regbadge_class = "badge-danger";
        $regbadge_text = $LANG['g_pending'];
        break;
    default:
        $regbadge_class = "badge-light";
        $regbadge_text = $LANG['g_inactive'];
}
$myregstatus = "<div class='badge {$regbadge_class}'>{$regbadge_text}</div>";

if (intval($mbrstr['mpid']) > 0) {
    $myplanpay = '';
    switch ($mbrstr['mpstatus']) {
        case "1":
            $badge_class = "badge-success";
            $badge_text = $LANG['g_active'];
            break;
        case "2":
            $badge_class = "badge-warning";
            $badge_text = $LANG['g_expire'];
            break;
        case "3":
            $badge_class = "badge-danger";
            $badge_text = $LANG['g_pending'];
            break;
        default:
            $badge_class = "badge-primary";
            $badge_text = "";
            $myplanpay = "<a href='index.php?hal=planpay' class='btn btn-danger btn-round'>{$LANG['m_makepayment']}</a>";
    }
    $myplanstatus = "<div class='badge {$badge_class}'>{$badge_text}</div>" . $myplanpay;
    $reg_date = formatdate($mbrstr['reg_date']);
    $regsince = "<span class='text-muted'>{$LANG['m_registeredsince']}</span> {$reg_date}";
} else {
    $myplanstatus = "<a href='index.php?hal=planreg' class='btn btn-primary btn-round'>{$LANG['g_register']}</a>";
    $regsince = '';
}

// ---

$sprstr = getmbrinfo($mbrstr['idspr']);
$sprstr['fullname'] = $sprstr['firstname'] . ' ' . $sprstr['lastname'];
$sprimgstr = ($sprstr['mbr_image']) ? $sprstr['mbr_image'] : $cfgrow['mbr_defaultimage'];
$spremailstr = (strlen($sprstr['email']) > 23) ? substr($sprstr['email'], 0, 20) . '...' : $sprstr['email'];
$sprphonestr = ($sprstr['phone']) ? $sprstr['phone'] : '-';
$sprcountrystr = ucwords(strtolower($country_array[$sprstr['country']]));
$sprstatusstr = badgembrplanstatus($sprstr['mbrstatus'], $sprstr['mpstatus']);
$spraboutstr = ($sprstr['mbr_intro']) ? "<blockquote class='text-small'>" . base64_decode($sprstr['mbr_intro']) . "</blockquote>" : '';

// ---

$recentrefl = '';
$condition = " AND sprlist LIKE '%:{$mbrstr['mpid']}|%' AND mppid = '{$mbrstr['mppid']}' AND id != {$mbrstr['id']}";
$userData = $db->getRecFrmQry("SELECT * FROM " . DB_TBLPREFIX . "_mbrs LEFT JOIN " . DB_TBLPREFIX . "_mbrplans ON id = idmbr WHERE 1 " . $condition . " ORDER BY mpid DESC LIMIT 9");
if (count($userData) > 0) {
    foreach ($userData as $val) {
        $sestime = strtotime($val['reg_utctime']);
        $timejoin = time_since($sestime);
        $dlnimgfile = ($val['mbr_image']) ? $val['mbr_image'] : $cfgrow['mbr_defaultimage'];
        $val['fullname'] = $val['firstname'] . ' ' . $val['lastname'];
        $stremail = (strlen($val['email']) > 24) ? substr($val['email'], 0, 21) . '...' : $val['email'];
        $recentrefl .= "<li class='media'>
                            <img class='mr-3 rounded-circle' width='48' src='{$dlnimgfile}' alt='avatar'>
                            <div class='media-body'>
                                <div class='float-right text-small text-success'>{$timejoin} ago</div>
                                <div class='media-title'>{$val['username']}</div>
                                <span class='text-small text-muted'><div>{$val['fullname']}</div><div data-toggle='tooltip' title='{$val['email']}'>{$stremail}</div></span>
                            </div>
                       </li>";
    }
} else {
    $recentrefl = '<div class="text-center mt-4 text-muted">
                        <div>
                            <i class="fa fa-3x fa-question-circle"></i>
                        </div>
                        <div>' . $LANG['g_norecordinfo'] . '</div>
                   </div>';
}

$expdatestr = ($mbrstr['reg_expd'] > $mbrstr['reg_date']) ? 'Expiration: ' . formatdate($mbrstr['reg_expd']) : '';
$istrial = get_optionvals($mbrstr['mptoken'], 'istrial');

$mysiteurl = $cfgrow['site_url'] . '/' . UIDFOLDER_NAME . '/' . $mbrstr['username'];
if ($cfgtoken['isrefqrcode'] == '1') {
    // or use https://chart.apis.google.com/ instead
    $google_chart_api_url = "https://chart.googleapis.com/chart?chs={$cfgrow['mbrmax_image_width']}x{$cfgrow['mbrmax_image_width']}&cht=qr&chl=" . urlencode($mysiteurl) . "&choe=UTF-8";
    $mysiteurlqr = "<img class='mr-3 rounded-circle img-responsive' src='" . $google_chart_api_url . "' alt=''>";
}
?>

<div class="section-header">
    <h1><i class="fa fa-fw fa-chart-line"></i> <?php echo myvalidate($LANG['g_dashboardtitle']); ?></h1>
</div>

<div class="section-body">
    <div class="row">
        <div class="col-lg-4 col-md-6 col-sm-6 col-12">
            <div class="card card-statistic-1">
                <div class="card-icon bg-info">
                    <i class="far fa-paper-plane"></i>
                </div>
                <div class="card-wrap">
                    <div class="card-header">
                        <h4><?php echo myvalidate($LANG['g_hits']); ?></h4>
                    </div>
                    <div class="card-body">
                        <?php echo myvalidate($mbrstr['hits']); ?>
                        <div class="text-small text-muted">
                            <?php echo myvalidate($hitratio); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 col-sm-6 col-12">
            <div class="card card-statistic-1">
                <a href="index.php?hal=userlist&clist=1&clisti=1">
                    <div class="card-icon bg-info">
                        <i class="far fa-handshake"></i>
                    </div>
                </a>
                <div class="card-wrap">
                    <div class="card-header">
                        <h4><?php echo myvalidate($LANG['g_referrals']); ?></h4>
                    </div>
                    <div class="card-body">
                        <?php echo myvalidate($myreftotal); ?>
                        <div class="text-small text-muted">
                            <?php echo myvalidate($LANG['m_ibpersonal']); ?>: <?php echo myvalidate($myrefonly); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6 col-sm-6 col-12">
            <div class="card card-statistic-1">
                <a href="index.php?hal=historylist&dohal=clear">
                    <div class="card-icon bg-warning">
                        <i class="far fa-money-bill-alt"></i>
                    </div>
                </a>
                <div class="card-wrap">
                    <div class="card-header">
                        <h4><?php echo myvalidate($LANG['g_earning']); ?></h4>
                    </div>
                    <div class="card-body">
                        <?php echo myvalidate($bpprow['currencysym'] . $myincometotal); ?>
                        <div class="text-small text-muted">
                            <?php echo myvalidate($LANG['m_ibwallet']); ?>: <?php echo myvalidate($bpprow['currencysym'] . $myewallet); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-8 col-md-12 col-12 col-sm-12">
            <?php
            $unpaidtxid = get_unpaidtxid($mbrstr);
            $myplanstatusbtn = ($unpaidtxid > 0) ? "<a href='index.php?hal=planpay' class='btn btn-danger btn-round'>{$LANG['m_makepayment']}</a>" : $myplanstatus;
            if (intval($mbrstr['mpid']) < 1 || intval($mbrstr['mpstatus']) != 1 || $unpaidtxid > 0) {
                ?>
                <div class="alert alert-light alert-has-icon">
                    <div class="alert-icon text-danger"><i class="far fa-bell"></i></div>
                    <div class="alert-body text-danger">
                        <div class="alert-title"><?php echo myvalidate($LANG['m_notice']); ?></div>
                        <?php
                        if (intval($mbrstr['mpid']) < 1) {
                            echo $LANG['m_noticereg'] . " <strong>{$bpprow['ppname']}</strong>.";
                        } elseif ($unpaidtxid > 0) {
                            echo $LANG['m_noticerepay'];
                        } elseif (intval($mbrstr['mpstatus']) != 1) {
                            echo $LANG['m_noticepay'];
                        }
                        ?>
                        <div class="float-right mt-4">
                            <?php echo myvalidate($myplanstatusbtn); ?>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>

            <div class="card">
                <div class="card-header">
                    <h4><?php echo myvalidate($LANG['g_accoverview']); ?></h4>
                    <div class="card-header-action">
                        <?php echo myvalidate($myregstatus); ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="summary-item">
                        <ul class="list-unstyled list-unstyled-border">
                            <li class="media">
                                <div class="media-body">
                                    <div class="media-title">
                                        <img class='mr-3 rounded-circle img-responsive' width='<?php echo myvalidate($cfgrow['mbrmax_image_width']); ?>' height='<?php echo myvalidate($cfgrow['mbrmax_image_height']); ?>' src='<?php echo myvalidate($mbrimgstr); ?>' alt='<?php echo myvalidate($mbrstr['username']); ?>'>
                                        <div class="float-right"><?php echo myvalidate($mysiteurlqr); ?></div>
                                    </div>
                                </div>
                            </li>
                            <li class="media">
                                <div class="media-body">
                                    <div class="text-small"><?php echo myvalidate($LANG['g_registered']); ?></div>
                                    <div class="media-title"><?php echo formatdate($mbrstr['in_date']); ?></div>
                                </div>
                            </li>
                            <li class="media">
                                <div class="media-body">
                                    <div class="text-small"><?php echo myvalidate($LANG['g_name']); ?></div>
                                    <div class="media-title"><?php echo myvalidate($mbrstr['fullname'] . ' (' . $mbrstr['email'] . ')'); ?></div>
                                </div>
                            </li>
                            <?php
                            if (intval($mbrstr['mpstatus']) == 1 && $cfgtoken['disreflink'] != 1) {
                                ?>
                                <li class="media">
                                    <div class="media-body">
                                        <div class="text-small"><?php echo myvalidate($LANG['g_refurl']); ?> <a href="<?php echo myvalidate($mysiteurl); ?>" target="_blank" class="d-sm-none" data-toggle="tooltip" title="<?php echo myvalidate($mysiteurl); ?>"><span class="text-small"><i class="fa fa-fw fa-external-link-alt"></i></span></a></div>
                                        <div class="media-title">
                                            <a class="d-none d-sm-block" href="<?php echo myvalidate($mysiteurl); ?>" target="_blank" data-toggle="tooltip" title="<?php echo myvalidate($mysiteurl); ?>">
                                                <?php echo myvalidate($mysiteurl); ?>
                                            </a>
                                            <div class="d-sm-none">
                                                <div class="form-group">
                                                    <div class="input-group mb-3">
                                                        <input type="text" class="form-control form-control-sm" value="<?php echo myvalidate($mysiteurl); ?>" id="myrefurlid" readonly>
                                                        <div class="input-group-append">
                                                            <button class="btn btn-primary btn-sm" type="button" onclick="copyInputText('myrefurlid')"><i class="fa fa-copy fa-fw"></i></button>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <?php
                            }
                            ?>
                        </ul>
                    </div>
                </div>
            </div>

            <?php
            if (intval($mbrstr['mpstatus']) == 1) {
                ?>
                <div class="card">
                    <div class="card-header">
                        <h4><?php echo myvalidate($LANG['g_performance']); ?></h4>
                    </div>
                    <div class="card-body">
                        <canvas id="myChart" height="182"></canvas>
                    </div>
                </div>
                <?php
            }
            ?>

            <div class="card">
                <div class="card-header">
                    <h4><?php echo myvalidate($LANG['g_membership']); ?></h4>
                    <div class="card-header-action">
                        <?php echo myvalidate($myplanstatus); ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="summary">
                        <div class="summary-info">
                            <h4><span class="text-success"><i class="fas fa-caret-up"></i></span><?php echo myvalidate($bpprow['currencysym'] . $mytxintotal); ?> <span class="text-danger"><i class="fas fa-caret-down"></i></span><?php echo myvalidate($bpprow['currencysym'] . $mytxouttotal); ?> <small><span class="text-warning"><i class="far fa-pause-circle"></i></span><?php echo myvalidate($bpprow['currencysym'] . $mydiftrx); ?></small></h4>
                            <div class="text-muted">from total <?php echo myvalidate($mytottrx); ?> transactions</div>
                            <h3 class="mt-2"><span class="text-info"><i class="fas fa-wallet"></i></span><?php echo myvalidate($bpprow['currencysym'] . $mbrstr['ewallet'] . ' ' . $bpprow['currencycode']); ?></h3>
                            <div class="d-block mt-2">
                                <a href="index.php?hal=historylist">View Details</a>
                            </div>
                        </div>
                        <div class="summary-item">
                            <h6><?php echo myvalidate($regsince); ?></h6>
                            <ul class="list-unstyled list-unstyled-border">
                                <li class="media">
                                    <a href="index.php?hal=planreg">
                                        <img class="mr-3 rounded" width="50" src="<?php echo myvalidate($planlogo); ?>" alt="Membership">
                                    </a>
                                    <div class="media-body">
                                        <div class="media-right"><?php echo myvalidate($bpprow['currencysym'] . $bpprow['regfee'] . ' ' . $bpprow['currencycode']); ?></div>
                                        <div class="media-title"><a href="index.php?hal=planreg"><?php echo myvalidate($bpprow['ppname']); ?></a></div>
                                        <h6 class="text-small">
                                            <?php
                                            if ($mbrstr['reg_expd'] < date("Y-m-d")) {
                                                ?>
                                                <span class="badge badge-danger"><?php echo myvalidate($expdatestr); ?></span>
                                                <?php
                                            } else {
                                                $renewHash = md5($mbrstr['mpid'] . $mbrstr['reg_expd']);
                                                ?>
                                                <span class="badge badge-info"><?php echo myvalidate($expdatestr); ?></span>
                                                <?php
                                                if ($cfgtoken['isadvrenew'] == '1') {
                                                    ?>
                                                    <div class="float-md-right">
                                                        <a href="javascript:;" data-href="index.php?hal=dashboard&renewHash=<?php echo myvalidate($renewHash); ?>&renewId=<?php echo myvalidate($mbrstr['mpid']); ?>&redir=planpay" class="btn btn-sm btn-primary bootboxconfirm" data-poptitle="Current Expiration: <?php echo formatdate($mbrstr['reg_expd']); ?>" data-popmsg="Are you sure want to renew your membership in advance?" data-toggle="tooltip" title="Manual renewal for <?php echo myvalidate($mbrstr['username']); ?>"><i class="fa fa-fw fa-sync-alt"></i> Renew</a>
                                                    </div>
                                                    <?php
                                                }
                                            }
                                            if ($istrial > 0) {
                                                ?>
                                                <span class="badge badge-danger">Trial</span>
                                                <?php
                                            }
                                            ?>
                                        </h6>
                                        <div class="text-muted text-small"><?php echo myvalidate($bpprow['planinfo']); ?></div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 col-md-12 col-12 col-sm-12">
            <?php
            if ($mbrstr['idspr'] > 0) {
                ?>
                <div class="card">
                    <div class="card-header">
                        <h4><?php echo myvalidate($LANG['g_mysponsor']); ?></h4>
                        <div class="card-header-action">
                            <?php echo myvalidate($sprstatusstr); ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled list-unstyled-border">
                            <li class='media'>
                                <img class='mr-3 rounded-circle' width='48' src='<?php echo myvalidate($sprimgstr); ?>' alt='avatar'>
                                <div class='media-body'>
                                    <div class='float-right text-small text-success'></div>
                                    <div class='media-title'><?php echo myvalidate($sprstr['username']); ?></div>
                                    <span class='text-small text-muted'>
                                        <div><?php echo myvalidate($sprstr['fullname']); ?></div>
                                        <div data-toggle='tooltip' title='<?php echo myvalidate($sprstr['email']); ?>'><i class="fa fa-fw fa-envelope"></i> <?php echo myvalidate($spremailstr); ?></div>
                                        <div><i class="fa fa-fw fa-mobile-alt"></i> <?php echo myvalidate($sprphonestr); ?></div>
                                        <div><?php echo myvalidate($sprcountrystr); ?></div>
                                    </span>
                                </div>
                            </li>
                        </ul>
                        <div><?php echo myvalidate($spraboutstr); ?></div>
                    </div>
                </div>
                <?php
            }
            ?>

            <div class="card">
                <div class="card-header">
                    <h4><?php echo myvalidate($LANG['g_recentref']); ?></h4>
                    <div class="card-header-action">
                        <a href="index.php?hal=userlist" class="btn btn-primary" data-toggle="tooltip" title="View All"><i class="fa fa-ellipsis-h"></i></a>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled list-unstyled-border">
                        <?php echo myvalidate($recentrefl); ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Template JS File -->
<script src="../assets/js/chart.min.js"></script>

<!-- Page Specific JS File -->
<script src="../assets/js/ucpchart.js"></script>

