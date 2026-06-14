=== HTML Forms & Contact Form for WordPress: Narrative Forms ===
Contributors: narrativecode
Tags: html forms, contact form, form builder, custom form, frontend submission
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The HTML form plugin for WordPress: build a contact form or custom form by pasting any HTML, even from AI. Fast AJAX, no drag and drop.

== Description ==

Narrative Forms is the HTML form plugin for WordPress. Instead of dragging boxes around a builder, you write plain semantic HTML, or paste it straight from an AI like ChatGPT, Claude, or Gemini, and Narrative Forms turns it into a real form with fast AJAX form submissions and stored, exportable data. Any input with a name attribute is saved. That is the whole idea: forms are just HTML, so there is no field type registry, nothing proprietary to lock you in, and no ceiling on what your form can be. If you want a clean, fast, developer friendly HTML form plugin, a simple contact form, or a custom form that a heavy form builder cannot easily produce, this is it. It stays lightweight, loads its assets only on pages that contain a form, and is built to scale to millions of submissions.

= Why an HTML form plugin beats a drag and drop builder =

Most WordPress form plugins lock you into a drag and drop form builder and a fixed list of field types. If the builder does not offer a field, you cannot have it. Narrative Forms is an HTML form plugin with no field registry: a field is simply an HTML element with a name. Need a multi step layout, an unusual input, a custom widget, or markup your designer already wrote? Paste it in and it works. You keep full control of the markup, the classes, and the look, so your contact form or custom form renders exactly the way you built it. Drag and drop made sense years ago. Today you describe what you want or paste what you already have. No builder, no bloat, and no fighting a clunky UI to recreate a form you can already picture. That is why people who outgrow a drag and drop form builder move to a real HTML form plugin.

= Build forms with AI: paste from ChatGPT, Claude, or Gemini =

Because forms are just HTML, any large language model can build one for you. Ask ChatGPT, Claude, Gemini, or your favourite AI for a contact form with a name, email, a dropdown, and a file upload, copy the HTML it returns, and paste it into Narrative Forms. There is no proprietary field format to satisfy, so whatever the AI generates simply works as an HTML form. That makes Narrative Forms an AI friendly form plugin: you bring your own model, paste the markup, and ship the form. The free plugin is completely model agnostic, so you are never tied to one AI. Prefer it built in? AI form generation that writes the HTML for you inside WordPress is on the Narrative Forms Pro roadmap. For now, the free HTML form plugin already works with any AI you paste in.

= Everything in the free HTML forms plugin =

The free HTML forms plugin is a complete forms solution, not a teaser. Here is what every install includes:

* **Fast AJAX form submissions.** Forms submit without a page reload, and there is a graceful fallback when JavaScript is turned off, so the form never breaks for a visitor. AJAX form submissions also keep your pages cache friendly, because the page can stay static while the form posts in the background.
* **A built in HTML editor.** One click field buttons generate the markup for common fields, and a live preview shows the form as you type. Write the HTML by hand, scaffold it quickly with the buttons, or paste a form an AI wrote for you. The editor never hides your markup behind a visual builder, so what you see is what ships.
* **Email notifications.** Send a clean, readable email for every form submission, to yourself or any address, in plain text or HTML. Each field is formatted tidily, and you can route different forms to different inboxes with separate email actions.
* **Webhook actions.** Send form data to any URL when the form is submitted. Connect your forms to Zapier, Make, a CRM, or your own endpoint with a webhook, no code required, and add more than one webhook action per form so one submission can fan out to several services.
* **File upload form fields.** Accept a file upload field with a maximum file size and a maximum number of files per field. Uploaded files are stored safely in the WordPress media folder and recorded with the submission, so building a file upload form is one paste of HTML away.
* **Stored form submissions and CSV export.** Optionally keep every submission in a fast, indexed database table that you browse in the admin. Run a CSV export of your form submissions at any time; the export streams in batches, so it works even with very large numbers of submissions and never times out.
* **Layered spam protection.** Stop bots with a honeypot, a time trap that rejects instant submissions, a same origin referrer check, a limit on the number of links in a message, and an optional rate limit per IP. You can also switch on Cloudflare Turnstile for a privacy respecting CAPTCHA. This spam protection adds no third party tracking to your site.
* **Custom messages and redirects.** Set your own success and error messages, redirect to any URL after a successful submit, and use template tags so the form behaves exactly how you want.
* **Clean, optional styling.** A calm, minimal stylesheet ships with the plugin and is on by default, and you can switch it off in one click if your theme or custom CSS should own the look. Semantic wrapper classes keep your theme in charge of how your HTML form appears.

