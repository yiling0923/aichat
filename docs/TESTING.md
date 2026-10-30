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


## AB. Sign-up page

1. Open `/signup` while signed out. Left side lists what you get; right side is the form. On a phone only the form shows.
2. Type a business name: a line under it shows your chat link.
3. Type a password: the bar and the word under it change. "Show" reveals it.
4. Press Create with empty fields: the first empty field is highlighted, nothing is sent.
5. Use an email that already exists: the error sits under the email with a "Sign in instead" link.
6. Fill everything and tick the terms: you land on "Check your email" showing your address.

## AC. Login, forgot and reset pages

1. Sign out and open `/login`: same look as sign-up, with "Show" on the password and "Keep me signed in".
2. Type a wrong password: the message appears under the email and your email stays filled in.
3. Press "Forgot password?", send the link, open it from `storage/logs/laravel.log`, choose a new password. All three pages share the same style.

## AD. Landing page

1. Open `/` signed out. Top: headline, a sample booking chat, and the channels it works with.
2. Scroll: features, how it works, plans (Starter marked "Most popular"), questions (click one to open), and a final sign-up band.
3. On a phone the menu shrinks to Sign in and Start free trial, and everything stacks in one column.

## AE. Setup wizard (Getting started)

1. Open Getting started as the owner. A progress bar and five step chips sit at the top; finished steps show a tick.
2. Step 1: paste a few price lines (for example `Facial - RM 188 (60 min)`), press "Draft my setup". Each service is one neat row with a tick box. Untick one: the row fades and the button count goes down.
3. Press "Approve and save". Then check Booking setup for the services. "Discard draft" asks before throwing it away.
4. Step 3 has a copyable chat link. Step 5: "I am ready, go live" changes the top to "You are live".
5. On a phone every row stacks and nothing is cut off.

## AF. Platform console

Sign in as the platform owner (`php artisan aichat:make-platform-owner you@example.com`).
1. Top row: businesses, on trial, paying (with monthly total) and money taken in 30 days.
2. Type in the search box or press Trial, Paying, Needs attention or Paused: the list narrows without reloading.
3. Press Pause on a business: a small pop-up asks for an optional reason. The row turns pink and shows the reason. Press Resume to undo.
4. Invitations tab: "Make a code" opens a pop-up. New codes show in the list; "Copy link" copies the sign-up link with the code in it.

## AG. Customer chat

Open the chat link (Settings, "Your customer chat link").
1. The header shows the business name and "Replies instantly" with a green dot. It uses the colour you chose in Settings.
2. Press Start, tap the buttons, then type a message. The send arrow is grey until you type something. Three bouncing dots show while the bot is thinking.
3. Each message shows its time. Links in messages are clickable.
4. When staff reply from the Inbox, the header says "A team member is replying".
5. The circular arrow in the header starts a new chat after a confirmation.
6. On a phone the chat fills the screen and the keyboard does not hide the box.

## AH. Plan and billing

1. Open Plan & billing as the owner. The top card shows your plan, its status and bars for AI replies, team members, practitioners and channels. Bars turn amber near the limit and red at the limit.
2. In the last 3 days of a trial (or after it ends) a coloured message explains what happens.
3. Choose Starter (test mode takes no money). The page then shows "Your plan" on Starter, "Upgrade to Growth" on the other, the renewal date, and a row under Payments.
4. "Cancel renewal" asks first, then shows "Not renewing" and "Works until ...".

## AI. Marketing pages (Broadcasts and Activity log)

1. Marketing has three tabs: Automations, Broadcasts, Activity log.
2. Broadcasts: three cards (drafts, sent, people reached). Press "New broadcast": a pop-up with name, English and Chinese messages, character counters and "Customer name" / "Business name" buttons that insert {name} and {business}.
3. Change the tag, lead level or language: the blue line says how many people can receive it, live. A preview bubble shows the message with a sample name.
4. "Save as draft", then press Send on the draft: a pop-up repeats the number and asks to confirm. After sending, the list shows a coloured bar of sent, skipped and failed.
5. Open a sent broadcast: filter who got it by Sent, Skipped or Failed. The message shows on the right.
6. Activity log: filters apply as you pick them, with removable chips and a Reset button.

## AJ. Terms and privacy pages

1. Open `/terms` and `/privacy` (also linked from the footer and the sign-up form). Each has numbered sections, a contents list on the left (above the text on a phone) and a link to the other page.
2. The yellow "Starter text only" note stays until a lawyer has reviewed the wording. The contact address comes from your mail "from" address.

## AK. Error pages and the new-owner walk-through

1. Open a wrong address such as `/abc`: you see a friendly "Page not found" card with a button home, not a plain white error.
2. Walk-through as a brand-new owner: sign up, confirm the email, Getting started (paste prices, approve), check Booking setup, open your chat link and book a visit, then see it in Inbox, Schedule, Customers and Today.

## AL. My booking with several bookings

1. In the customer chat, book two different times (use the same name and phone).
2. Press "My booking": the bot lists every upcoming booking, numbered 1, 2 ..., with a "Cancel 1", "Cancel 2" button for each and "Keep it".
3. Press "Cancel 2": only that booking is cancelled (check Schedule). With one booking the old single "Cancel booking" button shows.

