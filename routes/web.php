<?php

use App\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [InventoryController::class, 'index'])->name('inventory.index');
Route::post('/simulate-order', [InventoryController::class, 'simulateOrder'])->name('inventory.simulate');
Route::post('/check-low-stock', [InventoryController::class, 'checkLowStock'])->name('inventory.check-low-stock');
Route::post('/restock', [InventoryController::class, 'restock'])->name('inventory.restock');
Route::post('/simulate-low-stock', [InventoryController::class, 'simulateLowStock'])->name('inventory.simulate-low-stock');
Route::get('/api/products', function () {
    return response()->json(\App\Models\Product::all());
});

Route::get('/test-kafka', function () {
    $kafkaService = app(\App\Services\KafkaService::class);

    $result = $kafkaService->publish('test-laravel', [
        'test' => 'message',
        'timestamp' => now()->toIso8601String()
    ]);

    return response()->json([
        'kafka_available' => $kafkaService->isAvailable(),
        'publish_result' => $result,
        'check_logs' => 'Check storage/logs/laravel.log for details'
    ]);
});
