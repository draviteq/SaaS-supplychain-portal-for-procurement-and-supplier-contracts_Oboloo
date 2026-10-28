# 💰 Savings Section

This module handles the "Savings" feature in our application powered by Laravel (backend) and Vue 3 (frontend)

---

## 📂 Project Structure

### 🖙 Backend (Laravel)

Location:
`app/Http/Controllers/Savings/` – Controllers for managing savings
`app/Models/Savings/` – Eloquent models related to savings

Main Components:

`SavingsController.php`: Handles listing, creating, updating, and deleting savings.
`Saving.php`: Model representing the core savings data.

---

### 🎨 Frontend (Vue 3)

Location:
`resources/js/pages/savings/` – Main views for savings feature
`resources/js/views/apps/savings/` – Supportive components used in the views

Main Views:

* `list/index.vue`: Displays the list of existing savings.
* `view/index.vue`: Displays the savings record.
* `create/index.vue`: UI for creating new savings including milestone.


You're right — the **tech stack** isn't explicitly mentioned in the current README. Let's add a dedicated section for that.

Here's the updated section you can add at the top (just below the title):

---

## 🧰 Tech Stack

Backend:

* Laravel (PHP framework)
* Eloquent ORM
* MySQL

Frontend:

* Vue 3
* Vite
* Axios
* Vue Router
* Pinia

---

Would you like me to update the saved README with this section included?

---

## 🚀 Getting Started

1. Migrate the database:
   php artisan migrate

2. Run the development server:
   php artisan serve

3. Start the Vue frontend:
   npm install
   npm run dev

---

## 📌 Notes

* All API endpoints used in Vue components are registered in Laravel routes (`routes/api.php`).
* Vue components communicate via standard `axios` requests; ensure all tokens and headers are correctly configured for auth.
