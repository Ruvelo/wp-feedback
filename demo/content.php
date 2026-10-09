<?php

declare(strict_types=1);

/*
 * The demo's content: Halyard, a made-up B2B billing platform, and what its
 * customers ask for. Read by demo/build.php.
 *
 * Requests: [key, title, category, days ago, votes, author, body, history, tags]
 * where history is a list of [status, days ago, note|null, by].
 */

return [
    'categories' => ['Billing', 'Invoices', 'Integrations', 'Reporting', 'Customer portal', 'API and webhooks'],

    'requests' => [
        ['usage', 'Usage-based billing', 'Billing', 88, 167, 'Priya Raman', <<<'MD'
We sell API access and our customers pay per 1,000 calls. Today we export usage from our database every month, work out the totals in a spreadsheet and add them to invoices by hand.

It would save us two days a month if Halyard could:

- take **metered usage** through the API as it happens
- price it in tiers (first 10k calls free, then €0.40 per 1,000)
- show the running total to the customer before the invoice goes out
MD, [
            ['planned', 70, 'This is our most requested feature, and it’s next. We’ll share designs with a few of you first: comment if you want in.', 'Maya'],
            ['in_progress', 38, 'Metering API and tiered prices are built. We’re testing with six teams now.', 'Maya'],
        ], ['Enterprise']],

        ['dark', 'Dark mode', null, 120, 142, 'Lucas Ferreira', <<<'MD'
I do month-end close late in the evening and the white dashboard is blinding. A dark theme that follows the system setting would be lovely.
MD, [
            ['planned', 9, 'Good news: dark mode is planned for this quarter. It will follow your system setting, with a switch in your profile to pin light or dark.', 'Maya'],
        ], []],

        ['xero', 'Two-way Xero sync', 'Integrations', 95, 118, 'Hannah Brooks', <<<'MD'
Our accountant lives in Xero. Right now we export invoices as CSV and import them, and payments recorded in Xero never come back to Halyard.

We’d love a proper two-way sync: invoices and credit notes out, payments in.
MD, [
            ['planned', 60, null, 'Maya'],
            ['in_progress', 12, 'Two-way sync of invoices, credit notes and payments is in a private beta with 12 teams. Comment if you’d like to join.', 'Tom'],
        ], ['Accounting']],

        ['currency', 'Invoices in several currencies', 'Invoices', 70, 89, 'Jonas Weber', <<<'MD'
We bill customers in EUR, GBP and CHF. Today we need a separate Halyard workspace for each currency, which makes reporting painful.

Ideal: pick the currency per customer, convert to our reporting currency at the day’s rate.
MD, [
            ['planned', 20, 'Planned for after usage-based billing. We’ll support any currency Stripe does, with reports in your home currency.', 'Maya'],
        ], []],

        ['portal', 'Let customers update their card themselves', 'Customer portal', 160, 73, 'Amara Nwosu', <<<'MD'
When a card expires, we email the customer, they email us the new details (!), and we type them in. Customers should be able to do this from a link in the reminder email.
MD, [
            ['planned', 140, null, 'Maya'],
            ['in_progress', 110, null, 'Tom'],
        ], []],

        ['quickbooks', 'QuickBooks Online integration', 'Integrations', 50, 64, 'Daniel Kim', <<<'MD'
Same as the Xero request, for QuickBooks Online. Most of our US customers use it.
MD, [], ['Accounting']],

        ['dunning', 'Custom dunning schedules', 'Billing', 75, 57, 'Sofia Rossi', <<<'MD'
The three fixed reminders (3, 7 and 14 days) don’t suit everyone. We’d like to choose the days, the wording, and whether to pause the subscription after the last one.
MD, [
            ['planned', 40, null, 'Maya'],
            ['in_progress', 6, 'Now in progress. You’ll be able to set up to five reminders and edit each email.', 'Tom'],
        ], ['Quick win']],

        ['keys', 'Sign in with security keys', null, 64, 51, 'Kenji Mori', <<<'MD'
We already require two-factor authentication. Our security team would like hardware keys and passkeys (WebAuthn) to be allowed, and ideally required for admins.
MD, [
            ['planned', 15, null, 'Maya'],
        ], ['Enterprise']],

        ['exports', 'Export reports to CSV and Excel', 'Reporting', 130, 45, 'Grace Liu', <<<'MD'
Every report should have an export button. Our finance team does the rest in Excel.
MD, [
            ['planned', 100, null, 'Maya'],
            ['in_progress', 80, null, 'Tom'],
        ], ['Quick win']],

        ['mobile', 'Native mobile app', null, 150, 41, 'Oliver Grant', <<<'MD'
An iPhone and Android app to check revenue and approve refunds on the go.
MD, [
            ['closed', 30, 'We won’t build native apps for now. Instead we made the whole web app work well on phones, and you can add it to your home screen. Thanks for asking!', 'Maya'],
        ], []],

        ['slack', 'Slack alerts for failed payments', 'Integrations', 26, 38, 'Inès Laurent', <<<'MD'
A message in a Slack channel when a payment fails or a customer churns, so the account owner can reach out the same day.
MD, [], []],

        ['sso', 'SAML single sign-on', null, 44, 33, 'Kenji Mori', <<<'MD'
We manage everyone through Okta. SAML SSO (and SCIM provisioning, if possible) is required by our IT policy for anything holding customer data.
MD, [], ['Enterprise']],

        ['bulk', 'Edit several invoices at once', 'Invoices', 33, 29, 'Mateo García', <<<'MD'
Select 20 draft invoices and change the due date or add a note to all of them.
MD, [], ['Quick win']],

        ['webhooks', 'A dashboard for webhook deliveries', 'API and webhooks', 58, 24, 'Tom Reyes', <<<'MD'
When a webhook fails we only find out from our own logs. A page listing recent deliveries, their responses, and a **Retry** button would help a lot.
MD, [
            ['in_progress', 16, null, 'Tom'],
        ], []],

        ['revenue', 'Revenue recognition reports', 'Reporting', 21, 22, 'Hannah Brooks', <<<'MD'
Deferred revenue and recognised revenue per month, for annual plans paid up front.
MD, [], ['Accounting']],

        ['proration', 'Preview proration before changing a plan', 'Billing', 12, 19, 'Lucas Ferreira', <<<'MD'
Before I upgrade a customer mid-cycle, show me the exact amount they’ll be charged or credited.
MD, [], ['Quick win']],
    ],

    // Merged into another request: [title, into, days ago, votes, author, body]
    'duplicates' => [
        ['Sync with QuickBooks', 'quickbooks', 40, 9, 'Mateo García', 'Can invoices go straight into QuickBooks?'],
        ['Dark theme please', 'dark', 75, 14, 'Sofia Rossi', 'My eyes at 11pm say thank you in advance.'],
    ],

    // Waiting for a manager: [title, category, author, body]
    'pending' => [
        ['Pay invoices with SEPA Instant', 'Billing', 'Jonas Weber', 'Many of our European customers would pay faster with SEPA Instant transfers.'],
    ],

    // [request, author, days ago, body, replies: [[author, days ago, body]]]
    'comments' => [
        ['usage', 'Daniel Kim', 80, 'Same here. We bill per seat **and** per GB of storage, so we’d need more than one meter per customer.', [
            ['Maya Okafor', 79, 'Noted, Daniel: several meters per subscription are part of the plan.'],
        ]],
        ['usage', 'Grace Liu', 45, 'Would love to join the beta. We’re on the Scale plan.', []],
        ['usage', 'Priya Raman', 3, 'Just set it up, it took ten minutes. Thank you!', []],
        ['dark', 'Kenji Mori', 60, '+1, especially for the invoice editor.', []],
        ['dark', 'Amara Nwosu', 20, 'Please keep the charts readable in dark mode: the current greens are hard to tell apart.', [
            ['Maya Okafor', 8, 'Good point. The chart colours are being redone for both themes.'],
        ]],
        ['xero', 'Sofia Rossi', 50, 'Tracking categories would be important for us: we map products to Xero tracking options.', []],
        ['xero', 'Hannah Brooks', 10, 'In the beta since last week. Payments come back within a minute. Great work!', []],
        ['quickbooks', 'Daniel Kim', 30, 'Happy to test this when it’s ready.', []],
        ['keys', 'Tom Reyes', 50, 'For context: passkeys would work for everyone, hardware keys are the same standard.', []],
        ['mobile', 'Oliver Grant', 29, 'Fair enough, the mobile web version is good.', []],
    ],

    // [key, title, days ago, labels, cover, requests, body]
    'entries' => [
        ['usage', 'Usage-based billing is here', 2, ['new'], 'cover-usage.svg', ['usage'], <<<'MD'
You can now bill for what your customers use: API calls, seats, gigabytes, minutes, anything you can count.

- **Send usage as it happens** with the new metering API, or upload a CSV at the end of the month.
- **Price it your way**: per unit, in tiers or in packages, with a free allowance.
- **Customers see their running total** in the portal before the invoice is created.

```bash
curl https://api.halyard.test/v1/usage \
  -u sk_live_… \
  -d meter=api_calls -d customer=cus_42 -d quantity=1200
```

Usage-based prices work alongside your flat plans, so you can charge a base fee plus usage. Read [the guide](https://halyard.test/help/usage-billing) to get started.
MD],
        ['search', 'Faster invoice search', 9, ['improved'], null, [], <<<'MD'
Searching invoices is now up to ten times faster on large accounts, and it matches customer names, emails, invoice numbers and amounts.

Try typing `>500` to find invoices over 500, or `overdue` to see the late ones.
MD],
        ['reminders', 'No more duplicate payment reminders', 16, ['fixed'], null, [], <<<'MD'
Some customers received the same payment reminder twice when an invoice was edited after it was sent. That’s fixed, and reminders now say which version of the invoice they’re about.
MD],
        ['exports', 'Export any report to CSV or Excel', 34, ['new'], null, ['exports'], <<<'MD'
Every report now has an **Export** button. Pick CSV or Excel; the file keeps your filters and date range, and amounts stay numbers so formulas work.
MD],
        ['tax', 'Tax rates by region, and smaller fixes', 52, ['improved', 'fixed'], null, [], <<<'MD'
- Tax rates can now depend on the customer’s state or province, not only their country.
- Fixed: credit notes showed the wrong tax total when a discount applied.
- Fixed: the date picker ignored your first day of the week.
MD],
        ['portal', 'Customers can update their card themselves', 74, ['new'], 'cover-portal.svg', ['portal'], <<<'MD'
Payment reminders now include a secure link to the customer portal, where your customers can update their card, download invoices and see their plan.

No more card numbers by email. You can turn the link off in **Settings → Customer portal**.
MD],
    ],

    // A changelog entry that isn't live yet, for the admin area.
    'draft' => ['Dark mode', 'Halyard now follows your system’s dark mode. Pick light or dark for good in **Profile → Appearance**.', ['new']],

    // Everyone else who votes and comments.
    'customers' => [
        'Priya Raman', 'Lucas Ferreira', 'Hannah Brooks', 'Jonas Weber', 'Amara Nwosu', 'Daniel Kim', 'Sofia Rossi', 'Grace Liu',
        'Oliver Grant', 'Mateo García', 'Aisha Khan', 'Emil Johansson', 'Chloé Martin', 'Ravi Patel', 'Nora Lindqvist', 'Samuel Adeyemi',
        'Yuki Tanaka', 'Leila Haddad', 'Felix Wagner', 'Camila Torres', 'Ethan Clarke', 'Zara Ahmed', 'Lars Nilsen', 'Mia Novak',
    ],
];
