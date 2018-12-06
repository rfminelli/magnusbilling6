<table border=1 width="100%">
	<tr>
		<td width="50%" valign="top">
			<table border=1 width="100%">
				<tr>
					<td width="30%"></td>
					<td width="20%"><u><?php echo $packet[0]['fromip'] ?></u></td>
					<td width="30%"></td>
					<td width="20%"><u><?php echo $packet[0]['toip'] ?></u></td>
				</tr>
				<?php foreach ($packet as $key => $value): ?>
				<tr>
					<td><?php echo $value['date'] ?></td>
					<td></td>
					<td>
						<div align="center" onclick='selectMethod("<?php echo $value['id'] ?>","<?php echo count($packet) ?>")' >
							<font color="<?php echo $value['direction'] ?>">
								<?php echo $value['method'] ?><br><?php echo $value['direction'] == 'red' ? '&rarr;' : '&larr;' ?>

							</font>
						</div>
					</td>
					<td></td>
				</tr>
				<?php endforeach;?>
			</table>
		</td>
		<td width="50%" valign="top">
			<?php for ($i = 0; $i < count($packet); $i++): ?>
				<div style='display: none;'id='method<?php echo $packet[$i]['id'] ?>'><?php echo preg_replace('/\n/', '<br>', $packet[$i]['head']) ?></div>
			<?php endfor;?>
		</td>
	</tr>
</table>


<script type="text/javascript">

	function selectMethod(id,total) {
		for (var i = 1; i <= total; i++) {
			document.getElementById("method"+i).style.display  = 'none';
		}
		document.getElementById("method"+id).style.display  = 'inline';
	}

	document.getElementById("method1").style.display  = 'inline';
</script>
