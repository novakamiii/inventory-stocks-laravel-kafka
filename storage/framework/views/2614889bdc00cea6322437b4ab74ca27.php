<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo e(config('app.name', 'Inventory Stock Management')); ?></title>
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    
</head>

<body style="background-color: white;">
     <h1>Inventory Management System</h1>

    <div>
        <p><strong>Kafka Status:</strong>
            <span id="kafka-status"><?php echo e($kafkaStatus ? 'Connected' : 'Disconnected (Fallback Mode)'); ?></span>
        </p>
    </div>

    <h2>Simulation Controls</h2>
    <div>
        <button onclick="simulateOrder()">Simulate Random Order</button>
        <button onclick="simulateLowStock()">Simulate Low Stock (Reduce to 3-10 items)</button>
        <button onclick="checkLowStock()">Check Low Stock</button>
        <button onclick="restockAll()">Restock All Products</button>
    </div>

    <div id="alerts" style="margin-top: 20px;"></div>

    <h2>Admin Panel - Product Inventory</h2>
    <table border="1" id="products-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Product Name</th>
                <th>Stock</th>
                <th>Last Updated</th>
            </tr>
        </thead>
        <tbody>
            <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr data-product-id="<?php echo e($product->id); ?>">
                <td><?php echo e($product->id); ?></td>
                <td><?php echo e($product->name); ?></td>
                <td class="stock-cell"><?php echo e($product->stock); ?></td>
                <td class="updated-at"><?php echo e($product->updated_at->format('Y-m-d H:i:s')); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>

    <h2>Kafka Console Log</h2>
    <div style="margin-top: 10px; margin-bottom: 10px;">
        <button onclick="clearKafkaLog()">Clear Log</button>
        <button onclick="toggleAutoScroll()">Toggle Auto-Scroll: <span id="autoscroll-status">ON</span></button>
    </div>
    <div id="kafka-console" style="background: #1e1e1e; color: #00ff00; font-family: monospace; padding: 10px; height: 300px; overflow-y: auto; border: 1px solid #333;">
        <div id="kafka-log"></div>
    </div>

    <script>
        let autoScroll = true;

        function getCsrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]');
            if (!meta) {
                console.error('CSRF token meta tag not found');
                return '';
            }
            return meta.getAttribute('content');
        }

        function logToKafkaConsole(message, type = 'info') {
            const kafkaLog = document.getElementById('kafka-log');
            const timestamp = new Date().toLocaleTimeString();
            const logEntry = document.createElement('div');

            let color = '#00ff00'; // green
            if (type === 'error') color = '#ff0000'; // red
            if (type === 'warning') color = '#ffff00'; // yellow
            if (type === 'kafka') color = '#00ffff'; // cyan

            logEntry.style.color = color;
            logEntry.textContent = `[${timestamp}] ${message}`;

            kafkaLog.appendChild(logEntry);

            // Auto-scroll to bottom if enabled
            if (autoScroll) {
                const consoleDiv = document.getElementById('kafka-console');
                consoleDiv.scrollTop = consoleDiv.scrollHeight;
            }
        }

        function clearKafkaLog() {
            document.getElementById('kafka-log').innerHTML = '';
            logToKafkaConsole('Console cleared', 'info');
        }

        function toggleAutoScroll() {
            autoScroll = !autoScroll;
            document.getElementById('autoscroll-status').textContent = autoScroll ? 'ON' : 'OFF';
            logToKafkaConsole(`Auto-scroll ${autoScroll ? 'enabled' : 'disabled'}`, 'info');
        }

        function showAlert(message, type = 'info') {
            const alertsDiv = document.getElementById('alerts');
            const alertEl = document.createElement('div');
            alertEl.textContent = message;
            alertEl.style.padding = '10px';
            alertEl.style.marginBottom = '10px';
            alertEl.style.border = '1px solid';

            if (type === 'error') {
                alertEl.style.backgroundColor = '#ffcccc';
                alertEl.style.borderColor = '#cc0000';
            } else if (type === 'warning') {
                alertEl.style.backgroundColor = '#fff3cd';
                alertEl.style.borderColor = '#ffc107';
            } else {
                alertEl.style.backgroundColor = '#d1ecf1';
                alertEl.style.borderColor = '#0dcaf0';
            }

            alertsDiv.appendChild(alertEl);

            setTimeout(() => {
                alertEl.remove();
            }, 5000);
        }

        function updateProductRow(productId, stock, updatedAt) {
            const row = document.querySelector(`tr[data-product-id="${productId}"]`);
            if (row) {
                row.querySelector('.stock-cell').textContent = stock;
                row.querySelector('.updated-at').textContent = updatedAt;

                if (stock <= 10) {
                    row.style.backgroundColor = '#ffcccc';
                } else {
                    row.style.backgroundColor = '';
                }
            }
        }

        async function refreshProducts() {
            try {
                const response = await fetch('/api/products');
                const products = await response.json();

                products.forEach(product => {
                    updateProductRow(product.id, product.stock, new Date(product.updated_at).toLocaleString());
                });
            } catch (error) {
                console.error('Error refreshing products:', error);
            }
        }

        async function simulateOrder() {
            logToKafkaConsole('Simulating random order...', 'info');
            try {
                const response = await fetch('<?php echo e(route('inventory.simulate')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });

                const data = await response.json();

                if (response.ok) {
                    const order = data.order;
                    logToKafkaConsole(`✓ Kafka [orders]: {"product":"${order.product_name}","qty":${order.quantity},"stock":${order.remaining_stock}}`, 'kafka');
                    showAlert(`Order placed: ${order.quantity} units of ${order.product_name}. Remaining stock: ${order.remaining_stock}`, 'info');

                    await refreshProducts();

                    if (data.alert) {
                        logToKafkaConsole(`⚠ Kafka [stock-alerts]: {"product":"${order.product_name}","stock":${order.remaining_stock}}`, 'warning');
                        showAlert(data.alert, 'warning');
                    }
                } else {
                    logToKafkaConsole(`✗ Order failed: ${data.error}`, 'error');
                    showAlert(data.error || 'Error placing order', 'error');
                }
            } catch (error) {
                logToKafkaConsole(`✗ Network error: ${error.message}`, 'error');
                showAlert('Network error: ' + error.message, 'error');
            }
        }

        async function simulateLowStock() {
            logToKafkaConsole('Simulating low stock for all products...', 'info');
            try {
                const response = await fetch('<?php echo e(route('inventory.simulate-low-stock')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });

                const data = await response.json();

                if (response.ok) {
                    await refreshProducts();

                    data.results.forEach(result => {
                        if (result.alert) {
                            logToKafkaConsole(`✓ Kafka [orders]: {"product":"${result.product}","reduced":${result.reduced_by},"new_stock":${result.new_stock}}`, 'kafka');
                            logToKafkaConsole(`⚠ Kafka [stock-alerts]: {"product":"${result.product}","stock":${result.new_stock}}`, 'warning');
                            showAlert(`${result.product}: Reduced by ${result.reduced_by} units. ${result.alert}`, 'warning');
                        } else {
                            logToKafkaConsole(`ℹ ${result.product}: ${result.message} (${result.current_stock} items)`, 'info');
                            showAlert(`${result.product}: ${result.message} (${result.current_stock} items)`, 'info');
                        }
                    });
                } else {
                    logToKafkaConsole(`✗ Low stock simulation failed`, 'error');
                    showAlert('Error simulating low stock', 'error');
                }
            } catch (error) {
                logToKafkaConsole(`✗ Network error: ${error.message}`, 'error');
                showAlert('Network error: ' + error.message, 'error');
            }
        }

        async function checkLowStock() {
            logToKafkaConsole('Checking for low stock products...', 'info');
            try {
                const response = await fetch('<?php echo e(route('inventory.check-low-stock')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });

                const data = await response.json();

                if (data.alerts.length > 0) {
                    logToKafkaConsole(`⚠ Found ${data.alerts.length} low stock product(s)`, 'warning');
                    data.alerts.forEach(alert => {
                        logToKafkaConsole(`✓ Kafka [stock-alerts]: ${alert}`, 'kafka');
                        showAlert(alert, 'warning');
                    });
                } else {
                    logToKafkaConsole('✓ No low stock products found', 'info');
                    showAlert('No low stock products found', 'info');
                }
            } catch (error) {
                logToKafkaConsole(`✗ Network error: ${error.message}`, 'error');
                showAlert('Network error: ' + error.message, 'error');
            }
        }

        async function restockAll() {
            logToKafkaConsole('Restocking all products to 500 units...', 'info');
            try {
                const response = await fetch('<?php echo e(route('inventory.restock')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    }
                });

                const data = await response.json();

                if (response.ok) {
                    logToKafkaConsole('✓ Kafka [restock]: {"action":"restock_all","new_stock":500}', 'kafka');
                    showAlert(data.message, 'info');
                    await refreshProducts();
                } else {
                    logToKafkaConsole('✗ Restock failed', 'error');
                    showAlert('Error restocking products', 'error');
                }
            } catch (error) {
                logToKafkaConsole(`✗ Network error: ${error.message}`, 'error');
                showAlert('Network error: ' + error.message, 'error');
            }
        }

        // Initial log message
        window.addEventListener('DOMContentLoaded', function() {
            logToKafkaConsole('Kafka Console initialized. Waiting for events...', 'info');
        });
    </script>
</body>
</html>
<?php /**PATH /home/nova/Programming/laravel/InventoryStocksLaravelKafka/resources/views/inventory/index.blade.php ENDPATH**/ ?>