## AM. Bell sound

1. Open any staff page and click anywhere on it once (browsers only allow sound after a click or key press).
2. In the customer chat, type `human`. Within about 10 seconds you hear a four-note chime, the bell shakes and the number goes up. A new booking or message gives a softer two-note chime.
3. Click the bell: the bottom left says "Sound is on". Press it to turn sound off (and again to turn it on and hear a test chime). Your choice is remembered in that browser.
4. Check your computer or phone volume is up and the browser tab is not muted.

Bell menu position (AM, step 5): open the bell on the Inbox page, on Today, and on a phone. The list must always sit fully on screen, never hidden behind the left menu.

## AN. Sample data (so every page has something to show)

For testing only. In your project folder run:
- `php artisan aichat:sample-data` fills the sample clinic (Lumiere) with about 45 customers, 130 bookings (last 6 weeks and next 2), paid orders, 16 chats (some waiting for staff), tasks, surveys, an automation log, two broadcasts, AI usage and bell alerts.
- Run it again any time to refresh it. It never doubles up.
- `php artisan aichat:sample-data --remove` clears only the sample rows (their emails end in `@sample.test`). Your own data stays.
- Another business: `--business=your-slug`. More or fewer people: `--customers=80`.
Then press Ctrl+F5 and look at Today, Schedule, Customers, Reports, Orders, Inbox and Marketing.

Page bar (AN, step 2): with the sample data, open Customers, Orders, Conversations or the Activity log. At the bottom you see "Showing 1-25 of 133", greyed "Previous" on page 1, "Page 1 of 6" and a "Next" button, centred on a phone.

Schedule colours (section on Schedule): blue = Confirmed (upcoming), green = Done, amber = No-show. The same colours show in Month, Week and the key under the calendar.

## AO. Today page

1. Open Today. Top: greeting, a line saying how many chats need you (or who is next), and quick buttons: Open inbox, Schedule, + Task, Copy chat link.
2. Five clickable cards: waiting for staff (red when someone needs you), bookings today, open chats, new customers and paid sales, with up and down arrows against the week before.
3. Left: "Needs a person" with a Reply button for each, then today's bookings as a timeline with Done, Now, Next and No-show tags.
4. Right: your tasks (tick the circle to finish one, overdue ones are red), a small bar chart of this week, recent activity, and AI use for owners.
5. With no data (new business) every box shows a friendly message instead of being empty. On a phone it becomes one column.

## AP. Waiting for a person (customer side)

1. In the customer chat, type `human`. The bot stops answering on purpose, because a person should reply. A yellow bar now appears above the message box: "A team member will reply here" with a **Back to menu / 返回菜单** button.
2. Press the button (or type `menu`): the bot answers again and shows the main menu. Staff get a bell alert that the customer went back to the menu.
3. If staff reply in the Inbox instead, the bar disappears once they give the chat back to the bot.

## AQ. Reopening a resolved chat

1. In the Inbox open a chat and press **Resolve**. It leaves the "Open" list, and the button now says **Reopen**.
2. Switch the list to "Resolved", open that chat and press **Reopen**: it is open again.
3. A resolved chat also opens by itself when the customer writes again in the website chat (you get a bell alert "wrote again"), or when you send them a reply.
4. WhatsApp, Instagram and Messenger chats already reopened when the customer wrote again.

## AR. Testing the rating (survey) message

The survey automation waits until a visit has ended (2 hours by default), so "Run now" often sends nothing. To test it straight away:
1. Marketing → Automations → open **Satisfaction survey**, scroll to the bottom of its card.
2. Pick a customer under "Send a test now to" and press **Send a test**. The list only has customers who agreed to be contacted and have a chat (sample data customers qualify).
3. Open that customer's chat: the message asks for a rating with buttons 1 to 5. Tap one: the bot thanks them. A rating of 1 or 2 makes a task and a bell alert.
4. Marketing → Activity log → **Messages sent** shows the send, and the **Survey answers** tab shows the rating (stars), plus average, answer rate, low ratings and review links sent.
5. "Run now" now explains when it sends nothing (no automation on, nobody due, or people skipped and why).

Activity log tabs (AR): the page has two tabs, "Messages sent" and "Survey answers (n)". On Survey answers use the "Show" list to see only waiting, low (1 or 2) or one rating, and page through with Previous / Next.

## AS. Visit done, then pay at the shop

1. Book a visit in the chat for a priced service. A booking can only be marked done after its time has passed. For testing, move it into the past (see the tinker line in the chat with the developer), then open Schedule → List. Visits that finished but were never marked now stay in "Upcoming" with an amber "Finished? Mark it" tag and the Done and No-show buttons.
2. Press **Done** (or "Mark done" in the booking pop-up). You jump straight to **Orders** with a **Collect payment** pop-up: the customer and service, the amount (change it for a discount), "How did they pay?" (cash, card, bank transfer or QR, other) and notes.
3. Press **Save**: the order is Paid and the notes say "Paid by card." If the customer pays later, close the pop-up: the order stays "Waiting for payment" and you can press **Mark paid** on its row any time.
4. The customer's page shows "RM ... unpaid" under Spent while an order is waiting.
5. No order and no pop-up for free services (price 0), no-shows, or a second press of Done.

