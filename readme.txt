=== HTML Forms & Contact Form for WordPress – Narrative Forms ===
Contributors: narrativecode
Tags: contact form, form builder, forms, html forms, frontend submission
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build HTML forms and contact forms in WordPress: write or paste any HTML, even from AI, with fast AJAX submissions. No drag and drop.

== Description ==

Narrative Forms is the HTML-first WordPress form plugin. Instead of dragging boxes around a builder, you write plain, semantic HTML - or paste it straight from an AI like ChatGPT, Claude, or Gemini - and Narrative Forms turns it into a real form with fast AJAX submissions and stored, exportable data. Any input with a `name` attribute is saved. That is the whole idea: forms are just HTML, so there is no field-type registry, no lock-in, and no ceiling on what your form can be. It stays lightweight, loads its assets only on pages that actually contain a form, and is built to scale to millions of submissions. If you want a clean, fast, developer-friendly contact form or custom HTML form without the bloat of a heavy form builder, this is it.

= Why Narrative Forms is different =

Most WordPress form plugins lock you into a drag-and-drop builder and a fixed list of field types. If the builder does not offer a field, you cannot have it. Narrative Forms has no field registry - a field is simply an HTML element with a `name`. Need a multi-step layout, an unusual input, a custom widget, or markup your designer already wrote? Paste it in and it works. You keep full control of the markup, the classes, and the look. Drag-and-drop made sense years ago; today you describe what you want or paste what you already have. No builder, no bloat, and no fighting a clunky UI to recreate a form you can already picture.

= Bring your own AI =

Because forms are just HTML, any large language model can write one for you. Ask ChatGPT, Claude, Gemini, or your favourite AI for "a contact form with a name, email, a dropdown, and a file upload," copy the HTML it returns, and paste it into Narrative Forms. There is no proprietary field format to satisfy, so whatever the AI generates simply works. The free plugin is completely AI-agnostic: bring your own model, paste the markup, ship the form. (Prefer it built in? AI-assisted generation is on the Narrative Forms Pro roadmap - for now, bring any model you like.)

= Everything in the free plugin =

The free plugin is a complete forms solution, not a teaser. You get:

* **Fast AJAX submissions.** Forms submit without a page reload, with a graceful no-JavaScript fallback so nothing ever breaks for your visitors.
* **A built-in editor.** One-click field buttons generate the markup for common fields, and a live preview shows the form as you type - hand-write HTML or scaffold it fast.
* **Email notifications.** Send clean, readable submission emails to yourself or any address, in plain text or HTML, with tidy per-field formatting.
* **Webhook actions.** Send form data to any URL on submission - connect submissions to Zapier, Make, a CRM, or your own endpoint without writing code. Add multiple actions per form.
* **File uploads.** Accept file upload fields with a maximum file size and a maximum number of files per field; uploads are stored safely in the WordPress media folder.
* **Stored submissions and CSV export.** Optionally keep every submission in a fast, indexed database table, then export to CSV in batches that stream without timing out, even on large datasets.
* **Layered anti-spam.** Stop bots with a honeypot, a time trap, a same-origin referrer check, a link-count limit, an optional per-IP rate limit, and optional Cloudflare Turnstile - with no third-party tracking added.
* **Custom messages and redirects.** Set your own success and error messages, redirect after submit, and use template tags so the form behaves exactly how you want.
* **Clean, optional styling.** A calm, minimal stylesheet you can keep or drop, with semantic wrapper classes so your theme stays in charge of the look.

Every output is escaped and every input is sanitised, following WordPress coding and security standards.

= Developer-friendly by design =

Narrative Forms is built for people who like control. Pre-fill fields with template variables such as `{{ user.email }}`, `{{ get.utm_source | default:'direct' }}`, or `{{ site.name }}`, using providers like user, URL parameters, post, site, and date - each with filters such as default, upper, lower, date, and truncate. On the front end, public JavaScript events (`nrfm-submit`, `nrfm-submitted`, `nrfm-success`, `nrfm-error`) let you push conversions to Google Tag Manager or your dataLayer, show a toast, or run any custom logic with a tiny `nrfm.on()` helper. Filters and actions at every decision point - form HTML, validation, webhook request arguments, and more - let you extend behaviour without forking the plugin.

= Built to scale to millions =

Narrative Forms is engineered for sites that collect a lot of data. Submissions live in a dedicated, indexed database table - not bloated post meta - so lookups stay fast as the table grows. Repeated reads are cached, queries are paginated and bounded, CSV export streams in batches, and heavy work can run in the background so the front end stays quick. Whether you collect ten submissions a month or millions over time, the plugin is designed to stay lightweight and responsive.

= Who it is for =

* Developers and agencies who want a contact form or custom HTML form they fully control, without a heavy builder.
* Anyone who uses an AI assistant - generate the HTML, paste it, and you are done.
* Site owners who need reliable form submissions, email notifications, file uploads, and CSV export without the bloat.
* Teams building directories, job boards, testimonial walls, or event listings from form data (see Frontend Submissions below).

