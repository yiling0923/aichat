# AI Messaging Sales Assistant

A guided menu bot, staff inbox and booking assistant for WhatsApp, Instagram, Messenger and website chat.
Built with PHP and Laravel, runs on XAMPP on your own computer. See `docs/` for the plan (PDF) and the page layouts (PDF).

**Where we are: step 1 (guided menu bot).** A customer opens the test chat, picks a language, taps buttons
(prices, hours, location, promotions, book an appointment, talk to staff) and the bot answers. Booking requests are saved
as customers. Staff answer handed-over chats in the inbox. The owner edits the menu in the **Menu builder**. No AI is used yet.

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
4. Make `php` available. In **PowerShell** (or Command Prompt) check:

   ```
   php -v
   ```

   If it says `'php' is not recognized`, XAMPP's PHP is not on your PATH. For the current window run
   `$env:Path += ";C:\xampp\php"` (PowerShell) or `set PATH=C:\xampp\php;%PATH%` (Command Prompt).
   To fix it for good: Windows key > "Edit the system environment variables" > Environment Variables >
   Path > Edit > New > `C:\xampp\php` > OK, then reopen the window.

5. In `C:\xampp\htdocs\aichat` run these one by one:

   ```
   composer install
   copy .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   ```

   (`setup-windows.bat` in the project folder runs the same steps, if you prefer a double-click.)
6. Open the pages:

   | Page | Address |
   |---|---|
   | Start page | http://localhost/aichat/public |
   | Customer test chat | http://localhost/aichat/public/chat/lumiere |
   | Staff login (inbox, customers, menu builder) | http://localhost/aichat/public/login |

   Sample logins: `owner@example.com` / `password` (can use the Menu builder) and `staff@example.com` / `password`.
   The sample business is made up.

### Try the menu bot

1. Open the test chat, send "hi", and tap **English** or **中文**. Tap around. You can also type a number, a button name, or a word like "price".
2. Tap **Book an appointment**, choose a treatment, and give a name and phone number. Sign in and open **Customers**: the lead is there.
3. Tap **Talk to staff** (or type "human"). Sign in as staff, open the chat in the **Inbox**, and reply. Your reply appears in the test chat
   within a few seconds and the bot goes quiet. Press **Give back to bot** and the bot answers again.
4. Sign in as the owner, open **Menu builder**, change a text, press **Publish**, and start a new test chat to see it.
   The panel on the right lets you try the draft first. Nothing you try there is saved as a lead or handover.

Typed words that always work: `menu` (back to the main menu), `human` or `staff` (talk to a person), `stop` (no promotional messages).

### Staff reminders (Windows Task Scheduler)

When a chat is handed over and nobody replies, the app escalates after 5, 10, 20 and 30 minutes (a "thanks for waiting" message to the
customer, then events to n8n). This needs one job that runs every minute. In **Task Scheduler** choose **Create Basic Task**, trigger
**Daily**, then in the task's properties set **Repeat task every 1 minute** for **1 day**. Action: **Start a program**,
program `C:\xampp\php\php.exe`, arguments `artisan schedule:run`, start in `C:\xampp\htdocs\aichat`.
To try it without waiting, run `php artisan aichat:check-handovers` by hand.

### Business hours

Opening hours live in the business `settings` (the seeder sets Mon to Fri 10:00 to 19:00, Sat 10:00 to 17:00). Outside those hours
a handover tells the customer the team is away and will reply when they open. A settings page for this comes in step 3.

### Without Apache

`php artisan serve` starts a small web server. Then use http://localhost:8000 instead of `http://localhost/aichat/public`
and set `APP_URL=http://localhost:8000` in `.env`.

## Run the tests

```
php artisan test
```

The tests use a temporary in-memory database, so they never touch your real data.
They check the menu bot (every button of the sample menu, English and Chinese), lead capture, handover and timeouts, the menu builder,
the staff inbox, n8n events, and that one business can never see another business's data.

## n8n (optional)

See `n8n/README.md`. The app works without it.

## If something goes wrong

| Problem | Fix |
|---|---|
| `'php' is not recognized` | XAMPP's PHP is not on the PATH. See step 4, or type `C:\xampp\php\php.exe` instead of `php`. |
| `composer install` says an extension is missing | Open `C:\xampp\php\php.ini`, remove the `;` in front of that `extension=` line, restart Apache. |
| Composer says PHP is too old | Install a newer XAMPP (PHP 8.2 or newer). Check with `php -v`. |
| "Access denied" or "Unknown database" | MySQL not started, or database `aichat` not created. Check `.env` (`DB_DATABASE`, `DB_USERNAME=root`, empty `DB_PASSWORD`). |
| "No application encryption key" | Run `php artisan key:generate`. |
| Pages show 404 or the wrong styles | Use the address with `/public` as in the table. Make sure `APP_URL` in `.env` matches what you type in the browser. |
| Changed `.env` but nothing changes | Run `php artisan config:clear`. |

## Project layout

| Folder | What is inside |
|---|---|
| `app/Bot` | The menu bot: engine, sample menu, validator, business hours |
| `app/Console` | The handover reminder command |
| `app/Models` | Business, Customer, Conversation, Message. Every business-owned model is limited to the current business |
| `app/Http` | Controllers (chat, inbox, login) and middleware |
| `app/Services` | n8n events, lead saving, handover |
| `database/migrations` | The database tables, each with a `business_id` |
| `resources/views`, `public/css`, `public/js` | The pages (no build step needed) |
| `tests` | Automated tests |
| `n8n` | Starter workflow and notes |
| `docs` | Plan and page layouts (PDF) |

## Next

Step 2: booking with Google Calendar (one calendar per practitioner, free slots, confirmations and reminders).
