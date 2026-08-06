=== HTML Forms & Contact Form for WordPress: Narrative Forms ===
Contributors: narrativecode
Tags: html forms, contact form, form builder, custom form, conditional logic
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

HTML form plugin for WordPress: build a contact form or any custom form from HTML or AI. Conditional logic, webhooks, file uploads, no builder.

== Description ==

Narrative Forms is the HTML form plugin for WordPress. Instead of dragging boxes around a builder, you write plain semantic HTML, or paste it straight from an AI like ChatGPT, Claude, or Gemini, and Narrative Forms turns it into a real form with fast AJAX form submissions and stored, exportable data. Any input with a name attribute is saved. That is the whole idea: forms are just HTML, so there is no field type registry, nothing proprietary to lock you in, and no ceiling on what your form can be. If you want a clean, fast, developer friendly HTML form plugin, a simple contact form, or a custom form that a heavy form builder cannot easily produce, this is it. It stays lightweight, loads its assets only on pages that contain a form, and is built to scale to millions of submissions. Everything it does is free, with no premium tier: the AI form builder, conditional logic, webhooks with delivery logs and retries, a REST API, require login, schedule windows, and hosted share links are all included at no cost.

= Why an HTML form plugin beats a drag and drop builder =

Most WordPress form plugins lock you into a drag and drop form builder and a fixed list of field types. If the builder does not offer a field, you cannot have it. Narrative Forms is an HTML form plugin with no field registry: a field is simply an HTML element with a name. Need a multi step layout, an unusual input, a custom widget, or markup your designer already wrote? Paste it in and it works. You keep full control of the markup, the classes, and the look, so your contact form or custom form renders exactly the way you built it. Drag and drop made sense years ago. Today you describe what you want or paste what you already have. No builder, no bloat, and no fighting a clunky UI to recreate a form you can already picture. That is why people who outgrow a drag and drop form builder move to a real HTML form plugin.

= Build forms with AI, built in or pasted from ChatGPT, Claude, or Gemini =

Because forms are just HTML, any large language model can build one for you, and Narrative Forms gives you two ways to use that. The built in AI form builder writes the form HTML for you right inside WordPress: open a form, click Generate with AI, describe what you want, and keep refining it by chatting. You bring your own API key for an OpenAI compatible provider such as DeepSeek, OpenAI, or OpenRouter, so you stay in control of the model and the cost. You can also paste from anywhere: ask ChatGPT, Claude, or Gemini for a form and drop the HTML in, because there is no proprietary field format to satisfy. Either way, the plugin stays model agnostic, ships with no AI key, and contacts no AI service until you add one; see the Privacy and External services sections for what is sent and when.

= Everything in the free HTML forms plugin =

The free HTML forms plugin is a complete forms solution, not a teaser. Here is what every install includes:

* **Fast AJAX form submissions.** Forms submit without a page reload, and there is a graceful fallback when JavaScript is turned off, so the form never breaks for a visitor. AJAX form submissions also keep your pages cache friendly, because the page can stay static while the form posts in the background.
* **A built in HTML editor.** One click field buttons generate the markup for common fields, and a live preview shows the form as you type. Write the HTML by hand, scaffold it quickly with the buttons, or paste a form an AI wrote for you. The editor never hides your markup behind a visual builder, so what you see is what ships.
* **A built in AI form builder.** Describe the form you want and let AI write the HTML for you inside WordPress, then keep refining it by chatting. You bring your own API key for an OpenAI compatible provider (DeepSeek, OpenAI, OpenRouter, or a custom endpoint), so you control the model and the cost. Your key is stored for administrators only and is never exposed to visitors.
* **Conditional logic.** Show or hide any field based on what the visitor enters, using simple match rules. A hidden required field is dropped from validation automatically, so a conditional logic rule never blocks a submission.
* **Email notifications.** Send a clean, readable email for every form submission, to yourself or any address, in plain text or HTML. Each field is formatted tidily, and you can route different forms to different inboxes with separate email actions.
* **Webhook actions with delivery logs, retries, and templates.** Send form data to any URL when the form is submitted, with no code, and add more than one webhook per form so one submission can fan out to several services. Every webhook call is recorded in a delivery log, failed calls retry automatically with backoff, and reusable webhook templates ship with one click Discord, Slack, and Microsoft Teams presets plus custom JSON payloads and request headers.
* **REST API.** Read and manage your form submissions programmatically through a REST API (namespace nrfm/v1), authenticated by a capability check or an API key you generate. Connect your forms to another app, a dashboard, or your own tooling.
* **File upload form fields.** Accept a file upload field with a maximum file size and a maximum number of files per field. Uploaded files are stored safely in the WordPress media folder and recorded with the submission, so building a file upload form is one paste of HTML away.
* **Stored form submissions and CSV export.** Optionally keep every submission in a fast, indexed database table that you browse in the admin. Run a CSV export of your form submissions at any time; the export streams in batches, so it works even with very large numbers of submissions and never times out.
* **Save and resume.** Let visitors save a long form as a draft and continue later from a private resume link. Drafts expire automatically and are cleared once the form is submitted.
* **Submission notifications.** An unread badge on the Forms menu and the submissions list flags new entries, so a fresh form submission never slips by.
* **Require login.** Restrict any form to logged in users only, with your own message and login link for signed out visitors.
* **Schedule windows.** Open and close a form automatically on the dates and times you set, with separate messages for before it opens and after it closes.
* **Direct share links.** Give any form its own hosted URL, so you can share a working form without adding it to a page or copying a shortcode.
* **Layered spam protection.** Stop bots with a honeypot, a time trap that rejects instant submissions, a same origin referrer check, a limit on the number of links in a message, and an optional rate limit per IP. You can also switch on Cloudflare Turnstile for a privacy respecting CAPTCHA. This spam protection adds no third party tracking to your site.
* **Custom messages and redirects.** Set your own success and error messages, redirect to any URL after a successful submit, and use template tags so the form behaves exactly how you want.
* **Clean, optional styling.** A calm, minimal stylesheet ships with the plugin and is on by default, and you can switch it off in one click if your theme or custom CSS should own the look. Semantic wrapper classes keep your theme in charge of how your HTML form appears.

