<?php

    require_once "vendor/autoload.php";
    require_once "include/config.php"; 
	require_once 'rb-mysql.php';


    // Verifica si RedBeanPHP está funcionando
    try{
        R::setup('mysql:host='.hostBD.';dbname='.nombreBD, usuarioBD, claveBD);
        R::freeze(true); // Permitir que RedBean actualice el esquema
        //R::debug(true);  // poné true si querés ver el SQL en pantalla

    }catch(Exception $e){
        echo die(json_encode(["error" => "No se pudo conectar a la base de datos"]));
    }

    // Función para contar registros en una tabla específica
    function contarOperadores() {
        //return R::count('operador','id <> ? AND estado = ?', [1,0]);
        return R::count('operadores','estado = ?', [0]);
    }
        
    // Función para contar registros en una tabla específica
    function contarLotes_items() {
        $total = R::getCell('SELECT SUM(items) FROM lotes');
        return $total ?: 0; // Devuelve 0 si es null
    }

    // Función para contar registros en una tabla específica
    function contarLotes_tipologias() {
        $total = R::getCell('SELECT SUM(tipologias) FROM lotes');
        return $total ?: 0; // Devuelve 0 si es null
    }

    // Función para contar registros en una tabla específica
    function contarLotes_vidrios() {
        $total = R::getCell('SELECT SUM(vidrios) FROM lotes');
        return $total ?: 0; // Devuelve 0 si es null
    }

    // Función para contar registros en una tabla específica
    function contarLotes_piezas() {
        return R::count('piezas');
    }

    // Función para contar registros en una tabla específica
    function contarOrdenes_terminadas() {
        $total = R::count('ordenes','c_estado = ?', [2]);
        return $total ?: 0; // Devuelve 0 si es null
    }

    // Función para contar registros en una tabla específica
    function contarOrdenes_cargadas() {
        $total = R::count('ordenes','c_estado = ?', [0]);
        return $total ?: 0; // Devuelve 0 si es null
    }

    // NUEVO: usado por el gráfico "Tareas p/Producidas-Suspendidas" (antes tenía
    // datos hardcodeados [342, 480] y nunca mostraba información real).
    // c_estado en 'detalles': 0=pendiente, 1=pausada, 2=suspendida, 3(default)=terminada.
    function contarDetalles_producidasSuspendidas() {
        $year = date('Y');
        $producidas   = (int) R::getCell(
            'SELECT COUNT(id) FROM detalles WHERE c_estado = 3 AND YEAR(`update`) = ?',
            [$year]
        );
        $suspendidas  = (int) R::getCell(
            'SELECT COUNT(id) FROM detalles WHERE c_estado = 2 AND YEAR(created) = ?',
            [$year]
        );
        return ['producidas' => $producidas, 'suspendidas' => $suspendidas];
    }

    function getCharData($sql) {
        $year = date('Y');
        $results = R::getAll($sql, [':year' => $year]);
             
        $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
        $data = array_fill(0, 12, 0);


        foreach ($results as $row) {
            $mi = (int)$row['mes'] - 1;
            $data[$mi] = (int)$row['cantidad'];
        }
        
        $result = ['meses'   => $meses,
                   'datasets' => [
                        ['label' => 'Tareas Asignadas ' . $year,
                         'data'  => $data,
                         'borderColor' => '#6bd098',
                         'backgroundColor' => 'rgba(107,208,152,0.2)',
                        ]
                // puedes agregar más datasets (por operador, por tipo, etc.)
            ]
        ];
        return json_encode($result, JSON_NUMERIC_CHECK);

    }

    function getCharDatatwo($sql) {
        $year = date('Y');
        $results = R::getAll($sql,[':year' => $year]);
             
        $labels = [];
        $data= []; 
        foreach ($results as $row) {
            $oi = $row['operario'] ?: 0;
            $data[$oi] = [$row['nombre'] ?: 'SIN NOMBRE', (int)$row['cantidad']];
        }
        
        $result = ['labels'   => '',
                   'datasets' => [
                        [
                         'data'  => $data
                        ]
                    ]
                ];
                
        return json_encode($result, JSON_NUMERIC_CHECK);

    }


	// Run it!
	//$router->run();
?>