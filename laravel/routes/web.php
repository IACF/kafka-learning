<?php

use App\Http\Controllers\KafkaProducerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Fase 1: Producer — envia mensagem para o tópico laravel-events
Route::post('/kafka/produce', KafkaProducerController::class);
Route::get('/kafka/produce', KafkaProducerController::class); // teste rápido (browser/curl)
