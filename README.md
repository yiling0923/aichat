# AI Messaging Sales Assistant

A guided menu bot, staff inbox and booking assistant for WhatsApp, Instagram, Messenger and website chat.
Built with PHP and Laravel, runs on XAMPP on your own computer. See `docs/` for the plan (PDF) and the page layouts (PDF).

**Where we are: step 5b (AI mode, optional).** A customer opens the test chat, picks a language, taps buttons
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

### AI mode (step 5b, optional)

Off by default. When on, an AI helps in the chat. The buttons, the booking steps and the menu stay exactly as they are: the AI only steps in when a customer **types something the menu does not understand**, and for your team's inbox.
It uses Claude (model `claude-haiku-5-5`, set with `AI_MODEL`) through Anthropic's official PHP SDK.

**Install the new library once:** after `git pull`, run `composer install` in the project folder.

**Three system settings in `.env`** (then `php artisan config:clear`):

| `AI_PROVIDER=` | Meaning |
|---|---|
| `off` | No AI at all. The default. Costs nothing |
| `fake` | A free pretend AI that follows simple rules and quotes your own data. Use it to try every screen. It is not intelligent |
| `claude` | The real AI. Needs `ANTHROPIC_API_KEY=` from https://console.anthropic.com (pay as you go; a short answer costs a small fraction of a cent) |

**Then each business chooses a mode** at **AI assistant** in the sidebar (owner or manager):

| Mode | What happens |
|---|---|
| Off | Only the menu bot |
| Suggest | Customers never see AI text. When a chat is with your team, the AI drops a suggested reply card into the Inbox (**Send now**, **Edit first** or **Dismiss**) |
| Approve | The AI drafts an answer to a typed question and your team checks it first. The customer sees "One moment, I am checking this with our team" and the chat goes to your team with the draft ready |
| Auto | The AI answers typed questions by itself, with your menu buttons shown under the answer. It says once per chat that it is a virtual assistant |

**What it knows:** your opening hours, services with prices and durations, the information screens of your menu (prices, location, promotions) and the **extra answers** you add on the same page (parking, payment, aftercare). The page shows exactly what it knows.

**Safety built in:**
- It may only quote prices, discounts and links that exist in your own information. If it writes a price or a percentage that is not there, the answer is stopped and the customer gets the normal menu instead. These stops are counted on the page.
- It never offers appointment times. For a booking it points to the **Book now** button.
- An upset customer ("terrible", "refund") gets a short apology and is passed to your team. Someone who asks for a person is passed too. The Inbox shows what the AI understood (intent and mood) next to the customer.
- Customer messages are treated as untrusted text: instructions inside them are ignored.
- A monthly limit of AI calls (default 300, you can change it) and a limit per chat per hour protect your bill. When a limit is reached, or the AI is down, the menu bot carries on as normal.
- The **Try it** box lets you ask a question as a customer would, and shows what the AI understood, without sending anything.

**Summaries:** in the Inbox, **Summarise (AI)** writes a short private note about the chat for your team.

**Try it for free:** set `AI_PROVIDER=fake`, run `php artisan config:clear`, open **AI assistant**, choose **Auto**, Save, and in the test chat type `what is the pricing for a facial` or `what time are you around`. Try `I want a refund` to see the hand-over.
Words the menu already knows (price, hours, book and so on) still go to the menu; the AI handles the rest.

### Simpler menu and better reports

- The side menu is short: **Today, Inbox, Schedule, Customers, Tasks, Orders, Marketing, Reports, Settings**. Everything you set up once (menu builder, booking setup, channels, AI, templates, saved replies, tags, business and team, plan and billing) is on the **Settings** page as cards. **Marketing** has tabs: Automations, Broadcasts, Activity log. "Search all chats" is a link in the Inbox.
- **Reports:** filter by period (7, 30, 90 days, this month, last month, custom dates), channel, service and practitioner. Cards compare with the previous period. Charts for chats and bookings per day, busiest weekdays and hours, bookings by service and practitioner, no-show and cancellation rate, repeat customers, survey ratings, orders, AI use. **Export CSV** gives summary, day by day, bookings, new customers, orders and survey answers for the chosen period and filters (opens in Excel with Chinese names intact; names that start with `=` are made safe).

### Many businesses and self-service (step 6)

Anyone can now open `/signup`, create a business and get a **14-day free trial** (set in `config/plans.php`, together with the plans, prices and limits).

