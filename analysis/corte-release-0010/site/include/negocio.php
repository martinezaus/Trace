<?php
    function reglas_negocio(){
        $negocio = R::findOne('systemnegocio','id = 1');
        return $negocio ?: ['error' => 'Configuración de negocio no encontrada'];
    }