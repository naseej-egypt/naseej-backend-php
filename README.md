# Naseej Backend API - Egyptian Handmade Carpets E-Commerce

##  About

The Naseej Backend API is a comprehensive PHP-based RESTful service that powers a full-featured e-commerce platform for authentic Egyptian handmade carpets. Built with scalability, security, and performance in mind, it handles everything from user authentication to order processing and real-time notifications.

### Key Highlights

- RESTful Architecture Clean, standardized API endpoints
- Role-Based Access Control Separate authentication for customers, admins, and delivery personnel
- Real-Time Features Order tracking, notifications, and inventory updates
- Secure Payments Multiple payment method support with validation
- Email Integration PHPMailer for OTP verification and notifications
- Image Management Efficient file upload and storage system
- Advanced Analytics Dashboard with sales metrics and insights

---

##  Features

###  Authentication & Authorization

Customer Authentication
- User registration with email verification (OTP)
- Secure login with JWT token-based sessions
- Password reset flow with OTP validation
- Session management and token refresh
- Account deletion with data cleanup

Admin Authentication
- Separate admin login system
- Profile management with image upload
- Password change functionality
- Role-based permissions

Security Features
- Password hashing with bcrypt
- JWT token generation and validation
- Session timeout handling
- CSRF protection
- SQL injection prevention

###  E-Commerce Core

Product Management
- CRUD operations for products
- Multiple image upload per product
- Category-based organization
- Stock level tracking
- Low stock alerts
- Product status toggle (activeinactive)
- Advanced search and filtering

Category Management
- Category CRUD operations
- Image upload for categories
- Category reorderingprioritization
- Status management
- Category-based product filtering

Shopping Cart
- Addremove items
- Update quantities
- Cart persistence across sessions
- Cart clearing
- Real-time price calculations

FavoritesWishlist
- Add products to favorites
- Remove from favorites
- Check if product is favorited
- Retrieve user's favorite products

###  Order Management

Order Processing
- Order creation with validation
- Order status tracking (pending, processing, shipped, delivered, cancelled)
- Payment status management
- Order cancellation (customeradmin)
- Order history retrieval
- Filter orders by status
- Pending order count for dashboard

Delivery Management
- Delivery personnel CRUD
- Assign delivery person to orders
- Delivery status updates
- Available delivery personnel listing
- Delivery person performance tracking

Order Features
- Customer order history
- Order details with line items
- Order tracking
- Reorder functionality
- Order search and filtering

###  Customer Management

Customer Operations
- View all customers (admin)
- Customer profile retrieval
- Customer search
- Account status toggle
- Customer order history
- Top customers analytics
- Customer CRUD operations

Address Management
- Multiple addresses per customer
- Addeditdelete addresses
- Set default address
- Address validation

Payment Methods
- Store multiple payment methods
- Creditdebit card information
- Set default payment method
- Updatedelete payment methods

###  Store Management

Physical Stores
- Store CRUD operations
- Store location information
- Store image upload
- Operating hours management
- Store search and filtering
- Status toggle (openclosed)

Store Features
- Multiple store locations
- Store-specific pickup options
- Store details for customers
- Distance-based store suggestions

###  Dashboard & Analytics

Sales Analytics
- Real-time statistics
- Monthly sales reports
- Weekly sales trends
- Revenue growth tracking
- Order distribution analytics

Insights
- Top-selling products
- Recent orders overview
- Customer analytics
- Low stock alerts
- Pending order counts

###  Notifications System

Notification Management
- Create notifications for users
- Mark notifications as read
- Mark all notifications as read
- Get unread notification count
- Clear all notifications
- Delete specific notifications
- Notification history

Notification Types
- Order updates
- Delivery status changes
- Promotional offers
- System announcements

###  Email Services

Email Functionality
- OTP verification emails
- Password reset emails
- Order confirmation emails
- Shipping notifications
- Promotional campaigns
- Account notifications

---

##  Architecture

### Design Patterns

MVC Pattern Separation of concerns with models, controllers, and views
Service Layer Business logic abstraction in dedicated services
Middleware Pattern Request validation and authentication
Repository Pattern Data access layer abstraction
Factory Pattern Object creation and database connections

### API Structure

```
Request → Middleware → Controller → Service → Database
                ↓
            Response
```

### Security Layers

1. Input Validation Sanitization and validation of all inputs
2. Authentication JWT token verification
3. Authorization Role-based access control
4. Database Prepared statements and parameterized queries
5. Output Encoding Prevention of XSS attacks

---

##  Project Structure

