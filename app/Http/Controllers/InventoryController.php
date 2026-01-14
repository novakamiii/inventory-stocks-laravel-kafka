<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\KafkaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InventoryController extends Controller
{
    protected $kafkaService;

    public function __construct(KafkaService $kafkaService)
    {
        $this->kafkaService = $kafkaService;
    }

    public function index()
    {
        $products = Product::all();
        $kafkaStatus = $this->kafkaService->isAvailable();
        return view('inventory.index', compact('products', 'kafkaStatus'));
    }

    public function simulateOrder(Request $request)
    {
        $productId = rand(1, 3);
        $quantity = rand(1, 50);

        $product = Product::find($productId);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        if ($product->stock < $quantity) {
            return response()->json([
                'error' => 'Insufficient stock',
                'available' => $product->stock,
                'requested' => $quantity
            ], 400);
        }

        $product->stock -= $quantity;
        $product->save();

        $orderData = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'remaining_stock' => $product->stock,
            'timestamp' => now()->toIso8601String()
        ];

        $this->kafkaService->publish('orders', $orderData);

        $alert = null;
        if ($product->stock <= 10) {
            $alert = "Low stock alert for {$product->name}! Only {$product->stock} items remaining.";

            $this->kafkaService->publish('stock-alerts', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'stock' => $product->stock,
                'alert_type' => 'low_stock',
                'timestamp' => now()->toIso8601String()
            ]);
        }

        return response()->json([
            'success' => true,
            'order' => $orderData,
            'alert' => $alert
        ]);
    }

    public function simulateLowStock()
    {
        $products = Product::all();
        $results = [];

        foreach ($products as $product) {
            if ($product->stock > 10) {
                // Calculate how much to reduce to get to a random low stock (3-10 items)
                $targetStock = rand(3, 10);
                $quantityToReduce = $product->stock - $targetStock;

                $product->stock = $targetStock;
                $product->save();

                $orderData = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantityToReduce,
                    'remaining_stock' => $product->stock,
                    'timestamp' => now()->toIso8601String(),
                    'simulated_low_stock' => true
                ];

                $this->kafkaService->publish('orders', $orderData);

                $alert = "Low stock alert for {$product->name}! Only {$product->stock} items remaining.";

                $this->kafkaService->publish('stock-alerts', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'stock' => $product->stock,
                    'alert_type' => 'simulated_low_stock',
                    'timestamp' => now()->toIso8601String()
                ]);

                $results[] = [
                    'product' => $product->name,
                    'reduced_by' => $quantityToReduce,
                    'new_stock' => $product->stock,
                    'alert' => $alert
                ];
            } else {
                $results[] = [
                    'product' => $product->name,
                    'message' => 'Already at low stock',
                    'current_stock' => $product->stock
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results
        ]);
    }

    public function checkLowStock()
    {
        $lowStockProducts = Product::where('stock', '<=', 10)->get();

        $alerts = [];
        foreach ($lowStockProducts as $product) {
            $alert = "Low stock alert for {$product->name}! Only {$product->stock} items remaining.";
            $alerts[] = $alert;

            $this->kafkaService->publish('stock-alerts', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'stock' => $product->stock,
                'alert_type' => 'low_stock_check',
                'timestamp' => now()->toIso8601String()
            ]);
        }

        return response()->json([
            'low_stock_products' => $lowStockProducts,
            'alerts' => $alerts
        ]);
    }

    public function restock()
    {
        $products = Product::all();

        foreach ($products as $product) {
            $product->stock = 500;
            $product->save();

            $this->kafkaService->publish('restock', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'new_stock' => 500,
                'timestamp' => now()->toIso8601String()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'All products restocked to 500 units'
        ]);
    }
}
