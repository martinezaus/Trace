<?php
    $headerType = 'admin';
    include_once("include/header.php"); 
?>

    <div class="content">
		<div class="card shadow rounded-4">
			<div class="card-header bg-primary text-white rounded-top-4">
				<h5 class="mb-0">Ordenes</h5>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table" id="tabla_ordenes">
						<thead class=" text-primary">
							<th class="text-center">#</th>
							<th class="text-center">Presupuesto</th>
							<th class="text-center">Cliente</th>
							<th class="text-center">Estado</th>
                            <th class="text-center">Tipologias</th>
							<th class="text-center">Acciones</th>
						</thead>
						<tbody class="text-center">
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>    

	<?php include_once('include/modal.php'); ?>

<?php
   include_once("include/footer.php");
?>