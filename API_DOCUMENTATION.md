# POS API Documentation

## Overview

This API provides endpoints for managing promo codes, products, sales, and inventory events in a Point of Sale system.

## API Version

**Version:** 1.0.0

## Authentication

All API endpoints require Bearer token authentication using Laravel Sanctum.

Include the token in the `Authorization` header:

```
Authorization: Bearer YOUR_API_TOKEN
```

## Getting an API Token

Run the demo user seeder to create a test user and generate an API token:

```bash
php artisan db:seed --class=DemoUserSeeder
```

This will output:
- User credentials (email: demo@pos-api.local, password: password)
- API token for Bearer authentication
- Demo branches for testing

## Database Seeding

To populate your database with sample data for testing, run:

```bash
php artisan db:seed
```

This will create:
- A test user (email: test@example.com, password: password)
- Sample branches
- Sample products

### Sample Data

**Branches:**
- BR001: Main Branch (123 Main Street, Downtown)
- BR002: North Branch (456 North Avenue, Uptown)
- BR003: South Branch (789 South Boulevard, Southside)

**Products:**
- PROD-001: Coca Cola 500ml (Branch: BR001, Price: $2.50, Barcode: 1234567890123)
- PROD-002: Pepsi 500ml (Branch: BR001, Price: $2.50, Barcode: 1234567890124)
- PROD-003: Mineral Water 1L (Branch: BR002, Price: $1.50, Barcode: 1234567890125)
- PROD-004: Chips 100g (Branch: BR002, Price: $3.00, Barcode: 1234567890126)
- PROD-005: Chocolate Bar 50g (Branch: BR003, Price: $2.00, Barcode: 1234567890127)

## Accessing API Documentation

### Interactive Documentation

Visit the interactive API documentation at:

```
http://your-domain/api-docs
```

The documentation includes:
- Complete endpoint descriptions
- Request/response examples
- Try-it-out functionality
- Schema definitions

### OpenAPI Specification

Download the OpenAPI specification (JSON) at:

```
http://your-domain/api-docs.json
```

## API Endpoints

### Promo Codes

#### Generate Promo Code
**POST** `/api/v1/promo-codes/generate`

Creates a sale record and generates a promo code for the customer.

**Requirements:**
- Branch must exist in the system
- Check number must be unique

#### Cancel Promo Code
**POST** `/api/v1/promo-codes/cancel`

Cancels items from a receipt and updates the promo code status.

### Events

Event endpoints process incoming data from external systems with minimal validation. All events are saved with `status='new'` for later processing.

#### Product Catalog Created
**POST** `/api/v1/events/product-catalog/created`

Processes product catalog events. Duplicate barcodes are handled gracefully.

#### Inventory Items Added
**POST** `/api/v1/events/inventory/items/added`

Records inventory addition events.

#### Inventory Items Removed
**POST** `/api/v1/events/inventory/items/removed`

Records inventory removal events.

## Response Format

All API responses follow a standardized format:

```json
{
  "ok": true,
  "code": 200,
  "message": "Success message",
  "result": {
    // Response data
  },
  "meta": {
    "timestamp": "2025-11-17T10:00:00.000000Z"
  }
}
```

### Success Response
- `ok`: `true` for successful requests
- `code`: HTTP status code (200, 201, etc.)
- `message`: Human-readable success message
- `result`: Response data (optional)
- `meta`: Additional metadata (optional)

### Error Response
- `ok`: `false` for failed requests
- `code`: HTTP error code (400, 404, 500, etc.)
- `message`: Human-readable error message
- `result`: `null` or error details
- `meta`: Additional error information (validation errors, etc.)

## Database Migrations

Run migrations to create all required tables:

```bash
php artisan migrate
```

Tables created:
- `branches` - Store/branch information
- `products` - Product catalog
- `sales` - Sales receipts
- `sale_items` - Individual sale items
- `inventory_history` - Inventory changes
- `promo_code_generation_history` - Promo code records

## Event-Driven Architecture

The API supports an event-driven architecture for async processing:

1. **Events are written immediately** - No strict validation on foreign keys
2. **Status tracking** - All events have a `status` field ('new', 'processed', 'failed')
3. **Process later** - Background jobs can process events with `status='new'`

This allows the API to accept all incoming events even if related records don't exist yet.

## Support

For issues or questions, please refer to the interactive API documentation at `/api-docs`.
