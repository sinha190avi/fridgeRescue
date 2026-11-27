# 🥦 FridgeRescue — Eat it before it expires.

> An AI-powered smart pantry web application that prevents food waste. Snap a photo of your fridge or groceries, let Google Gemini Vision detect items and forecast expiration dates, cook personalized rescue recipes prioritizing soon-to-expire groceries, and track your financial and environmental savings.

![FridgeRescue Architecture](https://img.shields.io/badge/Stack-PHP_8_•_MySQL_•_Gemini_AI-1F4D3A?style=for-the-badge)
![Zero Frameworks](https://img.shields.io/badge/Frameworks-None_(Vanilla_PHP_%2B_JS)-A6D854?style=for-the-badge&labelColor=1F4D3A)
![AI Model](https://img.shields.io/badge/AI-Google_Gemini_2.5_Flash-E4572E?style=for-the-badge)

---

## 🌟 What the Product Does

Every year, the average household throws away ₹1,200 to ₹2,500 worth of forgotten, spoiled groceries. **FridgeRescue fixes this daily crisis:**

1. **Snap or Upload**: Drag & drop or take a photo of your fridge shelves or grocery haul with your phone/camera.
2. **Gemini Vision Inventory**: Google Gemini 2.5 Flash detects every visible food item, assigns appropriate emojis and categories, and estimates shelf life based on food science.
3. **"The Live Fridge" Dashboard**: Your pantry is visualized as physical shelves with circular SVG freshness rings (Green 5+ days, Yellow 2–4 days, Red pulsing 0–1 days).
4. **The Rescue Shelf**: Items expiring within 48 hours are automatically grouped at the top with an urgent "Cook this now" shortcut.
5. **Tailored Rescue Chef**: Gemini generates exactly 3 creative, home-cooked rescue recipes using your expiring ingredients, filtered by cooking time, dietary style, and spice level.
6. **Celebrate & Track Savings**: Mark a recipe as cooked to trigger confetti celebrations, log saved rupee value, update your 8-week heatmap, and unlock Zero-Waste Achievement Badges.

---

## 🛠️ Tech Stack & Architecture

- **Backend**: Pure **PHP 8.2+** with **PDO** (prepared statements only, emulation disabled), session-based authentication, CSRF tokens on every mutation.
- **Database**: **MySQL 8 / MariaDB** (`utf8mb4_unicode_ci`) with cascading foreign keys and optimized indexes.
- **AI Integration**: **Google Gemini 2.5 Flash** called via server-side PHP cURL using strict structured JSON schemas (`responseMimeType: "application/json"`). Includes defensive JSON parsing, retry logic, and an intelligent offline fallback/demo engine.
- **Frontend**: Modern semantic **HTML5**, custom responsive **CSS3** design system with CSS custom properties (Cream `#FAF6EE`, Forest Green `#1F4D3A`, Lime `#A6D854`, Tomato `#E4572E`), Google Fonts (*Fraunces* + *Inter*), and **Vanilla JavaScript** (Fetch API).
- **Visuals & Charts**: **Chart.js** (weekly savings bar chart & category doughnut) and **Canvas-Confetti** (cooking celebration fireworks).
- **Environment**: Runs locally on **XAMPP (Apache + MySQL)** with zero build tools, zero npm packages, and zero frameworks.

---

## 📁 Project Structure

```
fridgeRescue/
├── .env                       # Local environment secrets (ignored by Git)
├── .env.example               # Template environment configuration
├── .gitignore                 # Ignores .env and user uploads
├── README.md                  # Comprehensive setup & project guide
│
├── index.php                  # Public landing page with dynamic DB impact counters
├── login.php                  # Split-screen login with 1-Click Demo access
├── register.php               # Account creation with starter pantry items
├── logout.php                 # Session termination and flash alert
├── dashboard.php              # "The Live Fridge" shelf dashboard
├── scan.php                   # Drag-and-drop / Camera scanner with Gemini Vision
├── recipes.php                # AI Rescue recipe generator & cooking mode
├── impact.php                 # Weekly charts, 56-day heatmap, & achievement badges
├── settings.php               # Profile, dietary styles, currency, and theme settings
│
├── api/
│   ├── add_item.php           # Manual food item creation
│   ├── update_item.php        # Edit grocery details and shelf-life
│   ├── delete_item.php        # Remove item (marked as consumed vs wasted)
│   ├── scan_fridge.php        # Upload handler, GD auto-resize, & Gemini Vision
│   ├── save_items.php         # Batch save detected items from scan table
│   ├── generate_recipes.php   # Gemini 3-recipe generation with JSON schema
│   ├── mark_cooked.php        # Mark ingredients used, log savings to waste_log
│   ├── save_recipe.php        # Save favorite recipe to recipes_saved table
│   ├── stats.php              # JSON data endpoint for Chart.js & Heatmap
│   └── update_theme.php       # Persist light/dark theme preference
│
├── includes/
│   ├── config.php             # .env parser, constants, and session setup
│   ├── db.php                 # PDO database singleton connection
│   ├── helpers.php            # CSRF guard, XSS escape, freshness rings, flash alerts
│   ├── auth.php               # Session authentication and AI rate limiter (20/hr)
│   ├── gemini.php             # Reusable Gemini 2.5 Flash cURL helper & demo fallback
│   ├── header.php             # HTML head, typography, themes, navigation
│   ├── sidebar.php            # Desktop sidebar + mobile bottom navigation bar
│   └── footer.php             # Modals (Add, Edit, Delete), toasts, JS includes
│
├── assets/
│   ├── css/
│   │   └── style.css          # Design system, CSS variables, dark mode, animations
│   ├── js/
│   │   └── app.js             # Theme toggle, modals, toasts, FAQ accordion
│   └── img/
│       └── samples/           # Sample fridge & grocery photos for 1-click demos
│           ├── sample_fridge_1.jpg
│           └── sample_groceries_2.jpg
│
├── database/
│   ├── schema.sql             # Relational schema (users, items, recipes, waste_log)
│   └── seed.sql               # Demo user, 14 fridge items, 8-week waste history
│
├── tests/
│   └── test_e2e.php           # Automated 13-step integration test suite
│
└── uploads/                   # Staging directory for scanned photos (auto-cleaned)
```

---

## 🚀 Quick Start Guide (XAMPP Setup)

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) with **Apache** and **MySQL** (PHP 8.1 or PHP 8.2).

### 1. Place the Project in XAMPP
Either copy `fridgeRescue` into `C:\xampp\htdocs\fridgerescue` or create a directory junction:
```powershell
cmd /c mklink /J "C:\xampp\htdocs\fridgerescue" "c:\Users\Avilash\Desktop\fridgeRescue"
```

### 2. Start MySQL and Apache in XAMPP
Open the **XAMPP Control Panel** and start both **Apache** and **MySQL**.
*(Or start via command line / batch scripts in `C:\xampp`)*.

### 3. Import Database Schema and Seed Data
Open your terminal or phpMyAdmin (`http://localhost/phpmyadmin/`):
```bash
# Using MySQL CLI:
mysql -u root -e "SOURCE C:/xampp/htdocs/fridgerescue/database/schema.sql;"
mysql -u root -e "SOURCE C:/xampp/htdocs/fridgerescue/database/seed.sql;"
```

### 4. Configure `.env`
In `fridgeRescue/.env`:
```ini
APP_NAME=FridgeRescue
APP_ENV=development
APP_URL=http://localhost/fridgerescue

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=fridgerescue
DB_USER=root
DB_PASS=

# Optional: Get your free API key from Google AI Studio: https://aistudio.google.com/
GEMINI_API_KEY=
GEMINI_MODEL=gemini-2.5-flash
```
> **Note on Demo Mode**: If `GEMINI_API_KEY` is left blank, FridgeRescue automatically uses its built-in realistic mock data generator for both fridge scanning and recipe generation. The demo is 100% functional out of the box!

### 5. Open in Your Browser
Navigate to:
```
http://localhost/fridgerescue/
```

---

## 🔑 Demo Login Credentials

For quick evaluation, click the **⚡ One-Click Demo Login** button on the login page, or use:

- **Email**: `demo@fridgerescue.com`
- **Password**: `Demo@123`

The demo account includes:
- **14 Preloaded Fridge Items**: A mix of expiring today (Spinach, Fresh Paneer), use-soon (Milk, Strawberries, Mushrooms), and fresh items (Bell Peppers, Carrots, Cheddar Cheese).
- **8-Week Historical Waste Log**: Over ₹3,400+ in logged savings so the Impact charts, weekly bar chart, and activity heatmap display rich data immediately.
- **Saved Recipes**: Pre-saved rescue recipe cards.

---

## 🧪 Manual Testing Checklist

Use this checklist to test all core capabilities:

- [x] **Landing Page (`/index.php`)**:
  - Live impact counters (rupees saved, meals rescued, kg CO2e) queried from database.
  - Interactive "The Live Fridge" mockup with animated SVG freshness rings.
  - 3-step "How It Works" guide, Before & After comparison, and FAQ accordion.
- [x] **Authentication (`/login.php`, `/register.php`)**:
  - Split-screen design with brand showcase.
  - 1-Click Demo Login button logs in instantly as Chef Avilash.
  - CSRF protection and password hashing with `password_hash()`.
- [x] **The Live Fridge Dashboard (`/dashboard.php`)**:
  - Personalized time-of-day greeting.
  - 4 summary metrics cards (items in fridge, expiring soon, saved this month, recipes cooked).
  - **Top Rescue Shelf**: Highlighted urgent shelf with items expiring within 48 hours.
  - **Categorized Shelves**: Vegetables, Fruits, Dairy, Leftovers & Prepared Meals.
  - Dynamic SVG circular freshness rings (pulsing red for 0–1d, yellow for 2–4d, green for 5+d).
  - Client-side search bar and filter chips (All, Expiring, Use Soon, Fresh, Expired).
  - Add Item Modal (`+ Add Item`), Edit Modal, and Delete Modal with reason tracking.
- [x] **AI Grocery Scanner (`/scan.php`)**:
  - 1-Click Sample Buttons: "📸 Try Sample Fridge 1" and "🛒 Try Sample Haul 2".
  - Drag-and-drop file upload & mobile camera capture (`capture="environment"`).
  - High-tech scanning animation: moving lime laser line and detection overlays.
  - Editable review table: adjust item names, categories, quantities, and auto-computed expiry dates.
  - Stock My Fridge button batch saves items to the database.
- [x] **Rescue Recipe Generator (`/recipes.php`)**:
  - Auto-selects expiring items from the Rescue Shelf.
  - Quick-add chip cloud of other active items in your fridge.
  - Controls for cooking time (15/30/45 min), dietary preference, cuisine style, and spice level slider.
  - Gemini generates exactly 3 recipes with ingredients and step-by-step instructions.
  - Recipe Detail Modal with interactive cooking checklist.
  - **Mark as Cooked**: Updates items to 'used', logs saved value to `waste_log`, and triggers **canvas-confetti fireworks**!
- [x] **Impact & Food Savings (`/impact.php`)**:
  - Big animated currency counter.
  - Chart.js Weekly savings vs food loss bar chart.
  - Chart.js Food category breakdown doughnut chart.
  - 8-Week Activity Heatmap (GitHub-style 56-day activity grid).
  - Zero-Waste Achievement Badges (Fridge Hero, Zero Waste Streak, Leftover Legend, Smart Sentinel).
  - "Share My Impact" button that copies a formatted summary to your clipboard.
- [x] **Kitchen Settings (`/settings.php`)**:
  - Profile preferences: alert days (1–7 days), dietary restriction, currency symbol (₹, $, €, £), dark/light mode toggle.
  - Change password and Danger Zone account deletion.

---

## 🤖 Google AI Technologies Used

- **Google Gemini 2.5 Flash (`gemini-2.5-flash`)**: Multi-modal vision and language model.
- **Server-Side cURL Architecture**: API keys are strictly kept server-side in `.env` and never exposed to the client browser.
- **Structured JSON Schema (`responseSchema`)**: Forces deterministic JSON output with typed fields (`name`, `category`, `estimated_shelf_life_days`, `estimated_price_inr`).
- **Defensive Parsing & Fallbacks**: Cleans markdown code fences, handles timeouts, and seamlessly provides realistic demo data if offline.
- **AI Safety & Rate Limiting**: Enforces a per-user rate limit (20 scans/hr, 25 recipe gens/hr) and includes health & safety disclaimers.

---

## 📄 License
Open source for personal and community use. Built with ❤️ and Google AI for a zero-waste planet.