- **Sign-up:** business name, owner, email, password, tick the terms. A confirmation email is sent; until the link is opened the staff area is closed. On your own computer `MAIL_MAILER=log` writes the email to `storage/logs/laravel.log`: open that file and copy the link. For real email put SMTP settings in `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`).
- **Getting started** (owner, left menu): paste your website text or a price list, press **Draft my setup**, check the table, **Approve and save**. Nothing is saved to the business before you approve. With `AI_PROVIDER=off` it reads simple lines such as `Facial - RM 188 (60 min)`; with the AI on, the AI drafts services and answers. Approving also rewrites the Prices, Opening hours and Location screens of the chat menu. Then connect Google Calendar and WhatsApp (links to the pages with **Check connection**), try the test chat, choose a plan, and press **go live**.
- **Plans and limits:** AI replies per month, team members, practitioners, connected channels. At the AI limit (or when the trial ended, or a payment failed) the **AI pauses**, the owner gets an email once a month and sees a banner, and customer messages still reach the inbox and the menu bot keeps working. Adding people past the limit is refused with a message. The sample business has no plan, so it has no limits.
- **Plan & billing** (owner): usage against limits, choose Starter or Growth, cancel renewal (works until the paid month ends), payment list. **No money is taken yet:** the payment provider is a local test mode (`BILLING_PROVIDER=local`). A real provider (Stripe) is connected at go-live (step 7). Renewals run daily through the scheduler (`php artisan aichat:renew-subscriptions`).
- **Platform console** (`/platform`): only for the platform owner. Shows all businesses, plan, users, chats, AI calls, revenue, monthly recurring total, and **Pause / Resume** for any business (a paused business loses its chat and staff area). Sample login after seeding: `platform@example.com` / `password`. Make your own with `php artisan aichat:make-platform-owner you@example.com`.
- **Abuse protection:** sign-up is limited to 5 per hour per address, a hidden bot field, email confirmation, strong unique emails. **Legal pages** `/terms` and `/privacy` are starter text: have a lawyer review them before going public.
- **Isolation:** every table carries the business, every query is scoped to the signed-in business, and tests prove two businesses cannot see each other.

### Beta readiness (step 7)

Code for the invite-only beta is done; the accounts, server and Meta approvals are in **`docs/GO-LIVE.md`** (step-by-step with rough costs).

- **Invite-only sign-up:** `SIGNUP_MODE=invite` in `.env`. The platform console (`/platform`) makes invitation codes (optionally for one email only) and shows which are used. Default `SIGNUP_MODE=open`.
- **Forgot password:** link on the sign-in page, one-hour email link, never reveals whether an email has an account.
- **Customer privacy:** managers can **Download their data** or **Delete their data** on a customer's page (future bookings are cancelled first).
- **Real payments (optional):** `BILLING_PROVIDER=stripe` uses Stripe Checkout and a signed webhook (`/webhook/stripe`). Default stays test mode.
- **Safety:** security headers on every page, `/health` for an uptime monitor, `php artisan aichat:backup` (scheduled daily) and `php artisan aichat:preflight` (checks a server before real customers).

### Automations and marketing (step 5)

Owner or manager, sidebar group **Grow**. Everything is switched off until you turn it on, and every message follows the same rules:
it only goes to a customer who **agreed to be contacted** (they gave their phone number in the booking chat) and never typed `stop`,
and on WhatsApp, Messenger and Instagram only inside the **24-hour window** (WhatsApp can use an approved template name you type in the box).
Skipped messages are recorded with the reason on the **Activity** page.

| Page | What it does |
|---|---|
| **Automations** | Five ready-made messages, each with its own wording in English and 中文: **Satisfaction survey** (asks for 1 to 5, a few hours after the visit; a low score creates a task and tells n8n), **Review request** (sends your review link after a 4 or 5), **Rebooking reminder** (customers not back after N days with nothing booked), **No-show recovery** (after you mark a booking No-show), **Hot lead follow-up** (hot customers who never booked) |
| **Run now (test)** | Sends everything that is due right now, so you can test without waiting. The scheduler does it every 5 minutes |
| **Activity** | Every automatic message and every skipped one with the reason, plus survey answers and review links |
| **Broadcasts** | Write a message, choose who (tag, score, language), see how many people can receive it, then **Send now**. Only people who agreed and never sent STOP, at most 500 per broadcast, never twice |
| **Orders** | A simple list of orders or payments you type in (customer, items, amount, status) |
| **Reports** | Chats, new customers, bookings, handovers, average wait for a staff reply, top buttons (top questions), survey score, paid orders, broadcasts. Last 7, 30 or 90 days |