## AT. Opening hours (day buttons)

1. Settings → Booking setup. **Open days** are seven buttons: green means open. Tap a day to open or close it.
2. Under **Hours on open days** pick one opening and closing time: every open day gets those hours straight away.
3. The blue line sums it up in words, for example "Mon to Fri 10am to 7pm · Sat 10am to 5pm · Sun closed".
4. One day different (Saturday closes earlier)? Press "One day is different? Set each day separately" and change just that day. The summary shows the odd day.
5. Press **Save hours and rules** to keep it. A closing time before the opening time is refused with a message.

Reports summary tiles (Reports page): under the four big cards there are seven small tiles: handed over to staff, survey score, no-show rate, cancelled, repeat customers, opted out and AI calls, each with a short note. A coloured top edge shows how it is going (green good, amber so-so, red needs attention) for survey score, no-show rate and cancelled. On a phone they sit two per row.

Report card size (Reports → Overview, Bookings, People, Money): cards are larger and the charts have bigger numbers and dates. On a wide screen the cards sit in two big columns, on a smaller screen in one, and the bars are thicker.

Reports redesign (Reports page): four big cards with a small trend line and a green or red change badge, a health strip (survey score, no-show rate and cancelled get a coloured dot), then one large smooth "Day by day" chart. Switch it between Chats, Bookings, New customers and Paid; move the mouse over it to see the exact number for each day. Below: Top services as a ranked list with percentages, and Chats by channel as a colour bar with a legend. The Bookings, People and Money tabs use the same ranked lists.

## AU. Channels page

1. Channels: four cards at the top (website, WhatsApp, Messenger, Instagram) with a coloured icon and a status: Connected, Needs attention or Not connected.
2. **+ Connect a channel** (or the Connect button on a card) opens a pop-up: pick WhatsApp, Messenger or Instagram, read the three short steps, paste the details and press Connect.
3. Each connected account is a card with Check connection, Subscribe to messages and **Settings**. Settings opens a pop-up (name, token, on or off, reminder template) with Save and Remove. WhatsApp accounts also have a test message box.
4. "Meta app connection" at the bottom is the one-time technical setup, marked advanced. A yellow note at the top tells you when it still needs finishing.

## AV. Reminders when the team does not reply

1. In the customer chat type `human`. Do not reply as staff. (The scheduler must be running, or run `php artisan aichat:check-handovers` yourself each time to speed it up.)
2. At once: the bell chimes and the chat shows in Needs a person on Today.
3. After 5 minutes: the customer gets "Thank you for waiting...", and the bell chimes again ("has waited 5 minutes"). After 10 minutes it chimes again.
4. After 20 minutes: the customer gets "Sorry for the wait, our team is still busy... type menu to go back". The bell chimes again.
5. After 30 minutes: owners and managers get an "URGENT" alert.
6. On Today the waiting time turns red from 10 minutes.
7. As soon as a team member replies in the Inbox, all reminders stop.

## AW. Email and WhatsApp alerts, and the bot taking the chat back

Settings → Business → **When a customer waits for a person**:
1. **Give the chat back to the bot after**: choose a time (default Never). Save. Then in the customer chat type `human` and leave it. When the time has passed (run `php artisan aichat:check-handovers` to speed it up) the customer gets "Sorry, our team could not get to you yet, so I am back to help…" and the main menu, and staff get a bell alert "went back to the bot". Reply in the Inbox before that and nothing is taken back.
2. **Email the owner and managers**: tick it, Save. When a customer has waited 10 minutes the owner and managers get an email, and an URGENT one at 30. With `MAIL_MAILER=log` the email is written to `storage/logs/laravel.log`.
3. **WhatsApp alert to this number**: needs a connected WhatsApp channel. With an approved template name (use {{1}} for the customer name and {{2}} for the minutes) it works any time; without one WhatsApp only delivers if that number wrote to your business in the last 24 hours.
4. **Send me a test alert** sends a test email to you (and the WhatsApp message if set) and tells you what happened.

## Troubleshooting: "laravel.log could not be opened in append mode: Permission denied" (Windows)

Windows is stopping the app from writing its log file. Usually the file was created by a different Windows user (for example the Task Scheduler job) than the one running the web server.
1. Stop `php artisan serve` (Ctrl+C) and close any other terminal running the app.
2. Delete `storage\logs\laravel.log` (the app makes a new one).
3. Open Command Prompt **as Administrator** and run (change the path if yours differs):
   `icacls "C:\xampp\htdocs\aichat\storage" /grant Users:(OI)(CI)F /T`
4. Start the app again. If the Task Scheduler job runs as another user, run it as your own Windows user too.
Alerts and notifications no longer crash a page when this happens, but nothing can be written to the log (including test emails when `MAIL_MAILER=log`) until it is fixed.

