# AgriMarket  

**Fresh Produce, Straight from the Farm**

AgriMarket is a web-based agricultural marketplace concept designed to connect farmers and produce suppliers with buyers across Kenya. It provides an online platform where customers can discover agricultural products, view farmer information, add produce to a shopping cart, and place orders. The project also includes administrator-oriented management pages and an M-Pesa payment integration flow.

> **Project status:** This README is based on the files in the supplied `agrimarket-main.zip` archive. The archive contains PHP files from more than one apparent application structure. In particular, several entry-point files require an `includes/` directory that is not present in the archive, while other files use a different database structure. See [Known project issues](#known-project-issues-before-running) before attempting installation.

---

## Table of Contents

- [Project Overview](#project-overview)
- [Objectives](#objectives)
- [Features](#features)
- [User Roles](#user-roles)
- [Technology Stack](#technology-stack)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Installation and Setup](#installation-and-setup)
- [Database Setup](#database-setup)
- [Configuration](#configuration)
- [M-Pesa Integration](#m-pesa-integration)
- [Security](#security)
- [How the System Works](#how-the-system-works)
- [Testing Checklist](#testing-checklist)
- [Known Project Issues Before Running](#known-project-issues-before-running)
- [Future Improvements](#future-improvements)
- [Contributing](#contributing)
- [License](#license)

## Project Overview

Agriculture is an important part of Kenya's economy, but farmers and buyers may face challenges finding one another, accessing market information, and arranging produce sales. AgriMarket aims to make this process easier through a central online marketplace.

Farmers can present their produce and business information, while buyers can browse available products and place orders. The system is intended to support a more direct connection between agricultural producers and customers.

## Objectives

1. Provide an online marketplace for agricultural produce.
2. Help buyers discover produce from farmers and suppliers.
3. Give farmers an online channel for presenting their products.
4. Support product categories, pricing, stock information, and location details.
5. Provide a shopping-cart and checkout workflow.
6. Support order tracking and administrative management.
7. Provide an M-Pesa payment workflow suitable for demonstration and, after correct configuration and testing, Daraja sandbox or production use.

## Features

The supplied source files indicate the following intended functionality:

### Buyer features

- Create a buyer account and sign in.
- Browse the produce catalogue and product categories.
- View individual product information.
- Add products to a shopping cart and manage cart quantities.
- Proceed through checkout and view order information or receipts.
- Manage a buyer profile.

### Farmer features

- Register as a farmer/supplier.
- Provide farm and county information.
- Add agricultural produce with descriptions, prices, quantities, and photos.
- Manage farmer profile and product information.
- View relevant order information.

### Administrator features

- Access an administrator area.
- Review farmer and product records.
- Manage users and orders.
- View marketplace activity and summary information.

### Payments

- Includes an M-Pesa Daraja STK Push handler in `api/checkout.php`.
- Includes a simulation mode intended to support local demonstrations without sending a real payment request.
- Includes a callback handler for payment results.

**Note:** These are features indicated by the source code. Their availability in a working installation depends on resolving the missing and inconsistent project files described below.

## User Roles

| Role | Intended responsibilities |
|---|---|
| Buyer | Browse produce, manage a cart, place orders, and view order details. |
| Farmer | Maintain a farmer profile and list agricultural products for sale. |
| Administrator | Oversee users, listings, and orders. |

## Technology Stack

| Technology | Purpose |
|---|---|
| PHP | Server-side application logic and page processing |
| MySQL / MariaDB | Relational database |
| HTML5 | Page structure |
| CSS3 | Styling and responsive presentation |
| JavaScript | Client-side interactions |
| Tailwind CSS | Utility-based styling assets included in the project |
| Font Awesome | Icons used by the interface |
| PDO | PHP database access |
| Safaricom Daraja API | M-Pesa STK Push payment integration |

## Project Structure

The archive contains the following main files and directories:

```text
agrimarket-main/
├── about.php
├── admin.php
├── admin/
│   ├── add_product.php
│   ├── darshboard.php
│   └── orders.php
├── api/
│   ├── cart.php
│   ├── checkout.php
│   └── produce.php
├── assets/
│   └── css/
│       ├── style.css
│       └── tailwind.css
├── cart.php
├── catalogue.php
├── checkout.php
├── config/
│   ├── constants.php
│   └── db.php
├── config.php
├── contact.php
├── database/
│   ├── schema.sql
│   └── seed.php
├── farmer.php
├── farmer_profile.php
├── farmers.php
├── footer.php
├── functions.php
├── header.php
├── index.php
├── js/
│   ├── app.js
│   └── catalogue.js
├── login.php
├── logout.php
├── orders.php
├── product.php
├── profile.php
├── receipt.php
└── register.php
```

Some files reference an `includes/` directory that is not included in the supplied archive. The structure above lists the files actually present; it does not imply that every page is currently executable.

## Requirements

For a local PHP/MySQL development environment, prepare:

- PHP 8.1 or later recommended.
- MySQL 8 or MariaDB 10.4 or later.
- Apache or another PHP-compatible web server.
- PHP PDO MySQL extension.
- PHP cURL extension for real Daraja API requests.
- PHP Fileinfo extension for uploaded-image MIME checks.
- A modern web browser.
- XAMPP, WAMP, Laragon, or an equivalent local development environment.

The exact minimum PHP version and extension requirements should be confirmed against the completed application and deployment environment.

## Installation and Setup

The following is the intended local setup process. Complete the project-structure corrections in [Known project issues](#known-project-issues-before-running) before expecting the supplied archive to run successfully.

### 1. Install a local server

Install and start Apache and MySQL using XAMPP, WAMP, Laragon, or an equivalent environment.

### 2. Place the project in the web directory

For example, with XAMPP, extract the project into:

```text
C:\xampp\htdocs\agrimarket
```

The project URL would normally be:

```text
http://localhost/agrimarket/
```

### 3. Create the database

Open phpMyAdmin or a MySQL client and import:

```text
database/schema.sql
```

The supplied SQL script creates a database named `agrimarket` and its tables.

### 4. Configure database access

The supplied `config/constants.php` reads database settings from environment variables and otherwise uses these defaults:

| Setting | Default |
|---|---|
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `agrimarket` |
| Username | `root` |
| Password | Empty |

Change these values to match your local MySQL setup. Do not use an unsecured database account in a production environment.

### 5. Load demonstration data

The supplied seed script is designed to run from the project root using the command line:

```bash
php database/seed.php
```

**Warning:** The seed script truncates and recreates the demo records in the tables it targets. Do not run it against a database containing data you need to keep.

### 6. Open the application

After the missing application files and database inconsistencies have been corrected, open:

```text
http://localhost/agrimarket/
```

If the project is hosted at the domain root instead of a subdirectory, its base URL configuration must be adjusted accordingly.

## Database Setup

The supplied `database/schema.sql` defines these main tables:

| Table | Purpose |
|---|---|
| `users` | Buyer, farmer, and administrator account information |
| `categories` | Produce categories |
| `products` | Produce listings, prices, stock, and farmer ownership |
| `orders` | Buyer orders, delivery details, and payment information |
| `order_items` | Individual products recorded within an order |

The schema uses foreign keys to connect users, products, categories, orders, and order items. It also defines indexes to support common lookups and a full-text index for product name and description searches.

## Configuration

The archive contains two configuration approaches:

- `config/constants.php` defines settings such as the site name, currency, delivery fee, database connection, upload limits, and M-Pesa options.
- `config.php` defines a separate environment-based configuration loader and session/security settings.

These files define overlapping constants, including database and base URL settings. The application should be consolidated to use one configuration system before deployment. Do not load both systems in the same request until the duplicate definitions have been resolved.

For production, keep credentials and API secrets outside source control. Use environment variables or a protected local configuration file, and ensure error display is disabled.

## M-Pesa Integration

The file `api/checkout.php` contains an M-Pesa Daraja STK Push workflow.

The supplied configuration in `config/constants.php` includes:

| Variable | Purpose |
|---|---|
| `MPESA_SIMULATE` | Enables or disables simulated payments |
| `MPESA_ENV` | Selects `sandbox` or `production` |
| `MPESA_CONSUMER_KEY` | Daraja consumer key |
| `MPESA_CONSUMER_SECRET` | Daraja consumer secret |
| `MPESA_SHORTCODE` | Paybill or business shortcode |
| `MPESA_PASSKEY` | Daraja passkey |
| `MPESA_CALLBACK_URL` | Public callback URL for payment results |

The configuration defaults to simulation mode. This is appropriate for a local demonstration because it avoids sending a real payment request.

Before enabling live payments:

1. Obtain the required Daraja credentials from Safaricom.
2. Configure the correct production environment and business details.
3. Set a publicly reachable HTTPS callback URL.
4. Verify callback processing and payment status updates.
5. Test successful, cancelled, timed-out, and failed transactions in the sandbox.
6. Never publish consumer secrets or passkeys in the repository.

A simulated payment is not proof that money has been transferred. Live payment functionality must be tested and secured separately.

## Security

The supplied source includes several security-related measures, including:

- PDO prepared statements in the database connection code.
- Password hashing with PHP's `password_hash()` in the registration flow.
- Password verification patterns in the authentication code.
- CSRF token helpers in the supplied `functions.php`.
- HTML output escaping through an `htmlspecialchars()` helper.
- Session cookie settings and session timeout logic in `config.php`.
- Security response headers in `config.php`.
- Validation of uploaded image types and file sizes in the supplied upload helper.
- A simulated M-Pesa mode for local demonstrations.

Security must be reviewed across the complete, consolidated application. In particular, verify authorization on every protected page and API endpoint, validate all input on the server, protect payment callbacks, and use HTTPS in production.

## How the System Works

The intended marketplace workflow is:

1. A visitor opens AgriMarket.
2. The visitor registers or signs in as a buyer or farmer.
3. A farmer adds produce details and makes listings available.
4. A buyer browses categories and available produce.
5. The buyer adds selected items to the cart.
6. The buyer enters delivery information and submits checkout.
7. The application creates an order and starts the configured payment flow.
8. The payment result is processed and the order can be managed.
9. The buyer can view order details or a receipt.

The exact workflow and status transitions should be verified after the application files have been brought into a consistent state.

## Testing Checklist

Use this checklist when the application is complete and running:

- [ ] Home page loads without PHP errors.
- [ ] Database connection succeeds.
- [ ] Buyer registration works.
- [ ] Farmer registration works.
- [ ] Login and logout work correctly.
- [ ] Invalid login attempts are handled safely.
- [ ] Product categories and catalogue display correctly.
- [ ] Product details display correctly.
- [ ] Farmers can create and manage listings.
- [ ] Image uploads reject unsupported files and oversized images.
- [ ] Cart quantities and totals are calculated correctly.
- [ ] Checkout validates delivery information.
- [ ] Orders are saved correctly.
- [ ] Simulated M-Pesa payment flow works as expected.
- [ ] Payment callbacks update the correct order.
- [ ] Buyers can view only their own orders.
- [ ] Farmers can access only the order information intended for them.
- [ ] Administrator pages enforce administrator authorization.
- [ ] The layout works on mobile and desktop screens.


## Future Improvements

Possible improvements for a future version include:

- Add a farmer sales and earnings dashboard.
- Add product reviews and customer ratings.
- Improve search with county, category, and price filters.
- Add order notifications by email or SMS.
- Add delivery status updates and delivery-provider integration.
- Add stock alerts for farmers.
- Improve accessibility and mobile usability.
- Add automated tests for authentication, cart, orders, and payments.
- Add audit logs and stronger administrative reporting.

## Contributing

Contributions and suggestions are welcome.

1. Fork or copy the repository.
2. Create a branch for your change.
3. Make the change and test it locally.
4. Commit with a clear message.
5. Submit a pull request or share the updated project for review.

Please avoid committing database passwords, API credentials, real customer data, or private payment information.

## License

No license file was included in the supplied archive. Unless a license is added by the project owner, all rights remain with the respective copyright holder. Add an appropriate `LICENSE` file before distributing or reusing the project publicly.

---

**AgriMarket — Connecting Farmers to Buyers, One Harvest at a Time.** 
