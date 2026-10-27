# AI Messaging Sales Assistant

A guided menu bot, staff inbox and booking assistant for WhatsApp, Instagram, Messenger and website chat.
Built with PHP and Laravel, runs on XAMPP on your own computer. See `docs/` for the plan (PDF) and the page layouts (PDF).

**Where we are: step 0 (test base).** You can open a test chat as a customer, send a message, get a placeholder reply,
and answer it as staff in the inbox. The menu bot comes in step 1.

## What you need on Windows

| Software | Why | Where |
|---|---|---|
| **XAMPP** with PHP 8.2 or newer | Apache, MySQL and PHP | https://www.apachefriends.org |
| **Composer** | Installs the PHP libraries | https://getcomposer.org (in the installer, pick `C:\xampp\php\php.exe`) |
| **Node.js LTS** (optional) | Only to run n8n later | https://nodejs.org |
| **ngrok** (optional, later) | Lets Meta reach your computer for WhatsApp tests | https://ngrok.com |

## Set up on XAMPP (about 10 minutes)

1. Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
2. Put the project in `C:\xampp\htdocs\aichat`.
   With Git: `cd C:\xampp\htdocs` then `git clone -b claude/sleepy-brown-fv558x https://github.com/yiling0923/aichat.git aichat`
   (or download the branch as a ZIP from GitHub and unzip it into that folder).
3. Open http://localhost/phpmyadmin, click **New**, name the database `aichat`, choose `utf8mb4_unicode_ci`, click **Create**.
4. Open **Command Prompt** in `C:\xampp\htdocs\aichat` and run these one by one:

   ```
   composer install
   copy .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   ```

   (`setup-windows.bat` in the project folder runs the same steps, if you prefer a double-click.)
5. Open the pages:

   | Page | Address |
   |---|---|
   | Start page | http://localhost/aichat/public |
   | Customer test chat | http://localhost/aichat/public/chat/lumiere |
   | Staff inbox | http://localhost/aichat/public/login |

   Sample staff login: `owner@example.com` / `password`. The sample business is made up.

Try it: open the test chat in one browser window and send a message. Sign in to the inbox in another window,
open the conversation, and reply. Your reply appears in the test chat within a few seconds and the bot goes quiet.
Press **Give back to bot** and the bot answers again.

### Without Apache

`php artisan serve` starts a small web server. Then use http://localhost:8000 instead of `http://localhost/aichat/public`
and set `APP_URL=http://localhost:8000` in `.env`.

## Run the tests

```
php artisan test
```

The tests use a temporary in-memory database, so they never touch your real data.
They check the chat, the staff inbox, handover, n8n events, and that one business can never see another business's data.

## n8n (optional)

See `n8n/README.md`. The app works without it.

## If something goes wrong

| Problem | Fix |
|---|---|
| `composer install` says an extension is missing | Open `C:\xampp\php\php.ini`, remove the `;` in front of that `extension=` line, restart Apache. |
| Composer says PHP is too old | Install a newer XAMPP (PHP 8.2 or newer). Check with `php -v`. |
| "Access denied" or "Unknown database" | MySQL not started, or database `aichat` not created. Check `.env` (`DB_DATABASE`, `DB_USERNAME=root`, empty `DB_PASSWORD`). |
| "No application encryption key" | Run `php artisan key:generate`. |
| Pages show 404 or the wrong styles | Use the address with `/public` as in the table. Make sure `APP_URL` in `.env` matches what you type in the browser. |
| Changed `.env` but nothing changes | Run `php artisan config:clear`. |

## Project layout

| Folder | What is inside |
|---|---|
| `app/Bot` | The bot engine (placeholder now, menu bot in step 1) |
| `app/Models` | Business, Customer, Conversation, Message. Every business-owned model is limited to the current business |
| `app/Http` | Controllers (chat, inbox, login) and middleware |
| `app/Services/N8nNotifier.php` | Sends events to n8n |
| `database/migrations` | The database tables, each with a `business_id` |
| `resources/views`, `public/css`, `public/js` | The pages (no build step needed) |
| `tests` | Automated tests |
| `n8n` | Starter workflow and notes |
| `docs` | Plan and page layouts (PDF) |

## Next

Step 1: the guided menu bot (language choice, options, prepared answers, talk to staff), with the menu builder in the admin panel.