Today page layout (section AO, refreshed): date, greeting and one status line on the left, four buttons on the right (Copy chat link, + Task, Schedule, Open inbox). One white bar with five numbers (waiting for staff, bookings today, open chats, new customers, paid in 7 days). Below, tidy panels with a title, a count and a link: Needs a person (with waiting time and a Reply button), Today's bookings (time, customer, service and a status tag), Tasks (tick the circle), This week, Recent activity and AI use. On a phone everything is one column.

## AX. Message templates (smarter and neater)

1. Message templates: cards in a tidy grid with the status tag, the message as a WhatsApp bubble (changing parts highlighted), language, utility or marketing, and what uses it.
2. Filter with All, Approved, Waiting, Problem, or type in **Search templates**.
3. Each card: **Copy name**, **Duplicate** (opens New template with the same wording and a new name, so you can fix a rejected one) and **Remove**.
4. New template: a letter counter, a live preview, and a short checklist (a full sentence, no changing part at the start or end, numbers in order, no shouting). Amber marks are warnings before Meta reviews it.

## AY. Where things are in the side menu now

The Settings page is short again. Everything else has its own place:
- **Marketing** (Grow): Automations, Broadcasts, **Message templates** and Activity log are tabs on one page.
- **Set up** (owner and managers): Menu builder, Booking setup and Channels (owner only), AI assistant, Saved replies.
- **Settings**: Business and team, Plan and billing, Getting started, Tags.
Older notes in this guide that say "Settings → Channels" (or Menu builder, Booking setup, AI assistant, Saved replies, Message templates) now mean the matching entry in the side menu or Marketing tab.

## AZ. Filters apply by themselves

1. Customers: type in the search box. About half a second after you stop, the list updates and the cursor stays in the box. Change Lead level, Language or Tag: the list updates at once. There is no Search or Filter button any more.
2. The same on Orders (search, period), Conversations, Reports (period, channel, service, practitioner) and the Activity log. For Reports, pick "Custom dates", then both dates: it updates when both are chosen.
3. Reset links still clear everything.

## BA. Add task as a small pop-up

1. Tasks: press **+ Add task** (top right). A small pop-up asks what needs doing, when (with quick buttons Today 5pm, Tomorrow 10am, Next week) and who does it (you by default). Press **Add task**.
2. Edit uses the same pop-up with Save and Delete. Delete only shows when editing.
3. On Today, **+ Task** opens that same pop-up straight away.

## BB. Tasks filter bar

1. Tasks has one tidy bar: Open / Done, a search box (task text or customer), an Assigned to list (Everyone, Only me, or a person), and a Due list (Any time, Overdue, Due today, Next 7 days, No date).
2. Everything applies by itself as you choose or type. A red "5 overdue" button jumps straight to the overdue ones. **Reset** appears only while a filter is on.
3. With filters on and nothing matching, the page says "No tasks match these filters".

## BC. The same filter bar everywhere

Customers, All conversations, Orders, Reports, Activity log, Saved replies, Tasks and (for the platform owner) the business list all use one white filter bar: search box and lists in a single row, no labels above, the Search or Apply button hidden because everything applies by itself, a **Reset** button only while a filter is on, and a small note on the right where it helps (for example "133 customers"). On a phone each list takes a full row.

## BD. Customers can change the time of a booking

1. In the customer chat, book a visit at least a day ahead (the change rule is the same as cancelling: the "Customers may cancel until (hours before)" setting in Booking setup, 24 hours by default).
2. Menu → **My booking**. With one booking you see **Change time**, **Cancel booking** and **Keep it**. With several you see **Change 1**, **Cancel 1**, **Change 2**, and so on.
3. Press **Change time**: the bot shows the days and times that are free for the same service. Choose one: "Done! Your Haircut is now: Sun 1 Nov, 11:00". The booking is moved (not duplicated), reminders are reset, the Google Calendar event moves, and staff get a bell alert "Booking changed by ...".
4. Press **← Back** on the day list to leave without changing anything.
5. A booking less than 24 hours away: the bot says changes that close need the team and hands the chat over to staff.
6. If the person helping is busy at the new time but another practitioner is free, the booking moves to the free one.

## BE. Phone alerts (staff get a pop-up even when the page is closed)

One-time setup on the computer that runs the app:
1. `git pull origin claude/sleepy-brown-fv558x`, then `composer install`, then `php artisan migrate`.
2. `php artisan aichat:push-keys` (makes the private alert keys and saves them in `.env`; never share them), then `php artisan config:clear`.
3. Alerts need `localhost` or an https address. Plain `http://192.168...` will not work.

Per person and per device:
1. Open **My account** → **Phone alerts** → **Turn on alerts** → press Allow in the browser.
2. Press **Send me a test alert**. A pop-up should appear within seconds, even if you close the page first. Tapping it opens the inbox.
3. The bell menu has the same switch ("Turn on phone alerts").
4. Now close the page, then chat as a customer: staff get a pop-up for new chats, handovers and bookings. Several messages in one chat replace one pop-up instead of piling up.
5. **Turn off alerts** stops it for this device only. Dead devices are removed automatically.
6. iPhone: open the site in Safari, Share → Add to Home Screen, open it from the Home Screen icon, then turn alerts on.
7. If keys are missing, the card says "Not set up on the server yet".