Common uses include contact forms, lead capture, support requests, job applications, RSVPs, surveys, file submissions, registrations, and multi-field custom forms that a drag-and-drop builder cannot easily produce.

= Upgrade to Narrative Forms Pro =

Narrative Forms Pro keeps the same lightweight, HTML-first core and adds the power features that busy sites and agencies need:

* **Conditional logic** - show or hide fields, and trigger actions, based on what the visitor enters.
* **Save and resume** - let visitors save a long form as a draft and finish it later from where they left off.
* **Submission notifications** - unread-submission badges in the admin so a new entry never slips by.
* **Require login** - restrict any form to logged-in users only.
* **Schedule windows** - open and close a form automatically on the date and time you set.
* **Advanced webhooks** - reusable webhook templates, automatic retries with backoff, and a delivery log for every call.
* **REST API** - read submissions programmatically and connect your forms to anything.
* **Share links** - generate a hosted, shareable link to any form.

AI-assisted form generation - describe a form in plain language and have the HTML built for you inside WordPress - is on the Pro roadmap. Today, the free plugin already works with any AI you bring (see above). Learn more at https://narrative-forms.com/?utm_source=wordpress.org&utm_medium=readme&utm_campaign=free

= Publish submissions with Frontend Submissions (Views) =

Most form plugins keep submissions locked in the admin. The Narrative Forms Frontend Submissions add-on turns the data you collect into front-end content with reusable Views - display any form's submissions as a public directory, a testimonial wall, a photo gallery, a job board, an event timeline, product reviews, and more:

* **Multiple layouts** - ready-made table, grid, and calendar Views, plus fully custom templates written in your own HTML and CSS.
* **Starter design library** - one-click designs (Cards, Testimonials, Gallery, Profiles, Compact list, Timeline) that fill in with your fields.
* **Instant search and pagination** - fast filtering and browsing, built to handle large datasets.
* **Single pages** - a clean, SEO-friendly page for every entry, with a pretty permalink.
* **Per-field public and private control** - publish only the fields that are safe to show; nothing is public unless you choose it.
* **Approval moderation** - approve or reject submissions, individually or in bulk, so nothing appears publicly until you say so.
* **Theme-proof rendering** - each View is style-isolated, so your theme can't break its layout.

Collect with the form, publish with a View. Learn more at https://narrative-forms.com/?utm_source=wordpress.org&utm_medium=readme&utm_campaign=free

== Installation ==

1. Install Narrative Forms from Plugins → Add New in your WordPress dashboard, or upload the plugin folder to `/wp-content/plugins/` and activate it.
2. Go to Narrative Forms → Add New, then write or paste your form's HTML, or use the one-click field buttons to scaffold it. You can also paste markup generated by an AI such as ChatGPT or Gemini. Make sure every input, select, and textarea has a `name` attribute so its data is saved.
3. Copy the form's shortcode and paste it into any post, page, or block where you want the form to appear.
4. Optional: enable stored submissions, set up email or webhook actions, configure anti-spam, and customise the success message and redirect under the form's tabs.

That is it - your HTML form is live with fast AJAX submissions and stored, exportable data.

== Frequently Asked Questions ==

= How do I create an HTML form in WordPress? =

Go to Narrative Forms → Add New and write or paste your form's HTML, or use the field buttons to scaffold it. Any valid HTML works; just give every input, select, and textarea a `name` attribute so its value is saved. Copy the generated shortcode into any post, page, or block, and the form is live with AJAX submissions and optional stored data.

= Can I paste a form from ChatGPT or another AI? =

Yes. Ask ChatGPT, Claude, Gemini, or any AI for the form markup, then paste it straight into the editor. Because Narrative Forms saves any named HTML field and has no proprietary field format, whatever the model generates works as-is. If you would like the AI built into WordPress so you can generate forms from a single sentence, that is Narrative Forms Pro.

= Is there a drag-and-drop builder? =

No, and that is on purpose. Drag-and-drop form builders are slow and limit you to their field types. With Narrative Forms you write, paste, or AI-generate plain HTML and keep full control of the markup and styling. For anyone comfortable with HTML, or with an AI assistant, it is dramatically faster.

= Where are form submissions stored, and can I export them to CSV? =

You can optionally store every submission in a dedicated, indexed database table that you view in the admin. Export them to CSV at any time; the export streams in batches, so it works even with very large numbers of submissions. You can also forward submissions by email or webhook instead of, or in addition to, storing them.

= Does it support file uploads? =

Yes. Add a file upload field to your HTML and Narrative Forms handles it, with a configurable maximum file size and a maximum number of files per field. Uploaded files are stored safely in the WordPress media folder and recorded with the submission.

= How do I send form data to a webhook? =

Open the form's Actions tab and add a webhook action with your endpoint URL. On each submission the field data is sent to that URL, so you can connect Narrative Forms to Zapier, Make, a CRM, or your own service without writing any code.

= How do I track form conversions in Google Tag Manager or analytics? =

