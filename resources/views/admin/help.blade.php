@extends('admin.layout')
@section('title', 'Help')
@section('content')
    @php $owner = $role === 'owner'; @endphp
    <div class="page-head">
        <div><h1>Help</h1><p class="mute">How the whole system works, in plain words. Use the search box, or pick a topic on the left.</p></div>
        @if ($owner)<a class="btn" href="{{ route('guide') }}">Open the setup guide</a>@endif
    </div>

    <div class="hp">
        <aside class="hp-toc" id="hp-toc">
            <input type="search" id="hp-search" placeholder="Search help" aria-label="Search help">
            <nav>
                <a href="#overview">The big picture</a>
                <a href="#day">A day with the system</a>
                <a href="#reach">How customers reach you</a>
                <a href="#inbox">Inbox and handover</a>
                <a href="#menu">The chat menu</a>
                <a href="#booking">Bookings and schedule</a>
                <a href="#customers">Customers, leads and tags</a>
                <a href="#orders">Orders and payments</a>
                <a href="#marketing">Marketing and reviews</a>
                <a href="#reports">Reports</a>
                <a href="#alerts">Alerts and waiting customers</a>
                <a href="#ai">The AI assistant</a>
                <a href="#team">Team and roles</a>
                <a href="#billing">Plan and billing</a>
                <a href="#data">Your data and privacy</a>
                <a href="#faq">Questions and fixes</a>
                <a href="#words">Words used here</a>
            </nav>
        </aside>

        <div class="hp-main" id="hp-main">
            <section class="card hp-sec" id="overview">
                <h2>The big picture</h2>
                <p>This system answers your customers for you, books their appointments, and hands the chat to a person when a person is needed. It works in English and Chinese, and keeps everything about your customers in one place.</p>
                <ol class="hp-flow">
                    <li><b>A customer writes</b> on your website chat, WhatsApp, Messenger or Instagram.</li>
                    <li><b>The bot answers</b> with a menu (prices, booking, opening hours, location). It can book an appointment all by itself.</li>
                    <li><b>A person takes over</b> when the customer asks for one, or the bot does not understand. Your team gets an alert.</li>
                    <li><b>Everything is saved</b>: the customer, the chat, the booking, tags and lead level. You see it on Today, Inbox, Schedule and Customers.</li>
                    <li><b>After the visit</b> the system can ask for a rating, ask for a review, and invite the customer back.</li>
                </ol>
                <p class="mute">The side menu is the map: <b>Today</b> and <b>Inbox</b> are for every day, <b>Schedule</b>, <b>Customers</b>, <b>Tasks</b> and <b>Orders</b> are your work lists, <b>Marketing</b> and <b>Reports</b> help you grow, and <b>Set up</b> is where you change how the bot behaves.</p>
            </section>

            <section class="card hp-sec" id="day">
                <h2>A day with the system</h2>
                <ol class="hp-steps">
                    <li><b>Morning, open <a href="{{ route('today') }}">Today</a>.</b> The Daily summary shows yesterday's chats, bookings and money, and what needs you now (chats waiting, overdue tasks, tomorrow's bookings).</li>
                    <li><b>Reply in the <a href="{{ route('inbox') }}">Inbox</a>.</b> Chats marked <i>Needs a person</i> come first. Press Reply and the bot goes quiet. When you are done, press <b>Resolve</b>. If the customer writes again, the chat opens by itself.</li>
                    <li><b>Check the <a href="{{ route('schedule') }}">Schedule</a>.</b> Open a booking to see the customer, mark it done, record payment, or move it.</li>
                    <li><b>Do your <a href="{{ route('tasks') }}">Tasks</a>.</b> Follow-ups, call-backs, anything you do not want to forget.</li>
                    <li><b>End of day:</b> nothing to close. Alerts reach your phone if you turned them on in <a href="{{ route('account') }}">My account</a>.</li>
                </ol>
            </section>

            <section class="card hp-sec" id="reach">
                <h2>How customers reach you</h2>
                <ul>
                    <li><b>Website chat.</b> A link and a small chat box for your site. Copy the link from <b>Today</b> (Copy chat link) or Settings → Business and team.</li>
                    <li><b>WhatsApp, Messenger, Instagram.</b> Connect them in <a href="{{ route('channels') }}">Channels</a> @if (! $owner)<span class="tag">Owner</span>@endif. Replies you send appear in the customer's own app.</li>
                    <li><b>Language.</b> The customer picks English or 中文 first. The bot answers in that language. It also understands Traditional Chinese typing.</li>
                    <li><b>Words customers can always type:</b> <code>menu</code> (back to the menu), <code>human</code> or <code>人工</code> (talk to a person), <code>back</code> (one step back), <code>stop</code> (no more marketing messages).</li>
                </ul>
            </section>

            <section class="card hp-sec" id="inbox">
                <h2>Inbox and handover</h2>
                <ul>
                    <li><b>Bot is answering / You are handling this.</b> The badge at the top of a chat shows who is talking to the customer. When you reply, you become the owner of the chat and the bot stays quiet.</li>
                    <li><b>Give back to the bot</b> (in the <b>···</b> menu) lets the bot continue. <b>Resolve</b> closes a chat; it reopens when the customer writes again.</li>
                    <li><b>Private note.</b> Only your team sees it. The customer never does.</li>
                    <li><b>Saved replies.</b> Under the reply box, choose one, or tap a <b>Suggested</b> reply the system found for the customer's question. {name} and {business} are filled in, in the customer's language.</li>
                    <li><b>Tags.</b> Added automatically by what the customer writes (Price question, Booking help, Complaint ...). Click a tag to remove it, or add your own with <b>+ Tag</b>.</li>
                    <li><b>Handled by</b> assigns the chat to a team member. <b>+ Task</b> makes a follow-up.</li>
                    <li><b>WhatsApp 24-hour rule.</b> WhatsApp only lets you write freely for 24 hours after the customer's last message. After that you can only send an approved <b>template</b>. The Inbox tells you when this happens.</li>
                </ul>
            </section>

            <section class="card hp-sec" id="menu">
                <h2>The chat menu @unless ($owner)<span class="tag">Owner</span>@endunless</h2>
                <p>The <a href="{{ route('menu') }}">Menu builder</a> is what the bot says and shows. It is a set of screens: a message, buttons, and where each button leads.</p>
                <ul>
                    <li>Edit the words in English and Chinese. Press <b>Publish</b> to make the change live. Until you publish, customers keep seeing the old menu.</li>
                    <li><b>Preview</b> lets you try it like a customer without creating real chats.</li>
                    <li><b>Keywords</b> on a button are words customers can type instead of tapping it (for example "price", "多少钱").</li>
                    <li>Fixed sentences the bot says by itself (sorry, booking confirmations, reminders) are built in and already translated.</li>
                </ul>
            </section>

            <section class="card hp-sec" id="booking">
                <h2>Bookings and schedule</h2>
                <ul>
                    <li><b>Booking setup</b> @unless ($owner)<span class="tag">Owner</span>@endunless: your services (name, minutes, price), the people who give them, opening hours, days closed, and rules such as how many hours before a visit customers may cancel or change.</li>
                    <li><b>Customers book in the chat.</b> They pick a service, a day, a time, then give a name and phone. Only free times are offered, so double booking cannot happen.</li>
                    <li><b>Customers change or cancel</b> from the menu: <i>My booking</i> → Change time or Cancel. Changes closer than your rule go to your team instead. Staff get a bell alert.</li>
                    <li><b>Reminders</b> go to the customer 24 hours and 2 hours before.</li>
                    <li><b>Google Calendar</b> (optional): give each person a calendar and bookings appear there, and busy times there block the chat.</li>
                    <li>On the <a href="{{ route('schedule') }}">Schedule</a> you see the week or month. Click a booking to open it, or drag a confirmed one to a new time. Colours show the status (confirmed, done, no-show).</li>
                    <li>Press <b>Mark done</b> on a booking and the payment details open, so you can record what the customer paid, including paying at the shop.</li>
                </ul>
            </section>

            <section class="card hp-sec" id="customers">
                <h2>Customers, leads and tags</h2>
                <ul>
                    <li>Every person who gives a name or phone becomes a <a href="{{ route('customers') }}">customer</a>, with their chats, bookings, orders, tags and notes in one page.</li>
                    <li><b>Lead level</b> shows how close someone is to buying: <b>Just browsing</b>, <b>Interested</b> (asked about price or booking), <b>Ready to book</b> (made a booking). It only goes up by itself, never down.</li>
                    <li><b>Tags</b>: for people (VIP, First visit ...) and for chats (Complaint ...). Manage them in <a href="{{ route('tags') }}">Settings → Tags</a>@unless ($role !== 'staff') (owners and managers)@endunless. Automatic tagging uses word matching, no AI, and can be switched off in Settings → Business and team.</li>
                    <li>Every list has the same filter bar. Filters apply as you type or choose; <b>Reset</b> clears them.</li>
                </ul>
            </section>

            <section class="card hp-sec" id="orders">
                <h2>Orders and payments</h2>
                <ul>
                    <li>An <a href="{{ route('orders') }}">order</a> is created when you press <b>Mark done</b> on a visit, or with <b>New order</b> when you make a sale.</li>
                    <li>Statuses: <b>Pending</b> (not paid yet), <b>Paid</b>, <b>Failed</b>, <b>Refunded</b>. A customer who pays at the shop is simply marked Paid by you.</li>
                    <li>Paid money feeds <b>Today</b> and <b>Reports</b>.</li>
                </ul>
            </section>

            <section class="card hp-sec" id="marketing">
                <h2>Marketing and reviews @unless ($role !== 'staff')<span class="tag">Owner and manager</span>@endunless</h2>
                <ul>
                    <li><b>Automations</b>: messages that send themselves. After a visit: ask for a rating (1 to 5). A happy customer gets your review link; an unhappy one alerts a manager. Others: invite back after some weeks, follow up after a no-show, nudge someone who asked about a service.</li>
                    <li><b>Broadcasts</b>: one message to many customers, picked by tag, lead level or language. Only people who agreed to marketing are included, and anyone who types <code>stop</code> is removed automatically.</li>
                    <li><b>Message templates</b>: WhatsApp needs approved templates for messages you start. Manage them in Marketing → Templates.</li>
                    <li><b>Activity log</b>: what was sent to whom, and the survey answers and review links (second tab).</li>
                </ul>
            </section>

            <section class="card hp-sec" id="reports">
                <h2>Reports</h2>
                <p><a href="{{ route('reports') }}">Reports</a> shows chats, bookings, money and ratings, for any period, channel, service or person. Every table can be downloaded as a spreadsheet.</p>
            </section>

            <section class="card hp-sec" id="alerts">
                <h2>Alerts and waiting customers</h2>
                <ul>
                    <li><b>The bell</b> (top of every page) shows new chats, bookings and complaints, and chimes. Turn the sound on once with a click. It works only while a page is open.</li>
                    <li><b>Phone alerts</b>: in <a href="{{ route('account') }}">My account</a> press Turn on alerts for each phone or computer you use. You get a pop-up even when the page is closed. It needs an https address (a hosted site), or localhost on the same computer. On iPhone, add the site to the Home Screen first.</li>
                    <li><b>When nobody answers a waiting customer</b>, the system reminds the team at 5, 10, 20 and 30 minutes. At 10 and 30 minutes it can also email the owner and managers, and send a WhatsApp alert. Set this in Settings → Business and team.</li>
                    <li><b>Give the chat back to the bot</b> after a time you choose, so a customer is never left alone. They are told sorry and see the menu again.</li>
                    <li><b>Daily summary email</b> (optional): yesterday's numbers every morning at about 8:00.</li>
                </ul>
            </section>

            <section class="card hp-sec" id="ai">
                <h2>The AI assistant</h2>
                <p>AI is <b>optional and off by default</b>, so you pay nothing for it until you choose. The menu bot and booking work fully without it.</p>
                <ul>
                    <li>In <a href="{{ route('ai') }}">AI assistant</a> @unless ($role !== 'staff')<span class="tag">Owner and manager</span>@endunless you choose a mode and teach it your facts (price list, policies). It never invents prices: an answer with a price you did not give it is blocked.</li>
                    <li>With AI on, staff can press <b>Summarise with AI</b> in a chat, and the AI can draft replies for staff to approve before they are sent.</li>
                    <li>Each plan includes a number of AI replies per month. A message at the limit tells you.</li>
                </ul>
            </section>

            <section class="card hp-sec" id="team">
                <h2>Team and roles</h2>
                <div class="hp-table">
                    <div><span class="tag ok">Owner</span><p>Everything, including billing, channels, menu, booking setup, team and backups.</p></div>
                    <div><span class="tag warn">Manager</span><p>Saved replies, tags, marketing, AI and templates, plus everything staff can do.</p></div>
                    <div><span class="tag">Staff</span><p>Inbox, schedule, customers, tasks, orders and reports.</p></div>
                </div>
                <p class="mute">Add people in Settings → Business and team → Team. Each person has their own sign-in and their own alerts.</p>
            </section>

            <section class="card hp-sec" id="billing">
                <h2>Plan and billing @unless ($owner)<span class="tag">Owner</span>@endunless</h2>
                <ul>
                    <li>New businesses start with a free trial. <a href="{{ route('billing') }}">Plan and billing</a> shows how much of each limit you used (AI replies, team members, people, channels) and your payments.</li>
                    <li>If the trial or a payment ends, the AI pauses but customer messages still reach your Inbox.</li>
                    <li>You can stop renewal any time and keep the plan until the paid month ends.</li>
                </ul>
            </section>

            <section class="card hp-sec" id="data">
                <h2>Your data and privacy</h2>
                <ul>
                    <li><b>Backup and export</b> @unless ($owner)<span class="tag">Owner</span>@endunless: download customers, bookings, orders and chats as spreadsheets in Settings → Backup and export. The server also saves a nightly backup of the database.</li>
                    <li><b>Each business is separate.</b> Nobody can see another business's customers or chats.</li>
                    <li><b>Customer rights.</b> On a customer's page you can download everything about them, or erase them for good. Customers who type <code>stop</code> never get marketing again.</li>
                </ul>
            </section>

            <section class="card hp-sec" id="faq">
                <h2>Questions and fixes</h2>
                <details><summary>The bot is not replying to a customer.</summary><p>Open the chat in the Inbox. If it says <i>You are handling this</i>, the bot is quiet on purpose: press Give back to the bot in the ··· menu. On WhatsApp, Messenger or Instagram also check Channels shows the channel as connected.</p></details>
                <details><summary>I cannot send a WhatsApp message to a customer.</summary><p>WhatsApp's 24-hour rule: after 24 hours without a message from the customer, you can only send an approved template. Use <b>Send a template</b> in the chat.</p></details>
                <details><summary>The bell has no sound.</summary><p>Your browser blocks sound until you click anywhere on the page once. Check the bell menu says "Sound is on". Sound only plays while a page is open; use Phone alerts for when it is closed.</p></details>
                <details><summary>Phone alerts do not appear.</summary><p>Turn them on in My account on that device and press <b>Send me a test alert</b>. The site needs https (or localhost). Check the browser allows notifications for the site, and on iPhone add the site to the Home Screen first.</p></details>
                <details><summary>A chat went back to the bot by itself.</summary><p>You set "give the chat back to the bot after ..." in Settings → Business and team, and nobody replied in time. The customer was told sorry and sees the menu again.</p></details>
                <details><summary>Customers see no free times.</summary><p>Check Booking setup: the service has a person who gives it, your opening hours are set, the days are not marked closed, and Google Calendar is not showing that person busy.</p></details>
                <details><summary>A tag or lead level is wrong.</summary><p>Tags are guessed from words, so sometimes they are wrong. Click the tag to remove it. You can change a customer's lead level on their page.</p></details>
                <details><summary>I changed the menu but customers still see the old one.</summary><p>Press <b>Publish</b> in the Menu builder. Edits stay in a draft until then.</p></details>
            </section>

            <section class="card hp-sec" id="words">
                <h2>Words used here</h2>
                <dl class="hp-dl">
                    <dt>Handover</dt><dd>The bot passes a chat to a person.</dd>
                    <dt>Channel</dt><dd>A place customers write from: website, WhatsApp, Messenger, Instagram.</dd>
                    <dt>Practitioner</dt><dd>The person (or room or machine) that gives a service and has their own schedule.</dd>
                    <dt>Lead level</dt><dd>How close a customer is to booking: Just browsing, Interested, Ready to book.</dd>
                    <dt>Template</dt><dd>A message approved by WhatsApp that you may send after the 24-hour window.</dd>
                    <dt>Broadcast</dt><dd>One message sent to many customers.</dd>
                    <dt>Automation</dt><dd>A message that sends itself after something happens, such as a visit.</dd>
                    <dt>Trial</dt><dd>The free period when you start.</dd>
                </dl>
            </section>
            <p class="mute hp-none" id="hp-none" hidden>Nothing matches your search.</p>
        </div>
    </div>
@endsection
@push('scripts')
<script>
(function () {
  var box = document.getElementById('hp-search'), secs = document.querySelectorAll('#hp-main .hp-sec'), links = document.querySelectorAll('#hp-toc nav a'), none = document.getElementById('hp-none');
  box.addEventListener('input', function () {
    var q = box.value.trim().toLowerCase(), shown = 0;
    secs.forEach(function (s) {
      var ok = !q || s.textContent.toLowerCase().indexOf(q) !== -1;
      s.hidden = !ok; if (ok) { shown++; }
      var a = document.querySelector('#hp-toc a[href="#' + s.id + '"]'); if (a) { a.hidden = !ok; }
    });
    none.hidden = shown > 0;
  });
  // Open the question you land on, and highlight the topic you are reading.
  var obs = 'IntersectionObserver' in window ? new IntersectionObserver(function (es) {
    es.forEach(function (e) { if (e.isIntersecting) { links.forEach(function (l) { l.classList.toggle('on', l.getAttribute('href') === '#' + e.target.id); }); } });
  }, { rootMargin: '-20% 0px -70% 0px' }) : null;
  if (obs) { secs.forEach(function (s) { obs.observe(s); }); }
})();
</script>
@endpush
