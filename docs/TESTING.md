# How to run and test everything (steps 0 to 7)

Use the sample business "Lumiere Aesthetic Clinic (sample)". Sample logins: `owner@example.com` / `password` (everything), `staff@example.com` / `password` (inbox, schedule, customers, tasks) and `platform@example.com` / `password` (the `/platform` console).

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

### H. Automations and marketing (owner or manager)
1. Make a booking in the test chat. Move its time into the past: `php artisan tinker`, then
   `DB::table('bookings')->update(['starts_at'=>now()->subHours(4),'ends_at'=>now()->subHours(3)]);`
2. **Automations**: switch **Satisfaction survey** on, **Save**, press **Run now (test)**. The chat gets a message with rating buttons. Press Run now again: nothing is sent twice.
3. Tap **5 - Excellent**: thanks, then the main menu. **Activity** shows the rating. Repeat with a new booking and tap **1 - Very poor**: a task "Low survey score" appears under **Tasks**.
4. Switch **Review request** on with a link such as `https://example.com/review`: after a 4 or 5 the link is sent.
5. In **Bookings** mark a past booking **No-show**, switch **No-show recovery** on, set Wait to 0, Run now: the customer gets a message with **Book now**.
6. Type `stop` in the chat, then Run now: **Activity** shows the message as skipped (opted out).
7. **Broadcasts**: give two customers the same tag, create a broadcast for that tag, check "2 can receive it", **Send now**, open it to see the recipients. Pressing Send twice does not send twice.
8. **Orders**: add an order for a customer, change its status. **Reports**: switch between 7, 30 and 90 days.

### I. AI mode (free with the fake AI)
1. `.env`: `AI_PROVIDER=fake`, then `php artisan config:clear`. Open **AI assistant**: it says "System AI: fake".
2. Choose **Auto**, Save. In **Try it** type `what is the pricing for a facial`: it quotes your services and prices.
3. In the test chat (English menu) type `what time are you around`: the AI answers with your opening hours plus "(I am a virtual assistant.)" once, with the menu buttons under it. Type `I want a refund`: an apology, and the chat goes to staff; the Inbox shows "complaint, angry".
4. Type `i would like a slot tomorrow`: it points to **Book now**. Words like `price` or `hours` still go to the menu (the AI only gets what the menu cannot match).
5. Choose **Approve**: type a question, the customer sees "checking with our team", and the Inbox shows a purple AI suggestion card: **Send now**, **Edit first**, **Dismiss**.
6. Choose **Suggest**: customers get no AI text; after **Talk to staff** and another message, the Inbox gets a suggestion card.
7. **Summarise (AI)** in the Inbox adds a private note. Add an **extra answer** (Parking) and see it in "Everything the AI knows".
8. Real AI: `AI_PROVIDER=claude`, `ANTHROPIC_API_KEY=...`, `php artisan config:clear`, then repeat steps 2 to 3. Watch **Recent AI calls** for tokens and the estimated cost.

### K. Sign-up, plans and platform console (step 6)
1. Sign out. Open `http://localhost/aichat/public/signup`, fill the form, tick the terms, **Create my account**. You land on "Check your email".
2. Open `storage\logs\laravel.log`, scroll to the bottom, copy the long `.../email/verify/...` link into the browser. You land on **Getting started**. (Before that, any admin page sends you back to the email page.)
3. Paste into the box: `Cleaning - RM 120 (45 min)`, a new line `Whitening: RM 600 60 min`, a new line `Check-up - 50`. Add an address. **Draft my setup**: a table appears. Untick one row, **Approve and save**. Open **Booking setup** (services are there) and your test chat `/chat/<your-slug>` (type `hi`, English, Prices): the prices are yours.
4. **Plan & billing**: usage shows 0 of the trial limits. **Choose Starter**: a test payment appears, usage limits grow. **Cancel renewal**: still Active until the month ends.
5. Limits: on the trial, **Settings > Team** allows 2 people; adding a third shows a message. **Booking setup** refuses a third practitioner.
6. AI limit: set `AI_PROVIDER=fake`, `php artisan config:clear`, AI assistant > Auto. In tinker set `DB::table('businesses')->where('slug','<slug>')->update(['trial_ends_at'=>now()->subDay()]);` Reload: a red banner says the AI is paused. The test chat still answers from the menu.
7. Sign out, sign in as `platform@example.com` / `password`: you land on `/platform`. See both businesses and the totals. **Pause** yours: its test chat shows 404 and its staff cannot enter. **Resume** brings it back.
8. Two businesses stay apart: sign in as `owner@example.com` (sample clinic) and check Customers shows none of the new business's people.

