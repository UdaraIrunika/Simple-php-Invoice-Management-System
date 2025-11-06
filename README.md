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
└── database.sql
└── style.css
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

## 📄 Screenshots

<img width="1919" height="957" alt="Screenshot 2025-11-07 042640" src="https://github.com/user-attachments/assets/e75afb6a-267c-410f-a55a-ab92742758da" />

<img width="1905" height="958" alt="Screenshot 2025-11-07 042654" src="https://github.com/user-attachments/assets/ac5c18e9-e278-4e14-8823-1570858483b7" />

<img width="1901" height="955" alt="Screenshot 2025-11-07 042709" src="https://github.com/user-attachments/assets/643bb6d4-64bd-4e74-9665-6b73fa36bd91" />

<img width="1919" height="958" alt="Screenshot 2025-11-07 042718" src="https://github.com/user-attachments/assets/dab9c009-c3ce-4d33-aae1-b42aa95fbf71" />

<img width="1919" height="960" alt="Screenshot 2025-11-07 042726" src="https://github.com/user-attachments/assets/7c05d50d-1fc0-4aaa-a786-d82bd44f7cf3" />

<img width="1919" height="957" alt="Screenshot 2025-11-07 042732" src="https://github.com/user-attachments/assets/7c0d5bdc-62dd-4d75-920b-35a8c459c744" />

<img width="1906" height="957" alt="Screenshot 2025-11-07 042741" src="https://github.com/user-attachments/assets/4230e78c-d4cd-4cda-a37d-684de5bd3351" />

<img width="1905" height="959" alt="Screenshot 2025-11-07 042754" src="https://github.com/user-attachments/assets/1c9375f5-8fd5-4841-900b-98343a1ca1b2" />

<img width="1919" height="960" alt="Screenshot 2025-11-07 042801" src="https://github.com/user-attachments/assets/f3466d50-a8f8-44b1-b813-d6a354cd13d4" />

<img width="1918" height="964" alt="Screenshot 2025-11-07 042808" src="https://github.com/user-attachments/assets/f64d14a1-bb59-41e2-afcc-d8306053dd3d" />

<img width="1919" height="960" alt="Screenshot 2025-11-07 042814" src="https://github.com/user-attachments/assets/7571c659-a86a-4f9a-8f49-598f046aba1b" />

<img width="1904" height="952" alt="Screenshot 2025-11-07 042822" src="https://github.com/user-attachments/assets/8acce344-a530-4837-840f-ecd364b32cbd" />

---

## 🆘 Support

For questions or support:  
📧 **uiindustryprivetlimited@gmail.com**  

**Project Link:** [GitHub Repo](https://github.com/UdaraIrunika/Simple-php-Invoice-Management-System.git)

Built with ❤️ for the travel industry.