## BF. My account and Plan & billing, tidied

1. **My account**: a header card with your initials, name, email, role and business. Below it, two cards side by side (stacked on a phone): "Your details" with name and change password (new password and repeat sit next to each other), and "Phone alerts". Save changes still works; a wrong current password still shows the red message.
2. **Plan & billing**: the plan cards now use the full page width. In "Your plan", a plan with no limit shows "38 · no limit" next to the usage. **Cancel renewal** is now a small red link under the "Renews ..." date (only on a paid, active plan). "Choose a plan" and "Payments" have a small note on the right (prices in US dollars; number of payments).

## BG. Sidebar user card

1. Bottom of the left menu: a round badge with your initials, your name, your role (Owner, Manager, Staff) and a small sign-out icon on the right. Hover the icon: "Sign out". Click the badge or your name: My account opens.
2. On a phone the menu is a bar across the top; the badge and sign-out icon sit at its right end (scroll the bar sideways).

## BH. Settings page and Business page, tidied

1. **Settings** (bottom of the left menu): tiles with an icon, a title, a short line and an arrow, grouped as Your business, Organise and You. New tiles: **My account** and **Phone alerts** (opens My account at the alerts card).
2. **Business and team → Business**: three clear cards, each with a title and a short explanation: About your business (name and address side by side, time zone and currency side by side, colour swatches, Save at the bottom right), When a customer waits for a person (the email option sits in its own box, **Send me a test alert** and **Save** at the bottom right), and Your customer chat link.
3. On a phone the fields stack and the buttons fill the width.

## BI. Team tab, tidied

1. Business and team → **Team**: a top card "Your team" with how many seats you use ("2 of 3 team members on your plan", with a bar that turns amber and red near the limit) and **+ Add a team member**.
2. Members are listed owner first, then managers, then staff (switched-off people last), each with a round initials badge, name ("(you)" on yours), email, role tag and **Edit**. Switched-off people are faded with a red "Switched off" tag.
3. "What each role can do" is three small cards (Owner, Manager, Staff). On a phone the role tag sits under the name and the cards stack.
4. Add and Edit still use the same pop-up.

## BJ. Tags page, tidied

1. Settings → **Tags**. The "New tag" card has a **Preview** chip: type a name and pick a colour and the chip shows the tag as it will look. Press **Add tag**.
2. The find / sort bar is the same white bar as other pages (Find a tag, A to Z / Most used) with "4 tags · 34 in use" on the right.
3. Each row lines up: the coloured tag, a small bar showing how much it is used compared with your busiest tag, the count (click it to see those customers or chats), and **Edit** (rename, colour, merge, delete).
4. "Ideas" shows one-tap suggestions you do not have yet. On a phone the bars are hidden and the rows stay compact.

## BK. Saved replies page, tidied

1. Saved replies: cards sit in an even grid (two across on a computer, one on a phone). Each card has the title and group at the top, the English and Chinese texts in two speech-bubble boxes, and buttons at the bottom: **Copy English**, **Copy 中文** (only when there is a Chinese text) and **Edit** on the right (owners and managers only).
2. `{name}` and `{business}` are highlighted so you can see what gets filled in when the reply is used.
3. The search box and group buttons work as before; "3 replies" shows the count on the right. **+ New reply** opens the same pop-up.

## BL. Suggested saved replies in the Inbox (no AI, free)

1. Add a few saved replies (Saved replies page), for example "Opening hours", "Parking", "Prices", "Aftercare", each with an English text and, if you like, a Chinese text.
2. As a customer, write to the chat: "What time do you open tomorrow?" (or "where can I park", "how much is a facial", "几点开门", "多少钱", "红肿怎么办").
3. Open that chat in the Inbox. Above the reply box a **Suggested** row shows up to 3 replies that fit, for example **Opening hours**. Hover to see the start of the text. Click one: it goes into the box in the customer's language with their name filled in. You can edit before sending.
4. When a person replies, or nothing matches (for example "ok thanks"), the row disappears.
5. This is plain word matching (English, Malay and Chinese words for prices, hours, location, booking, aftercare, cancel, payment, thanks and hello). It needs no AI and no paid service. The better your saved reply titles, the better the suggestions.

## BM. Automatic tags (no AI, free)

1. As a customer in the website chat, write: "How much is a facial? Also this is terrible, I want a refund".
2. In the Inbox open that chat. Under the name you see the tags **Price question** and **Complaint**, added by themselves. The tags are created the first time they are needed (see Settings → Tags → Chats).
3. Owners and managers get a bell alert "... may be a complaint" (once per chat). Staff do not get it.
4. Other examples: "can I book tomorrow?" → Booking help; "I want to cancel" → Cancel or change; "where is the parking?" or "地址在哪里" → Location; "deposit" or "付款" → Payment; "my face is swollen" or "红肿" → Aftercare; "多少钱" → Price question.
5. A customer who was cold and asks about price or booking becomes warm. A hot customer is never lowered.
6. Staff and bot messages never add tags. You can still add or remove tags by hand; removed tags can come back only if the customer writes about that topic again.
7. Turn it off: Settings → Business and team → **Automatic tags** → untick **Tag chats automatically** → Save.

