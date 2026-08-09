# 🚀 CareerPro AI — Resume Builder & Job Matcher

**CareerPro AI** is a full-stack web application designed to help students and job seekers build professional resumes, analyze resumes using ATS-style scoring, find relevant job opportunities, and get AI-powered career assistance.

The project is built using **PHP, MySQL, HTML, CSS, JavaScript, and Gemini AI** and includes separate user and administrator functionality.

---

## ✨ Features

### 👤 User Features

* 🔐 User Registration & Login
* 📊 Personalized User Dashboard
* 📝 Professional Resume Builder
* 📄 Resume Management
* 🤖 AI Career Chatbot
* 📈 ATS Resume Scanner
* 🎯 Job Matching
* 💼 Job Listings
* 👤 User Profile Management
* 🚪 Secure Logout

### 🤖 AI Features

* AI-powered career assistance
* Resume analysis
* ATS-style resume scoring
* Resume improvement suggestions
* Career-related chatbot
* Job matching based on user information

### 🛠️ Admin Features

* 🔐 Admin Login
* 📊 Admin Dashboard
* 👥 User Management
* 💼 Job Management
* ⚙️ Admin Settings
* ➕ Add and manage job listings
* 🗑️ Manage users and jobs

---

## 🧰 Tech Stack

| Technology | Purpose                  |
| ---------- | ------------------------ |
| PHP        | Backend development      |
| MySQL      | Database                 |
| HTML5      | Web structure            |
| CSS3       | Styling                  |
| JavaScript | Frontend functionality   |
| Gemini AI  | AI-powered features      |
| PDO        | Database connectivity    |
| XAMPP      | Local development server |

---

## 📂 Project Structure

```text
careerpro-ai-resume-job-matcher/
│
├── admin/
│   ├── index.php
│   ├── jobs.php
│   ├── login.php
│   ├── logout.php
│   ├── settings.php
│   └── users.php
│
├── api/
│   ├── ats-scanner.php
│   ├── auth.php
│   ├── builder-api.php
│   ├── chat-handler.php
│   ├── matcher-api.php
│   └── profile-api.php
│
├── database/
│   ├── careerpro_db.sql
│   └── update_patch_001.sql
│
├── includes/
│   ├── config.php
│   └── db.php
│
├── index.php
├── builder.php
├── chatbot.php
├── dashboard.php
├── jobs.php
├── login.php
├── logout.php
├── profile.php
└── register.php
```

---

## ⚙️ Installation & Setup

### 1. Install XAMPP

Download and install **XAMPP** with Apache and MySQL.

Start:

```text
Apache
MySQL
```

### 2. Clone the Repository

```bash
git clone https://github.com/YOUR-USERNAME/careerpro-ai-resume-job-matcher.git
```

Move into the project directory:

```bash
cd careerpro-ai-resume-job-matcher
```

### 3. Move the Project to XAMPP

Copy the project into:

```text
C:\xampp\htdocs\
```

The final path should look like:

```text
C:\xampp\htdocs\careerpro-ai-resume-job-matcher\
```

### 4. Create the Database

Open:

```text
http://localhost/phpmyadmin
```

Create a database named:

```text
careerpro_db
```

Import:

```text
database/careerpro_db.sql
```

If required, also execute:

```text
database/update_patch_001.sql
```

### 5. Configure Database Connection

Update your local database configuration in:

```text
includes/config.php
includes/db.php
```

Example:

```php
$host = "localhost";
$dbname = "careerpro_db";
$username = "root";
$password = "";
```

> ⚠️ Do not upload real database passwords or API keys to GitHub.

### 6. Configure Gemini AI

If your installation uses the Gemini API, configure your API key locally.

Do **not** commit your real API key to GitHub.

Use an environment variable or a local configuration file that is included in `.gitignore`.

---

## ▶️ Running the Project

After starting Apache and MySQL in XAMPP, open:

```text
http://localhost/careerpro-ai-resume-job-matcher/
```

---

## 🗄️ Database

The project uses **MySQL** for storing application data.

The database files are included in:

```text
database/
```

Main database:

```text
careerpro_db.sql
```

Database update:

```text
update_patch_001.sql
```

---

## 🔒 Security

For security reasons, the following should **never** be committed to the public repository:

* API keys
* Database passwords
* `.env` files
* Production credentials
* Private configuration files

Use `.env.example` to document required environment variables without exposing sensitive information.

---

## 📸 Screenshots

Add screenshots of the major pages here:

### 🏠 Home Page

```text
<img width="1813" height="933" alt="image" src="https://github.com/user-attachments/assets/82505143-43cb-4a71-937d-dac6aed96ab2" />

```

### 📊 Dashboard

```text
<img width="1901" height="952" alt="image" src="https://github.com/user-attachments/assets/f77e542e-4858-4235-a42f-c6739f7b7ece" />

```

### 📝 Resume Builder

```text
<img width="1906" height="959" alt="image" src="https://github.com/user-attachments/assets/9680063a-35d6-4d24-af14-9493a4f1f4ce" />

```

### 📈 ATS Scanner

```text
<img width="1918" height="965" alt="image" src="https://github.com/user-attachments/assets/24ee691c-93ba-486f-9b22-5397f11abf3c" />

```

### 🎯 Job Matcher

```text
<img width="1919" height="973" alt="image" src="https://github.com/user-attachments/assets/00fca663-eda7-4aa6-bd8e-e17bb05ffb3f" />

```

### 🤖 AI Chatbot

```text
<img width="448" height="690" alt="image" src="https://github.com/user-attachments/assets/09594c02-a8c4-42cc-81b2-3db309cb9857" />

```

### 🛠️ Admin Dashboard

```text
<img width="1905" height="955" alt="image" src="https://github.com/user-attachments/assets/65d93f3f-e3df-42ec-a473-b91cbe953787" />

```

---

## 🎯 Project Objectives

The main objectives of CareerPro AI are:

1. Simplify professional resume creation.
2. Help users improve their resumes for ATS screening.
3. Provide AI-powered career guidance.
4. Match users with relevant job opportunities.
5. Provide administrators with tools to manage users and jobs.
6. Demonstrate full-stack web development using PHP and MySQL.

---

## 🔮 Future Improvements

* 📄 PDF resume export
* 🎨 Additional resume templates
* 🔗 LinkedIn profile integration
* 📧 Email notifications
* ☁️ Cloud deployment
* 📱 Improved mobile responsiveness
* 🧠 Advanced AI job recommendations
* 📊 Resume analytics
* 🔍 More advanced ATS analysis
* 🔑 Improved authentication and authorization
* 🌐 Deployment using a production database

---

## 👨‍💻 Developer

**Siva TS**

BCA Student | Full-Stack Web Development | AI & Data Analytics

---

## 📜 License

This project is licensed under the **MIT License**.

---

## ⭐ Support

If you find this project useful, consider giving the repository a ⭐ on GitHub.
