# vCard Organizer 🌐📇

A simple, modern vCard generator web app that helps users create, preview, and download vCard (.vcf) files with contact details and a profile photo.

Built with Alpine.js, Tailwind CSS, and a lightweight PHP endpoint for vCard generation.

> Note: This project is not hosted anywhere right now. I'll publish it to another GitHub repository later — the app is intended to be run locally or deployed when you're ready.

---

## 🚀 Features

- Beautiful, responsive UI with dark mode support
- Live preview while editing contact details
- Photo upload and vCard embedding (base64-encoded)
- Export standard vCard (.vcf) compatible with most contact managers
- Accessibility-minded markup and keyboard-friendly focus styles
- Small footprint and easy to self-host

## 🧩 Tech Stack

- Frontend: HTML, Tailwind CSS (CDN), Alpine.js
- Backend: PHP (single file: `generate_vcard.php`)
- No build step required — just host the files on any PHP-enabled web server

## 🛠️ Installation & Quick Start

1. Clone or download the repository and place the files on your PHP-enabled host (Apache, Nginx, shared hosting, or local PHP dev server).

2. Ensure the `uploads/` folder is writable if you plan to store uploaded images on the server.

3. Open the project in your browser (e.g., `http://localhost:8000`) or run locally:

```powershell
# from project folder on Windows PowerShell
php -S localhost:8000
```

4. Fill in the form and click "Generate vCard" to download your `.vcf` file.

## ✅ SEO & Social Preview (what I added)

I added canonical, Open Graph, Twitter Card meta tags, JSON-LD structured data, and a `sitemap.xml` with a `robots.txt` reference to help search engines and social platforms show rich previews.

NOTE: Replace `https://example.com/` placeholders in `index.html`, `robots.txt`, and `sitemap.xml` with your production domain before submitting to search consoles.

##🔒 Security Notes

- The PHP endpoint validates uploaded files (MIME type and size) and sanitizes input. Review `generate_vcard.php` before deploying to production and add any organization-specific security policies.
- If you host a public demo or staging environment, consider re-adding `noindex` directives while testing.

## 🧪 Testing & Import Notes

- Most modern contact apps (Google Contacts, Outlook, Apple Contacts) support vCard 3.0. If you see missing photos in some importers, check the vCard line-folding and base64 photo encoding; this repo includes compatible folding logic.
- To test import: generate a vCard with a photo and import it into the target contact manager.

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repo
2. Create a feature branch: `git checkout -b feature/my-change`
3. Make your changes and commit: `git commit -m "feat: add ..."`
4. Push to your fork and open a Pull Request

Please follow a clear commit message style and keep changes focused.

## 📜 License

This project is open source and available under the MIT License. See `LICENSE` (or add one) to include the full text.

- Before publishing to GitHub, consider adding a `LICENSE` file (MIT is suggested) and a small `CONTRIBUTING.md` if you want to accept PRs.
- If you publish and host the project later, update `index.html` with canonical/OG meta and add a `sitemap.xml` hosted at the same domain.
- When you're ready, I can prepare a small checklist and PR-ready assets (favicon, OG image) and commit them to your target repo.