Every output is escaped and every input is sanitised, following WordPress coding and security standards, so the plugin stays review safe and secure.

= Send form data anywhere with webhooks and a REST API =

Narrative Forms is built to move your form submissions wherever they need to go. Add a webhook action with your endpoint URL and every submission is delivered to that webhook, so you can connect a form to Zapier, Make, a CRM, Discord, Slack, Microsoft Teams, or your own service without writing code. A webhook delivery log shows every call and its response, failed webhooks retry automatically with backoff, and webhook templates let you shape a custom JSON payload with one click presets for Discord, Slack, and Teams. When you would rather pull data than push it, the REST API exposes your form submissions under the nrfm/v1 namespace, protected by a capability check or an API key. Add conditional logic to show or hide fields, require login to restrict a form, and schedule windows to open and close it automatically, and your form data never gets stuck in the admin.

= Contact forms, custom forms, and every form in between =

Narrative Forms is a general purpose form plugin, so the same HTML first workflow covers almost any form: a simple contact form, a lead capture or support request form, a job application, an RSVP, a survey, a registration form, a file upload form, or a custom form with a layout a drag and drop builder cannot easily produce. Because a field is just an HTML element with a name attribute, you paste the HTML, give the fields names, and the form is live with AJAX form submissions, stored data, conditional logic, and CSV export. There is no template to fight and no field type you cannot add, which is the difference between an HTML form plugin and a drag and drop form builder, and the same lightweight form plugin scales from a handful of submissions a month to millions over time.

= Developer friendly HTML form plugin =

Narrative Forms is built for people who like control. Prefill fields with template variables such as {{ user.email }}, {{ get.utm_source | default:'direct' }}, or {{ site.name }}, using providers like user, URL parameters, post, site, and date, each with filters such as default, upper, lower, date, and truncate. On the front end, public JavaScript events (`nrfm-submit`, `nrfm-submitted`, `nrfm-success`, `nrfm-error`) let you push conversions to Google Tag Manager or your dataLayer, show a toast, or run any custom logic with a tiny nrfm.on() helper. Filters and actions sit at every decision point, including the form HTML, validation, and webhook request arguments, and a REST API is available for reading and managing form submissions, so you can extend this HTML form plugin without forking it.

= Built to scale to millions of form submissions =

Narrative Forms is engineered for sites that collect a lot of data. Form submissions live in a dedicated, indexed database table rather than bloated post meta, so lookups stay fast as the table grows. Repeated reads are cached, queries are paginated and bounded, CSV export streams in batches, and heavy work can run in the background so the front end stays quick. Whether you collect ten form submissions a month or millions over time, this lightweight form plugin is designed to stay responsive. Scaling to millions describes the architecture, indexed storage, caching, and bounded queries, rather than a benchmarked guarantee.

= Who this HTML forms plugin is for =

* Developers and agencies who want a contact form or custom HTML form they fully control, without a heavy builder.
* Anyone who uses an AI assistant: generate the HTML with the built in AI form builder or paste it from ChatGPT, and you are done.
* Site owners who need reliable form submissions, email notifications, webhooks, file upload forms, and CSV export without the bloat.
* Teams that want conditional logic, require login, scheduled forms, and a REST API without paying for a premium tier.
* Teams that have outgrown a drag and drop form builder and want a faster, lighter way to build forms.

