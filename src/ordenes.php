<?php
    $headerType = 'admin';
    include_once("include/header.php"); 
?>
	<style>
		body.ordenes-page .main-panel { height:auto !important; min-height:100vh; }
		#panelOrdenes .card-header { background:#4fc6c9; }
		#panelOrdenes .card-header h5 { color:#fff; }
		#panelOrdenes #ordenes-export-buttons { display:flex; gap:3px; }
		#panelOrdenes #ordenes-export-buttons .btn {
			background:#fff;
			border:1px solid #35c3c7;
			border-radius:3px;
			color:#32bfc3 !important;
			font-size:10px;
			line-height:1.4;
			padding:4px 11px;
			text-transform:uppercase;
		}
		#panelOrdenes #ordenes-export-buttons .btn:hover,
		#panelOrdenes #ordenes-export-buttons .btn:focus { background:#e9ffff; }
		#tabla_ordenes thead th {
			color:#35bfc3;
			font-size:11px;
			font-weight:600;
			text-transform:uppercase;
		}
		#tabla_ordenes tbody td { vertical-align:middle; }
		#tabla_ordenes tbody td:nth-child(4) .btn {
			font-size:11px;
			font-weight:600;
			padding:8px 17px;
			text-transform:uppercase;
		}
		#tabla_ordenes tbody .btn-outline-secondary {
			background:#fff;
			border-color:#c8c8c8;
			border-radius:3px;
			line-height:1;
			padding:3px 7px;
		}
		#tabla_ordenes .estado-cargada,
		#tabla_ordenes .estado-procesando {
			border:0;
			color:#fff;
		}
		#tabla_ordenes .estado-cargada { background:#68635e; }
		#tabla_ordenes .estado-procesando { background:#51bcda; }
		#tabla_ordenes .documento-revisado {
			align-items:center;
			display:flex;
			gap:8px;
			justify-content:space-between;
			min-width:220px;
		}
		#tabla_ordenes .documento-revisado + .documento-revisado { margin-top:6px; }
		#tabla_ordenes .documento-revisado .nombre-documento { overflow-wrap:anywhere; text-align:left; }
		#tabla_ordenes .btn-revertir-revision { flex:none; padding:3px 8px; }
	</style>
	<script>document.body.classList.add('ordenes-page');</script>
    <div class="content" id="panelOrdenes">
		<div class="card shadow rounded-4">
			<div class="card-header rounded-top-4 d-flex align-items-center justify-content-between flex-wrap">
				<h5 class="mb-0">Ordenes</h5>
				<div id="ordenes-export-buttons" class="d-flex align-items-center"></div>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table" id="tabla_ordenes">
						<thead>
							<tr>
								<th class="text-center">#</th>
								<th class="text-center">Presupuesto</th>
								<th class="text-center">Cliente</th>
								<th class="text-center">Estado</th>
								<th class="text-center">Tipologias</th>
								<th class="text-center">Documentos revisados</th>
								<th class="text-center">Acciones</th>
							</tr>
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