## BN. Daily summary

1. **Today** page: under the five numbers there is a **Daily summary** card with two tabs, **Yesterday** and **Today so far**. Each shows new chats, bookings made, visits, new customers, money paid (and how many orders), cancelled bookings, and the topics customers asked about (from the automatic tags). Times follow your business time zone.
2. At the bottom of the card: how many chats are waiting for a person, how many tasks are overdue and how many bookings are tomorrow. Red means something needs you. Each one is a link.
3. **Morning email (optional):** Settings → Business and team → **Smart helpers** → tick **Email me yesterday's summary every morning** → Save. Owners and managers get one email at about 8:00 your time. It needs the scheduler running (`php artisan schedule:work`) and email set up.
4. Test it now without waiting: `php artisan aichat:daily-summary --force` sends it immediately to the owners and managers of businesses that turned it on. With MAIL_MAILER=log the email is written to `storage/logs/laravel.log` instead of sent.

## BO. Fewer labels in the Inbox header

The grey/pink AI label next to the customer ("complaint · angry") no longer repeats a tag: if a tag already says it (for example the Complaint tag), the AI label drops that word. A "neutral" mood is not shown at all. So you see the tags, plus the AI label only when it adds something (for example "angry").

## BP. Simpler Inbox chat header

1. Open a chat in the Inbox. Top row: the customer, "Website · open", who is answering (bot or you; hover it to see why it was handed over), the green **Resolve** button, and a small **···** menu.
2. **···** holds the less used actions: **Summarise with AI** (only when the AI is on) and **Give back to the bot** (only when you are handling the chat). The menu is hidden when neither applies.
3. Second row: the customer (click for their page), the tags (click one to remove it), a dashed **+ Tag** to add one, and on the right **who looks after the chat** (Unassigned or a person) and **+ Task**.
4. The long AI text and the extra AI label are gone. If the AI thinks the customer is upset, one red **Upset customer** label shows; the topics are the tags.

## BQ. Backup and export (owner only)

1. Settings → **Backup and export** (a new tile under Your business).
2. **Download all (zip)**: one file with a spreadsheet for customers, bookings, orders, chats, chat messages, tasks and saved replies, plus a short README. Unzip it and open the .csv files with Excel or Google Sheets. Chinese names and messages show correctly. Times are in your business time zone. Private team notes are not included.
3. **Download one list**: each list has its own **Download .csv** button, with the row count next to it. The row counts match the files (a file also has one header line).
4. A customer who types something like `=HYPERLINK(...)` as their name is saved as plain text in the file, so a spreadsheet never runs it.
5. Only owners can open the page or the downloads. Each business only ever gets its own data.
6. **Automatic server backup**: the page shows when the last nightly backup of the whole database happened and how many copies are kept. If there is none yet, it says how to start one (`php artisan aichat:backup`, needs `mysqldump` from XAMPP, and `php artisan schedule:work` for the nightly run). Copies are in `storage/app/backups`; copy the newest to a USB stick or a cloud folder now and then.

## BR. Chinese wording review

