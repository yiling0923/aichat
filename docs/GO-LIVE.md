# Going live: invite-only beta (step 7)

Everything in the app for the beta is built. This page is the checklist for the parts that need your accounts, a server and some money. Prices below are rough estimates from memory: check each provider's page before paying.

## 0. What costs money

| Item | Rough cost | Needed for |
|---|---|---|
| Small server (VPS, 1 to 2 GB RAM) | about US$5 to 12 a month | The app online all day, with backups |
| Domain name | about US$10 to 15 a year | A proper address and https |
| Email sending (SMTP service) | free tier usually enough for a beta | Confirmation and password emails |
| Stripe | no monthly fee, a percentage per payment | Taking real payments (skip during a free beta) |
| WhatsApp number | a SIM or number that is not already on WhatsApp | The business's real number |
| Meta messaging fees | billed by Meta to each business | Template messages sent outside 24 hours |
| AI (Claude Haiku) | cents per hundred chats | AI mode, counted per business |

You can run the whole beta with **no payment provider** (`BILLING_PROVIDER=local`, test mode): invited businesses use their trial and nobody is charged.

## 1. Meta (WhatsApp, Instagram, Messenger)

1. **Business verification:** Meta Business Settings > Security Center > Start verification. Needs your company documents. It can take days.
2. **Real WhatsApp number:** WhatsApp Manager > add a phone number (not already used on WhatsApp). Choose a display name; Meta reviews it.
3. **Add a payment method** in WhatsApp Manager, otherwise template messages stop.
4. **A token that does not expire:** Business Settings > Users > **System users** > add one (Admin) > assign your app and the WhatsApp account > **Generate token** with `whatsapp_business_messaging` and `whatsapp_business_management`. Paste it in **Channels** instead of the 24-hour test token.
5. **App settings:** add your Privacy policy URL (`https://YOUR-DOMAIN/privacy`) and a Terms URL (`/terms`), then switch the app from Development to **Live**.
6. **Instagram and Messenger for people outside your team** need Meta **App Review** (Advanced access for `instagram_manage_messages`, `pages_messaging`). Until approved they only work for people with a role on your app. Plan a few weeks.
7. In `.env` set `META_APP_ID`, `META_APP_SECRET`, `META_VERIFY_TOKEN`. Webhook URL: `https://YOUR-DOMAIN/webhook/meta` (use **Channels > Save webhook in Meta**).

## 2. Server (Ubuntu 24.04 VPS)

Replace `YOUR-DOMAIN`. Run as a normal user with sudo.

```bash
sudo apt update && sudo apt install -y nginx mariadb-server git unzip certbot python3-certbot-nginx \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-intl php8.3-bcmath
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# database and its own user (never use root)
sudo mysql -e "CREATE DATABASE aichat CHARACTER SET utf8mb4; CREATE USER 'aichat'@'localhost' IDENTIFIED BY 'A-LONG-RANDOM-PASSWORD'; GRANT ALL ON aichat.* TO 'aichat'@'localhost';"

# the app
sudo mkdir -p /var/www/aichat && sudo chown $USER /var/www/aichat
git clone -b claude/sleepy-brown-fv558x https://github.com/yiling0923/aichat.git /var/www/aichat
cd /var/www/aichat && composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
sudo chown -R www-data:www-data storage bootstrap/cache
```

`/etc/nginx/sites-available/aichat` (then `sudo ln -s` it into `sites-enabled`, `sudo nginx -t`, `sudo systemctl reload nginx`):

```nginx
server {
    server_name YOUR-DOMAIN;
    root /var/www/aichat/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
    location ~ /\.(?!well-known) { deny all; }
}
```

Then: `sudo certbot --nginx -d YOUR-DOMAIN` (free https certificate, renews itself).

Edit `.env` on the server:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-DOMAIN
DB_CONNECTION=mysql
DB_DATABASE=aichat
DB_USERNAME=aichat
DB_PASSWORD=A-LONG-RANDOM-PASSWORD
SESSION_SECURE_COOKIE=true
SIGNUP_MODE=invite
BILLING_PROVIDER=local
MAIL_MAILER=smtp        # plus MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS from your email service
AI_PROVIDER=claude      # and ANTHROPIC_API_KEY, or leave off
```

Then:

```bash
php artisan migrate --force
php artisan aichat:make-platform-owner you@your-company.com     # your own console login
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo crontab -u www-data -e     # add this one line:
* * * * * cd /var/www/aichat && php artisan schedule:run >> /dev/null 2>&1
```

Do **not** run `db:seed` on the server: it creates sample logins with the password "password".

To update later: `git pull`, `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `php artisan config:cache route:cache view:cache`.

## 3. Domain and email

- Buy a domain, point an **A record** to the server's IP address.
- Pick an SMTP service (most have a free tier), add its **SPF and DKIM** records to your DNS so emails do not land in spam, and put its details in `.env`.
- Test: sign up with a real email; the confirmation email must arrive in the inbox.

## 4. Backups and monitoring

- The scheduler already runs `php artisan aichat:backup` at 02:30 and keeps 14 files in `storage/app/backups`. **That is on the same server**: also copy them elsewhere. Simple way: `php artisan aichat:backup --copy-to=/mnt/other-disk`, or have the server provider's snapshot feature switched on (a few dollars a month).
- **Try a restore once** (on a spare database): `gunzip < backup.sql.gz | mysql -u aichat -p aichat_test`. A backup you never restored is a guess.
- Monitoring: create a free account at an uptime service (for example UptimeRobot) and watch `https://YOUR-DOMAIN/health`. It returns 200 when the database works and the scheduler ran in the last 5 minutes, otherwise 503, and the service emails you.
- Errors: `storage/logs/laravel.log` on the server.

## 5. Payments with Stripe (only when you charge)

1. Create a Stripe account. In **Product catalog** make two recurring monthly prices (Starter and Growth, same amounts as `config/plans.php`). Copy each price id (`price_...`).
2. **Developers > API keys**: copy the secret key. **Developers > Webhooks > Add endpoint**: URL `https://YOUR-DOMAIN/webhook/stripe`, events `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated`, `customer.subscription.deleted`. Copy the signing secret.
3. `.env`: `BILLING_PROVIDER=stripe`, `STRIPE_SECRET=sk_...`, `STRIPE_WEBHOOK_SECRET=whsec_...`, `STRIPE_PRICE_STARTER=price_...`, `STRIPE_PRICE_GROWTH=price_...`, then `php artisan config:cache`.
4. **Test first with Stripe's test keys** (`sk_test_...`) and the test card `4242 4242 4242 4242`. Only switch to live keys after a full test.

## 6. Legal

- The pages `/terms` and `/privacy` are starter text. Have a lawyer in your country review and complete them (company name, address, contact, data retention, the AI notice, Meta and Google as processors).
- Each business is responsible for messaging only people who contacted them or agreed. The app already stops promotions for people who typed STOP, and managers can download or delete one customer's data from the customer page.

## 7. Run the invite-only beta

1. `.env`: `SIGNUP_MODE=invite`.
2. Open `/platform`, make an invitation code (optionally locked to one email), and send the link it shows.
3. Start with 3 to 5 friendly businesses. Watch `/platform` (usage, AI calls) and `/health`.
4. Ask each one to run for two weeks and note every confusing screen.

## 8. Final check

Run `php artisan aichat:preflight` on the server. It lists every setting that matters with PASS, WARN or FAIL. Fix every FAIL before inviting anyone.
