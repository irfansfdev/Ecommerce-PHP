# 🛒 E-Commerce Platform

A modern and fully functional **E-Commerce Web Application** built with **Core PHP and MySQL**, featuring a complete customer shopping experience, secure checkout, payment processing, automated email notifications, and a dedicated admin dashboard.

### 🌐 Live Demo

**[🚀 Visit Live Website](https://irfan-php-domain.infy.click/)**

---

## ✨ Features

### 🛍️ Customer Side

* 🏠 Modern Home Page
* 🔎 Product browsing and search
* 📦 Product details
* 🛒 Shopping cart
* 👤 Customer registration & login
* 📋 Order placement and order history
* 📱 Responsive design

### 💳 Payment System

* 💵 **Cash on Delivery (COD)**
* 💳 **Stripe Online Payment**
* 🔐 Secure Stripe checkout
* ✅ Automatic order/payment status handling
* 📦 Order completion based on payment/delivery status

### 📧 Email Notifications

The application includes automated transactional email notifications for important order events.

Customers can receive emails when:

* 🛒 An order is placed
* 💳 A Stripe payment is successfully completed
* 📦 Order status is updated
* 🚚 Order is delivered
* ❌ Order is cancelled

Admin/order management workflows can also trigger relevant email notifications when order statuses change.

---

## 🔐 Admin Panel

The dedicated admin dashboard allows administrators to manage the complete store.

### Admin Features

* 📊 Dashboard
* 📦 Product Management
* ➕ Add Products
* ✏️ Edit Products
* 🗑️ Delete Products
* 🗂️ Category Management
* 👥 Customer Management
* 📋 Order Management
* 🔄 Update Order Status
* 💳 Monitor payment/order status
* 📧 Trigger transactional order notifications

---

## 🛒 Order & Checkout Flow

The platform supports both **Cash on Delivery** and **Stripe online payments**.

### Cash on Delivery

```text
Customer
   ↓
Add Products
   ↓
Cart
   ↓
Checkout
   ↓
Cash on Delivery
   ↓
Order Created
   ↓
Admin Updates Status
   ↓
Delivered
```

### Stripe Payment

```text
Customer
   ↓
Add Products
   ↓
Cart
   ↓
Checkout
   ↓
Stripe Payment
   ↓
Payment Confirmation
   ↓
Order Completed
```

---

## 📧 Transactional Emails

The application uses email notifications to keep customers informed throughout the order lifecycle.

Example flow:

```text
Order Placed
     ↓
Email Notification
     ↓
Order Processing
     ↓
Status Updated
     ↓
Email Notification
     ↓
Delivered / Cancelled
     ↓
Final Email
```

This provides customers with real-time updates about their orders.

---

## 🛠️ Tech Stack

| Technology          | Usage                       |
| ------------------- | --------------------------- |
| 🐘 PHP              | Backend & application logic |
| 🗄️ MySQL           | Database                    |
| 🌐 HTML5            | Structure                   |
| 🎨 CSS3             | Styling                     |
| ⚡ JavaScript        | Client-side functionality   |
| 🔧 MySQLi           | Database connection         |
| 💳 Stripe           | Online payments             |
| 📧 PHP Email System | Transactional emails        |
| 📦 Composer         | PHP dependencies            |
| 🚀 InfinityFree     | Hosting                     |

---

## 📁 Project Structure

```text
ecommerce-project/
│
├── admin/              # Admin panel
├── config/             # Configuration & database connection
├── core/               # Core application logic
├── includes/           # Reusable PHP components
├── migrations/         # Database migrations
├── public/             # Public application files & assets
│   ├── index.php
│   └── uploads/
│
├── vendor/             # Composer dependencies
├── .env                # Environment variables (not committed)
├── .htaccess           # Apache configuration
├── composer.json       # Composer dependencies
└── composer.lock       # Locked dependency versions
```

---

## 🗄️ Database

The application uses **MySQL** for storing and managing:

* Users
* Products
* Categories
* Orders
* Order Items
* Customer Information
* Payment Information
* Order Status
* Product Information

Database credentials are handled through environment variables and are not exposed directly in the source code.

---

## 🔐 Security

The project follows security practices including:

* 🔒 Environment variables for sensitive configuration
* 🚫 `.env` excluded from Git
* 🛡️ Protected configuration files
* 🔑 Admin authentication
* 🔐 Secure payment processing through Stripe
* 🗄️ Server-side database operations
* 🔒 Protected database credentials

> **Important:** Never commit `.env`, database passwords, Stripe secret keys, or other sensitive credentials to a public repository.

---

## 🚀 Local Setup

### 1. Clone the Repository

```bash
git clone YOUR_GITHUB_REPOSITORY_URL
```

### 2. Move Into the Project

```bash
cd ecommerce-project
```

### 3. Install Dependencies

```bash
composer install
```

### 4. Configure Environment Variables

Create a `.env` file in the project root:

```env
DB_HOST=your_database_host
DB_USER=your_database_user
DB_PASS=your_database_password
DB_NAME=your_database_name

STRIPE_SECRET_KEY=your_stripe_secret_key
STRIPE_PUBLISHABLE_KEY=your_stripe_publishable_key

MAIL_HOST=your_mail_host
MAIL_USERNAME=your_mail_username
MAIL_PASSWORD=your_mail_password
MAIL_FROM=your_mail_address
```

**Never commit this `.env` file to GitHub.**

### 5. Configure MySQL

Create/configure the required MySQL database and make sure the credentials in `.env` match your environment.

### 6. Run the Project

If using **WAMP**, configure the project through your Apache virtual host and open the local project URL in your browser.

---

## 🌐 Deployment

The application is deployed on **InfinityFree** with a MySQL database.

### Production Features

* 🐘 Core PHP application
* 🗄️ MySQL database
* 💳 Stripe online payments
* 💵 Cash on Delivery
* 📧 Automated email notifications
* 🔐 Environment-based configuration
* 🌐 Apache `.htaccess` routing
* 📦 Composer dependencies

---

## 💡 What I Learned

Through this project, I gained practical experience with:

* Core PHP development
* MySQL database design and queries
* CRUD operations
* Authentication systems
* Shopping cart functionality
* Checkout systems
* Stripe payment integration
* Order management
* Transactional email systems
* Admin dashboard development
* Apache `.htaccess` routing
* Environment variable management
* Composer & PHP dependencies
* Git & GitHub
* PHP deployment and hosting

---

## 🚀 Future Improvements

Planned improvements include:

* ❤️ Wishlist functionality
* ⭐ Product reviews & ratings
* 🔍 Advanced product filtering
* 📊 Advanced sales analytics
* 📱 Further mobile optimization
* 🎟️ Discount & coupon system
* 📦 Advanced inventory management

---

## 👨‍💻 Developer

### Muhammad Irfan

🎓 **BS Computer Science — Iqra University**

💻 **Full-Stack / Frontend Developer**

### 🔗 Links

* 🌐 **[Live Website](https://irfan-php-domain.infy.click/)**
* 🐙 **[GitHub](https://github.com/irfansfdev)**

---

## ⭐ Support

If you find this project interesting, consider giving the repository a ⭐ on GitHub!

---

**Built with ❤️ using Core PHP, MySQL, Stripe & Email Notifications**