**Try it:** make a booking in the test chat, then in a terminal mark it as visited by moving its time into the past
(`php artisan tinker`, then `DB::table('bookings')->update(['starts_at'=>now()->subHours(4),'ends_at'=>now()->subHours(3)]);`).
Open **Automations**, switch **Satisfaction survey** on, save, and press **Run now (test)**. The survey appears in that customer's chat with rating buttons.
Tap a rating, then check **Activity**. Switch **Review request** on with your review link first to see the link sent after a good rating.
To test a broadcast, give a customer a tag, create a broadcast for that tag, and press **Send now**.

Comment-to-DM (replying to Instagram or Facebook comments by private message) is not included yet.

### Real channels: WhatsApp, Messenger, Instagram (step 4)

Customers can now chat with the same menu bot on WhatsApp, Messenger and Instagram. Staff answer in the same Inbox.
What the bot sends: text, WhatsApp reply buttons (up to 3 options) or a list (up to 10), and quick replies on Messenger and Instagram.
What it does not handle yet: pictures, voice notes, locations (they are ignored), and comments on posts.
Everything is free while you use the Meta test number and development mode.

**How a message travels:** customer to Meta, Meta calls `/webhook/meta` on your computer, the bot answers, the app sends the answer back through Meta.
For Meta to reach your computer you need a public address, so we use a free tunnel.

#### A. Set up WhatsApp with Meta's free test number (about 30 minutes)

1. Go to https://developers.facebook.com, sign in with Facebook, click **My Apps > Create app**. Choose **Other**, then **Business**, name it `aichat`.
2. On the app dashboard find **WhatsApp** and click **Set up**. Accept creating a test business account if asked.
3. Open **WhatsApp > API Setup**. You will see:
   - a **test phone number** (the "From" number),
   - **Phone number ID** and **WhatsApp Business Account ID**: copy both,
   - a **temporary access token** (valid 24 hours): copy it.
