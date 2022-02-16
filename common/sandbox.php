<?php

include_once('init.loader.php');

function doipnbox($txmpid, $payamount, $paygate, $txbatch, $redirurl, $ipnreturn = '', $skipamount = 0, $addtoken = '') {
    global $db, $cfgrow, $bpprow, $FORM;

    $defredirurl = $cfgrow['site_url'] . '/' . MBRFOLDER_NAME;
    $redirurl = ($redirurl != '') ? $redirurl : $defredirurl;
    $redirurl = ($redirurl == '-HTTPREF-') ? $_SERVER['HTTP_REFERER'] : $redirurl;

    $txtmstamp = date('Y-m-d H:i:s', time() + (3600 * $cfgrow['time_offset']));
    $sb_txmpidarr = explode('-', $txmpid);
    $txid = $sb_txmpidarr[0];
    $mpid = $sb_txmpidarr[1];

    // get member details
    $mbrstr = getmbrinfo('', '', $mpid);

    // get transaction details
    $condition = ' AND txid = "' . $txid . '" ';
    $row = $db->getAllRecords(DB_TBLPREFIX . '_transactions', '*', $condition);
    $trxstr = array();
    foreach ($row as $value) {
        $trxstr = array_merge($trxstr, $value);
    }
    $existingtxstatus = $trxstr['txstatus'];

    // remove proof of payment file
    $proofimg = get_optionvals($trxstr['txtoken'], 'proofimg');
    if ($proofimg) {
        $proofimgfile = INSTALL_PATH . '/assets/imagextra/' . $proofimg;
        if (file_exists($proofimgfile)) {
            unlink($proofimgfile);
            $trxstr['txtoken'] = put_optionvals($trxstr['txtoken'], 'proofimg', '');
            $data = array(
                'txtoken' => $trxstr['txtoken'],
            );
            $update = $db->update(DB_TBLPREFIX . '_transactions', $data, array('txid' => $trxstr['txid']));
        }
    }

    $txpaytype = $paygate;
    $txbatch = ($txbatch == '') ? strtoupper(date("RmdH-Di")) . $txid : $txbatch . '-' . $txid;
    if ($FORM['sb_type'] == 'payreg' && get_optionvals($trxstr['txtoken'], 'isapproved') == 1) {
        if ($ipnreturn == 'exit') {
            exit;
        } else if ($ipnreturn) {
            die($ipnreturn);
        } else {
            $_SESSION['dotoaster'] = "toastr.warning('Payment previously has been approved!', 'Info');";
            redirpageto($redirurl);
            exit;
        }
    }

    $txamount = $payamount;
    $is_ppsubscr = is_ppsubscr($mbrstr['mppid']);
    $reg_expd = ($is_ppsubscr && $mbrstr['reg_date'] > $mbrstr['reg_expd']) ? $mbrstr['reg_date'] : $mbrstr['reg_expd'];

    // is the trx exist [error...]
    $newtrxid = 0;
    $sqlstr = "SELECT * FROM " . DB_TBLPREFIX . "_transactions WHERE txfromid = '{$mbrstr['id']}' AND txppid = '{$mbrstr['mppid']}' AND ((txpaytype LIKE '{$txpaytype}' AND txbatch LIKE '{$txbatch}') OR txstatus = '0')";
    $sql = $db->getRecFrmQry($sqlstr);

    if ($is_ppsubscr && count($sql) < 1) {

        $data = array(
            'txdatetm' => $txtmstamp,
            'txfromid' => $mbrstr['id'],
            'txamount' => (float) $txamount,
            'txmemo' => 'Renewal fee',
            'txppid' => $mbrstr['mppid'],
            'txtoken' => "|RENEW:{$mbrstr['mpid']}|, |PREVEXP:{$reg_expd}|",
        );
        $insert = $db->insert(DB_TBLPREFIX . '_transactions', $data);
        $newtrxid = $db->lastInsertId();

        // get recent transaction details
        $condition = ' AND txid = "' . $newtrxid . '" ';
        $row = $db->getAllRecords(DB_TBLPREFIX . '_transactions', '*', $condition);
        $trxstr = array();
        foreach ($row as $value) {
            $trxstr = array_merge($trxstr, $value);
        }
    }
    // ---

    if (strpos($trxstr['txtoken'], '|RENEW:') !== false) {
        $expdarr = get_actdate($bpprow['expday'], $reg_expd);
        $reg_expd = $expdarr['next'];

        $mptoken = $mbrstr['mptoken'];
        $renewx = intval(get_optionvals($mptoken, 'renewx')) + 1;
        $mptoken = put_optionvals($mptoken, 'renewx', $renewx);
        $mptoken = put_optionvals($mptoken, 'istrial', '0');
    }

    printlog('sandbox.ipn/doipnbox', "txamount:{$trxstr['txamount']} ({$txamount}) / skipamount:{$skipamount}");

    if (($trxstr['txamount'] <= $txamount || $skipamount == 1) && get_optionvals($trxstr['txtoken'], 'isapproved') != 1) {
        // member
        $mptoken = put_optionvals($mptoken, 'isinitpay', '1');
        $data = array(
            'reg_expd' => $reg_expd,
            'mpstatus' => 1,
            'mptoken' => $mptoken,
            'rmdexp' => 0,
        );
        $update = $db->update(DB_TBLPREFIX . '_mbrplans', $data, array('mpid' => $mpid));

        // transaction
        $txtoken = ($update) ? put_optionvals($trxstr['txtoken'], 'isapproved', 1) : $trxstr['txtoken'];
        $txtoken = ($addtoken) ? $txtoken . ", {$addtoken}" : $txtoken;

        $amountadjt = $txamount - $trxstr['txamount'];
        $txadminfo = ($amountadjt != 0) ? 'Payment processor fee: ' . $amountadjt . chr(13) . $trxstr['txadminfo'] : $trxstr['txadminfo'];
        $data = array(
            'txpaytype' => $txpaytype,
            'txamount' => (float) $txamount,
            'txbatch' => $txbatch,
            'txtmstamp' => $txtmstamp,
            'txtoken' => $txtoken,
            'txstatus' => 1,
            'txadminfo' => $txadminfo,
        );
        $update = $db->update(DB_TBLPREFIX . '_transactions', $data, array('txid' => $trxstr['txid']));

        // process commission
        if ($update && ($newtrxid > 0 || $existingtxstatus == 0)) {
            // personal referral commission list
            $refstr = getmbrinfo($mbrstr['idref']);
            $reflist = dosprlist($refstr['mpid'], $refstr['sprlist'], $mbrstr['mpdepth']);
            $getcmlist = getcmlist($refstr['mpid'], $reflist, $bpprow['cmdrlist'], $mbrstr);
            addcmlist('Referrer Commission', 'PREF', $getcmlist, $mbrstr, $trxstr);

            // level commission list
            $sprstr = getmbrinfo($mbrstr['idspr']);
            $getcmlist = getcmlist($sprstr['mpid'], $mbrstr['sprlist'], $bpprow['cmlist'], $mbrstr);
            addcmlist('Level Commission', 'TIER', $getcmlist, $mbrstr, $trxstr);

            //process available commission to wallet
            dotrxwallet();

            // level complete reward list
            dolvldone($mbrstr, $trxstr);
        }

        if ($ipnreturn == 'exit') {
            exit;
        } else if ($ipnreturn) {
            die($ipnreturn);
        } else {
            $_SESSION['dotoaster'] = "toastr.success('Payment has been successfully approved!', 'Success');";
            redirpageto($redirurl);
            exit;
        }
    } else {
        die('Invalid Amount');
    }
}

