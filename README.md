# CleanMyData

CleanMyData is a professional SaaS web application built with Laravel and TailwindCSS that allows users to clean, transform, analyze, visualize, and convert datasets efficiently through an intuitive interface and AI-assisted tools.

It is designed for analysts, developers, and businesses who need fast and reliable data preparation without complex software.

## Features

### Core functionality

- Upload and process CSV, Excel, JSON, XML, and TXT files  
- Automatic dataset cleaning and normalization  
- Format conversion between multiple file types  
- Interactive data visualization with charts (bar, line, pie, doughnut, histogram, scatter)  
- Generate professional PDF reports with downloadable charts  
- Select which charts to include in reports  
- File preview before processing  
- Pipeline modes (clean only or full processing)  

### AI Assistant

- Ask questions about uploaded datasets  
- Automatic summaries and insights  
- Detect anomalies, missing values, and trends  

### User system

- Secure authentication (register, login, logout)  
- Subscription-based access tiers (Free, Medium, Pro)  
- File size limits based on plan  
- User dashboard  

### Payments

- Integrated Stripe payments  
- Subscription management  
- Plan upgrades and billing handling  

### Admin panel

- View and manage registered users  
- Monitor platform usage  
- Manage user access and plans  

### UI / UX

- Fully responsive design  
- Modern SaaS-style interface  
- Built with TailwindCSS  
- Alpine.js interactivity  
- Clean and professional dashboard  

## Tech Stack

### Backend

- Laravel 12  
- PHP 8.2+  
- MySQL / MariaDB  

### Frontend

- TailwindCSS  
- Alpine.js  
- Blade  

### Visualization and Export

- Chart.js  
- html2pdf.js  

### Payments

- Stripe API  


## Docker Architecture

CleanMyData runs using Docker to ensure a consistent and scalable development and production environment.

The application is composed of multiple containers:

- **App container (Laravel)**  
  Runs the main Laravel application, handles authentication, dashboards, file processing, and API endpoints.

- **Python container**  
  Executes data processing tasks, cleaning pipelines, and AI-related operations.

- **Scheduler container**  
  Runs scheduled background jobs such as automated processing, maintenance tasks, and queue handling.

- **Stripe service container**  
  Handles payment processing, subscription events, and webhook integration.

- **PostgreSQL container**  
  Stores application data, user accounts, subscriptions, and dataset metadata.

Docker ensures isolation between services, easier deployment, and reproducible environments across development and production.

## Main Modules

- Dataset cleaner  
- File converter  
- Interactive chart dashboard  
- PDF report generator  
- AI data assistant  
- Subscription system  
- Admin panel  


