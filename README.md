# HRNexus

HRNexus is an HR management web application built with Laravel + Inertia + Vue.

It includes modules for:
- Employee management
- Attendance and overtime tracking
- Leave management
- Calendar and events
- Documents and smart search
- Internal chats

## Tech Stack

- PHP 8.2+
- Laravel 12
- Vue 3 + Inertia.js
- Vite
- MySQL (or compatible)

## Quick Setup (Local)

1. Install backend dependencies:
   - `composer install`
2. Install frontend dependencies:
   - `npm install`
3. Create environment file:
   - Copy `.env.example` to `.env`
4. Generate app key:
   - `php artisan key:generate`
5. Configure your database in `.env`
6. Run migrations:
   - `php artisan migrate`
7. Start development servers:
   - `composer run dev`

## Build for Production

- `npm run build`
- `php artisan optimize`

## UAT Seed (Recommended)

For UAT, use the deployment-focused seeder only:

- `php artisan db:seed --class=DeploymentBaseSeeder`

This seeds:
- Default departments
- Default positions
- Holidays (from `HolidaySeeder`, including 2026 entries currently defined)
- One admin account

Default admin login:
- Email: `admin@hrnexus.com`
- Password: `password`

## Clean UAT Reset (Optional)

If you want a full reset:

- `php artisan migrate:fresh --seed --seeder=DeploymentBaseSeeder`

## Testing

- `php artisan test`

## Useful Maintenance Commands

- Clear application caches:
  - `php artisan optimize:clear`
- Rebuild optimized cache:
  - `php artisan optimize`

## Project Docs

- Quick start guide: `docs/HRNexus_QuickStart_OnePager.md`
- Admin SOP: `docs/HRNexus_Admin_Operations_SOP.md`