Common uses include contact forms, lead capture, support requests, job applications, RSVPs, surveys, file uploads, registrations, and multi field custom forms with conditional logic that a drag and drop builder cannot easily produce.

= Publish form submissions with Frontend Submissions (Views) =

Most form plugins keep submissions locked in the admin. The Narrative Forms Frontend Submissions add on turns your form submissions into front end content with reusable Views: display any form's submissions as a public directory, a testimonial wall, a photo gallery, a job board, an event timeline, or product reviews, with instant search, pagination, single pages, per field privacy, and approval moderation. Frontend submission display is the one paid add on and it requires the free Narrative Forms plugin. Collect with the form, publish with a View. Learn more at https://narrative-forms.com/?utm_source=wordpress.org&utm_medium=readme&utm_campaign=free

== Frequently Asked Questions ==

= What is the best HTML form plugin for WordPress? =

If you want full control of your markup, Narrative Forms is built to be the simplest HTML form plugin for WordPress. You write or paste plain HTML, or generate it with an AI, and any input with a name attribute becomes a saved field. There is no drag and drop builder and no field type registry to limit you, so the form is exactly what you build, from a basic contact form to a complex custom form with conditional logic and file uploads.

= How do I create an HTML contact form without a builder? =

Go to Narrative Forms, then Add New, and write or paste your contact form HTML, or use the one click field buttons to scaffold it. Give every input, select, and textarea a name attribute so its value is saved. Copy the generated shortcode into any post, page, or block, and your HTML contact form is live with fast AJAX form submissions and optional stored data.

= Does Narrative Forms have a built in AI form builder? =

Yes. The built in AI form builder writes the form HTML for you inside WordPress: click Generate with AI, describe the form you want, and keep refining it by chatting. You bring your own API key for an OpenAI compatible provider such as DeepSeek, OpenAI, or OpenRouter, so you control the model and the cost. You can also paste a form from ChatGPT, Claude, or Gemini, because Narrative Forms saves any named HTML field.

= How do I show or hide fields with conditional logic? =

Open the form's Conditional Logic tab and add a rule such as show or hide a field when another field equals, contains, is greater than, or is empty. Conditional logic runs in the browser for an instant response and is enforced again on the server, and any required field hidden by a rule is dropped from validation so it never blocks a submission.

= How do I send form data to a webhook such as Discord, Slack, or Zapier? =

Open the form's Actions tab and add a webhook action with your endpoint URL. On each submission the field data is sent to that webhook URL, so you can connect Narrative Forms to Zapier, Make, a CRM, or your own service without writing code. Webhook templates include one click Discord, Slack, and Teams presets, every call is recorded in a delivery log, and failed webhooks retry automatically. You can add more than one webhook per form.

= Where are form submissions stored, and can I export them to CSV? =

You can optionally store every submission in a dedicated, indexed database table that you view in the admin. Run a CSV export of your form submissions at any time; the export streams in batches, so it works even with very large numbers of submissions. You can also forward form submissions by email or webhook, or read them through the REST API, instead of or in addition to storing them.

= Is there a REST API for form submissions? =

Yes. Narrative Forms exposes a REST API under the nrfm/v1 namespace so another application can read and manage your form submissions. Requests are authenticated by a capability check or by an API key you generate in Settings, so access stays under your control. It is handy for dashboards, integrations, and your own tooling.

= Can I display form submissions on the front end? =

Yes, with the Narrative Forms Frontend Submissions add on, which publishes your form submissions as public Views such as directories, testimonials, galleries, and listings, with instant search, single pages, per field privacy, and approval moderation. Frontend submission display is the one paid add on; the free HTML form plugin focuses on collecting, storing, and routing your form submissions.

== Screenshots ==

1. Build an HTML contact form by pasting markup, even an AI's: here a custom RSVP card with pill buttons and a star rating.
2. No field type limits in this HTML form plugin: a table booking form with a seating switcher, a guest stepper, and native date and time pickers.
3. Build a custom form fast: an instant quote form with selectable option cards and range sliders that update a live price.
4. The built in HTML editor with one click field buttons, a live preview, and a file upload form field with size and count limits.
5. The built in AI form builder writing a WordPress form from a plain language prompt, with your own OpenAI compatible API key.
6. Conditional logic rules that show or hide a field based on what the visitor enters.
7. Webhook actions with a delivery log and automatic retries, plus Discord, Slack, and Teams payload templates.
8. Stored form submissions in the admin with one click, batched CSV export.
9. Appearance and protection settings: an optional stylesheet, honeypot, and Cloudflare Turnstile, privacy friendly with no third party tracking.

== Changelog ==

= 1.2.3 =
* Fixed: forms placed in a page through a theme template (rather than in the page content) now load their script and styles correctly, so they submit without a page reload and show a properly styled confirmation message.

