# How to run and test everything (steps 0 to 4)

Use the sample business "Lumiere Aesthetic Clinic (sample)". Sample logins: `owner@example.com` / `password` (everything) and `staff@example.com` / `password` (inbox, schedule, customers, tasks).

## 1. Start of every working session (Windows)

| # | Do this | Why |
|---|---|---|
| 1 | Start **Apache** and **MySQL** in the XAMPP Control Panel | The app and its database |
| 2 | `cd C:\xampp\htdocs\aichat` then `git pull origin claude/sleepy-brown-fv558x` | Get the newest code |
| 3 | `php artisan migrate` (only if the pull brought new files in `database\migrations`) | New tables |
| 4 | Make sure the Windows Task Scheduler job runs `php artisan schedule:run` every minute (README: "Staff reminders") | Handover timeouts, booking reminders, automations |
| 5 | Only for WhatsApp: double-click or run `.\tools\start-tunnel.bat` and keep that window open | Lets Meta reach your computer and saves the new address in Meta |
| 6 | Only for WhatsApp, once per day: Meta app > WhatsApp use case > Step 1 > **Generate password**, then paste it in **Channels > New access token > Save** | The test token expires after 24 hours |

Manage the app from `http://localhost/aichat/public` (no tunnel needed). Only Meta needs the tunnel.

## 2. Quick health check (2 minutes)

1. `php artisan test` in the project folder: every test should pass.
2. Open `http://localhost/aichat/public/chat/lumiere`: the bot greets you with English and 中文 buttons.
3. Sign in as owner at `/login`: you land on **Today**.
4. **Channels > Check connection**: green "Connected to +1 555-141-3149" (needs a fresh token).

## 3. Test scenarios

### A. Website chat (menu bot)
1. Open the test chat. Tap **English**. Expect: "How can I help you today?" with buttons and a dashed **Back**.
2. Tap **Prices**, **Opening hours**, **Location**, **Promotions**. Each answers and returns to the menu.
3. Type `price`, `how much`, `2`, `hours`: the bot understands numbers, button names and keywords.
4. Tap **中文**: everything switches to Chinese. Tap **Back** to return to the language question.
5. Type `menu` (main menu), `human` (talk to staff), `stop` (opt out of promotions).
6. Type nonsense three times in a row: the bot hands over to staff.

### B. Booking
1. Tap **Book an appointment** > a service > a day > a time > a name > a phone number. Expect "You are booked".
2. **Schedule**: the booking is under Upcoming. **Customers**: the person is there with score hot.
3. Book the same time again in a second browser: it goes to the second practitioner. A third time: that time is no longer offered.
4. In the chat tap **My booking**: you see it. Tap **Cancel booking**: it is cancelled (if more than 24 hours away). Closer than that, it goes to staff.
5. Wrong tap? Use **Back** on every booking step. Typing `back` while giving a name returns to the times.
6. Check rules in **Booking setup** (hours, gap after appointments, earliest booking, days ahead). Change one, save, and see the chat change.

### C. Staff and handover
1. In the chat tap **Talk to staff**. **Today** shows it under "Needs a person". **Inbox** shows a badge.
2. Open the chat in **Inbox**: add a chat tag, choose who handles it, insert a **saved reply**, press **Send reply**. The reply appears in the chat within seconds and the bot goes quiet.
3. **Add task** from the chat: see it under **Tasks**.
4. **Give back to bot**: the bot answers again. **Resolve** closes the chat.
5. Timeouts: leave a handed-over chat unanswered, then run `php artisan aichat:check-handovers` at different times (5, 10, 20, 30 minutes: holding message, then n8n events).

### D. Admin pages
- **All conversations**: search for a word you typed in a chat; filters work; private notes never appear.
- **Customers**: filter by score, language or tag; tick people and add a tag; open one to edit details and notes.
- **Tags** (owner or manager), **Library** (saved replies), **Settings** (name, time zone, accent colour, team and roles).
- Sign in as `staff@example.com`: Tags, Menu builder, Booking setup, Channels and Settings must be forbidden.

### E. Menu builder (owner)
1. Change a text, try it in the preview panel on the right, press **Save draft** and **Publish**.
2. Open a new chat window: the change is live. Break a link on purpose: Publish refuses and shows what is wrong.
3. **Reset to sample** brings back the sample menu (then Publish).

### F. Google Calendar (optional)
Follow README "Connect Google Calendar". Then add an event in that calendar: that time disappears from the chat. A booking appears as an event; cancelling removes it.

### G. WhatsApp (needs the tunnel and a fresh token)
1. **Channels > Send a test message** to your number: you get Meta's "Hello World".
2. Reply `hi` from your phone: the bot answers with buttons. A menu with more than 3 options arrives as a list.
3. Make a booking on your phone. It shows in **Schedule** and **Customers**.
4. Tap **Talk to staff**, then reply from the **Inbox**: it arrives on your phone.
5. Type `stop`: you are marked opted out (shown on **Customers**).
6. 24-hour window: after 24 hours of silence the Inbox shows a yellow bar; use **Send a template** (create one in **Templates**, wait for approval).

### H. n8n (optional)
Start n8n, import `n8n/workflows/01-aichat-events-starter.json`, put its Production URL in `.env` as `N8N_WEBHOOK_URL`, run `php artisan config:clear`. Start a chat, book, hand over: each shows as an execution.

## 4. Useful commands

| Command | What it does |
|---|---|
| `php artisan test` | Runs all automated tests |
| `php artisan schedule:run` | Runs whatever is due now (the Task Scheduler runs this every minute) |
| `php artisan aichat:check-handovers` | Handover reminders and escalation |
| `php artisan aichat:send-reminders` | Booking reminders (24 h and 2 h before) |
| `php artisan aichat:meta-webhook` | Saves the webhook address in Meta |
| `php artisan config:clear` | Use after every change to `.env` |
| `php artisan migrate:fresh --seed` | **Wipes all data** and restores the sample data |

## 5. If something goes wrong

| Problem | Fix |
|---|---|
| Page shows an error | Look at the last lines of `storage\logs\laravel.log` |
| 419 Page Expired | Use one browser window, same address, and reload the page |
| Bot does not answer on WhatsApp | Tunnel window open? Fresh token? Chat in staff mode (press Give back to bot)? `META_APP_SECRET` correct? |
| "no tunnel" | The tunnel dropped. Restart `tools\start-tunnel.bat` |
| Changed `.env` but nothing changed | `php artisan config:clear` |
| Booking does not appear | In **Menu builder**: Reset to sample, then Publish |
