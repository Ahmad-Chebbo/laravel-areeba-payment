# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2026-09-27

A rewrite as a thin gateway SDK aligned with the Mastercard Gateway REST API
(version 100). Your application now owns orders, cards and pages; the package returns
data and fires events. See "Upgrading from 2.x" in the README.

### Added

- `Areeba::checkout()` returning a `CheckoutSession`, with `interaction.returnUrl`/`cancelUrl`/`timeoutUrl`
  and an automatic `order.notificationUrl` when the webhook URL is https.
- `CheckoutSession::$paymentUrl`, a direct link to the hosted payment page for a plain redirect.
- Order, transaction and token IDs are URL-encoded in request paths.
- `Areeba::verify()`: checks the result indicator, then confirms the order with the gateway
  (optionally its amount and currency).
- `retrieveOrder()`, `refund()`, `capture()`, `payWithToken()`, `tokenize()`, `retrieveToken()`,
  `deleteToken()`, all returning typed DTOs.
- Webhook endpoint that verifies `X-Notification-Secret`, ignores redelivered notifications
  (`X-Notification-Id`) and fires `NotificationReceived`, `PaymentSucceeded`, `PaymentFailed`
  and `PaymentRefunded`.
- `OrderStatus`, `GatewayResult` and `CheckoutOperation` enums.
- `<x-areeba::checkout :session="$session" />` with payment-page and embedded modes.
- A test suite using `Http::fake()`.

### Changed

- The Composer package is renamed to `ahmad-chebbo/laravel-areeba-payment`
  (was `ahmad-chebbo/areeba-payment-gateway-integration`).
- The facade is now `AhmadChebbo\AreebaPayment\Facades\Areeba`.
- HTTP goes through Laravel's HTTP client with timeouts. Only reads are retried.
- Configuration is reduced to the settings the code uses. `AREEBA_PAYMENT_URL` is replaced
  by `AREEBA_GATEWAY_URL` + `AREEBA_API_VERSION`.
- Gateway errors throw `GatewayException`. Declines are returned as a `TransactionResult`.

### Removed

- Package models, migrations and tables (`areeba_orders`, `areeba_credit_cards`,
  `areeba_webhooks`, `areeba_refunds`).
- The orders, cards, refund, analytics and subscription routes. They had no authentication.
- The success/decline/cancel pages and routes.
- `AnalyticsService`, `RefundService`, `WebhookService`, `ProcessWebhookJob`, `CheckoutOptions`
  and the `areeba:install` command.
- Subscriptions (the gateway has no subscription API; use `payWithToken()` with agreement fields).
- The unfinished direct 3-D Secure flow. Hosted checkout handles 3-D Secure.

### Fixed

- Missing Composer dependencies. The package now installs on its own.
- Webhooks blocked by CSRF, verified with the wrong header, and accepted unsigned.
- Refunds sent to the session ID instead of the order ID.
- The 3% transaction fee enabled by default and saved into the order amount.

## [2.0.0] - 2024-12-19

### Added

- **Webhook Support**: Complete webhook handling for payment notifications
- **Recurring Payments**: Support for subscription and recurring payment processing
- **Enhanced Error Handling**: Comprehensive exception classes and error management
- **Payment Analytics**: Built-in analytics and reporting features
- **Multi-Currency Support**: Enhanced currency handling with exchange rates
- **Payment Refunds**: Full refund processing capabilities
- **Payment Disputes**: Dispute management and handling
- **Enhanced Security**: Additional security features and validation
- **Queue Support**: Background job processing for payment operations
- **Event System**: Laravel events for payment lifecycle hooks
- **API Rate Limiting**: Built-in rate limiting for API calls
- **Payment Methods**: Support for additional payment methods (Apple Pay, Google Pay)
- **3DS Enhanced**: Improved 3D Secure handling with better UX
- **Testing Suite**: Comprehensive test coverage with PHPUnit
- **Code Quality**: PHPStan analysis and Laravel Pint formatting
- **Documentation**: Enhanced documentation with examples and API reference

### Changed

- **PHP Requirement**: Updated minimum PHP version to 8.1
- **Laravel Support**: Added support for Laravel 11.x
- **Service Architecture**: Refactored service layer for better maintainability
- **Configuration**: Enhanced configuration options with better defaults
- **Database Schema**: Improved database structure with additional fields
- **API Integration**: Enhanced API integration with better error handling
- **Facade Implementation**: Improved facade with better method signatures

### Fixed

- **Memory Leaks**: Fixed potential memory leaks in long-running processes
- **Error Logging**: Improved error logging and debugging information
- **Database Queries**: Optimized database queries for better performance
- **Security Vulnerabilities**: Fixed potential security issues
- **Compatibility Issues**: Resolved compatibility issues with newer Laravel versions

### Removed

- **Deprecated Methods**: Removed deprecated methods and features
- **Legacy Code**: Cleaned up legacy code and unused dependencies

## [1.0.0] - 2024-11-12

### Added

- Initial release of Areeba Payment Gateway Integration
- Basic payment processing functionality
- Credit card tokenization
- 3D Secure support
- Basic checkout component
- Order and credit card models
- Configuration management
- Basic error handling

---

## Version Compatibility

| Package Version | Laravel Version | PHP Version |
| --------------- | --------------- | ----------- |
| 2.0.0           | 9.x, 10.x, 11.x | 8.1+        |
| 1.0.0           | 9.x, 10.x       | 8.0+        |

## Migration Guide

### From 1.0.0 to 2.0.0

1. **Update Dependencies**:

   ```bash
   composer update AhmadChebbo/areeba-payment-gateway-integration
   ```

2. **Publish New Configuration**:

   ```bash
   php artisan vendor:publish --provider="AhmadChebbo\AreebaPayment\AreebaServiceProvider" --tag="areeba-config" --force
   ```

3. **Run New Migrations**:

   ```bash
   php artisan migrate
   ```

4. **Update Environment Variables**:
   Add new environment variables for webhooks and enhanced features:

   ```env
   AREEBA_WEBHOOK_SECRET=your_webhook_secret
   AREEBA_API_TIMEOUT=30
   AREEBA_RETRY_ATTEMPTS=3
   ```

5. **Update Code**:
   - Replace deprecated method calls with new implementations
   - Update event listeners if using custom payment events
   - Review and update any custom payment processing logic

## Breaking Changes

- Minimum PHP version increased to 8.1
- Some method signatures have changed for better type safety
- Configuration structure has been reorganized
- Database schema changes require migration
- Event names have been updated for consistency

## Deprecation Notices

The following features will be removed in version 3.0.0:

- Legacy payment processing methods
- Old configuration keys
- Deprecated facade methods