= 1.2.2 =
* New: edit submissions. Update any entry from the Edit action on the submissions list, and optionally include a secure, expiring edit link in a confirmation email or redirect so people can update their own submission.
* Fixed: fields whose name contains "token" (for example a price token) are no longer dropped as spam. Re-enter any affected values.

= 1.2.1 =
* Fixed: direct share links now work right after you change the Direct Link Base, with no need to visit Settings then Permalinks to refresh.
* Fixed: on the form Settings tab, the direct link field and Copy button no longer occasionally stay hidden until a page refresh.

= 1.2.0 =
* New: webhook delivery logs and automatic retries. See every webhook call and its response, and failed calls retry automatically with backoff.
* New: webhook templates with Discord, Slack, and Microsoft Teams presets, plus custom JSON payloads and request headers.
* New: REST API for form submissions (namespace nrfm/v1), authenticated by a capability check or an API key.
* New: require login. Restrict any form to logged in users, with your own message and login link.
* New: schedule windows. Open and close a form automatically on the dates and times you set.
* New: direct share links. Give any form its own hosted URL, with no page or shortcode required.

= 1.1.0 =
* New: built in AI form builder. Describe a form and let AI write the HTML for you inside WordPress, then keep refining it by chatting. Bring your own API key for an OpenAI compatible provider (DeepSeek, OpenAI, OpenRouter, or a custom endpoint). See the External services and Privacy sections.
* New: conditional logic. Show or hide any field based on what the visitor enters.
* New: save and resume. Visitors can save a long form as a draft and finish later from a private resume link.
* New: submission notifications. An unread badge flags new form submissions in the admin.

= 1.0.4 =
* Added a developer hook for add-ons: nrfm_field_buttons_after (after the editor toolbar).

= 1.0.3 =
* Fixed a Security check failed error that could appear when dismissing the usage analytics notice on the form edit screen.
* Deleting submissions now shows a confirmation message, and bulk deletes ask before removing.
* Fixed the form preview being clipped at its edges.
* Internal cleanup: removed unused code, including a dead admin action handler and an unused plugin constant.

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

= 1.2.2 =
Adds editing of submissions from the admin and via a secure link, and fixes token-named fields being dropped as spam. Existing forms keep working.

= 1.2.1 =
Fixes direct share links needing a manual permalink refresh after a base change, and a direct link field that could stay hidden on the Settings tab until reload. No changes for existing forms.

= 1.2.0 =
Adds webhook delivery logs and retries, webhook templates, a REST API, require login, schedule windows, and share links, all free. Existing forms keep working.

= 1.1.0 =
Adds a built in AI form builder, conditional logic, save and resume, and submission notifications, all free. Existing forms keep working.

= 1.0.4 =
Adds a developer hook (nrfm_field_buttons_after) so add-ons can extend the form editor toolbar. No changes for existing forms.

= 1.0.3 =
Fixes a Security check failed notice on the form edit screen, adds a confirmation when deleting submissions, and stops the form preview from being clipped.

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

This plugin connects to external services only in these optional cases. Each is off until you enable or allow it.

* **Cloudflare Turnstile (optional CAPTCHA).** Only if you enable Turnstile. The browser loads `https://challenges.cloudflare.com/turnstile/v0/api.js` to render the widget, and on submit the server posts the token, your secret key, and the visitor IP to `https://challenges.cloudflare.com/turnstile/v0/siteverify`. Terms: `https://www.cloudflare.com/website-terms/` Privacy: `https://www.cloudflare.com/privacypolicy/`
* **Webhooks (optional, configured by you).** Only if you add a Webhook action. After a successful submission, the form fields plus limited metadata (timestamp, IP address, user agent, referrer) are sent to the exact URL you configure. The destination is your choice, so consult that service's terms and privacy policy.
* **Appsero (optional usage analytics).** Off until you allow it in the admin notice. It then sends basic environment details (site URL, WordPress and PHP versions, active theme and plugins, locale, admin email) to `https://api.appsero.com`. No form submissions or visitor data are sent. Privacy: `https://appsero.com/privacy-policy/`
* **AI form builder (optional, configured by you).** Off until you add an API key. It contacts your chosen provider only when you click Generate, sending your instruction text and the current form markup to that provider's chat completions endpoint. No form submissions or visitor data are ever sent, and your key is stored for admins only. Supported endpoints: DeepSeek `https://api.deepseek.com` (Privacy: `https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html`), OpenAI `https://api.openai.com` (Privacy: `https://openai.com/policies/privacy-policy/`), OpenRouter `https://openrouter.ai` (Privacy: `https://openrouter.ai/privacy`), or any OpenAI compatible endpoint you configure.