```
backend
│
├── config                          # Configuration files
│   ├── database.php                 # Database connection
│   └── constants.php                # Application constants
│
├── core                            # Core functionality
│   ├── functions.php                # Helper functions
│   ├── middleware.php               # Authentication middleware
│   └── email_service.php            # Email service handler
│
├── auth                            # Admin authentication
│   ├── login.php                    # Admin login
│   ├── logout.php                   # Admin logout
│   ├── change_password.php          # Password change
│   ├── refresh_token.php            # Token refresh
│   ├── validate_session.php         # Session validation
│   └── test_auth.php                # Auth testing
│
├── user_auth                       # Customer authentication
│   ├── signup.php                   # User registration
│   ├── login.php                    # User login
│   ├── logout.php                   # User logout
│   ├── verify_email.php             # Email verification
│   ├── forgot_password.php          # Password reset request
│   ├── verify_reset_otp.php         # OTP verification
│   ├── reset_password.php           # Password reset
│   ├── resend_otp.php               # Resend OTP
│   ├── change_password.php          # Password change
│   └── validate_session.php         # Session validation
│
├── admin                           # Admin management
│   ├── add.php                      # Create admin
│   ├── get_all.php                  # List admins
│   ├── update.php                   # Update admin
│   ├── delete.php                   # Delete admin
│   ├── get_profile.php              # Admin profile
│   ├── update_profile.php           # Update profile
│   ├── update_profile_image.php     # Upload profile image
│   ├── delete_profile_image.php     # Delete profile image
│   ├── change_admin_password.php    # Change password
│   └── test_profile.php             # Profile testing
│
├── categories                      # Category management
│   ├── add.php                      # Create category
│   ├── get_all.php                  # List all categories
│   ├── get_active.php               # List active categories
│   ├── update.php                   # Update category
│   ├── delete.php                   # Delete category
│   ├── upload_image.php             # Upload category image
│   ├── delete_image.php             # Delete category image
│   ├── toggle_status.php            # Toggle active status
│   ├── reorder.php                  # Reorder categories
│   ├── search.php                   # Search categories
│   └── test_categories.php          # Category testing
│
├── products                        # Product management
│   ├── add.php                      # Create product
│   ├── get_all.php                  # List all products
│   ├── get_by_id.php                # Get product details
│   ├── update.php                   # Update product
│   ├── delete.php                   # Delete product
│   ├── upload_image.php             # Upload product images
│   ├── delete_image.php             # Delete product image
│   ├── toggle_status.php            # Toggle availability
│   ├── get_low_stock.php            # Low stock alerts
│   ├── search.php                   # Search products
│   └── test_products.php            # Product testing
│
├── stores                          # Store management
│   ├── add.php                      # Create store
│   ├── get_all.php                  # List stores
│   ├── get_by_id.php                # Store details
│   ├── update.php                   # Update store
│   ├── delete.php                   # Delete store
│   ├── upload_image.php             # Upload store image
│   ├── toggle_status.php            # Toggle store status
│   ├── search.php                   # Search stores
│   └── temp.txt                     # Temporary file
│
├── customers                       # Customer management
│   ├── get_all.php                  # List customers
│   ├── get_by_id.php                # Customer details
│   ├── update.php                   # Update customer
│   ├── delete.php                   # Delete customer
│   ├── search.php                   # Search customers
│   ├── toggle_status.php            # Toggle account status
│   ├── get_orders.php               # Customer orders
│   └── get_top.php                  # Top customers
│
├── cart                            # Shopping cart
│   ├── add_to_cart.php              # Add item to cart
│   ├── get_cart.php                 # Retrieve cart
│   ├── update_cart_item.php         # Update quantity
│   ├── remove_from_cart.php         # Remove item
│   └── clear_cart.php               # Clear cart
│
├── favorites                       # Wishlist management
│   ├── add_favorite.php             # Add to favorites
│   ├── remove_favorite.php          # Remove favorite
│   ├── get_favorites.php            # List favorites
│   └── check_favorite.php           # Check if favorited
│
├── addresses                       # Address management
│   ├── add_address.php              # Add address
│   ├── get_addresses.php            # List addresses
│   ├── update_address.php           # Update address
│   ├── delete_address.php           # Delete address
│   └── set_default_address.php      # Set default
│
├── payment_methods                 # Payment methods
│   ├── add_payment_method.php       # Add payment method
│   ├── get_payment_methods.php      # List payment methods
│   ├── update_payment_method.php    # Update payment method
│   ├── delete_payment_method.php    # Delete payment method
│   └── default_payment_method.php   # Set default
│
├── orders                          # Order management
│   ├── get_all.php                  # List all orders
│   ├── get_by_id.php                # Order details
│   ├── get_by_customer.php          # Customer orders
│   ├── get_by_status.php            # Filter by status
│   ├── update_status.php            # Update order status
│   ├── update_payment_status.php    # Update payment
│   ├── cancel.php                   # Cancel order
│   ├── assign_delivery.php          # Assign delivery
│   ├── get_available_delivery_men.php
│   ├── get_pending_count.php        # Pending count
│   └── search.php                   # Search orders
│
├── user_orders                     # Customer order operations
│   ├── create_order.php             # Place order
│   ├── get_orders.php               # Order history
│   ├── get_order_details.php        # Order details
│   ├── track_order.php              # Track order
│   └── cancel_order.php             # Cancel order
│
├── delivery                        # Delivery management
│   ├── add.php                      # Add delivery person
│   ├── get_all.php                  # List delivery personnel
│   ├── get_by_id.php                # Delivery person details
│   ├── get_available.php            # Available personnel
│   ├── update.php                   # Update details
│   ├── delete.php                   # Remove delivery person
│   ├── toggle_active.php            # Toggle availability
│   ├── update_status.php            # Update status
│   ├── upload_image.php             # Upload image
│   └── search.php                   # Search delivery persons
│
├── user_profile                    # User profile management
│   ├── get_profile.php              # Get user profile
│   ├── update_profile.php           # Update profile
│   ├── upload_profile_image.php     # Upload image
│   ├── delete_profile_image.php     # Delete image
│   ├── change_password.php          # Change password
│   └── delete_account.php           # Delete account
│
├── user_shop                       # Customer shopping
│   ├── get_all_products.php         # Browse products
│   ├── get_product_details.php      # Product details
│   ├── get_active_categories.php    # Active categories
│   ├── get_home_products.php        # Featured products
│   ├── get_new_arrivals.php         # New products
│   ├── get_special_offers.php       # Discounted products
│   ├── get_free_shipping.php        # Free shipping items
│   ├── search_products.php          # Search products
│   └── temp.txt                     # Temporary file
│
├── user_stores                     # Store browsing
│   ├── get_stores.php               # List stores
│   ├── get_store_details.php        # Store details
│   ├── search_stores.php            # Search stores
│   └── test_stores.php              # Store testing
│
├── dashboard                       # Admin dashboard
│   ├── get_stats.php                # Overview statistics
│   ├── get_monthly_sales.php        # Monthly sales
│   ├── get_weekly_sales.php         # Weekly sales
│   ├── get_revenue_growth.php       # Revenue analytics
│   ├── get_order_distribution.php   # Order analytics
│   ├── get_top_products.php         # Best sellers
│   ├── get_recent_orders.php        # Recent orders
│   ├── test_dashboard.php           # Dashboard testing
│   └── test_dashboard_stats.php     # Stats testing
│
├── notifications                   # Notification system
│   ├── create.php                   # Create notification
│   ├── get_all.php                  # List notifications
│   ├── mark_read.php                # Mark as read
│   ├── mark_all_read.php            # Mark all read
│   ├── get_unread_count.php         # Unread count
│   ├── delete.php                   # Delete notification
│   ├── clear_all.php                # Clear all
│   └── test_notifications.php       # Notification testing
│
├── upload                          # File storage
│   ├── categories                  # Category images
│   ├── products                    # Product images
│   ├── profiles                    # Profile images
│   └── stores                      # Store images
│
├── vendor                          # Dependencies
│   ├── autoload.php                 # Composer autoload
│   ├── composer                    # Composer files
│   └── phpmailer                   # PHPMailer library
│       └── phpmailer
│           ├── src
│           │   ├── PHPMailer.php
│           │   ├── SMTP.php
│           │   ├── Exception.php
│           │   └── OAuth.php
│           └── language            # Email templates
│
├── composer.json                    # Composer dependencies
├── composer.lock                    # Dependency lock file
└── structure.txt                    # Project structure
```