function dotxsuspend($txmpid, $suspendbatch, $addtoken) {
    global $db, $cfgrow, $bpprow;

    if ($suspendbatch != 'cancel') {
        $txtmstamp = date('Y-m-d H:i:s', time() + (3600 * $cfgrow['time_offset']));
        $sb_txmpidarr = explode('-', $txmpid);
        $txid = $sb_txmpidarr[0];
        $mpid = $sb_txmpidarr[1];

        // get transaction details
        $condition = ($suspendbatch != '') ? ' AND txbatch = "' . $suspendbatch . '" ' : ' AND txid = "' . $txid . '" ';
        $row = $db->getAllRecords(DB_TBLPREFIX . '_transactions', '*', $condition);
        $trxstr = array();
        foreach ($row as $value) {
            $trxstr = array_merge($trxstr, $value);
        }

        if ($trxstr['txstatus'] != '3') {
            $txtoken = $trxstr['txtoken'] . ', ' . $addtoken;
            $data = array(
                'txtmstamp' => $txtmstamp,
                'txtoken' => $txtoken,
                'txstatus' => 3,
            );
            $update = $db->update(DB_TBLPREFIX . '_transactions', $data, array('txid' => $trxstr['txid']));
        }
    }
}

$paytoken = $payrow['paytoken'];
$isppsandbox = get_optionvals($paytoken, 'paypalsbox');

