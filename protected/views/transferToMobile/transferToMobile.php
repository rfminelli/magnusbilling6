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

if (strlen($modelTransferToMobile->country) > 4):
    $fieldOption['readonly'] = true;
endif;
?>

<?php if (strlen($modelTransferToMobile->country) > 4): ?>
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
        'disabled' => strlen($modelTransferToMobile->country) > 4,
    ));
?>
	<?php echo $form->error($modelTransferToMobile, 'method') ?>

	</div>
</div>



<?php endif?>


<br>



<div class="field">
	<?php echo $form->labelEx($modelTransferToMobile, Yii::t('yii', 'Number')) ?>
	<?php echo $form->textField($modelTransferToMobile, 'number', $fieldOption) ?>
	<?php echo $form->error($modelTransferToMobile, 'number') ?>
	<p class="hint"><?php echo Yii::t('yii', 'Enter your') . ' ' . Yii::t('yii', 'number') ?></p>
</div>

<?php if (strlen($modelTransferToMobile->country) > 4): ?>
	<div class="field">
		<?php echo $form->labelEx($modelTransferToMobile, Yii::t('yii', 'country')) ?>
		<?php echo $form->textField($modelTransferToMobile, 'country', $fieldOption) ?>
		<?php echo $form->error($modelTransferToMobile, 'country') ?>
		<p class="hint"><?php echo Yii::t('yii', 'Enter your') . ' ' . Yii::t('yii', 'country') ?></p>
	</div>


	<div class="field">
		<?php echo $form->labelEx($modelTransferToMobile, Yii::t('yii', 'operator')) ?>
		<?php echo $form->textField($modelTransferToMobile, 'operator', $fieldOption) ?>
		<?php echo $form->error($modelTransferToMobile, 'operator') ?>
		<p class="hint"><?php echo Yii::t('yii', 'Enter your') . ' ' . Yii::t('yii', 'operator') ?></p>
	</div>



	<div class="field">
		<?php echo $form->labelEx($modelTransferToMobile, Yii::t('yii', 'Amount')) ?>
		<div class="styled-select">
			<?php

$buttonName = 'Confirm';

echo $form->dropDownList($modelTransferToMobile, 'amountValues',
    Yii::app()->session['amounts'],
    array(
        'empty'    => Yii::t('yii', 'Select the amount'),
        'disabled' => false,
        'onchange' => 'showPrice(' . $modelTransferToMobile->transfer_show_selling_price . ')',
        'id'       => 'amountfiel',
    ));
?>
		<?php echo $form->error($modelTransferToMobile, 'amount') ?>

		</div>
	</div>

<?php endif?>
<div class='field' id="divsellingPrice" style="display:none; border:0">
	<label>Selling Price</label>
	<div id="sellingPrice" class="input" style="border:0; width:650px" ></div>
</div>

<div class="controls" id="sendButton">
<?php echo CHtml::submitButton(Yii::t('yii', $buttonName), array(
    'class'   => 'button',
    'onclick' => "button2()",
    'id'      => 'secondButton'));
?>
<input class="button" style="width: 80px;" onclick="window.location='../../index.php/transferToMobile/read';" value="Cancel">

</div>
<div class="controls" id="buttondivWait"></div>
<?php

$this->endWidget();?>


<script type="text/javascript">
	function button2(buttonId) {
	  	document.getElementById("sendButton").style.display = 'none';
	  	document.getElementById("buttondivWait").innerHTML = "<font color = green>Wait! </font>";
	}
	function showPrice(argument) {

		text = document.getElementById('amountfiel').options[document.getElementById('amountfiel').selectedIndex].text;

		var valueAmout = text.split(' ');
		if (argument < 10) {
			fee = Number('1.0'+argument);
		}else{
			fee = Number('1.'+argument);
		}
		var showprice = Number(valueAmout[4] * fee);

		newText = '<font color=blue size=7><b>'+valueAmout[3]+ ' '+showprice.toFixed(2);+'</b></font>'
		document.getElementById('divsellingPrice').style.display = 'inline';
		document.getElementById('sellingPrice').innerHTML = newText;
	}
</script>