---

##  Getting Started

### Prerequisites

- PHP = 7.4 (8.0+ recommended)
- MySQL = 5.7 or MariaDB = 10.2
- ApacheNginx Web server with mod_rewrite enabled
- Composer For dependency management
- SSL Certificate For production deployment

### Installation

1. Clone the repository

```bash
git clone httpsgithub.comyourusernamenaseej-backend.git
cd naseej-backend
```

2. Install dependencies

```bash
composer install
```

3. Configure database connection

Edit `configdatabase.php`

```php
php
define('DB_HOST', 'localhost');
define('DB_NAME', 'naseej_db');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');
```

4. Import database schema

```bash
mysql -u your_username -p naseej_db  databaseschema.sql
```

5. Configure application constants

Edit `configconstants.php`

```php
php
 API Configuration
define('API_VERSION', 'v1');
define('BASE_URL', 'httpsyour-domain.comapi');

 JWT Configuration
define('JWT_SECRET', 'your-secure-secret-key');
define('JWT_EXPIRY', 3600);  1 hour

 Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'noreply@naseej.com');
define('SMTP_FROM_NAME', 'Naseej');

 File Upload Configuration
define('MAX_FILE_SIZE', 5242880);  5MB
define('ALLOWED_IMAGE_TYPES', ['imagejpeg', 'imagepng', 'imagewebp']);
define('UPLOAD_PATH', __DIR__ . '..upload');
```

6. Set directory permissions

```bash
chmod -R 755 upload
chmod -R 755 config
```

7. Configure web server

Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.)$ index.phpurl=$1 [QSA,L]

# Enable CORS
Header set Access-Control-Allow-Origin 
Header set Access-Control-Allow-Methods GET, POST, PUT, DELETE, OPTIONS
Header set Access-Control-Allow-Headers Content-Type, Authorization
```

Nginx
```nginx
location api {
    try_files $uri $uri index.php$query_string;
    
    add_header Access-Control-Allow-Origin ;
    add_header Access-Control-Allow-Methods GET, POST, PUT, DELETE, OPTIONS;
    add_header Access-Control-Allow-Headers Content-Type, Authorization;
}
```

8. Test the installation

```bash
php -S localhost8000
```

Visit `httplocalhost8000authtest_auth.php`

---