if ($FORM['sb_type'] == 'payreg') {
    $txmpid = do_prerenewtx($FORM['sb_txmpid'], $FORM['sb_mpstatus']);
    $payamount = $FORM['sb_amount'];
    $paybatch = $FORM['sb_batch'];
    $paygate = $FORM['sb_label'];
    $redirurl = $FORM['sb_success'];
    $ipnreturn = $FORM['sb_ipnreturn'];
    doipnbox($txmpid, $payamount, $paygate, $paybatch, $redirurl, $ipnreturn);
}

if ($FORM['custom'] != '' && $FORM['mc_currency'] == $bpprow['currencycode']) {
    $txmpid = $FORM['custom'];
    $skipamount = 0;
    if ($FORM['txn_type'] == 'web_accept') {
        $payamount = $FORM['mc_gross'];
    }
    $paygate = 'paypal';
    $paybatch = $FORM['txn_id'];

    require('paypal.ipv.php');
    $ipn = new PaypalIPN();
    if ($isppsandbox == 1) {

        // ---
        $postarr = array();
        foreach ($FORM as $key => $value) {
            $postarr[] = $key . '=' . $value;
        }
        printlog('sandbox.ipn/response', implode(', ', $postarr));
        $postarr = '';
        // ---

        $ipn->useSandbox();
    }
    $verified = $ipn->verifyIPN();

    printlog("sandbox.ipn/{$paygate}", "result:{$verified} / amount:{$payamount} / txn_type:{$FORM['txn_type']}");

    if ($verified) {
        if ($payamount < 0 || $FORM['txn_type'] == 'subscr_cancel' || $FORM['txn_type'] == 'subscr_eot') {
            $suspendbatch = ($payamount < 0) ? $paybatch : 'cancel';
            $payment_status = ($FORM['payment_status']) ? $FORM['payment_status'] : $FORM['txn_type'];
            dotxsuspend($txmpid, $suspendbatch, "|payment_status:{$payment_status}|, |amount:{$payamount}|");
        } else {
            //doipnbox($txmpid, $payamount, $paygate, $paybatch, '', 'OK', $skipamount);
            doipnbox($txmpid, $payamount, $paygate, $paybatch, '', 'exit', $skipamount);
        }
    }
}

if ($FORM['invoice'] != '') {
    $txmpid = $FORM['invoice'];
    $payamount = $FORM['amount1'];
    $paygate = 'coinpayments';

    $hmac_pass = 1;
    $merchant_id = base64_decode($payrow['coinpaymentsmercid']);
    $coinpaymentsipnkey = base64_decode($payrow['coinpaymentsipnkey']);

    $merchant = isset($FORM['merchant']) ? $FORM['merchant'] : '';
    if ($merchant != $merchant_id) {
        $hmac_pass = 0;
    }

    $request = file_get_contents('php://input');
    $hmac = hash_hmac("sha512", $request, $coinpaymentsipnkey);
    if ($coinpaymentsipnkey && $hmac_pass == 1 && $hmac != $_SERVER['HTTP_HMAC']) {
        $hmac_pass = 0;
    }

    printlog("sandbox.ipn/{$paygate}", "result:{$hmac_pass} / confirms:{$FORM['status']} / {$hmac}:{$_SERVER['HTTP_HMAC']}");

    if ($hmac_pass == 1 && $FORM['status'] == '1') {
        doipnbox($txmpid, $payamount, $paygate, $FORM['currency2'] . '-' . $FORM['txn_id'], '', 'IPN OK');
    }
}