Narrative Forms fires public JavaScript events on submit; `nrfm-success` is the one you usually want. Listen for it with the `nrfm.on('success', ...)` helper or a standard event listener, then push a custom event to your dataLayer for Google Tag Manager, fire a Google Analytics event, or show a confirmation. You can also read the submitted values with `FormData`.

= How does Narrative Forms block spam? =

It layers several lightweight, privacy-friendly checks: a honeypot field, a time trap that rejects instant bot submissions, a same-origin referrer check, a limit on the number of links in a submission, and an optional per-IP rate limit. You can also enable Cloudflare Turnstile for a privacy-respecting CAPTCHA. No third-party tracking is added to your site.

= Is it lightweight, and does it work with caching and at scale? =

Yes. The plugin loads its CSS and JavaScript only on pages that actually contain a form, the admin UI is intentionally minimal, and submissions use a fast indexed table with cached reads and bounded, paginated queries. It is built to stay quick from your first submission to your millionth, and it works with page caching because submissions go through AJAX.

= Can I display submissions on the front end, or generate forms with AI? =

Yes to displaying submissions: the Narrative Forms Frontend Submissions add-on publishes them as public Views - directories, testimonials, galleries, and listings - with instant search, single pages, per-field privacy, and approval moderation. For AI, the free plugin already works with any model you paste in; built-in AI generation is on the Pro roadmap, alongside Pro's conditional logic, advanced webhooks, save-and-resume, REST API, require-login, schedule windows, and share links.

== Screenshots ==

1. Forms are just HTML: paste your markup - or an AI's - and it becomes a real form. Here a custom RSVP card with pill buttons and a star rating.
2. No field-type limits - a table-booking form with a seating switcher, a guest stepper, and native date and time pickers.
3. Build anything: an instant-quote form with selectable option cards and range sliders that update a live price.
4. The built-in editor - one-click field buttons scaffold the HTML (here a file-upload field with size and count limits), or write your own.
5. Decide what happens after a submission: store it, hide the form, or redirect - with tokens like [NAME] and [NRFM_IP_ADDRESS].
6. Email notifications: send each submission to any address, in plain text or HTML, with per-field tokens.
7. Actions run after every submission - send email and POST the data to any webhook URL.
8. Settings: an optional stylesheet, honeypot, and Cloudflare Turnstile - privacy-friendly, with no third-party tracking.
9. Layered anti-spam - a time trap, same-origin check, link limit, and per-IP rate limiting - plus a clean uninstall.

== Changelog ==

= 1.0.1 =
* Readme updated.

= 1.0.0 =
* Initial release of Narrative Forms, the HTML-first WordPress form plugin.
* Build contact forms and custom forms by writing or pasting any semantic HTML.
* Built-in editor with one-click field buttons and a live preview.
* Fast AJAX submissions with a no-JavaScript fallback.
* Stored submissions in a dedicated, indexed table with batched CSV export.
* Email notifications and webhook actions for every submission.
* File upload fields with size and per-field count limits.
* Layered anti-spam: honeypot, time trap, referrer check, link limit, per-IP rate limit, and optional Cloudflare Turnstile.
* Template variables for pre-filling fields, public JavaScript events, and developer hooks.
* Customisable success and error messages, redirects, and template tags.

== Upgrade Notice ==

= 1.0.1 =
Readme updated.

= 1.0.0 =
First public release of Narrative Forms - the HTML-first WordPress form plugin.

== External services ==

This plugin may connect to external services in the following situations:

- Cloudflare Turnstile (optional CAPTCHA)
  - What it is and why: A free, privacy‑friendly CAPTCHA by Cloudflare used to protect forms from automated spam.
  - When data is sent: Only when a page with a Narrative Forms form is viewed (the Turnstile JS is loaded) and when a form is submitted (server verifies the token).
  - What data is sent:
    - To the JS endpoint: the browser requests `https://challenges.cloudflare.com/turnstile/v0/api.js` to render the widget.
    - To the verify endpoint: the server sends the Turnstile response token, your site’s secret key, and the requester’s IP address to `https://challenges.cloudflare.com/turnstile/v0/siteverify` to validate the submission.
  - Policies: Terms `https://www.cloudflare.com/website-terms/` • Privacy `https://www.cloudflare.com/privacypolicy/`

- Webhooks (optional, user‑configured)
  - What it is and why: If you add a Webhook action, Narrative Forms will send the submitted form fields to the URL you specify to integrate with external systems (e.g., marketing automation, CRMs, servers you control).
  - When data is sent: After a successful submission, for each configured Webhook action.
  - What data is sent: Submitted form fields and limited metadata (timestamp, IP address, user agent, referrer). Data is sent as JSON or form‑encoded depending on your configuration.
  - Where it is sent: To the exact URL you configure in the action, on a domain you choose. Any example like `https://example.com/webhook` in the UI or docs is a placeholder; no data is sent there unless you explicitly configure it.
  - Policies: The destination service is chosen by you. Please consult that service’s terms of use and privacy policy.