### L. Beta readiness (step 7)
1. **Forgot password:** sign out, **Sign in > Forgot password?**, enter `owner@example.com`. Open `storage\logs\laravel.log`, copy the `reset-password/...` link from the bottom, choose a new password, sign in. (Set it back to `password` the same way.)
2. **Invite-only:** in `.env` set `SIGNUP_MODE=invite`, `php artisan config:clear`. Open `/signup`: it asks for an invitation code and a made-up code is refused. Sign in as `platform@example.com`, **Invitations > Make a code**, open the link it shows, sign up: it works once. Using the same code again is refused. Set `SIGNUP_MODE=open` afterwards.
3. **Customer privacy:** as owner open a customer with chats, press **Download their data** (a file opens with their chats). Press **Delete their data**: the customer, their chats and future bookings (cancelled) are gone. Staff accounts do not see these buttons.
4. **Health:** open `http://localhost/aichat/public/health`. With the Task Scheduler running it shows `"ok":true`; if the scheduler is not running it shows 503 (that is the point).
5. **Checker:** `php artisan aichat:preflight` prints PASS, WARN or FAIL per setting. On your PC many FAIL (it is not a production server): that is expected.
6. **Backup:** `php artisan aichat:backup` writes a file in `storage\app\backups` (needs `mysqldump` from XAMPP in your PATH: `C:\xampp\mysql\bin`).
7. **Stripe** is not testable until you have a Stripe test account (`docs/GO-LIVE.md` section 5). The automated tests already check the signature, the plan changes and the payment records.

### M. Reports and the simpler menu
1. The left menu has 9 items. **Settings** opens cards for everything else; **Marketing** shows three tabs.
2. **Reports:** change **Period** to 7 days, this month, last month and **Custom dates** (two date boxes appear, then press Apply). Numbers and charts change.
3. Choose a **Channel** (for example WhatsApp) and press Apply: chats and bookings narrow down; a grey note explains what each filter affects. **Reset** clears everything.
4. Hover a bar in the daily charts: a tooltip shows the day and the number. Open "Show the daily numbers as a table".
5. **Export CSV** (top right) > **Bookings (detail)**: a file downloads. Open it in Excel: the columns are Booked on, Visit, Customer, Phone, Service, Practitioner, Status. Try the other files too.
6. With little data the page still works: a new business shows zeros and "No data in this period".

### N. Schedule calendar and drag to move
1. **Schedule** opens the **Month** calendar. Use ← Today → and the practitioner filter. **Week** shows an hourly grid. **List** is the old table.
2. Click a booking: a pop-up shows the details with **Done / No-show / Cancel booking**.
3. **Week view:** drag a booking block to another time or day. A box asks "Move X to Thu 29 Oct, 15:00?" (times snap to 15 minutes). Press **Move it**. The page reloads with the booking at the new time.
4. **Month view:** drag a booking to another day (the time stays the same).
5. Tick **Tell the customer in the chat** (shown when the booking came from a chat): the customer gets "your appointment has been moved to ..." if their chat window is still open.
6. Drop it on a time when the same practitioner already has a booking: the box says so and nothing changes. Dropping in the past is refused too.
7. On a phone (no dragging): open the booking pop-up and use **Change time**.
8. With Google Calendar connected, the event is replaced at the new time. Events you added by hand in Google are not checked when moving.

