# 🏝️ Invoice Management System

A comprehensive, modern invoice management system specifically designed for travel agencies. Built with PHP, MySQL, and Bootstrap 5, this system streamlines booking management, invoice generation, and financial reporting for travel businesses.

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.0+-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![Chart.js](https://img.shields.io/badge/Chart.js-FF6384?style=for-the-badge&logo=chart.js&logoColor=white)

---

## ✨ Features

### 🧾 Core Functionality
- **Invoice Management** - Create, view, edit, and delete invoices
- **Booking System** - Manage travel bookings with automatic invoice generation
- **Payment Tracking** - Track paid, pending, and overdue invoices
- **Tax & Discount Calculation** - Automatic calculation of totals with tax and discounts
- **Multi-currency Support** - 130+ currencies with automatic formatting

### 📊 Analytics & Reporting
- Dashboard Overview - Real-time statistics and revenue charts
- Financial Reports - Comprehensive financial summaries and trends
- Package Performance - Track most profitable travel packages
- Customer Insights - Top customers and spending patterns

### 🎨 User Experience
- Modern UI/UX - Beautiful gradient design with glass morphism effects
- Responsive Design - Works perfectly on desktop, tablet, and mobile
- Real-time Calculations - Instant total calculations as you type
- Interactive Charts - Beautiful data visualizations with Chart.js

### 🔧 Administrative Features
- Role-based Access - Admin and Staff user roles
- System Settings - Customizable company info, tax rates, and invoice templates
- Activity Logging - Complete audit trail of all system actions
- Email Configuration - SMTP setup for invoice emailing

---

## 🚀 Quick Start

### Prerequisites
- PHP 8.0 or higher  
- MySQL 5.7 or higher  
- Web server (Apache/Nginx)  
- Composer (optional)

### Installation

```bash
git clone https://github.com/your-username/royal-travel-invoices.git
cd royal-travel-invoices
```

Set up the database:

```sql
mysql -u username -p < database/schema.sql
```

Configure the application:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'royal_travel_invoices');
```

Set file permissions:

```bash
chmod 755 uploads/
chmod 644 config.php
```

Access the application:

```
http://localhost/royal-travel-invoices/login.php
```

**Default Login Credentials:**
```
Username: admin
Password: password
```

---

## 📁 Project Structure

```
royal-travel-invoices/
├── config.php
├── login.php
├── dashboard.php
├── invoices.php
├── bookings.php
├── reports.php
├── settings.php
├── invoice_view.php
├── generate_pdf.php
├── logout.php
├── assets/
│   ├── css/style.css
│   ├── js/custom.js
│   └── uploads/
└── database/schema.sql
```

---

## 🗄️ Database Schema

### Core Tables
- `users`
- `booking`
- `invoice`
- `invoice_items`
- `system_log`
- `settings`

---

## 🎯 Key Features in Detail

### Invoice Management
- Auto-generated invoice numbers (RTT-INV-0001)
- Dynamic tax and discount calculations
- Multiple payment statuses (Pending, Paid, Overdue)
- PDF export with professional formatting
- Email integration for invoice delivery

### Booking System
- Pre-defined travel packages with automatic pricing
- Duration-based price calculations
- Booking status tracking
- One-click invoice generation
- Customer information management

### Reporting & Analytics
- Financial summary reports
- Revenue trend analysis
- Package performance metrics
- Customer spending patterns
- Export functionality (CSV, PDF)

### Security Features
- Secure authentication system
- CSRF protection
- SQL injection prevention
- Role-based access control
- Activity logging and audit trails

---

## 🌐 Multi-currency Support

Includes 130+ currencies such as:
USD, EUR, GBP, JPY, CAD, AUD, CNY, INR, SGD, AED, ZAR, BTC, ETH, etc.

---

## 📱 Responsive Design

- Desktop: Full feature access  
- Tablet: Touch-friendly layouts  
- Mobile: Streamlined workflow  

---

## 🔒 Security Best Practices
- Password hashing (bcrypt)
- Session timeout & CSRF protection
- SQL prepared statements
- File upload sanitization

---

## 📈 Performance Optimizations
- Efficient database indexing
- Optimized images and caching
- Minimal dependencies
- Lazy-loaded content

---

## 🤝 Contributing

1. Fork the repo  
2. Create your feature branch  
3. Commit your changes  
4. Push and open a Pull Request

---

## 📄 License

MIT License — see LICENSE file for details.

---

## 🆘 Support

For questions or support:  
📧 **uiindustryprivetlimited@gmail.com**  

**Project Link:** [GitHub Repo](https://github.com/UdaraIrunika/Simple-php-Invoice-Management-System.git)

Built with ❤️ for the travel industry.
