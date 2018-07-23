<?php
/**
 * =======================================
 * ###################################
 * MagnusBilling
 *
 * @package MagnusBilling
 * @author Adilson Leffa Magnus.
 * @copyright Copyright (C) 2005 - 2018 MagnusSolution. All rights reserved.
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
<form method="POST" action="https://pagseguro.uol.com.br/v2/checkout/payment.html" target="_parent" id="buyForm">
    <input type="hidden" name="receiverEmail" value="<?php echo $modelMethodPay->username ?>"  />
    <input type="hidden" name="shippingType" value="3"  />
    <input type="hidden" name="currency" value="BRL"  />
    <input type="hidden" name="shippingAddressPostalCode" value="<?php echo $modelUser->zipcode; ?>"  />
    <input type="hidden" name="shippingAddressStreet" value="<?php echo $modelUser->address; ?>"  />
    <input type="hidden" name="shippingAddressNumber" value="4875"  />
    <input type="hidden" name="shippingAddressDistrict" value="centro"  />
    <input type="hidden" name="shippingAddressComplement" value=""  />
    <input type="hidden" name="shippingAddressCity" value="<?php echo $modelUser->city; ?>"  />
    <input type="hidden" name="shippingAddressState" value="<?php echo $modelUser->state; ?>"  />
    <input type="hidden" name="shippingAddressCountry" value="<?php echo $modelUser->country; ?>"  />
    <input type="hidden" name="senderAreaCode" value="11"  />
    <input type="hidden" name="senderPhone" value="40040435"  />
    <input type="hidden" name="senderEmail" value="<?php echo $modelUser->email; ?>"  />
    <input type="hidden" name="senderCPF" value="<?php echo $modelUser->doc; ?>"  />
    <input type="hidden" name="senderName" value="<?php echo $modelUser->firstname . ' ' . $modelUser->lastname ?>"  />
    <input type="hidden" name="holderCPF" value="<?php echo $modelUser->doc; ?>"  />
    <input type="hidden" name="itemId1" value="<?php echo $reference; ?>"  />
    <input type="hidden" name="itemDescription1" value="Credito voip"  />
    <input type="hidden" name="itemQuantity1" value="1"  />
    <input type="hidden" name="itemAmount1" value="<?php echo $_GET['amount']; ?>"  />
</form>