### O. Polish pass (landing, search, inbox, today)
1. Open `http://localhost/aichat/public/` signed out: a proper landing page with features, how it works and pricing (the demo block with test chats shows only on your PC, `APP_ENV=local`).
2. In the staff area press **Ctrl+K** (or `/`): the search box at the top is focused. Type 2 letters of a customer name or phone: results appear; arrow keys and Enter open one. It also finds words inside chats.
3. **Today** (owner): a "Get set up" card with a progress bar until services, hours, a channel and go-live are done.
4. **Inbox**: each chat shows an initial, channel, time and last message; chats waiting for a person have an amber edge and a "Needs a person" tag.
5. **Booking setup**: "Days you are closed" is a date picker with removable chips (no more typing dates with commas).
6. **Marketing > Automations**: each automation is a folded card (open when switched on).

### P. Bell notifications
1. Open the staff area (the bell is at the top, left of the search box; in the Inbox it is in the chat list header).
2. In another browser window open the test chat, type `human`. Within 20 seconds the bell shows a red number and the tab title shows `(1)`. Open the bell: "Visitor needs a person".
3. Send another message as the customer: the list shows "New message from ..." (one entry, updated, not one per message).
4. Click an entry: it opens that chat and clears all alerts about it. **Mark all read** clears everything.
5. Make a booking in the chat: everyone gets "New booking". Cancel it as the customer (My booking): "Booking cancelled by ...". Add a task assigned to someone else: only they get "New task for you".
6. **Turn on desktop alerts** (bottom of the bell): the browser asks permission; after that a pop-up appears for new alerts while the staff page is open in any tab.

### Q. Channels page
1. **Settings > Channels**: four status cards at the top (Website chat always on; WhatsApp, Messenger, Instagram show Not connected / Connected / Needs attention).
2. **Website chat**: Copy the link, or copy the embed code and paste it into any web page to show the chat there.
3. **Connect a channel**: pick WhatsApp, Messenger or Instagram; the "Where to find these" box changes. **Show** reveals the token while you paste it.
4. Each connected account has a coloured dot (green working, amber off, red problem), **Check connection**, **Subscribe to messages**, a folded **Settings** area (name, token, on/off, reminder template, Remove) and, for WhatsApp, **Send me a test message**.
5. **Meta app connection** (bottom, folded unless something is missing): four checks (public address, app ID, app secret, verify token) with what to do for each, Copy buttons, and **Save these in Meta for me**.

### R. Saved replies
1. **Settings > Saved replies**: cards with a group tag, the English and Chinese text, **Copy English** and (owner or manager) **Edit**. Use the search box and the group buttons (All, Greeting, Prices ...) to filter.
2. **New reply**: a pop-up asks for title, group (pick or type), English and optional Chinese. The `{name}` and `{business}` buttons add placeholders. **Edit** opens the same pop-up with a **Delete** button.
3. Staff accounts see the cards and **Copy English** but no New reply or Edit.
4. In the **Inbox**, "Insert a saved reply" now groups the list. The text goes in the customer's language (Chinese if they chose 中文) and `{name}` becomes their name (or "there" when unknown), `{business}` your business name.

### S. Menu builder polish
1. **Settings > Menu builder**: the tag at the top says **Published**; **Save draft** is greyed out until you change something.
2. Edit any text: the tag changes to **Unsaved changes**. Press **Save draft** or **Ctrl+S**: a "Draft saved" message appears and the tag becomes "Saved, not published". Leaving the page with unsaved changes asks first.
3. Under "What the bot says" the **{name} {business} ...** buttons insert a placeholder where your cursor is.
4. **+ Add step**: a pop-up asks for a name and what the step does (with a one-line explanation). The step id is made for you.
5. Each button is a small card ("Button 1: English"); typed words are folded under "Typed words (n)".
6. **Delete step** warns which other steps link to it. **Publish** refuses a menu with broken links and says so; a good menu says "Published. Customers see the new menu now."