Every output is escaped and every input is sanitised, following WordPress coding and security standards, so the plugin stays review safe and secure.

= Contact forms, custom forms, and every form in between =

Narrative Forms is a general purpose form plugin, so the same HTML first workflow covers almost any form you need to build. Use it for a simple contact form, a lead capture form, a support request form, a job application, an RSVP, a survey, a registration form, a file upload form, or a custom form with a layout that a drag and drop builder cannot easily produce. Because a field is just an HTML element with a name attribute, you can mix text inputs, email fields, dropdowns, checkboxes, radio buttons, date pickers, textareas, and file upload fields in any structure you like.

Agencies and developers reach for this HTML form plugin when a client needs a form that does not fit a builder's template: a multi column layout, a branded card, a stepper, a pricing calculator, or markup a designer handed over. You paste the HTML, give the fields names, and the form is live with AJAX form submissions, stored data, and CSV export. There is no template to fight and no field type you cannot add, which is the difference between an HTML form plugin and a drag and drop form builder.

It is also a great fit when you want to keep your stack lean. A single lightweight form plugin handles your contact form, your file upload form, and your custom forms, with email and webhook actions for routing, layered spam protection to keep out bots, and stored form submissions you can export to CSV whenever you need them. Whether you collect a handful of submissions a month or millions over time, the same plugin scales with you.

= Developer friendly HTML form plugin =

Narrative Forms is built for people who like control. Prefill fields with template variables such as {{ user.email }}, {{ get.utm_source | default:'direct' }}, or {{ site.name }}, using providers like user, URL parameters, post, site, and date, each with filters such as default, upper, lower, date, and truncate. On the front end, public JavaScript events (`nrfm-submit`, `nrfm-submitted`, `nrfm-success`, `nrfm-error`) let you push conversions to Google Tag Manager or your dataLayer, show a toast, or run any custom logic with a tiny nrfm.on() helper. Filters and actions sit at every decision point, including the form HTML, validation, and webhook request arguments, so you can extend this HTML form plugin without forking it.

= Built to scale to millions of form submissions =

Narrative Forms is engineered for sites that collect a lot of data. Form submissions live in a dedicated, indexed database table rather than bloated post meta, so lookups stay fast as the table grows. Repeated reads are cached, queries are paginated and bounded, CSV export streams in batches, and heavy work can run in the background so the front end stays quick. Whether you collect ten form submissions a month or millions over time, this lightweight form plugin is designed to stay responsive. Scaling to millions describes the architecture, indexed storage, caching, and bounded queries, rather than a benchmarked guarantee.

= Who this HTML forms plugin is for =

* Developers and agencies who want a contact form or custom HTML form they fully control, without a heavy builder.
* Anyone who uses an AI assistant: generate the HTML, paste it, and you are done.
* Site owners who need reliable form submissions, email notifications, file upload forms, and CSV export without the bloat.
* Teams that have outgrown a drag and drop form builder and want a faster, lighter way to build forms.

Common uses include contact forms, lead capture, support requests, job applications, RSVPs, surveys, file uploads, registrations, and multi field custom forms that a drag and drop builder cannot easily produce.

= Upgrade to Narrative Forms Pro =

Narrative Forms Pro keeps the same lightweight, HTML first core and adds the power features that busy sites and agencies need:

* Conditional logic: show or hide fields, and trigger actions, based on what the visitor enters.
* Save and resume: let visitors save a long form as a draft and finish it later from where they left off.
* Submission notifications: a badge for unread form submissions in the admin so a new entry never slips by.
* Require login: restrict any form to logged in users only.
* Schedule windows: open and close a form automatically on the date and time you set.
* Advanced webhooks: reusable webhook templates, automatic retries with backoff, and a delivery log for every call.
* REST API: read form submissions programmatically and connect your forms to anything.
* Share links: generate a hosted, shareable link to any form.

AI form generation, which writes the HTML for you from a plain language description, is on the Pro roadmap. Today the free HTML form plugin already works with any AI you paste in. Learn more at https://narrative-forms.com/?utm_source=wordpress.org&utm_medium=readme&utm_campaign=free

= Publish form submissions with Frontend Submissions (Views) =

Most form plugins keep submissions locked in the admin. The Narrative Forms Frontend Submissions add on turns your form submissions into front end content with reusable Views: display any form's submissions as a public directory, a testimonial wall, a photo gallery, a job board, an event timeline, or product reviews, with instant search, pagination, single pages, per field privacy, and approval moderation. Frontend submission display is a paid add on that requires the free Narrative Forms plugin. Collect with the form, publish with a View. Learn more at https://narrative-forms.com/?utm_source=wordpress.org&utm_medium=readme&utm_campaign=free

