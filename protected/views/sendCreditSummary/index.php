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

<?php
if (Yii::app()->session['isAdmin'] == 1):

    $modelUser = User::model()->findAll();
    $users     = CHtml::listData($modelUser, 'id', 'username');?>
                                                                                              <div class="field">
                                                                                                  <?php echo $form->labelEx($model, Yii::t('yii', 'Select a user')) ?>
                                                                                                  <div class="styled-select">
                                                                                                      <?php echo $form->dropDownList($model,
        'id',
        $users,
        array('options' => array($_POST['SendCreditSummary']['id'] => array('selected' => true)))
    ); ?>
                                                                                                  </div>
                                                                                              </div>

                                                                                          <?php endif;?>


<div class="field">
    <?php echo $form->labelEx($model, Yii::t('yii', 'From Date')) ?>

<?php
$this->widget(
    'ext.jui.EJuiDateTimePicker',
    array(
        'model'     => $model,
        'attribute' => 'date',
        'language'  => 'en', //default Yii::app()->language
        'mode'      => 'date', //'datetime' or 'time' ('datetime' default)
        'options'   => array(
            'dateFormat' => 'yy-mm-dd',
            'timeFormat' => 'HH:mm:ss',
        ),
    )
);

?>
<?php echo $form->error($model, 'date') ?>
    <p class="hint"><?php echo Yii::t('yii', 'Enter your') . ' ' . Yii::t('yii', 'date') ?></p>

</div>
<br>
<div class="field">
    <?php echo $form->labelEx($model, Yii::t('yii', 'To Date')) ?>
<?php
$this->widget(
    'ext.jui.EJuiDateTimePicker',
    array(
        'model'     => $model,
        'attribute' => 'stopdate',
        'language'  => 'en', //default Yii::app()->language
        'mode'      => 'date', //'datetime' or 'time' ('datetime' default)

        'options'   => array(
            'dateFormat' => 'yy-mm-dd',
            'timeFormat' => 'HH:mm:ss',
        ),
    )
);
?>
</div>
<br>
<?php echo CHtml::submitButton(Yii::t('yii', 'Filter'), array('class' => 'button')); ?>
<?php $this->endWidget();?>
<div class="rounded">

  <table class="blueTable">
<thead>
<tr>
<th>Day</th>
<th>Service</th>
<th>Amount</th>
<th>Total_cost</th>
<th>Total_sale</th>
<th>Earned</th>
</tr>
</thead>
<tfoot>
<tr>
<td colspan="5">
</td>
</tr>
</tfoot>
<tbody>

<?php $total_cost = 0;?>
<?php $total_sale = 0;?>
<?php $earned     = 0;?>
<?php $amount     = 0;?>
<?php foreach ($modelSendCreditSummary as $key => $value): ?>
    <?php $amount     = $amount + $value->count;?>
    <?php $total_cost = $total_cost + $value->total_cost;?>
    <?php $total_sale = $total_sale + $value->total_sale;?>
    <?php $earned     = $earned + $value->earned;?>

    <tr>
<td><?php echo $value->day; ?></td>
<td><?php echo $value->service; ?></td>
<td><?php echo $value->count; ?></td>
<td><?php echo number_format($value->total_cost, 2); ?></td>
<td><?php echo number_format($value->total_sale, 2); ?></td>
<td><?php echo number_format($value->earned, 2); ?></td>
</tr>
 <?php endforeach;?>

<tr>

<td></td>
<td><b>Total</b></td>
<td><b><?php echo $amount; ?></b></td>
<td><b><?php echo number_format($total_cost, 2); ?></b></td>
<td><b><?php echo number_format($total_sale, 2); ?></b></td>
<td><b><?php echo number_format($earned, 2); ?></b></td>
</tr>

 </tbody>
</table>
 </div>

 <style type="text/css">

  table.blueTable {
  border: 1px solid #5b855b;
  background-color: #EEEEEE;
  width: 100%;
  text-align: left;
  border-collapse: collapse;
}
table.blueTable td, table.blueTable th {
  border: 1px solid #AAAAAA;
  padding: 3px 2px;
}
table.blueTable tbody td {
  font-size: 13px;
}
table.blueTable tr:nth-child(even) {
  background: #e2efe2;
}
table.blueTable thead {
  background: #5b855b;
  background: -moz-linear-gradient(top, #9ecea1 0%, #9ecea1 66%, #9ecea1 100%);
  background: -webkit-linear-gradient(top, #9ecea1 0%, #9ecea1 66%, #9ecea1 100%);
  background: linear-gradient(to bottom, #9ecea1 0%, #9ecea1 66%, #9ecea1 100%);
  border-bottom: 2px solid #444444;
}
table.blueTable thead th {
  font-size: 15px;
  font-weight: bold;
  color: #FFFFFF;
  border-left: 2px solid #e2efe2;
}
table.blueTable thead th:first-child {
  border-left: none;
}

table.blueTable tfoot {
  font-size: 14px;
  font-weight: bold;
  color: #FFFFFF;
  background: #e2efe2;
  background: -moz-linear-gradient(top, #adebad 0%, #d4e6f6 66%, #e2efe2 100%);
  background: -webkit-linear-gradient(top, #adebad 0%, #d4e6f6 66%, #e2efe2 100%);
  background: linear-gradient(to bottom, #adebad 0%, #d4e6f6 66%, #e2efe2 100%);
  border-top: 2px solid #444444;
}
table.blueTable tfoot td {
  font-size: 14px;
}
table.blueTable tfoot .links {
  text-align: right;
}
table.blueTable tfoot .links a{
  display: inline-block;
  background: #5b855b;
  color: #FFFFFF;
  padding: 2px 8px;
  border-radius: 5px;
}

 </style>