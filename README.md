# AI Messaging Sales Assistant

A guided menu bot, staff inbox and booking assistant for WhatsApp, Instagram, Messenger and website chat.
Built with PHP and Laravel, runs on XAMPP on your own computer. See `docs/` for the plan (PDF) and the page layouts (PDF).

**Where we are: step 3 (business admin panel).** A customer opens the test chat, picks a language, taps buttons
(prices, hours, location, promotions, book an appointment, talk to staff) and the bot answers. Booking requests are saved
as customers. Staff answer handed-over chats in the inbox. The owner edits the menu in the **Menu builder**. No AI is used yet.
Step 2 adds real booking: the customer taps a service, a day and a time, gives a name and phone, and the booking is saved
(and put on the practitioner's Google Calendar if you connect one). Customers can view and cancel their booking in the chat.

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

### Admin panel (step 3)

Sign in and you land on **Today**. The left menu:

| Page | What it does | Who |
|---|---|---|
| **Today** | Chats waiting for staff, today's bookings, your tasks | everyone |
| **Inbox** | Answer chats. Add chat tags, choose who handles it, add a task, insert a saved reply, open the customer | everyone |
| **All conversations** | Every chat including resolved ones. Search inside messages, filter by status, bot or staff, tag | everyone |
| **Schedule** | Bookings, cancel, done, no-show | everyone |
| **Tasks** | Follow-ups with assignee and due date. Also from a chat or customer | everyone |
| **Customers** | One record per phone number. Filter by score, language, tag. Tick people and add or remove a tag. Open one for details, notes, chats, bookings | everyone |
| **Tags** | Customer tags and chat tags: create, rename, recolour, merge, delete | owner, manager |
| **Library** | Saved replies in English and 中文 | everyone reads, owner and manager edit |
| **Menu builder**, **Booking setup**, **Settings** | Bot menu, services and hours, business name, time zone, accent colour, team accounts | owner |

Roles: **owner** does everything, **manager** can also edit tags and the library, **staff** works the inbox, schedule, customers and tasks.
An owner can add team members in **Settings**, switch an account off, or set a new password. The business always keeps at least one active owner.

Try it: book in the test chat, tap **Talk to staff**, then sign in. Open the chat from **Today**, add a tag, insert a saved reply,
add a task, open the customer, and tick them on **Customers** to add the VIP tag. To see the bot use a changed price or answer:
change a service price in **Booking setup** or a text in **Menu builder** (then Publish) and start a new test chat.

Not in this step yet: media folders and forms in the library, saved views, segments, orders and review invitations. They come with later steps.

### Booking (step 2)

1. Sign in as owner and open **Booking setup**. The sample has 5 services and 2 practitioners. Set opening hours and rules there
   (gap after each appointment, how early or far ahead people can book, how late they can cancel).
2. In the test chat tap **Book an appointment**, choose a service, a day, a time, then type a name and phone number.
3. Open **Schedule**: the booking is there. Book the same time again: it goes to the second practitioner, then that time disappears.
4. In the chat tap **My booking** to see it or cancel it. Cancelling inside the cancellation window (24 hours by default) is passed to staff.
5. Reminders: 24 hours and 2 hours before the visit the customer gets a message in the chat and n8n gets a `booking.reminder` event.
   They are sent by the same Task Scheduler job as below (`php artisan aichat:send-reminders` runs it by hand).

**Already ran step 1?** Your published menu is the old one. In **Menu builder** press **Reset to sample**, then **Publish**,
to get the new booking steps.

#### Connect Google Calendar (optional, free)

Without this, bookings still work and double bookings are still blocked, using the app's own calendar.
With it, the app also avoids times already busy in Google Calendar and adds each booking as an event.

1. Go to https://console.cloud.google.com, create a project, and enable **Google Calendar API** (APIs & Services > Library).
2. APIs & Services > Credentials > **Create credentials > Service account**. Open it, **Keys > Add key > JSON**. Save the file as
   `C:\xampp\htdocs\aichat\storage\google.json`. Keep it private; it is ignored by Git.
3. Copy the service account's email (looks like `name@project.iam.gserviceaccount.com`).
4. In Google Calendar, open the practitioner's calendar > Settings and sharing > **Share with specific people** > add that email with
   **Make changes to events**. Copy the **Calendar ID** from the same page.
5. In `.env` set `GOOGLE_SERVICE_ACCOUNT_JSON=C:\xampp\htdocs\aichat\storage\google.json`, run `php artisan config:clear`.
6. In **Booking setup** paste the Calendar ID next to that practitioner and save. The page now says "connected".

If Google cannot be reached when a customer books, the bot tells them and hands the chat to staff instead of guessing.

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
They check the menu bot (every button of the sample menu, English and Chinese), booking (slots, buffers, double booking, Google Calendar with a fake Google, cancel, reminders), lead capture, handover and timeouts, the menu builder,
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
| `app/Bot` | The menu bot: engine, sample menu, validator, business hours, booking steps |
| `app/Booking` | Free times, saving and cancelling bookings, Google Calendar |
| `app/Console` | The handover reminder command |
| `app/Models` | Business, Customer, Conversation, Message. Every business-owned model is limited to the current business |
| `app/Http` | Controllers (chat, inbox, customers, tasks, tags, library, settings, booking, menu) and middleware |
| `app/Services` | n8n events, lead saving, handover |
| `database/migrations` | The database tables, each with a `business_id` |
| `resources/views`, `public/css`, `public/js` | The pages (no build step needed) |
| `tests` | Automated tests |
| `n8n` | Starter workflow and notes |
| `docs` | Plan and page layouts (PDF) |

## Next

Step 4: real channels. WhatsApp test number first (needs Meta setup and a free tunnel), then Instagram and Messenger.