4. Under "To", click **Manage phone number list**, add your own mobile number, and enter the code WhatsApp sends you. The test number can only message numbers on this list.
5. Open **App settings > Basic**, click **Show** next to **App secret**, and copy it.
6. Install ngrok (https://ngrok.com, free account). In a new PowerShell window run, once, `ngrok config add-authtoken YOUR_TOKEN`, then keep this running:

   ```
   ngrok http 80
   ```

   It shows an address like `https://abc-123.ngrok-free.app`. Keep this window open while testing. A free address changes each time ngrok restarts.
   (Alternative: `cloudflared tunnel --url http://localhost:80`.)
7. Open `C:\xampp\htdocs\aichat\.env` in Notepad and set (use your own tunnel address, and keep `/aichat/public`):

   ```
   APP_URL=https://abc-123.ngrok-free.app/aichat/public
   META_APP_SECRET=the app secret from step 5
   META_VERIFY_TOKEN=any-long-random-words-you-choose
   ```

   Then run `php artisan config:clear`.
8. Sign in as owner (use the tunnel address from now on, or http://localhost/aichat/public) and open **Channels**. The page shows your **Callback URL** and **Verify token**.
9. Under **Add an account** choose **WhatsApp**, paste the Phone number ID, WhatsApp Business Account ID and the access token, and click **Add channel**. Then click **Check connection**: it should say "Connected to ...".
10. **Easiest way:** add `META_APP_ID=` (Meta app dashboard, Application Settings > Basic, "Application Number") to `.env`, run `php artisan config:clear`, keep the tunnel open,
    and press **Save these in Meta for me** on the Channels page. It saves the Callback URL and Verify token in Meta for you. Then press **Subscribe to messages** on the channel and skip to step 11.
    (The manual way, if you prefer: in Meta: **WhatsApp > Configuration > Webhook > Edit**. Paste the Callback URL and Verify token from the Channels page and click **Verify and save**.
    Then under **Webhook fields** click **Subscribe** next to **messages**. In the app, also press **Subscribe to messages** on the channel.)
11. Test: on the Channels page type your phone number (country code, no `+`) and press **Send a test message**. You get Meta's "hello_world" message.
    Reply to it from your phone with `hi`. Within seconds the bot answers with language buttons. Tap through the menu and make a booking. The chat appears in the **Inbox**.

The temporary token stops working after 24 hours (the bot then cannot answer: the channel shows the problem). For a token that does not expire:
**business.facebook.com > Business settings > Users > System users > Add**, give it your app and WhatsApp account, and **Generate token** with `whatsapp_business_messaging` and `whatsapp_business_management`. Paste it in **Channels** under "New access token".

#### Easier: one double-click for the tunnel (Windows)

Free tunnel addresses change every time the tunnel restarts. Instead of editing `.env` and Meta by hand each time, add `META_APP_ID=` to `.env`
(see step 10), then double-click **`tools\start-tunnel.bat`** in the project folder. It starts the tunnel, writes the new address into `.env` as `APP_URL`,
clears the cache and saves the webhook address in Meta. If the tunnel drops it starts a new one and does it all again. Keep its window open while you test.
(The same webhook step can be run by hand with `php artisan aichat:meta-webhook`.) This script was not tested on Windows by its author.

#### B. The 24-hour window and templates

WhatsApp, Messenger and Instagram only allow free-form replies for 24 hours after the customer's last message. After that:
- the Inbox shows a yellow bar and blocks normal replies,
- on WhatsApp you can send an **approved template** (the **Send a template** button), and booking reminders use a template you choose on the **Channels** page.

Manage templates on the **Templates** page (owner or manager): write a message with `{{1}}`, `{{2}}` for parts that change, send it to Meta for review,
and press **Refresh status** until it says approved (usually minutes). Example: `Hi {{1}}, a reminder of your {{2}} appointment on {{3}}.`

#### C. Messenger and Instagram (development mode)

These work only for people who have a role on your Meta app (you, admins, testers) until Meta approves your app. That is enough for testing.

- **Messenger:** add the **Messenger** product to the same Meta app and connect a Facebook Page you manage. Generate a **Page access token**.
  In **Channels** add **Messenger** with the Page ID and that token, click **Check connection** and **Subscribe to messages**.
  In Meta, Messenger settings > Webhooks: use the same Callback URL and Verify token, and subscribe the page to `messages` and `messaging_postbacks`.
- **Instagram:** use an Instagram professional account linked to that Page, add the Instagram messaging product, and give the app the permissions `instagram_manage_messages` and `pages_messaging`.
  In **Channels** add **Instagram** with the Instagram account ID, the linked Facebook Page ID and the Page token. Use the same webhook values.
- Message the Page or the Instagram account from a tester account. The bot answers with quick replies.

These Messenger and Instagram steps follow Meta's documentation but I could only test them against a simulated Meta, so expect small differences in Meta's screens.

#### D. Safety

Every message from Meta must carry a valid signature made with your app secret, otherwise it is rejected. Access tokens are stored encrypted and never shown again.
The same message delivered twice is only handled once. A number or page can belong to one business only.

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
| **Orders**, **Reports** | Simple order records; numbers for the owner | everyone |
| **AI assistant** | AI mode: how it helps, extra answers, a test box, usage | owner, manager |
| **Automations**, **Broadcasts** | Automatic messages and opt-in broadcasts | owner, manager |
| **Templates** | WhatsApp message templates: create, send to Meta, check approval | owner, manager |
| **Channels**, **Menu builder**, **Booking setup**, **Settings** | Bot menu, services and hours, business name, time zone, accent colour, team accounts | owner |

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

**Easiest:** double-click **`tools\setup-scheduler.bat`** in the project folder once. It adds a Windows task that runs the jobs every minute while you are signed in to Windows (this script was not tested on Windows by its author). The staff area shows a yellow warning on Today, Automations and Settings until the jobs are running. The manual way is below.

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
| `app/Channels` | Meta webhook parsing, sending to WhatsApp, Messenger and Instagram, templates |
| `app/Ai` | AI mode: Claude and the fake AI, the business knowledge, the safety check |
| `app/Marketing` | Automations, survey answers, broadcasts, and the rules for sending to customers |
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

Step 7 needs your decisions and accounts: Meta business verification, a real WhatsApp number, a server, a domain, email sending and a legal review (all in `docs/GO-LIVE.md`). Step 8: public launch (open sign-up, multi-branch, packages, e-commerce, more channels).
