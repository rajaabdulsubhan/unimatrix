<?php
if (!defined('OK_LOADME')) {
    die('o o p s !');
}

if ($FORM['dohal'] == 'clear') {
    $_SESSION['filteruid'] = '';
    redirpageto('index.php?hal=historylist');
    exit;
}
if ($FORM['dohal'] == 'filter' && $FORM['dompid']) {
    $_SESSION['filteruid'] = $FORM['dompid'];
}

$condition = " AND txtoken NOT LIKE '%|WIDR:%'";

if (isset($FORM['txbatch']) and $FORM['txbatch'] != "") {
    $condition .= ' AND txbatch LIKE "%' . $FORM['txbatch'] . '%" ';
}
if (isset($FORM['txmemo']) and $FORM['txmemo'] != "") {
    $condition .= ' AND txmemo LIKE "%' . $FORM['txmemo'] . '%" ';
}
if (isset($FORM['txadminfo']) and $FORM['txadminfo'] != "") {
    $condition .= ' AND (txtoken LIKE "%' . $FORM['txtoken'] . '%" OR txadminfo LIKE "%' . $FORM['txadminfo'] . '%") ';
}

$clistid = ($_SESSION['clistview'] > 0) ? $_SESSION['clistview'] : $mbrstr['mpid'];

if ($_SESSION['filteruid']) {
    $filteruid = intval($_SESSION['filteruid']);
    $btnclorclear = 'btn-danger';
    $filterusrstr = getmbrinfo('', '', $filteruid);
    $clearfilterusrstr = " filter for member ({$filterusrstr['username']})";
    $filterusrnow = " <span class='text-info'>" . strtoupper($filterusrstr['username']) . "</span>";
} else {
    $filteruid = intval($mbrstr['id']);
    $btnclorclear = 'btn-warning';
    $filterusrstr = getmbrinfo('', '', $filteruid);
    $clearfilterusrstr = $filterusrnow = "";
}

if ($_SESSION['filteruid'] && strpos($filterusrstr['sprlist'], ":{$clistid}|") === false) {
    $_SESSION['filteruid'] = "";
    redirpageto('index.php?hal=historylist');
    exit;
} else {
    $condition .= " AND (txfromid = '$filteruid' OR txtoid = '$filteruid') ";
}

$tblshort_arr = array("txdatetm", "txbatch", "txmemo", "txamount");
$tblshort = dborder_arr($tblshort_arr, $FORM['_stbel'], $FORM['_stype']);
if ($FORM['_stbel'] != '' && (in_array($FORM['_stbel'], $tblshort_arr))) {
    $sqlshort = ($FORM['_stype'] == 'up') ? " ORDER BY {$FORM['_stbel']} DESC " : " ORDER BY {$FORM['_stbel']} ASC ";
} else {
    $sqlshort = " ORDER BY txid DESC ";
}

//Main queries
$sql = $db->getRecFrmQry("SELECT * FROM " . DB_TBLPREFIX . "_transactions WHERE 1 " . $condition . "");
$pages->items_total = count($sql);
$pages->mid_range = 3;
$pages->paginate();

$userData = $db->getRecFrmQry("SELECT * FROM " . DB_TBLPREFIX . "_transactions WHERE 1 " . $condition . $sqlshort . $pages->limit . "");
?>

<div class="section-header">
    <h1><i class="fa fa-fw fa-cash-register"></i> <?php echo myvalidate($LANG['g_historylist']); ?></h1>
</div>