### T. Tags page
1. **Settings > Tags**: two tabs, **People** and **Chats**, with counts. In each tab: a "New tag" row with a name and colour dots, a search box, **A to Z / Most used** sorting, and one row per tag with how many customers or chats use it. The count is a link: it opens Customers or All conversations filtered by that tag.
2. Under **Ideas**, click a suggestion such as "+ VIP": the tag is created at once (suggestions you already have are not shown).
3. **Edit** opens a pop-up: rename, pick a colour (dots or the colour box), **Merge into another tag**, or **Delete tag**. Text on light colours turns dark so it stays readable.
4. Customers list and Inbox still show the tags as coloured chips.

### U. Message templates
1. **Settings > Message templates** (needs a WhatsApp number on Channels). Cards show each template as a green WhatsApp bubble with the changing parts highlighted, its status in plain words (Approved: ready to use, Waiting for Meta, or a problem with Meta's reason), language, type, number and **Used by:** (booking reminders or an automation that uses it).
2. Filter with **All / Approved / Waiting / Problem**. **Refresh status** asks Meta for the latest.
3. **New template** (or a card under **Start from an example**): choose number, language, name (it fixes spaces and capitals for you), type, and write the message. The **1 · name, 2 · service, 3 · date and time** buttons add the changing parts; "How it will look" shows it with sample values. **Send to Meta for review** submits it.
4. **Remove** only removes it from this list; it stays in Meta until you delete it there.

### V. AI assistant page
1. **Settings > AI assistant**: a tag at the top says Real AI, Practice AI or AI off; technical help is folded under "How to turn it on".
2. **How should the AI help?**: four cards (Off, Suggest, Approve "Good start", Auto). Click one, then **Save**.
3. **Tone and style**: type your own or click the "+ Friendly and short" style buttons. **Spending limit** (folded) sets the monthly maximum.
4. **This month**: a bar showing AI replies used against your limit (your plan's limit too), when it resets, and a warning near the limit.
5. **Try it**: click an example question (it uses one of your real services) or type your own. The answer appears as a chat bubble with what the AI understood. (Needs `AI_PROVIDER=fake` or `claude` in `.env`.)
6. **Extra answers**: **Add an answer**, or click an idea such as "+ Parking". **Edit** opens a pop-up with a Delete button and a "may use" switch.
7. Folded at the bottom: everything the AI knows, and the recent AI calls in plain words (Answered, Stopped, Failed).

### W. Orders page
1. **Orders**: four tiles (Paid, Waiting for payment, Refunded, Average paid order) in your currency (set `currency` in business settings, default RM). They follow the filters below.
2. Filters: **Search** (customer, item, reference, note), **Period** (All time, This month, Last month, last 30 or 90 days, This year) and the status buttons with counts. Active filters show as chips you can remove; **Reset** clears them.
3. **New order** opens a pop-up (customer, amount, what, status, date, reference, notes). **Edit** on a row opens the same pop-up with every field and **Delete**; before, only the status could be changed.
4. **Export CSV** downloads the orders for the chosen period (same file as Reports > Export CSV > Orders).

### X. Customer detail page
1. Open any customer (Customers list or the search box). The top shows their initial, name, phone, email, language, since when, lead level, tags and whether they can receive promotions.
2. Buttons: **Open chat** (their latest chat), **WhatsApp** (opens wa.me with their number), **Edit** (pop-up with all details, tags and private notes) and **...** (managers: download their data, delete their data).
3. Four tiles: **Spent** (paid orders), **Bookings** (and the next one), **Last visit**, **Satisfaction** (average of their survey ratings).
4. **Activity** lists everything newest first: upcoming and past bookings, orders, survey ratings, chats and when they became a customer. Click an item to jump to the schedule, orders or chat.
5. **Follow-ups**: open tasks with a **Done** button, and a quick add (title and optional due time). **Notes** shows the private note and what they asked about.

### Y. Tasks page
1. **Tasks**: a quick add row at the top (what, due time, who). The **Today 5pm / Tomorrow 10am / Next week** buttons fill the due time for you. The task is assigned to you by default.
2. Open tasks are grouped: **Overdue** (red), **Today**, **Tomorrow**, **This week**, **Later**, **No date**. Switch between **Open** and **Done**, and **Everyone** and **Only mine**; counts are shown.
3. Click the **circle** to tick a task done (it moves to Done); in Done, click the green tick to reopen it.
4. **Edit** opens a pop-up to change the text, due time and person, or **Delete**. Giving a task to someone else sends them a bell notification.
5. Tasks from a customer page or the Inbox show the customer's name and an **Open chat** link.

### Z. Inbox chat screen
1. Open a chat in the **Inbox**. The header shows the customer's initial, channel, and a tag: **Bot is answering** (green) or **You are handling this** (amber), with **Give back to bot** and **Resolve**.
2. Messages: day dividers (Today, Yesterday), times, who sent it (Bot or Staff), centred grey system notes (such as the handover), and yellow **Private note** cards. If you scroll up to read, new messages do not pull you down: a **N new messages** button appears instead.
3. **Composer**: tabs **Reply to customer** and **Private note**. Enter sends, Shift+Enter makes a new line; the box grows as you type. **Saved replies** (right) inserts the reply in the customer's language with their name filled in.
4. **+ Task** opens a small form (it suggests "Follow up with ..."), no more pop-up prompt boxes. Messages such as "Saved" or errors appear as a short banner at the bottom.
5. WhatsApp chat older than 24 hours: the yellow bar has **Send a template**. Pick one, fill the parts (name is pre-filled), see the preview, press **Send template**.
6. On a phone: the list and the chat are separate screens; the **←** button goes back to the list.

### AA. Business and team, My account
1. **Settings > Business and team**: tabs **Business** and **Team**. Business: name, address, time zone (common ones first, then by region), the currency shown with prices, and the colour of your staff area. Click a colour dot: the whole staff area changes colour at once; press **Save** to keep it. Below it, your customer chat link with Copy.
2. **Team**: everyone with initial, email, role tag and a "(you)" mark, how many team members your plan allows, **Add a team member** (name, email, role, password) and **Edit** (role, switch off, new password). "What each role can do" lists the three roles. Changing your own role away from the last owner is still refused.
3. **My account** (click your name at the bottom of the side menu; every role can open it): change your name and password. A new password needs your current one and must be typed twice.

### J. n8n (optional)
Start n8n, import `n8n/workflows/01-aichat-events-starter.json`, put its Production URL in `.env` as `N8N_WEBHOOK_URL`, run `php artisan config:clear`. Start a chat, book, hand over: each shows as an execution.

## 4. Useful commands

| Command | What it does |
|---|---|
| `php artisan test` | Runs all automated tests |
| `php artisan schedule:run` | Runs whatever is due now (the Task Scheduler runs this every minute) |
| `php artisan aichat:check-handovers` | Handover reminders and escalation |
| `php artisan aichat:send-reminders` | Booking reminders (24 h and 2 h before) |
| `php artisan aichat:run-automations` | Sends the automatic messages that are due (the scheduler runs it every 5 minutes) |
| `php artisan aichat:renew-subscriptions` | Takes the next monthly payment for paid plans (scheduled daily; test mode takes no real money) |
| `php artisan aichat:make-platform-owner you@example.com` | Creates the login for the `/platform` console |
| `php artisan aichat:backup` | Saves a compressed copy of the database (scheduled daily at 02:30) |
| `php artisan aichat:preflight` | Checks a server's settings before real customers |
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
