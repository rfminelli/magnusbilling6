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
<div class="field">
    <?php echo $form->labelEx($model, Yii::t('yii', 'StartTime')) ?>

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
    <?php echo $form->labelEx($model, Yii::t('yii', 'StopTime')) ?>
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
<?php echo $form->hiddenField($model, 'id_user', array('value' => $modelUser->id)); ?>
<?php echo CHtml::submitButton(Yii::t('yii', 'Filter'), array('class' => 'button')); ?>
<?php $this->endWidget();?>
<div class="rounded">

  <table class="blueTable">
<thead>
<tr>
<th>Day</th>
<th>Service</th>
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



<?php foreach ($modelSendCreditSummary as $key => $value): ?>
    <tr>
<td><?php echo $value->day; ?></td>
<td><?php echo $value->service; ?></td>
<td><?php echo number_format($value->total_cost, 2); ?></td>
<td><?php echo number_format($value->total_sale, 2); ?></td>
<td><?php echo number_format($value->earned, 2); ?></td>
</tr>
 <?php endforeach;?>

 </tbody>
</table>
 </div>

 <style type="text/css">

  table.blueTable {
  border: 1px solid #1C6EA4;
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
  background: #D0E4F5;
}
table.blueTable thead {
  background: #1C6EA4;
  background: -moz-linear-gradient(top, #5592bb 0%, #327cad 66%, #1C6EA4 100%);
  background: -webkit-linear-gradient(top, #5592bb 0%, #327cad 66%, #1C6EA4 100%);
  background: linear-gradient(to bottom, #5592bb 0%, #327cad 66%, #1C6EA4 100%);
  border-bottom: 2px solid #444444;
}
table.blueTable thead th {
  font-size: 15px;
  font-weight: bold;
  color: #FFFFFF;
  border-left: 2px solid #D0E4F5;
}
table.blueTable thead th:first-child {
  border-left: none;
}

table.blueTable tfoot {
  font-size: 14px;
  font-weight: bold;
  color: #FFFFFF;
  background: #D0E4F5;
  background: -moz-linear-gradient(top, #dcebf7 0%, #d4e6f6 66%, #D0E4F5 100%);
  background: -webkit-linear-gradient(top, #dcebf7 0%, #d4e6f6 66%, #D0E4F5 100%);
  background: linear-gradient(to bottom, #dcebf7 0%, #d4e6f6 66%, #D0E4F5 100%);
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
  background: #1C6EA4;
  color: #FFFFFF;
  padding: 2px 8px;
  border-radius: 5px;
}

 </style>