== Installation ==

1. Install Narrative Forms from Plugins, then Add New in your WordPress dashboard, or upload the plugin folder to `/wp-content/plugins/` and activate it. A ready to use Contact Form is created on a fresh install, so you can start from a working example instead of a blank screen.
2. Go to Narrative Forms, then Add New, then write or paste your form HTML, or use the one click field buttons to scaffold it. You can also paste markup generated by an AI such as ChatGPT or Gemini. Make sure every input, select, and textarea has a name attribute so its data is saved.
3. Copy the form's shortcode and paste it into any post, page, or block where you want the HTML form to appear.
4. Optional: enable stored form submissions, set up email or webhook actions, configure spam protection, and customise the success message and redirect under the form's tabs.

That is it. Your HTML form is live with fast AJAX form submissions and stored, exportable data.

== Frequently Asked Questions ==

= What is the best HTML form plugin for WordPress? =

If you want full control of your markup, Narrative Forms is built to be the simplest HTML form plugin for WordPress. You write or paste plain HTML, or generate it with an AI, and any input with a name attribute becomes a saved field. There is no drag and drop builder and no field type registry to limit you, so the form is exactly what you build, from a basic contact form to a complex custom form.

= How do I create an HTML contact form without a builder? =

Go to Narrative Forms, then Add New, and write or paste your contact form HTML, or use the one click field buttons to scaffold it. Give every input, select, and textarea a name attribute so its value is saved. Copy the generated shortcode into any post, page, or block, and your HTML contact form is live with fast AJAX form submissions and optional stored data.

= Can I paste a form from ChatGPT or another AI? =

Yes. Ask ChatGPT, Claude, Gemini, or any AI for the form markup, then paste it straight into the editor. Because Narrative Forms saves any named HTML field and has no proprietary field format, whatever the model generates works as is. If you would like AI form generation built into WordPress so you can create a form from a single sentence, that is on the Narrative Forms Pro roadmap.

= Is there a drag and drop builder? =

No, and that is on purpose. Drag and drop form builders are slow and limit you to their field types, while an HTML form plugin lets you build anything. With Narrative Forms you write, paste, or AI generate plain HTML and keep full control of the markup and styling. For anyone comfortable with HTML, or with an AI assistant, it is dramatically faster than a drag and drop form builder.

= Does it support file upload forms? =

Yes. Add a file upload field to your HTML and Narrative Forms handles it, with a configurable maximum file size and a maximum number of files per field. Uploaded files are stored safely in the WordPress media folder and recorded with the submission, so a file upload form takes one paste of HTML.

= Where are form submissions stored, and can I export them to CSV? =

You can optionally store every submission in a dedicated, indexed database table that you view in the admin. Run a CSV export of your form submissions at any time; the export streams in batches, so it works even with very large numbers of submissions. You can also forward form submissions by email or webhook instead of, or in addition to, storing them.

= How do I send form data to a webhook? =

Open the form's Actions tab and add a webhook action with your endpoint URL. On each submission the field data is sent to that webhook URL, so you can connect Narrative Forms to Zapier, Make, a CRM, or your own service without writing any code. You can add more than one webhook per form.

= How does Narrative Forms block spam? =

It layers several lightweight, privacy friendly checks for spam protection: a honeypot field, a time trap that rejects instant bot submissions, a same origin referrer check, a limit on the number of links in a submission, and an optional rate limit per IP. You can also enable Cloudflare Turnstile for a privacy respecting CAPTCHA. This spam protection adds no third party tracking to your site.

= Is it a lightweight form plugin that works with caching and at scale? =

Yes. The plugin loads its CSS and JavaScript only on pages that contain a form, the admin is intentionally minimal, and form submissions use a fast indexed table with cached reads and bounded, paginated queries. It is a lightweight form plugin built to stay quick from your first submission to your millionth, and it works with page caching because submissions go through AJAX.

= How do I track form conversions in Google Tag Manager or analytics? =

Narrative Forms fires public JavaScript events on submit; `nrfm-success` is the one you usually want. Listen for it with the nrfm.on('success', ...) helper or a standard event listener, then push a custom event to your dataLayer for Google Tag Manager or fire a Google Analytics event. You can also read the submitted values with FormData.

= Can I display form submissions on the front end? =

Yes, with the Narrative Forms Frontend Submissions add on, which publishes your form submissions as public Views such as directories, testimonials, galleries, and listings, with instant search, single pages, per field privacy, and approval moderation. Frontend submission display is a paid add on; the free HTML form plugin focuses on collecting, storing, and routing your form submissions.

