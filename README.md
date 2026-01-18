# Laravel Kafka Inventory System - Setup Guide

- BSIT 3-1 PUP-SPC
- ELEC IT E-1 - IT ELECTIVE 

# Members
**GROUP 4**
- Paulo Neil A. Sevilla
- Johnella Llana Muriel A. Gutay
- James Bond M. Ranchez
- Jazper Angelo M. Bonagua
- Jhonabelle H. Palec
- James Martin F. Dio
- Danzel M. Bordeos
- Jhondrei V. Apeta
- Arhvin Tedimar B. Gaman
- Jonathan M. Rioveros
## System ran on:
- CachyOS (Arch Linux)

## Prerequisites:
- Laravel 12 installed
- Kafka installed and running
- PHP 8.4+

## Installation Steps

### 1. Setup Database
```bash
touch database/database.sqlite
```

### 2. Configure Environment
Add to `.env` file:
```
DB_CONNECTION=sqlite
KAFKA_BROKERS=localhost:9092
```

### 3. Run Migrations
```bash
php artisan migrate
```

### 4. Seed Database
```bash
php artisan db:seed --class=ProductSeeder
```

### 5. Start Laravel
```bash
composer run dev
```

### 6. Access Application
Open browser: `http://localhost:8000`

---

## Optional: Monitor Kafka Messages

>This only applicable to the AUR (Arch User Repository) version of Apache Kafka
    
### List Topics
```bash
kafka-topics.sh --bootstrap-server localhost:9092 --list
```

### View Orders
```bash
kafka-console-consumer.sh --bootstrap-server localhost:9092 --topic orders --from-beginning
```

### View Stock Alerts
```bash
kafka-console-consumer.sh --bootstrap-server localhost:9092 --topic stock-alerts --from-beginning
```

### View Restocks
```bash
kafka-console-consumer.sh --bootstrap-server localhost:9092 --topic restock --from-beginning
```

---

## Features
- **Simulate Random Order**: Random purchase (1-50 units)
- **Simulate Low Stock**: Reduce all products to 3-10 items
- **Check Low Stock**: Show alerts for products ≤10 stock
- **Restock All**: Reset all products to 500 units
- **Console Log**: For better view of logs and data flow.

## Notes
- Application works without Kafka (fallback mode)
- Real-time table updates (no page reload needed)
- Low stock threshold: ≤10 items
- Initial stock: 500 units per product
