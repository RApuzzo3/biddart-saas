# Biddart SAAS - Multi-Tenant Auction Platform

A powerful, modern multi-tenant SAAS application for managing charity auctions, fundraising events, and bidding systems. Built with Laravel 11, Livewire 3, and designed for real-time performance.

## Features

### **Multi-Tenant Architecture**
- **Complete Tenant Isolation**: Each organization has isolated data and configuration
- **Subdomain Support**: `nonprofit1.biddart.com` or custom domains
- **Flexible Database Strategy**: Single database with tenant scoping
- **Automatic Tenant Resolution**: Smart tenant detection from URL or session

### **Event Management**
- **Real-time Dashboard**: Live bid tracking and statistics
- **Event Lifecycle**: Draft → Active → Completed workflow
- **Revenue Tracking**: Platform fees and total revenue calculation
- **Multi-Event Support**: Unlimited events per tenant

### **Advanced Bidding System**
- **Multiple Bid Types**: Regular bids, Buy Now, Raffle entries, Donations
- **Smart Bid Validation**: Automatic minimum bid calculation
- **Real-time Updates**: Live bid feed with Livewire
- **Winning Bid Management**: Automatic winner determination

### **Bidder Management**
- **Quick Registration**: Fast bidder onboarding
- **Unique Bidder Numbers**: Auto-generated sequential IDs (001, 002, etc.)
- **Contact Management**: Email, phone, address tracking
- **Payment History**: Complete bidder transaction history

### **Shared Credential System** 
- **SMS Verification**: Phone-based authentication with Twilio
- **Auto-Generated Aliases**: "SwiftEagle42" style staff names
- **Multi-User Sessions**: Multiple staff using same credentials
- **Permission-Based Access**: Role-specific functionality

### **Payment Processing** 
- **Square Integration**: Complete card processing
- **Multiple Payment Methods**: Card, cash, Check
- **Automatic Fee Calculation**: Platform fees + processing fees
- **Digital Receipts**: Email receipts with PDF generation
- **Refund System**: Complete refund processing

### **Revenue Model** 
- **Transaction-Based Fees**: 2.5% + $0.30 per checkout (configurable)
- **Automatic Calculation**: Platform fees calculated on every transaction
- **Revenue Reporting**: Real-time revenue tracking per tenant
- **Fee Transparency**: Clear fee breakdown for customers

## Technology Stack

- **Backend**: Laravel 11, PHP 8.2+
- **Frontend**: Livewire 3, Volt, Alpine.js, Tailwind CSS
- **Database**: MySQL 8.0+
- **Authentication**: Laravel Breeze + Custom Shared Credentials
- **Payments**: Square API
- **SMS**: Twilio API
- **Development**: Laravel Herd, NGINX

## Quick Start with Laravel Herd

### Prerequisites
- Laravel Herd installed
- MySQL running
- PHP 8.2+
- Composer

### Setup Instructions

1. **Clone and Setup**
   ```bash
   cd C:\dev\biddart-saas
   ```

2. **Run Herd Setup Script**
   ```powershell
   .\setup-herd.ps1
   ```

3. **Configure Herd**
   ```bash
   herd link biddart-saas
   herd secure biddart-saas  # Optional: Enable HTTPS
   ```

4. **Add to Hosts (for subdomains)**
   Add to `C:\Windows\System32\drivers\etc\hosts`:
   ```
   127.0.0.1 biddart-saas.test
   127.0.0.1 demo-nonprofit.biddart-saas.test
   ```

### Access Points

| Service | URL | Credentials |
|---------|-----|-------------|
| **Main App** | http://biddart-saas.test | - |
| **Demo Tenant** | http://demo-nonprofit.biddart-saas.test | admin@demo-nonprofit.org / password |
| **Shared Login** | http://biddart-saas.test/shared/login | Create in admin panel |
| **Mailpit** | http://localhost:8025 | - |

## Architecture Overview

### **Multi-Tenancy Flow**
```
Request → TenantMiddleware → Resolve Tenant → Set Context → Route to Controller
```

### **Authentication Methods**
1. **Regular Users**: Laravel Breeze authentication
2. **Shared Credentials**: SMS-verified shared login system

### **Database Schema**
- **Tenant Isolation**: All models automatically scoped by `tenant_id`
- **Shared Tables**: `tenants`, `tenant_users`, `phone_verifications`
- **Tenant-Specific**: `events`, `bidders`, `bid_items`, `bids`, `checkout_sessions`

## Key Components

### **Livewire Components**
- `event-app.blade.php`: Main single-page auction interface
- `quick-bidder-registration.blade.php`: Fast bidder registration
- Real-time updates every 30 seconds

### **Middleware Stack**
- `TenantMiddleware`: Multi-tenant resolution and context
- `SharedAuthMiddleware`: Dual authentication support
- `RoleMiddleware`: Permission-based access control
- `EventAccessMiddleware`: Event-level security

### **Revenue Calculation**
```php
Platform Fee = (Subtotal × 2.5%) + $0.30
Processing Fee = (Total × 2.6%) + $0.10  // Square fees
Total = Subtotal + Platform Fee + Processing Fee
```

## Configuration

### **Environment Variables**
```env
# Multi-Tenancy
TENANT_DATABASE_STRATEGY=single
SESSION_DOMAIN=.biddart-saas.test

# Square API
SQUARE_APPLICATION_ID=your_app_id
SQUARE_ACCESS_TOKEN=your_access_token
SQUARE_ENVIRONMENT=sandbox

# Twilio SMS
TWILIO_SID=your_sid
TWILIO_TOKEN=your_token
TWILIO_PHONE=your_phone_number
```

### **Tenant Configuration**
Each tenant can have custom:
- Transaction fee percentages
- Branding colors and logos
- Email settings
- Feature limits based on subscription

## Mobile-Responsive Design

- **Touch-Optimized**: Large buttons for quick bid entry
- **Real-Time Updates**: Live bid feed without page refresh
- **Offline Capability**: Graceful handling of network issues
- **Fast Performance**: Optimized for event staff efficiency

## Deployment

### **Production Checklist**
- [ ] Configure production database
- [ ] Set up Square production credentials
- [ ] Configure Twilio SMS service
- [ ] Set up SSL certificates for custom domains
- [ ] Configure email service (SendGrid, Mailgun, etc.)
- [ ] Set up monitoring and logging
- [ ] Configure backup strategy

### **Scaling Considerations**
- **Database**: Consider read replicas for high-traffic events
- **Caching**: Redis for session and cache storage
- **CDN**: Asset delivery optimization
- **Queue Workers**: Background job processing

## Revenue Model

This application is designed for a **transaction-based revenue model**:

- **2.5% + $0.30** per successful checkout
- **Automatic calculation** on every transaction
- **Transparent fee structure** for customers
- **Real-time revenue tracking** for platform owner
- **Configurable fee rates** per tenant if needed

## Security Features

- **Tenant Isolation**: Complete data separation
- **SQL Injection Protection**: Eloquent ORM with parameter binding
- **CSRF Protection**: Laravel's built-in CSRF tokens
- **XSS Prevention**: Blade template escaping
- **Rate Limiting**: API and form submission limits
- **Secure Sessions**: Encrypted session storage

## Analytics & Reporting

- **Real-time Statistics**: Live event dashboards
- **Revenue Tracking**: Platform fee calculation and reporting
- **Bidder Analytics**: Participation and spending patterns
- **Event Performance**: Success metrics and insights

## Contributing

This is a proprietary SAAS application. For feature requests or bug reports, please contact the development team.

## License

Proprietary - All rights reserved.
