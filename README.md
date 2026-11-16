# Uber for Ambulance — Emergency Dispatch & Fleet Management System

A role-based **Emergency Ambulance Dispatch & Fleet Management** web application built for **CSE370 (Database Systems)**. The project models a normalized **3NF MySQL/MariaDB** database using **Option 8A (disjoint-total) EER specialization**, backed by a **PHP + MySQLi** transactional web application for **Patients**, **Drivers**, and **Administrators**.

---

## Database Design & Diagrams

### 1. Enhanced Entity-Relationship (EER) Diagram

![Uber for Ambulance EER Diagram](./Diagram/Uber%20for%20Ambulance%20EER%20Diagram.drawio.png)

- **User Superclass / Subtype Hierarchy (Disjoint-Total):** Every account lives once in `USER` (`user_id`, `username`, `first_name`, `last_name`, `password_hash`) with multivalued `USER_PHONE`, specialized into exactly one role: `PATIENT`, `DRIVER`, or `ADMIN`.
- **Ambulance Superclass / Subtype Hierarchy (Disjoint-Total):** Fleet vehicles live in `AMBULANCE` (`reg_no`, `hourly_rate`, `admin_cost`, `available_status`, `vehicle_type`) and specialize into `BASIC_AMBULANCE` (`stretcher_type`, `kit_class`) or `ICU_AMBULANCE` (`ventilator_count`, `has_oxygen`).
- **Ternary Relationship (`ACCEPTS`):** Connects `RIDE_REQUEST`, `DRIVER`, and `AMBULANCE`, snapshotting `admin_cost`, `driver_earning`, `settlement_status`, and `settled_at` at dispatch time so later fleet changes never alter historical accounting.

### 2. Relational Schema Diagram

![Uber for Ambulance Relational Schema Diagram](./Diagram/Uber%20for%20Ambulance%20Schema%20Diagram.drawio.png)

The logical schema maps cleanly to **10 InnoDB tables** with primary-key-as-foreign-key subtype links, `CHECK` constraints, and cascading rules:

`USER` · `USER_PHONE` · `ADMIN` · `DRIVER` · `PATIENT` · `AMBULANCE` · `BASIC_AMBULANCE` · `ICU_AMBULANCE` · `RIDE_REQUEST` · `ACCEPTS`

---

## Features by Role

| Role | Module | Capabilities |
| :--- | :--- | :--- |
| **Auth** | `index.php`, `signup.php` | Session-based login (`password_verify`), CSRF protection, and role-specific account registration (`Patient`, `Driver`, `Admin`). |
| **Patient** | `patient.php` + Features `01–03` | **01:** Scan available `Basic` (৳1,500) or `ICU` (৳8,000) ambulances.<br>**02:** Broadcast ride requests with automatic least-loaded admin routing.<br>**03:** View ride history, driver contact info, and submit 1–5 star ratings & feedback (atomically recalculates driver rating). |
| **Driver** | `driver.php` + Features `04–06` | **04:** Track total settled earnings, completed trips, and patient reviews.<br>**05:** Accept pending requests by pairing a matching available ambulance using `FOR UPDATE` row locking.<br>**06:** Complete active trips, settle admin/driver revenue splits, and release the ambulance back to `Available`. |
| **Admin** | `admin.php` + Features `07–09` | **07:** Deploy or delete `Basic` & `ICU` ambulances and monitor total rides, driver earnings, and admin earnings.<br>**08:** View Top-3 driver earners and benchmark drivers below the average completed-ride count.<br>**09:** Access a unified patient & driver contact directory. |

---

## Tech Stack

- **Backend:** PHP 8+ (`mysqli`, InnoDB transactions with `FOR UPDATE` locking, CSRF tokens, session role guards)
- **Database:** MySQL / MariaDB (3NF schema with `CHECK` constraints and foreign keys)
- **Frontend:** HTML5, custom neo-brutalist CSS (`style.css`), Vanilla JavaScript

---

## Project Structure