1. **Chat in Chinese** (website chat, choose 中文). Everything the bot says by itself now reads more naturally: for example "抱歉，这个时间刚刚被其他人预约了", "近期暂时没有空位", the Change button is "更改第 1 个" next to "取消第 1 个", and Keep is "保留预约".
2. **Traditional Chinese customers** (繁體): type "請問多少錢", "我想預約", "有優惠嗎", "幾點開門", "有停車嗎", "轉人工", "菜單". The bot now understands them, including on a menu you saved earlier, because Traditional is read as Simplified. It still replies in Simplified. (This needs PHP's **intl** extension, which XAMPP has on by default; without it the old behaviour stays.)
3. **Full-width typing** from a Chinese keyboard works too: "ＭＥＮＵ", "０", "人工。".
4. More ways to ask for a person: "人工客服", "真人客服", "联系员工", "找客服". More ways to go back or to the menu: "返回上一步", "返回菜单".
5. **Setup wizard**: after approving services and opening hours, the Chinese menu now shows them in Chinese ("- 面部护理：RM 188（60 分钟）", "周一 10:00 - 19:00", "周日 休息", "请咨询") instead of English words.
6. **Automatic tags** also understand Traditional Chinese ("多少錢" → Price question, "退款" → Complaint).
7. The "no-show" and "come back" message templates read more naturally. Templates you already saved keep your own wording.

## BS. Setup guide and Help page

**Setup guide (owner only)**
1. Left menu → **Setup guide** with a small count like 3/6 (shown until the essential steps are done). It is also in Settings → Setup guide, and Today's "Get set up" card links to it.
2. The page shows a progress ring, how many minutes are left, and a list of steps: Check your business details, Add services and prices, Set opening hours, Look at your chat menu, Try it as a customer, Go live (essential), then Add your team, Connect a channel, Write saved replies, Turn on phone alerts (nice to have).
3. Steps tick themselves ("Done automatically") when the app sees them done: for example add a service and its step ticks; set opening hours and that one ticks.
4. For anything the app cannot see (for example looking at the menu), press **Mark as done**. Press **Mark as not done** to undo.
5. The **Next up** step is highlighted and its button is also in the top card. When all essential steps are done the card turns green ("You are ready for customers") and the left-menu link goes away.
6. Staff cannot open the guide.

**Help page (everyone)**
1. Left menu → **Help** (above your name), or Settings → Help.
2. Topics on the left (or as buttons on a phone): the big picture, a day with the system, how customers reach you, inbox and handover, chat menu, bookings, customers and tags, orders, marketing, reports, alerts, the AI assistant, team and roles, plan and billing, your data, questions and fixes, words used.
3. Type in **Search help** to show only the topics that match (for example "template" or "24 hour").
4. Links inside the text open the real pages. Pages only for owners or managers are marked **Owner** or **Owner and manager** when a staff member reads them.

## BT. A friendlier Help page

1. **Help** now opens with a green banner: "How can we help?" and a big search box, with quick words under it (opening hours, saved reply, tags, alerts, template, backup). Click a word to search for it.
2. **I want to...** shows shortcut cards for everyday jobs (reply to a customer, change opening hours, add a service, change what the bot says, get alerts on my phone, message many customers, see how the business is doing, add a team member, download my data). Each card opens the right page. You only see the cards for things your role can do.
3. **How a chat flows** is a five-step picture: customer writes, bot answers, person takes over, everything is saved, follow-up.
4. **Browse by topic** is a grid of all topics; click one to jump to it.
5. Yellow **Tip** boxes give small tricks (Ctrl+K search, Enter to send, Publish after editing, phone alerts). A **Still stuck?** box ends the page.
6. When you type in the search box, the top blocks hide and only the matching topics show. Clear the box to come back.

## BU. Staff screens in Chinese (part 1: switch, menu, bell, Today, Inbox)

Run `php artisan migrate` once (adds a language setting per person).

1. At the top of every page (next to the bell, and in the Inbox list header) there is a small button: **中文** when you are in English, **English** when you are in Chinese. Press it: the page reloads in the other language. The choice is saved for you only; other team members keep their own language.
2. In Chinese you now see: the whole left menu (今天, 收件箱, 日程, 客户 ...), the bell and its messages, the search box, the trial banner, the whole **Today** page (greeting, numbers, 每日摘要, 需要人工, 今日预约, 任务, 本周), and the whole **Inbox** (list, chat header, tags, reply box, 快捷回复, buttons, pop-ups and messages). Dates read like 2026年10月29日 星期四, times like "5 分钟".
3. Not translated yet (still English): the other pages (Schedule, Customers, Tasks, Orders, Marketing, Reports, Settings, Menu builder, Booking setup, Channels, AI, Saved replies, Tags, Billing, My account, Setup guide, Help), the sign-in pages and the emails. They will follow page by page.
4. Things your business typed stay as typed (customer names, task titles, service names, saved replies). The tags that the system creates itself (Price question, Complaint ...) and the usual hand-over reasons show in Chinese.
5. Bell messages that were created before the switch are stored in English and stay English.
6. Simplified Chinese only. If a phrase is missing a translation it simply shows in English.

## BV. Staff screens in Chinese (part 2: Schedule, Customers, Tasks, Orders)

Run `git pull`, then `php artisan migrate` if you have not yet (it adds the language setting). Switch with the **中文** button at the top.

In Chinese you now see, fully translated:
1. **Customers**: filters, bulk tag buttons, table headings, lead levels (准备预约 / 有兴趣 / 随便看看), promotions status, dates like 2026年10月14日, and the **customer page** (activity timeline, follow-ups, notes, edit pop-up, download or delete their data).
2. **All conversations**: filters and the table.
3. **Tasks**: Open/Done, filters, groups (已逾期, 今天, 明天, 本周, 稍后, 没有日期), the Add task pop-up and its quick buttons.
4. **Orders**: summary boxes, filters, periods, statuses (已付款, 等待付款, 已退款, 已取消), the New order and Collect payment pop-up, payment methods.
5. **Schedule**: Month, Week and List views, weekday names (周一 ...), titles like 10月26日 至 2026年11月1日, the booking pop-up (change time, mark done, no-show, cancel), the drag-to-move question, and the legend.
6. The page numbers at the bottom of lists ("第 1 / 3 页", 上一页, 下一页).
7. The standard tag names (VIP, First visit, Regular ...) show in Chinese: VIP, 首次到店, 常客. Tags you create yourself show as you typed them.

Still English: Tags page, Saved replies, Settings, Marketing, Reports, Menu builder, Booking setup, Channels, AI, Billing, My account, Setup guide, Help, sign-in pages and emails. Customer names, service names and notes stay as typed.

## BW. Staff screens in Chinese (part 3: Tags, Saved replies, Settings, Backup, Billing, My account)

Switch with **中文** at the top (or in My account → Language). Fully translated now:
1. **Settings** hub (tiles), **Business and team** (business details, waiting-customer alerts, smart helpers, chat link, the Team tab with seat meter, role cards and the add/edit pop-up), **Backup and export**, **Plan and billing** (banners, usage, plan cards, payments), **Tags** (new tag, preview, list, ideas, edit pop-up) and **Saved replies** (cards, search, pop-up).
2. **My account** has a new **Language** card (English / 中文) next to the top-bar button, and the Phone alerts card and its messages are in Chinese, including the test alert messages.
3. The standard tag names (VIP, First visit ...), reply groups (Greeting, Info) and plan names (Free trial, Starter, Growth) show in Chinese. Names you type yourself stay as typed.
4. Editing a tag or reply always shows the stored name, so saving never replaces your own wording with a translation.

Still English: Marketing (automations, broadcasts, templates, activity), Reports, Menu builder, Booking setup, Channels, AI assistant, Setup guide, Help, the sign-in pages and the emails.

## BX. Staff screens in Chinese (part 4: Marketing and Reports)

Switch with **中文** at the top. Fully translated now:
1. **Marketing** tabs: 自动消息 (automations: titles, help texts, wait times, message boxes, test), 群发消息 (broadcasts: summary boxes, list, new-broadcast pop-up with live "how many people can receive it", send confirmation, details page with who got it and why people were skipped), 消息模板 (message templates: status words, groups, examples, the new-template pop-up and its checks), 动态记录 (activity log: messages and survey answers).
2. **Reports**: the period line (最近 30 天, 9月30日 至 2026年10月29日), filters, the four top boxes, the health strip (转交给员工, 问卷得分, 未到店比例 ...), the tabs (概览, 预约, 客户, 收入), the day-by-day chart with its tooltip, all the ranked lists (热门服务, 最忙的星期 with 周一 ... 周日), and the CSV export menu names.
3. Reasons recorded by the system (for example "opted out", "no consent") show in Chinese. CSV files keep the English column names so they stay easy to compare.
4. WhatsApp message templates you create keep the wording you wrote (they are sent to Meta as written).

Still English: Menu builder, Booking setup, Channels, AI assistant, Setup guide, Help, the setup wizard, sign-in and sign-up pages, the landing page, the emails, and the green "Saved." style messages after saving.

## BY. Chinese, part 5 (Menu builder, Booking setup, Channels, AI assistant, Setup wizard, Setup guide)

1. Press 中文 at the top. Open Menu builder, Booking setup, Channels, AI assistant, Setup wizard and Setup guide.
2. Everything you read there should be Chinese (names you typed yourself stay as typed).
3. In Menu builder, break a step on purpose (empty text) and press Publish: the red messages are in Chinese.
4. Press English: all six pages look as before.

## BZ. Chinese, part 6 (Help page, green and red messages)

1. Press 中文. Open Help: the whole page is Chinese, search works with Chinese words (try 营业时间).
2. Save something (a tag, a task, Settings): the green message at the top is Chinese. Try a wrong password in My account: the red message is Chinese.
3. Press English: Help and the messages are English again.

## CA. Chinese, part 7 (sign-in, sign-up, landing, legal, platform console, emails, error pages)

1. Sign out. On the sign-in page press 中文 (top right). The page is Chinese. Open "Forgot password?", "Start a free trial", the home page, Terms and Privacy: all Chinese, and the choice is remembered.
2. Press English: everything is English again.
3. Staff screens still follow each person's own language (My account). A person set to 中文 gets the daily summary, plan-limit and waiting-customer emails in Chinese; the confirmation email follows the language chosen on the sign-up page.
4. The customer chat is not affected: customers still pick English or 中文 inside the chat.

## CB. Fixes: Orders "Edit", customer "Spent" card, tidier inbox header

1. Orders: press Edit on any order (in English and 中文): the "Edit order" window opens. Same for Tasks, Saved replies, Settings > Team and Tags.
2. Customers: open a customer. The Spent card shows e.g. "3 paid orders" and no odd text.
3. Inbox: the header has the title with the bell and 中文 button on top, and the Open filter with "Search all chats" below.

## CC. Marketing page polish

1. Marketing > Automations: a "x / 5 switched on" strip, one icon and an Edit button per automation, and the Run now button on the top right.
2. Press Edit on a card: it opens with Status, timing, messages and Save. Switch one On and Save: the card gets a green edge and the count goes up.
3. Check on the phone (narrow window) and in 中文.

## CD. Booking setup polish

1. Open Booking setup. Three pills at the top jump to Opening hours and rules, Practitioners and Services (with counts).
2. Practitioners and Services are now tidy tables with one header row. Change a name or price and press Save on that row.
3. Press "+ New practitioner" or "+ Add service" to open the add form, add one and check it appears.
4. On the phone (narrow window) each row becomes a clean card and nothing runs off the right edge. Check in 中文 too.

## CE. Channels: Manage / Fix connection buttons

1. Channels page: a card with one account has a Manage button (or a green Fix connection button when something is wrong). It opens that account's settings window straight away, where you can paste a new access token.
2. A card with two or more accounts still jumps to the list below. "Connect" on an unconnected card opens the Connect window.
