<link rel="stylesheet" type="text/css" href="../../resources/css/signup.css" />

<?php

$form = $this->beginWidget('CActiveForm', array(
    'id'                   => 'contactform',
    'htmlOptions'          => array('class' => 'rounded'),
    'enableAjaxValidation' => false,
    'clientOptions'        => array('validateOnSubmit' => true),
    'errorMessageCssClass' => 'error',
));
?>

<br/>


<?php

$buttonName = 'Next';

$fieldOption = array('class' => 'input');

if (strlen($modelTransferToMobile->number) > 10):
    $fieldOption['readonly'] = true;
endif;
?>

<?php if (strlen($modelTransferToMobile->number) > 10): ?>
	<div class="field">
	<?php echo $form->labelEx($modelTransferToMobile, Yii::t('yii', 'Method')) ?>
	<?php echo $form->textField($modelTransferToMobile, 'method', $fieldOption) ?>
	<?php echo $form->error($modelTransferToMobile, 'method') ?>
	<p class="hint"><?php echo Yii::t('yii', 'Enter your') . ' ' . Yii::t('yii', 'Method') ?></p>
</div>

<?php else: ?>

<div class="field">
	<?php echo $form->labelEx($modelTransferToMobile, Yii::t('yii', 'Method')) ?>
	<div class="styled-select">
		<?php

echo $form->dropDownList($modelTransferToMobile, 'method',
    $methods,
    array(
        'empty'    => Yii::t('yii', 'Select the method'),
        'disabled' => strlen($modelTransferToMobile->number) > 4,
    ));
?>
	<?php echo $form->error($modelTransferToMobile, 'method') ?>

	</div>
</div>



<?php endif?>
<br>

<div class="field">
	<?php echo $form->labelEx($modelTransferToMobile, Yii::t('yii', 'Number')) ?>
	<?php echo $form->numberField($modelTransferToMobile, 'number', $fieldOption) ?>
	<?php echo $form->error($modelTransferToMobile, 'number') ?>
	<p class="hint"><?php echo Yii::t('yii', 'Enter your') . ' ' . Yii::t('yii', 'number') ?></p>
</div>

<?php if (strlen($modelTransferToMobile->number) > 10): ?>
	<div class="field">
		<?php echo $form->labelEx($modelTransferToMobile, Yii::t('yii', 'Amount')) ?>
		<?php echo $form->numberField($modelTransferToMobile, 'amountValues',
    array(
        'class'   => 'input',
        'id'      => 'amountfiel',
        'onkeyup' => $modelTransferToMobile->transfer_show_selling_price > 0
        ? 'showPrice("' . $modelTransferToMobile->transfer_show_selling_price . '","' . $this->config['global']['BDService_cambio'] . '","' . $this->config['global']['fm_transfer_currency'] . '")'
        : '',
    )) ?>
		<?php echo $form->error($modelTransferToMobile, 'amountValues') ?>
		<p class="hint"><?php echo $amountDetails ?></p>
	</div>
<?php endif?>
<br>
<div class='field' id="divsellingPrice" style="display:none; border:0">
	<label>Selling Price</label>
	<div id="sellingPrice" class="input" style="border:0; width:650px" ></div>
</div>

<div class="controls" id="sendButton">
<?php echo CHtml::submitButton(Yii::t('yii', $buttonName), array(
    'class'   => 'button',
    'onclick' => 'button2()',
    'id'      => 'secondButton'));
?>
<input class="button" style="width: 80px;" onclick="window.location='../../index.php/transferToMobile/read';" value="Cancel">

</div>
<div class="controls" id="buttondivWait"></div>

<?php $this->endWidget();?>


<script type="text/javascript">
	function button2(buttonId) {
	  	document.getElementById("sendButton").style.display = 'none';
	  	document.getElementById("buttondivWait").innerHTML = "<font color = green>Wait! </font>";
	}
	function showPrice(transfer_show_selling_price,exchange,currency) {

		valueAmout = document.getElementById('amountfiel').value;

		//convert to eur
		valueAmout = valueAmout * exchange;

		fee = Number('1.'+transfer_show_selling_price);
		var showprice = Number(valueAmout * fee);

		newText = '<font color=blue size=7><b>'+currency+' '+showprice.toFixed(2);+'</b></font>'
		document.getElementById('divsellingPrice').style.display = 'inline';
		document.getElementById('sellingPrice').innerHTML = newText;
	}
</script>

