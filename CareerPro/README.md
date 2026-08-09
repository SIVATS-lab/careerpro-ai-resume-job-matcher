CareerPro Suite

AI-Powered Resume Builder, ATS Scanner & Job Matching Platform

CareerPro Suite is a full-stack career platform designed to help students and job seekers create professional resumes, analyze resumes using ATS-style scoring, discover relevant job opportunities, and receive AI-powered career assistance.

Features
📝 AI-powered resume builder
🤖 AI career chatbot
📊 ATS resume scanner
🎯 Job matching based on skills
👤 User registration and authentication
💼 Job listings
🛠️ Admin dashboard
🔐 Role-based access
💾 MySQL database
⚡ PHP REST-style APIs
📱 Responsive interface


Tech Stack

Technology	Usage
PHP	Backend
MySQL	Database
HTML5	Structure
CSS / Tailwind CSS	UI
JavaScript	Frontend interactions
PDO	Database connectivity
Gemini API	AI features
XAMPP	Local development
Project Structure



CareerPro/
├── admin/          # Admin dashboard
├── api/            # Backend API endpoints
├── database/       # MySQL schema
├── includes/       # Configuration & database connection
├── builder.php     # Resume builder
├── chatbot.php     # AI career assistant
├── dashboard.php   # User dashboard
├── jobs.php        # Job listings
├── profile.php     # User profile
└── index.php       # Landing page

Database Setup

Install XAMPP.
Start Apache and MySQL.
Open phpMyAdmin.
Create/import the database using:
database/careerpro_db.sql
Configure your database credentials using environment variables or your local configuration.
Place the project inside:
xampp/htdocs/
Open:
http://localhost/CareerPro/

Security

API keys and production credentials should not be committed to the repository.

Use environment variables for sensitive configuration.

Future Improvements

Resume PDF export
More resume templates
Advanced job recommendation algorithms
LinkedIn integration
Email notifications
Cloud deployment
Improved AI-powered career recommendations

