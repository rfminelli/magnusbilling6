<?php
/**
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2016 MagnusBilling. All rights reserved.
 * ###################################
 *
 * This software is released under the terms of the GNU Lesser General Public License v2.1
 * A copy of which is available from http://www.gnu.org/copyleft/lesser.html
 *
 * Please submit bug reports, patches, etc to https://github.com/magnusbilling/mbilling/issues
 * =======================================
 * Magnusbilling.com <info@magnusbilling.com>
 *
 */
?>
<div id="load" ><?php echo Yii::t('yii', 'Please wait while loading...') ?></div>
<script languaje="JavaScript">
	window.onload = function () {
        var form = document.getElementById("buyForm");
        form.submit();
    };
</script>
<?php
if (Yii::app()->session['currency'] == 'U$S') {
    $currency = 'USD';
} else if (Yii::app()->session['currency'] == 'R$') {
    $currency = 'BRL';
} elseif (Yii::app()->session['currency'] == '€') {
    $currency = 'EUR';
} elseif (Yii::app()->session['currency'] == 'AUD$') {
    $currency = 'AUD';
} else if (Yii::app()->session['currency'] == '$') {
    $currency = 'USD';
} else {
    $currency = Yii::app()->session['currency'];
}
?>

<form method="GET" action="<?php echo $modelMethodPay->url ?>" target="_parent" id="buyForm">
<input type="text" name="precio" value="<?php echo $_GET['amount'] * 1.27; ?>">
<input type="hidden" name="id" value="<?php echo $modelMethodPay->username ?>">

<input type="hidden" name="codigo" value="<?php echo $modelUser->username ?>">
<input type="hidden" name="venc" value="7">
<input type="hidden" name="concepto" value="<?php echo $reference; ?>">
<input type="text" name="moneda" value="<?php echo $currency; ?>">
</form>