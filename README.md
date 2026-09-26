# 🍁 Maple House — Comprehensive Senior Care & Assisted Living ERP

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Frontend](https://img.shields.io/badge/Vanilla_CSS3-Glassmorphism-2965F1?style=for-the-badge&logo=css3&logoColor=white)](https://www.w3.org/Style/CSS/)
[![Architecture](https://img.shields.io/badge/Architecture-RBAC%20%7C%20Multi--Portal-green?style=for-the-badge)](https://en.wikipedia.org/wiki/Role-based_access_control)
[![License](https://img.shields.io/badge/License-MIT-blue?style=for-the-badge)](LICENSE)

**Maple House** is an enterprise-grade, full-stack Old Age Home and Senior Care ERP (Enterprise Resource Planning) platform. Built with modern PHP, PDO, MySQL, and a responsive glassmorphic UI, it streamlines elderly care operations, electronic health records (EHR), automated subscription billing, personalized culinary dietary planning, facility logistics, inventory costing, staff payroll, and donor fundraising into a unified, role-segregated ecosystem.

---

## 📑 Table of Contents
1. [Key Features & Highlights](#-key-features--highlights)
2. [Role-Based Access Control (RBAC) Portals](#-role-based-access-control-rbac-portals)
3. [System Architecture & Data Pipelines](#-system-architecture--data-pipelines)
4. [Technology Stack](#-technology-stack)
5. [Database Architecture](#-database-architecture)
6. [Automated Cron & Scheduled Workers](#-automated-cron--scheduled-workers)
7. [Installation & Setup Guide](#-installation--setup-guide)
8. [Directory Structure](#-directory-structure)
9. [Security Implementations](#-security-implementations)
10. [Sample User Accounts for Testing](#-sample-user-accounts-for-testing)

---

## ✨ Key Features & Highlights

- **Multi-Role RBAC System:** Granular access isolation across **6 distinct user roles** (`Admin`, `Manager`, `Doctor`, `Chef`, `Staff`, `Resident`).
- **Electronic Health & Wellness Management:** Real-time vital monitoring, clinical diagnoses, medical history tracking, mental health evaluations, and automated appointment scheduling.
- **Smart Culinary & Dietary Pipeline:** Dynamic mapping connecting resident medical restrictions and culinary preferences (spiciness, oiliness, diabetic diets) directly to kitchen batch preparation and recipe cost tracking.
- **Tiered Resident Subscription Engine:** Automated monthly billing with multiple service packages (Basic to Plan 4), quota allocation (laundry, room cleaning, transport), and grace-period lifecycle management.
- **Automated Payroll System:** Automated salary calculations and renewals scheduled via cron workers on the 10th of every month.
- **Inventory & Supply Chain Costing:** Real-time stock tracking with category organization, unit conversions, consumption auditing per meal session, and CSV export.
- **Facility Infrastructure & Bed Occupancy:** Live visualization of building floors, room availability, occupancy rates, and asset management.
- **Philanthropic Donation Portal:** Public-facing donation system supporting verified and anonymous contributions, transaction tracking, and payment gateways (bKash, Nagad, Rocket, Cards, Bank Transfer).
- **Resident Family & Communication Hub:** Direct announcement broadcasts, event alerts, emergency contacts management, and video call scheduling.

---

## 👥 Role-Based Access Control (RBAC) Portals

The application implements strict server-side session authentication with automatic role detection and redirect gating:

| Portal | Role | Primary Responsibilities & Features |
| :--- | :--- | :--- |
| **Admin & Manager** | `Admin` / `Manager` | Master executive dashboard; resident onboarding; doctor, chef, and staff HR management; facility infrastructure; financial overview (revenue, expenses, net balance); donation verification; inventory procurement; system-wide broadcasts. |
| **Doctor / Medical** | `Doctor` | Patient rosters; EHR logs (vitals, diagnoses, prescription regimens); mental health assessment reports; appointment scheduling; dietary medical approvals. |
| **Chef / Kitchen** | `Chef` | Daily multi-course meal planning (Breakfast, Lunch, Dinner, Snacks); real-time resident taste & dietary preference aggregations; kitchen cooking sessions; ingredient consumption auditing with unit-level cost tracking. |
| **Care Staff** | `Staff` | Daily shift rosters; task execution pipeline (room cleaning, laundry, resident mobility assistance); real-time AJAX task status updates; monthly salary history view. |
| **Resident & Family** | `Resident` | Personal care overview; health record transparency; meal preference configuration (oil/spiciness levels); on-demand service purchases; service quota consumption tracker; video call appointments with family. |
| **Public & Donor** | `Guest` | Informational landing page; responsive virtual tour; transparent pricing plans; secure donation gateway with transaction ID generation. |

---

## 🏛️ System Architecture & Data Pipelines

```
                             [ Public Website / Visitors ]
                                         │
             ┌───────────────────────────┴───────────────────────────┐
             ▼                                                       ▼
      [ Donate Portal ]                                       [ Auth Gateway ]
      (bKash/Nagad/Cards)                                   (login.php / register.php)
             │                                                       │
             │                                           [ Role-Based Redirector ]
             │                                              (dashboard.php)
             │                                                       │
     ┌───────┴───────────────┬───────────────────┬───────────────────┼───────────────────┐
     ▼                       ▼                   ▼                   ▼                   ▼
 [ Admin ]               [ Doctor ]           [ Chef ]            [ Staff ]         [ Resident ]
• Financials           • Health Records    • Meal Planning      • Task Queue      • Profile & Health
• Subscriptions        • Appointments      • Inventory Usage    • Shift Schedule  • Meal Preferences
• Inventory/Rooms      • Mental Health     • Cost Breakdown     • Salary Ledger   • Service Quotas
• User Management      • Care Approvals    • Session Logs                         • Video Calls
     │                       │                   │                   │                   │
     └───────────────────────┼───────────────────┴───────────────────┴───────────────────┘
                             ▼
              [ Core Database Layer (PDO) ]
              • 30+ Relational MySQL Tables
              • Prepared Statements & ACID Transactions
                             ▲
                             │
            [ CLI Background Cron Workers ]
            • cron_subscription_processor.php (Grace period & overdue billing)
            • cron_monthly_salary_renewal.php (10th of every month payroll)
```

---

## 💻 Technology Stack

### **Backend**
- **PHP 8.0+**: Object-Oriented Architecture (OOP), PDO database abstractions, session-based RBAC, input sanitization, and Bcrypt password hashing (`PASSWORD_DEFAULT`).
- **REST-like Internal APIs**: Asynchronous JSON endpoints for task updates (`api/update_task_status.php`), service purchasing (`api/purchase_service.php`), and salary lookups (`api/get_staff_salary.php`).
- **CLI Cron Engine**: Headless background task runners with structured file-based execution logging.

### **Database**
- **MySQL 5.7+ / MariaDB 10.4+**: Highly normalized schema with 30+ tables, foreign key constraints, indexes, views, and automated timestamp tracking.

### **Frontend & UI/UX**
- **Semantic HTML5 & Vanilla CSS3**: Custom design system utilizing CSS variables, glassmorphic backdrop filters, flexbox, and CSS Grid layouts.
- **Vanilla JavaScript (ES6+)**: Dynamic DOM updates, modal controllers, AJAX/Fetch API request handlers without bulky external runtime overhead.
- **Iconography & Fonts**: Font Awesome 6, Google Fonts (Inter / Segoe UI typography).

---

## 🗄️ Database Architecture

The schema contains over **30 relational tables and views** categorized into core functional domains:

1. **Authentication & RBAC:** `users`, `user_roles`
2. **Resident & Subscription Management:** `residents`, `payment_plans`, `plans`, `resident_revenue_history`, `resident_service_quotas`, `services`, `service_requests`, `service_purchases`
3. **Healthcare & Clinical Operations:** `doctors`, `health_records`, `mental_health_reports`, `video_calls`
4. **Kitchen, Meals & Nutrition:** `chefs`, `daily_meals`, `meal_plans`, `meal_items`, `meal_preferences`, `chef_cooking_sessions`, `chef_inventory_usage`, `chef_usage_details`, `chef_usage_sessions`, `chef_daily_assignments` (View)
5. **Staff, Tasks & Payroll:** `staff`, `staff_salaries`
6. **Logistics, Inventory & Infrastructure:** `infrastructure`, `inventory_categories`, `inventory_items`, `inventory_units`, `inventory_transactions`
7. **Accounting, Donations & Bills:** `donations`, `expenses`, `financial_transactions`, `utility_bills`, `payments`
8. **Communication & Messaging:** `announcements`, `notifications`, `messages`, `contact_messages`

---

## ⚙️ Automated Cron & Scheduled Workers

Maple House features automated background daemons that maintain system health without manual administrative intervention:

### 1. Subscription & Grace Period Processor
- **Script:** `admin/cron_subscription_processor.php`
- **Schedule:** Runs daily at 2:00 AM (`0 2 * * *`)
- **Operations:** Identifies subscriptions past their 32-day grace period, flags payment statuses, issues renewal notices, and logs actions.

### 2. Monthly Staff Salary Renewal
- **Script:** `admin/cron_monthly_salary_renewal.php`
- **Schedule:** Evaluates daily at 2:00 AM (`0 2 * * *`), executes payroll on the 10th of each month
- **Operations:** Iterates over active doctors, chefs, and care staff, generates payroll invoices in `staff_salaries`, updates financial balance sheets, and records persistent logs in `admin/logs/`.

---

## 🚀 Installation & Setup Guide

### **Prerequisites**
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.4+
- Web Server: Apache (with `mod_rewrite`), Nginx, or PHP Built-in Server
- Environment Suites: XAMPP, Laragon, WampServer, or native Unix stack

### **Step-by-Step Installation**

#### 1. Clone the Repository
```bash
git clone https://github.com/mahmudulmashrafe/Maple_House.git
cd Maple_House
```

#### 2. Configure Web Server Document Root
Place the project folder inside your web server directory:
- **XAMPP (Windows):** `C:/xampp/htdocs/Maple_House`
- **XAMPP (macOS):** `/Applications/XAMPP/xamppfiles/htdocs/Maple_House`
- **Laragon:** `C:/laragon/www/Maple_House`
- **Linux (Apache):** `/var/www/html/Maple_House`

#### 3. Database Initialization
1. Start your MySQL service.
2. Open phpMyAdmin or your MySQL CLI client:
```bash
mysql -u root -p
```
3. Create the database and import the pre-configured dump:
```sql
CREATE DATABASE maple_house_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE maple_house_db;
SOURCE database/maple_house_db.sql;
```

#### 4. Configure Database Credentials
Edit `config/database.php` to match your local database settings:
```php
<?php
class Database {
    private $host = "localhost";
    private $db_name = "maple_house_db";
    private $username = "root";       // Your database user
    private $password = "";           // Your database password
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>
```

#### 5. Run the Application
Open your browser and navigate to:
```
http://localhost/Maple_House
```
*(Or if using the PHP built-in server: `php -S localhost:8000` -> `http://localhost:8000`)*

#### 6. (Optional) Set Up Cron Automations
Add the following entries to your crontab (`crontab -e` on Unix):
```bash
# Daily subscription grace period verification
0 2 * * * /usr/bin/php /path/to/Maple_House/admin/cron_subscription_processor.php > /dev/null 2>&1

# Monthly staff salary disbursement
0 2 * * * /usr/bin/php /path/to/Maple_House/admin/cron_monthly_salary_renewal.php > /dev/null 2>&1
```

---

## 📁 Directory Structure

```plaintext
Maple_House/
├── admin/                           # Admin & Management Portal
│   ├── dashboard.php                # Master analytics & operational dashboard
│   ├── residents.php                # Resident lifecycle & admission controls
│   ├── doctors.php / chefs.php      # Medical & culinary staff management
│   ├── staff.php                    # Operations staff administration
│   ├── meal_plan.php / meal_items.php # Nutrition & meal catalogue
│   ├── inventory.php                # Stock tracking & procurement
│   ├── finances_overview.php        # Financial balance sheet & cashflow
│   ├── donations_management.php     # Public donation verification & reporting
│   ├── occupancy_overview.php       # Room infrastructure & availability
│   ├── cron_subscription_processor.php # Overdue subscription cron worker
│   └── cron_monthly_salary_renewal.php # Payroll automation cron worker
├── api/                             # Internal AJAX / REST Endpoints
│   ├── get_staff_salary.php         # Salary lookup endpoint
│   ├── purchase_service.php         # Service quota booking API
│   ├── service_request.php          # Resident service dispatcher
│   └── update_task_status.php       # Staff task progression API
├── assets/                          # Static Assets
│   ├── css/                         # Modular stylesheets (style.css, auth.css, etc.)
│   ├── js/                          # Client-side scripts
│   └── images/                      # Branding, backgrounds, icons
├── chef/                            # Culinary & Kitchen Operations Portal
│   ├── dashboard.php                # Daily meal schedules & resident dietary counts
│   ├── daily_cooking.php            # Active cooking sessions & inventory deduction
│   ├── inventory_usage.php          # Ingredient cost & consumption ledger
│   └── meal_planning.php            # Multi-course menu management
├── config/                          # Application Configuration
│   └── database.php                 # PDO database connector class
├── database/                        # Database Assets
│   └── maple_house_db.sql           # Complete schema dump with seed data
├── doctor/                          # Clinical & Medical Staff Portal
│   ├── dashboard.php                # Patient vitals & appointment overview
│   ├── health_records.php           # EHR, diagnosis, and prescription management
│   ├── patients.php                 # Patient directory & medical charts
│   └── appointments.php             # Resident medical checkups
├── resident/                        # Resident & Family Portal
│   ├── dashboard.php                # Resident summary, upcoming meals, and health
│   ├── meal_preferences.php         # Custom dietary restrictions & spice preferences
│   ├── services.php / buy_services.php # Care packages, quota usage & booking
│   ├── health.php                   # Personal medical history transparency
│   └── communication.php            # Family video calls & notices
├── staff/                           # Caregiver & Operations Staff Portal
│   ├── dashboard.php                # Assigned daily duties & resident assistance
│   ├── my_tasks.php                 # Interactive task checklist (cleaning/laundry)
│   ├── schedule.php                 # Shift calendar & duty timing
│   └── my_salary.php                # Personal salary disbursement slips
├── contact_process.php              # Public contact form processor
├── dashboard.php                    # Central role-based router
├── donate.php                       # Public philanthropic donation interface
├── index.php                        # Public-facing responsive homepage
├── login.php / logout.php           # Authentication & session clearance
├── register.php                     # Account registration with role assignment
└── reset_password.php               # Account recovery workflow
```

---

## 🔒 Security Implementations

- **SQL Injection Defense:** All database interactions utilize **PDO prepared statements** with strict type-binding (`bindParam` / `execute([$params])`).
- **Password Security:** Credentials hashed using industry-standard **Bcrypt** (`PASSWORD_DEFAULT`), verified with timing-attack resistant `password_verify()`.
- **Role-Based Guarding:** Every protected portal includes session verification and role authentication checks at the top of the execution lifecycle; unauthorized requests are rejected and redirected.
- **Transactional Integrity:** Complex financial operations and automated cron routines run inside atomic **PDO Transactions** (`beginTransaction`, `commit`, `rollBack`) to prevent database corruption.
- **XSS Mitigation:** Dynamic user inputs rendered in HTML views are sanitized with `htmlspecialchars()`.

---

## 🔑 Sample User Accounts for Testing

The database seed contains pre-configured test users across all user personas (passwords can be updated or reset via `reset_password.php`):

| Role | Username | Email | Sample Responsibility |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin` | `admin@maplehouse.com` | Facility oversight, finance, full management |
| **Doctor** | `doctorsarah` | `doctor@maplehouse.com` | Resident diagnoses, health records, vitals |
| **Chef** | `chefjobbar` | `chefjobbar@gmail.com` | Daily cooking sessions, meal preferences, recipes |
| **Staff** | `staff1` | `staff@maplehouse.com` | Room cleaning, service fulfillment, task updates |
| **Resident** | `resident1` | `resident1@email.com` | Personal dashboard, meal choices, service requests |

---

## 📄 License

This project is licensed under the **MIT License** — feel free to customize and extend it for academic, non-profit, or commercial care facility applications.