== Screenshots ==

1. Build an HTML contact form by pasting markup, even an AI's: here a custom RSVP card with pill buttons and a star rating.
2. No field type limits in this HTML form plugin: a table booking form with a seating switcher, a guest stepper, and native date and time pickers.
3. Build a custom form fast: an instant quote form with selectable option cards and range sliders that update a live price.
4. The built in HTML editor with one click field buttons, a live preview, and a file upload form field with size and count limits.
5. Form settings: store form submissions, hide the form, or redirect after submit, with template tokens like [NAME] and [NRFM_IP_ADDRESS].
6. Email notifications for every form submission, in plain text or HTML, with a template token for each field.
7. Webhook actions: send form data to any webhook URL after every submission, with no code.
8. Appearance and protection settings: an optional stylesheet, honeypot, and Cloudflare Turnstile, privacy friendly with no third party tracking.
9. Advanced spam protection and data settings: a time trap, same origin check, link limit, and rate limit per IP.

== Changelog ==

= 1.0.2 =
* New installs now include a ready to use Contact Form with a dropdown, so you do not start from a blank screen.
* The basic Narrative Forms stylesheet is now enabled by default on new installs.
* Simplified the field setup panel by removing a nested box.
* Removed the redirect after error option. Form errors now always show inline, the same way in AJAX and non JavaScript submissions.
* Forms whose template includes its own form tag now render and submit correctly; the plugin always provides the form wrapper.

= 1.0.1 =
* Added optional usage analytics through the Appsero SDK. Nothing is collected unless you allow it in the admin notice. See the Privacy section.
* Readme updated.

= 1.0.0 =
* Initial release of Narrative Forms, the HTML form plugin built on plain HTML.
* Build contact forms and custom forms by writing or pasting any semantic HTML.
* Built in editor with one click field buttons and a live preview.
* Fast AJAX form submissions with a fallback when JavaScript is off.
* Stored form submissions in a dedicated, indexed table with batched CSV export.
* Email notifications and webhook actions for every submission.
* File upload form fields with a size limit and a file count limit for every field.
* Layered spam protection: honeypot, time trap, referrer check, link limit, rate limit per IP, and optional Cloudflare Turnstile.
* Template variables for prefilling fields, public JavaScript events, and developer hooks.
* Customisable success and error messages, redirects, and template tags.

== Upgrade Notice ==

= 1.0.2 =
Adds a ready to use Contact Form on install, enables the basic stylesheet by default, simplifies the field setup panel, and shows form errors inline.

= 1.0.1 =
Adds optional usage analytics (nothing is collected unless you allow it) and updates the readme.

= 1.0.0 =
First public release of Narrative Forms, the HTML form plugin built on plain HTML.

== Privacy ==

Narrative Forms uses the [Appsero](https://appsero.com) SDK to collect some telemetry data, but only after you confirm it. This helps us troubleshoot problems faster and make product improvements.

The Appsero SDK **does not gather any data by default.** It only starts collecting basic telemetry **when you allow it through the admin notice**. We collect the data to ensure a great experience for all of our users.

Integrating the Appsero SDK **DOES NOT IMMEDIATELY** start gathering data, and never **without your confirmation.**

Learn more about how [Appsero collects and uses this data](https://appsero.com/privacy-policy/).

== External services ==

This plugin connects to external services only in these optional cases, and each is off until you enable or allow it.

* **Cloudflare Turnstile (optional CAPTCHA).** Used only if you enable Turnstile. When a page with a form loads, the browser requests `https://challenges.cloudflare.com/turnstile/v0/api.js` to render the widget; on submit, the server sends the Turnstile token, your secret key, and the visitor's IP address to `https://challenges.cloudflare.com/turnstile/v0/siteverify` to verify it. Terms: `https://www.cloudflare.com/website-terms/` Privacy: `https://www.cloudflare.com/privacypolicy/`
* **Webhooks (optional, configured by you).** Used only if you add a Webhook action. After a successful submission, the submitted form fields plus limited metadata (timestamp, IP address, user agent, referrer) are sent as JSON or URL encoded data to the exact URL you configure, on a domain you choose. Example URLs in the UI are placeholders and receive nothing. The destination is your choice, so consult that service's terms and privacy policy.
* **Appsero (optional usage analytics).** Off until you allow it through the admin notice; nothing is sent before that. It then sends basic environment details (site URL, WordPress and PHP versions, active theme and plugins, locale, and the admin email used to confirm) to `https://api.appsero.com`, plus an optional survey if you deactivate. No form submissions or visitor data are sent. Privacy: `https://appsero.com/privacy-policy/`