<div class="section-body">

    <form method="get">
        <div class="card card-primary">
            <div class="card-header">
                <h4><i class="fa fa-fw fa-search"></i> <?php echo myvalidate($LANG['g_findhistory'] . $filterusrnow); ?></h4>
            </div>
            <div class="card-body">
                <div class="row">

                    <div class="col-sm-4">
                        <div class="form-group">
                            <label><?php echo myvalidate($LANG['g_transactionid']); ?></label>
                            <input type="text" name="txbatch" id="txbatch" class="form-control" value="<?php echo isset($FORM['txbatch']) ? $FORM['txbatch'] : '' ?>" placeholder="Transaction ID">
                        </div>
                    </div>

                    <div class="col-sm-4">
                        <div class="form-group">
                            <label><?php echo myvalidate($LANG['g_description']); ?></label>
                            <input type="text" name="txmemo" id="txmemo" class="form-control" value="<?php echo isset($FORM['txmemo']) ? $FORM['txmemo'] : '' ?>" placeholder="Transaction description">
                        </div>
                    </div>

                    <div class="col-sm-4">
                        <div class="form-group">
                            <label><?php echo myvalidate($LANG['g_keyword']); ?></label>
                            <input type="txadminfo" name="txadminfo" id="txadminfo" class="form-control" value="<?php echo isset($FORM['txadminfo']) ? $FORM['txadminfo'] : '' ?>" placeholder="Enter transaction keyword">
                        </div>
                    </div>

                </div>
            </div>
            <div class="card-footer bg-whitesmoke">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="float-md-right">
                            <a href="index.php?hal=historylist&dohal=clear" class="btn <?php echo myvalidate($btnclorclear); ?>"><i class="fa fa-fw fa-redo"></i> Clear<?php echo myvalidate($clearfilterusrstr); ?></a>
                            <button type="submit" name="submit" value="search" id="submit" class="btn btn-primary"><i class="fa fa-fw fa-search"></i> Search</button>
                        </div>
                        <div class="d-block d-sm-none">
                            &nbsp;
                        </div>
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
        <input type="hidden" name="hal" value="historylist">
    </form>

    <hr>

    <div class="clearfix"></div>

    <div class="row marginTop">
        <div class="col-sm-12 paddingLeft pagerfwt">
            <?php if ($pages->items_total > 0) { ?>
                <div class="row">
                    <div class="col-md-7">
                        <?php echo myvalidate($pages->display_pages()); ?>
                    </div>
                    <div class="col-md-5 text-right">
                        <span class="d-none d-md-block">
                            <?php echo myvalidate($pages->display_items_per_page()); ?>
                            <?php echo myvalidate($pages->display_jump_menu()); ?>
                            <?php echo myvalidate($pages->items_total()); ?>
                        </span>
                    </div>
                <?php } ?>
            </div>
            <div class="clearfix"></div>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="clearfix"></div>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col" nowrap><?php echo myvalidate($tblshort['txdatetm']); ?>Date</th>
                    <th scope="col" nowrap><?php echo myvalidate($tblshort['txbatch']); ?>Transaction ID</th>
                    <th scope="col" nowrap><?php echo myvalidate($tblshort['txmemo']); ?>Description</th>
                    <th scope="col" nowrap><?php echo myvalidate($tblshort['txamount']); ?>Amount</th>
                    <th scope="col" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (count($userData) > 0) {
                    $pgnow = ($FORM['page'] > 1) ? $FORM['page'] - 1 : 0;
                    $s = ($FORM['ipp'] > 0) ? $pgnow * $FORM['ipp'] : $pgnow * $cfgrow['maxpage'];
                    foreach ($userData as $val) {
                        $s++;
                        $hasdel = md5($val['txid'] . date("dH"));

                        if ($val['txfromid'] == $mbrstr['id']) {
                            $bletmark = '<span class="bullet text-danger"></span>';
                        } elseif ($val['txtoid'] == $mbrstr['id']) {
                            $bletmark = '<span class="bullet text-success"></span>';
                        }
                        if ($val['txstatus'] != 1) {
                            $bletmark = '<span class="bullet text-muted"></span>';
                        }

                        $txtoken = get_optionvals($val['txtoken']);
                        $txrefid = intval($txtoken['SRCIDMBR']);
                        $refuname = ($txrefid > 0) ? "<div>" . $LANG['g_reference'] . ' - ' . getusernameid($txrefid, 'username') . "</div>" : '';

                        if (strpos($val['txtoken'], '|NOTE:') !== false) {
                            $notestr = base64_decode($txtoken['NOTE']);
                            $txmemostr = "<span class='text-info'>{$notestr}</span>";
                        } elseif (strpos($val['txtoken'], '|WALT:IN|') !== false) {
                            $txmemostr = "<span class='text-success'>{$LANG['g_walletcredit']}</span>";
                        } elseif (strpos($val['txtoken'], '|WALT:OUT|') !== false) {
                            $txmemostr = "<span class='text-danger'>{$LANG['g_walletdebit']}</span>";
                        } elseif (strpos($val['txtoken'], '|WIDR:') !== false) {
                            $txmemostr = "<span class='text-warning'>{$LANG['g_withdrawreq']}</span>";
                        } else {
                            $txmemostr = '';
                        }

                        $overview = "<label>Info</label><div>" . $val['adminfo'] . "</div>";
                        ?>
                        <tr>

                            <th scope="row"><?php echo myvalidate($s); ?></th>
                            <td data-toggle="tooltip" title="<?php echo myvalidate($val['txdatetm']); ?>"><?php echo formatdate($val['txdatetm']); ?></td>
                            <td><?php echo ($val['txbatch']) ? myvalidate($val['txbatch']) : '-'; ?></td>
                            <td><?php echo myvalidate($val['txmemo']); ?></td>
                            <td class="text-right"><?php echo myvalidate($val['txamount'] . $bletmark); ?></td>
                            <td align="center" nowrap>
                                <a href="javascript:;"
                                   class="btn btn-sm btn-secondary"
                                   data-html="true"
                                   data-toggle="popover"
                                   data-trigger="hover"
                                   data-placement="left" 
                                   title=""
                                   data-content="<h6><?php echo myvalidate($val['txbatch']); ?></h6><?php echo myvalidate($refuname); ?><div class='mt-2'><?php echo myvalidate($txmemostr); ?></div>">
                                    <i class="far fa-fw fa-question-circle"></i>
                                </a>
                            </td>

                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="6">
                            <div class="text-center mt-4 text-muted">
                                <div>
                                    <i class="fa fa-3x fa-question-circle"></i>
                                </div>
                                <div><?php echo myvalidate($LANG['g_norecordinfo']); ?></div>
                            </div>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="clearfix"></div>

    <div class="row marginTop">
        <div class="col-sm-12 paddingLeft pagerfwt">
            <?php if ($pages->items_total > 0) { ?>
                <div class="row">
                    <div class="col-md-7">
                        <?php echo myvalidate($pages->display_pages()); ?>
                    </div>
                    <div class="col-md-5 text-right">
                        <span class="d-none d-md-block">
                            <?php echo myvalidate($pages->display_items_per_page()); ?>
                            <?php echo myvalidate($pages->display_jump_menu()); ?>
                            <?php echo myvalidate($pages->items_total()); ?>
                        </span>
                    </div>
                <?php } ?>
            </div>
            <div class="clearfix"></div>
        </div>
        <div class="clearfix"></div>
    </div>

</div>