```text
Cse370 Project - Uber for Ambulance/
├── Diagram/
│   ├── Uber for Ambulance EER Diagram.drawio.png
│   └── Uber for Ambulance Schema Diagram.drawio.png
├── Source Code/
│   ├── uber_for_ambulance.sql          # Full phpMyAdmin SQL dump
│   └── ambulance/
│       ├── database.sql                # Clean schema & seed script
│       ├── DBconnect.php               # Database connection config
│       ├── db.php                      # Session, CSRF, escaping & layout helpers
│       ├── index.php                   # Sign-in & logout
│       ├── signup.php                  # Multi-role registration
│       ├── style.css                   # Application stylesheet
│       ├── patient.php                 # Patient dashboard & feature_01..03
│       ├── driver.php                  # Driver dashboard & feature_04..06
│       └── admin.php                   # Admin dashboard & feature_07..09
└── README.md
```

---

## Running Locally (XAMPP / Laragon / PHP + MySQL)

1. **Start Apache & MySQL** (for example, via XAMPP Control Panel).
2. **Create and seed the database:**
   - Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
   - Import `Source Code/ambulance/database.sql` (clean schema + seed data) or `Source Code/uber_for_ambulance.sql` (full dump). Both create and populate the `uber_for_ambulance` database.
3. **Verify database credentials** in `Source Code/ambulance/DBconnect.php`:
   - Default configuration uses `localhost`, user `root`, empty password `""`, and database `uber_for_ambulance`. Update if your local MySQL uses a custom password.
4. **Serve the application:**
   - Copy or symlink `Source Code/ambulance` into your web root (e.g., `htdocs/ambulance`), or run PHP's built-in server from `Source Code/ambulance`:
     ```bash
     php -S localhost:8000
     ```
5. **Open in browser:** Visit `http://localhost/ambulance/` (or `http://localhost:8000/`) and sign in with a seeded account below or register a new **Patient**, **Driver**, or **Admin** account via `signup.php`.

---

## Default Demo Accounts (`database.sql`)

> **Note:** Only bcrypt password hashes are stored in `database.sql`. Replace the password placeholders below with the plaintext passwords you set when creating the seed hashes before pushing to GitHub.

| Role | User ID | Username | Default Password |
| :--- | :--- | :--- | :--- |
| **Patient** | `PT-101` *(also `PT-102`, `PT-103`)* | `ariana_chowdhury` *(or `nabil_hasan`, `sadia_khan`)* | `patient123` |
| **Driver** | `DR-201` *(also `DR-202`, `DR-203`)* | `rafi_ahmed` *(or `tania_sultana`, `imran_hossain`)* | `driver123` |
| **Admin** | `AD-901` | `maya_rahman` | `admin123` |

---

## Step-by-Step Application Walkthrough

1. **Sign In or Register (`index.php` / `signup.php`):**
   - Log in using one of the accounts above, or click **Create a patient, driver, or admin account** to register a new user.
2. **Patient Flow (`patient.php`):**
   - Open **Feature 01 (Find an ambulance)** to check available `Basic` (৳1,500) or `ICU` (৳8,000) ambulances.
   - Open **Feature 02 (Request a ride)** to enter pickup and drop locations and submit a ride request.
   - After completion, open **Feature 03 (Ride history)** to view driver contact details and submit a 1–5 star rating and feedback.
3. **Driver Flow (`driver.php`):**
   - Open **Feature 05 (Pending ride requests)**, select a matching available ambulance, and accept a `Requested` ride.
   - Open **Feature 06 (Complete a trip)** to finish the ride, settle the admin cost vs. driver earning split, and release the ambulance back to `Available`.
   - Open **Feature 04 (Earnings & reviews)** to inspect total settled earnings and patient feedback.
4. **Admin Flow (`admin.php`):**
   - Open **Feature 07 (Fleet management)** to deploy new `Basic` or `ICU` ambulances, remove idle ones, and monitor total rides and admin/driver earnings.
   - Open **Feature 08 (Driver benchmark)** to see the top-3 earners and drivers below the average completed-ride count.
   - Open **Feature 09 (Call the network)** to view the unified patient and driver phone directory.
