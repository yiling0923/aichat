# n8n beside the app (optional)

n8n runs the automations: alerts, reminders, reports. The app works without it.
Events sent so far (each is a JSON body `{event, data, sent_at}`):

| Event | When |
|---|---|
| `conversation.started` | A new chat begins |
| `lead.captured` | The bot saved a customer (name, phone, interest, preferred time, score) |
| `handover.requested` | A chat was passed to staff (includes `open_now`) |
| `handover.holding` | 5 min with no staff reply: the customer got a "thanks for waiting" message |
| `customer.message` | A customer wrote in a chat that staff are handling (WhatsApp, Messenger, Instagram). Use it to alert staff |
| `survey.answered` | A customer rated their visit (`rating`, name, phone) |
| `survey.low_score` | The rating was 1 or 2. A task was created too. Use it to alert the manager |
| `broadcast.sent` | A broadcast finished (`sent`, `skipped`, `failed`) |
| `handover.reminder` | 10 min with no staff reply |
| `handover.reassign` | 20 min with no staff reply |
| `handover.owner_alert` | 30 min with no staff reply |
| `booking.created` | A customer booked (service, practitioner, `starts_at`, customer name and phone) |
| `booking.cancelled` | A booking was cancelled by the customer or by staff (`cancelled_by`) |
| `booking.reminder` | 24 h or 2 h before a visit (`hours_before`). The chat already got a reminder message; use this to also send a WhatsApp template or email |

The starter workflow just records them. Add your own steps, for example a WhatsApp or email alert to staff on `handover.requested`.

## Start n8n on Windows

1. Install **Node.js LTS** from https://nodejs.org (one time).
2. Open Command Prompt and run: `npx n8n`
3. Open http://localhost:5678 and create the local owner account.

## Connect it to the app

1. In n8n choose **Workflows > Import from file** and pick `n8n/workflows/01-aichat-events-starter.json`.
2. Click **Publish** (or toggle **Active**) on the workflow.
3. Copy the **Production URL** of the "Aichat event" node. It looks like `http://localhost:5678/webhook/aichat`.
4. Put it in the project's `.env`: `N8N_WEBHOOK_URL=http://localhost:5678/webhook/aichat`
5. Start a new chat in the test chat. The workflow now shows a new execution with the event.

To try it without publishing, click **Listen for test event** in n8n and set the URL to `http://localhost:5678/webhook-test/aichat`.

Next steps (later phases) add the real alerts: a Telegram or email node after the last node, reminders, and the daily report.

Note: check n8n's licence terms before offering the product publicly. The Laravel scheduler can replace n8n if needed.
