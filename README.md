# Tiffin Service Management System

A robust, custom-built WordPress Child Theme designed to streamline the operations of a Tiffin (Meal Delivery) Service. Built on top of the Hello Elementor theme, this project offers a comprehensive suite of tools for order management, meal planning, delivery logistics, and financial reporting.

## 🚀 Overview

This project transforms a standard WordPress installation into a full-fledged business management platform for meal delivery services. It replaces manual tracking with automated systems for customer subscriptions, daily dispatch counts, kitchen management, and financial oversight.

## 🌟 Key Features

### 1. Order & Customer Management
-   **Custom Order Forms**: Tailored interfaces for capturing customer preferences and subscription details (`customer-order-form.php`, `add-order.php`).
-   **Customer Profiles**: centralized customer management (`customers-page.php`) with detailed order history.
-   **OTP Login**: Secure, passwordless authentication system for users (`otp-login.php`).

### 2. Meal Planning & Menu System
-   **Dynamic Menu Composition**: Tools to manage daily meal compositions (`meal-plan-compositions.php`).
-   **Tiffin Logic Engine**: Intelligent handling of meal variations and customer preferences (`tiffin_logic.php`).
-   **Menu Scheduling**: System for planning menus in advance (`tiffin-menu-system.php`).

### 3. Subscription & Service Management
-   **Smart Renewals**: Automated tracking of subscription expiries and renewal management (`renewals-page.php`).
-   **Pause/Resume Functionality**: Self-service options for customers to pause their tiffin service for specific dates (`pause-management.php`), automatically adjusting their subscription end dates.

### 4. Logistics & Delivery
-   **Delivery System**: Dedicated module for managing delivery routes and assignments (`delivery-system/`).
-   **Dispatch Counter**: Real-time counters for daily dispatch requirements to aid kitchen operations (`order-dispatch-counter.php`, `cron-tiffin-count.php`).

### 5. Finance & Administration
-   **Financial Dashboard**: comprehensive dashboard for tracking revenue, expenses, and order financials (`finance.php`).
-   **Admin Controls**: Centralized admin settings and dashboard for system configuration (`admin-dashboard.php`, `admin-settings.php`).
-   **Data Export**: Integrated with `PhpSpreadsheet` for exporting reports and data to Excel.

## 🛠️ Tech Stack

-   **Platform**: WordPress (Child Theme of Hello Elementor)
-   **Languages**: PHP, JavaScript (jQuery), CSS3, HTML5
-   **Database**: MySQL (via WordPress DB APIs)
-   **Dependencies**: 
    -   [Hello Elementor Theme](https://elementor.com/hello-theme/)
    -   [PHPOffice/PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) (for Excel exports)
    -   jQuery UI (for date pickers and interactions)

## 📂 Project Structure

```
├── admin-dashboard.php       # Admin control panel
├── customer-order-form.php   # Frontend ordering interface
├── delivery-system/          # Logic for delivery routing and management
├── finance.php               # Financial reporting and analytics
├── tiffin_logic.php          # Core business logic for meal allocation
├── pause-management.php      # Logic for service suspension
├── otp-login.php             # Custom authentication handling
├── renewals-page.php         # Subscription renewal tracking
└── composer.json             # PHP dependencies
```

## ⚙️ Installation

1.  **Prerequisites**: A WordPress installation with the **Hello Elementor** parent theme installed.
2.  **Deploy**: Upload the `hello-elementor-child-v2` folder to your `wp-content/themes/` directory.
3.  **Dependencies**: Run `composer install` within the theme directory to install required PHP libraries.
    ```bash
    cd wp-content/themes/hello-elementor-child-v2
    composer install
    ```
4.  **Activate**: Activate the child theme via the WordPress Admin Dashboard.

## 📜 License

This project is proprietary software designed for specific business requirements. All rights reserved.
