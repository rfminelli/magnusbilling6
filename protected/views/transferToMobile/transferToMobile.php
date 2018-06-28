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

if (strlen($modelTransferToMobile->country) > 3):
    $fieldOption['readonly'] = true;
endif;
?>

<?php if (strlen($modelTransferToMobile->country) > 3): //select the method ?>
																																	<div class="field">
																																	<?php echo $form->labelEx($modelTransferToMobile, Yii::t('yii', 'Method')) ?>
																																	<?php echo $form->textField($modelTransferToMobile, 'method', $fieldOption) ?>
																																	<?php echo $form->error($modelTransferToMobile, 'method') ?>
																																	<p class="hint"><?php echo Yii::t('yii', 'Enter your') . ' ' . Yii::t('yii', 'Method') ?></p>
																																</div>

																																<?php else: //methos already selected?>

																																<div class="field">
																																	<?php echo $form->labelEx($modelTransferToMobile, Yii::t('yii', 'Method')) ?>
																																	<div class="styled-select">
																																		<?php

    echo $form->dropDownList($modelTransferToMobile, 'method',
        $methods,
        array(
            'empty'    => Yii::t('yii', 'Select the method'),
            'disabled' => strlen($modelTransferToMobile->country) > 3,
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



<?php if (strlen($modelTransferToMobile->country) > 3): ?>
	<div class="field">
		<?php echo $form->labelEx($modelTransferToMobile, Yii::t('yii', 'country')) ?>
		<?php echo $form->textField($modelTransferToMobile, 'country', $fieldOption) ?>
		<?php echo $form->error($modelTransferToMobile, 'country') ?>
		<p class="hint"><?php echo Yii::t('yii', 'Enter your') . ' ' . Yii::t('yii', 'country') ?></p>
	</div>



	<?php

$modelSendCreditProducts = SendCreditProducts::model()->findAll(array(
    'condition' => 'country = :key',
    'params'    => array(':key' => $modelTransferToMobile->country),
    'group'     => 'operator_name',
));

$operators = CHtml::listData($modelSendCreditProducts, 'operator_name', 'operator_name');?>
	    <div class="field">
	        <?php echo $form->labelEx($modelTransferToMobile, Yii::t('yii', 'operator')) ?>
	        <div class="styled-select">
	            <?php echo $form->dropDownList($modelTransferToMobile,
    'operator',
    $operators,
    array(
        'empty'    => Yii::t('yii', 'Select the operator'),
        'options'  => array(isset($_POST['TransferToMobile']['operator_name']) ? $_POST['TransferToMobile']['operator_name'] : null => array('selected' => true)),
        'onchange' => 'showProducts()',
        'id'       => 'operatorfield',
    )
); ?>
	        </div>
	    </div>




	<div class="field" id="divamount">
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
        'style'    => 'color:blue; font-size:20',
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
    'onclick' => "button2(event)",
    'id'      => 'secondButton'));
?>
<input class="button" style="width: 80px;" onclick="window.location='../../index.php/transferToMobile/read';" value="Cancel">
<input id ='buying_price'  class="button" style="display:none; width: 100px;" onclick="getBuyingPrice()" value="R" readonly>
</div>
<div class="controls" id="buttondivWait"></div>
<?php

$this->endWidget();?>


<script type="text/javascript">

	function getBuyingPrice(argument) {
		amountValues = document.getElementById('amountfiel').options[document.getElementById('amountfiel').selectedIndex].value;
		operator = document.getElementById('operatorfield').options[document.getElementById('operatorfield').selectedIndex].text;
		if (document.getElementById('buying_price').value != 'R') {
			document.getElementById('buying_price').value = 'R';
		}else{

			if (amountValues > 0) {
		var http = new XMLHttpRequest()

		http.onreadystatechange = function() {
		       		if (this.readyState == 4 && this.status == 200) {
		       			document.getElementById('buying_price').value = this.responseText;
		        	}
		    	};

		http.open("GET", "../../index.php/transferToMobile/getBuyingPrice?amountValues="+amountValues+"&method=<?php echo isset($_POST['TransferToMobile']['method']) ? $_POST['TransferToMobile']['method'] : 0 ?>&operatorname="+operator,true)
		http.send(null);
			}
		}
	}

	function button2(buttonId) {

		valueAmout = document.getElementById('amountfiel').value;


		if (valueAmout > 0) {
			if(!confirm('Are you sure to send this request?')){
				e.preventDefault();
				document.getElementById("sendButton").style.display = 'none';
		  		document.getElementById("buttondivWait").innerHTML = "<font color = green>Wait! </font>";
			}else{
				document.getElementById("sendButton").style.display = 'none';
		  		document.getElementById("buttondivWait").innerHTML = "<font color = green>Wait! </font>";
			}
		}else{
			document.getElementById("sendButton").style.display = 'none';
		  	document.getElementById("buttondivWait").innerHTML = "<font color = green>Wait! </font>";
		}


	}
	function showPrice(argument) {
		document.getElementById('buying_price').style.display = 'inline';
		document.getElementById('buying_price').value = 'R';
	}

	function showProducts(argument) {

		operator = document.getElementById('operatorfield').options[document.getElementById('operatorfield').selectedIndex].text;
		var http = new XMLHttpRequest()
		http.onreadystatechange = function() {
       		if (this.readyState == 4 && this.status == 200) {
       			document.getElementById("amountfiel").innerHTML = this.responseText;
       		}
        	}

		http.open("GET", "../../index.php/transferToMobile/getProducts?operator="+operator);
		http.send(null);

		document.getElementById('divsellingPrice').style.display = 'none';
		document.getElementById('buying_price').style.display = 'none';

	}
</script>

